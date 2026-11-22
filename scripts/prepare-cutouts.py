"""Prepare transparent WooCommerce media from the retained original catalog files."""
from concurrent.futures import ThreadPoolExecutor
from pathlib import Path
from PIL import Image, ImageDraw, ImageFilter, ImageOps
import argparse
import hashlib
import json
import sys

import numpy as np
import onnxruntime as ort

MODEL_MD5 = "60024c5c889badc19c04ad937298a77b"
SIZES = {"thumbnail": 150, "woocommerce_gallery_thumbnail": 100, "woocommerce_thumbnail": 440}
MEAN = np.array((.485, .456, .406), dtype=np.float32)
STD = np.array((.229, .224, .225), dtype=np.float32)


def bg_model(path):
    with path.open("rb") as source:
        actual = hashlib.file_digest(source, "md5").hexdigest()
    if actual != MODEL_MD5:
        raise ValueError("The U²-Net model checksum differs from the published release.")
    options = ort.SessionOptions()
    options.intra_op_num_threads = 2
    options.inter_op_num_threads = 1
    return ort.InferenceSession(str(path), providers=["CPUExecutionProvider"], sess_options=options)


def segmented_alpha(rgb, existing, session):
    sampled = np.asarray(rgb.resize((320, 320), Image.Resampling.LANCZOS), dtype=np.float32) / 255
    tensor = np.ascontiguousarray(((sampled - MEAN) / STD).transpose(2, 0, 1)[None])
    logits = session.run([session.get_outputs()[0].name], {session.get_inputs()[0].name: tensor})[0].squeeze()
    support = np.isfinite(logits)
    if not support.all() or float(logits.max() - logits.min()) <= 1e-8:
        raise ValueError("Object segmentation returned an empty or invalid mask.")
    mask = (logits - logits.min()) / (logits.max() - logits.min())
    mask = np.clip((mask - .085) / .83, 0, 1)
    predicted = Image.fromarray(np.round(mask * 255).astype(np.uint8)).resize(rgb.size, Image.Resampling.BILINEAR)
    if existing.getextrema()[0] < 245:
        combined = np.minimum(np.asarray(predicted), np.asarray(existing))
        predicted = Image.fromarray(combined)
    return predicted


def border_alpha(rgb):
    """For near-uniform backdrops, remove only background connected to an outer edge."""
    preview = rgb.copy()
    preview.thumbnail((360, 360), Image.Resampling.LANCZOS)
    px = np.asarray(preview, dtype=np.int16)
    if min(px.shape[:2]) < 9:
        return None
    h, w = px.shape[:2]
    border = np.concatenate([px[0], px[-1], px[:, 0], px[:, -1]])
    reference = np.median(border, axis=0)
    delta = np.max(np.abs(px - reference), axis=2)
    if float(np.mean(delta[0] <= 31)) < .6 and float(np.mean(delta[-1] <= 31)) < .6:
        return None
    map_image = Image.fromarray(np.where(delta <= 31, 0, 255).astype(np.uint8))
    border_points = [(x, 0) for x in range(w)] + [(x, h - 1) for x in range(w)]
    border_points += [(0, y) for y in range(h)] + [(w - 1, y) for y in range(h)]
    for point in border_points:
        if map_image.getpixel(point) == 0:
            ImageDraw.floodfill(map_image, point, 110)
    matte = Image.fromarray(np.where(np.asarray(map_image) == 110, 0, 255).astype(np.uint8))
    return matte.resize(rgb.size, Image.Resampling.BILINEAR).filter(ImageFilter.GaussianBlur(.65))


def coverage(alpha):
    pixels = np.asarray(alpha)
    return float(np.mean(pixels > 96)), float(np.mean(pixels <= 5))


def fitted(rgb, alpha, target):
    box = alpha.point(lambda value: 255 if value >= 30 else 0).getbbox()
    if not box:
        raise ValueError("No foreground pixels remain.")
    merged = rgb.convert("RGBA")
    merged.putalpha(alpha)
    cutout = merged.crop(box)
    longest = max(cutout.size)
    gutter = max(4, round(target * .04))
    subject = ImageOps.contain(cutout, (target - 2 * gutter, target - 2 * gutter), Image.Resampling.LANCZOS)
    if longest <= 4:
        raise ValueError("Extracted foreground is too small.")
    canvas = Image.new("RGBA", (target, target))
    canvas.alpha_composite(subject, ((target - subject.width) // 2, (target - subject.height) // 2))
    return canvas


def pick_alpha(rgb, existing, session):
    if coverage(existing)[1] >= .07 and coverage(existing)[0] >= .06:
        return existing, "preserved-alpha"
    predicted = segmented_alpha(rgb, existing, session)
    front, clear = coverage(predicted)
    if front >= .09 and clear >= .045:
        return predicted, "u2net"
    alternative = border_alpha(rgb)
    if alternative is not None:
        foreground, transparent = coverage(alternative)
        if .09 <= foreground <= .92 and transparent >= .045:
            return alternative, "border"
    if front < .04 or clear < .01:
        raise ValueError(f"Mask retains {front:.0%} foreground and {clear:.0%} transparent pixels.")
    return predicted, "review"


def reviewed_alpha(rgb, original_alpha, override, session):
    width, height = original_alpha.size
    mask = Image.new("L", original_alpha.size, 0)
    if isinstance(override, dict):
        columns, rows = override["tiles"]
        for y in range(rows):
            for x in range(columns):
                box = (
                    width * x // columns, height * y // rows,
                    width * (x + 1) // columns, height * (y + 1) // rows,
                )
                panel, _ = pick_alpha(rgb.crop(box), original_alpha.crop(box), session)
                mask.paste(panel, box)
        return mask, "reviewed-collage"

    drawing = ImageDraw.Draw(mask)
    if override == "preserve-detail":
        # A flat artwork, size chart or fabric close-up has no backdrop apart from its own content.
        drawing.rounded_rectangle(
            (0, 0, width - 1, height - 1), radius=max(2, round(min(width, height) * .022)), fill=255
        )
        method = "preserved-detail"
    else:
        for polygon in override:
            drawing.polygon([(round(x * (width - 1)), round(y * (height - 1))) for x, y in polygon], fill=255)
        mask = mask.filter(ImageFilter.GaussianBlur(max(.65, min(width, height) * .0018)))
        method = "reviewed-outline"
    return Image.fromarray(np.minimum(np.asarray(mask), np.asarray(original_alpha))), method


def process(source_name, source_dir, output, session, override):
    stem = Path(source_name).stem
    receipt = output / (stem + ".json")
    if receipt.exists():
        cached = json.loads(receipt.read_text("utf-8"))
        expected = [cached["full"], *cached["sizes"].values()]
        if cached.get("override") == override and all((output / item).is_file() for item in expected):
            return cached

    with Image.open(source_dir / source_name) as raw:
        original = ImageOps.exif_transpose(raw).convert("RGBA")
    rgb = original.convert("RGB")
    original_alpha = original.getchannel("A")
    alpha, method = reviewed_alpha(rgb, original_alpha, override, session) if override else pick_alpha(rgb, original_alpha, session)
    front, clear = coverage(alpha)
    big = fitted(rgb, alpha, 800)
    full = stem + ".webp"
    big.save(output / full, "WEBP", quality=87, method=3, alpha_quality=100)
    sizes = {}
    for name, size in SIZES.items():
        small = stem + f"-{size}x{size}.webp"
        big.resize((size, size), Image.Resampling.LANCZOS).save(output / small, "WEBP", quality=84, method=2, alpha_quality=100)
        sizes[name] = small
    metadata = {
        "full": full, "sizes": sizes, "method": method,
        "foreground": round(front, 4), "transparent": round(clear, 4),
    }
    if override:
        metadata["override"] = override
    temp = receipt.with_suffix(".partial")
    temp.write_text(json.dumps(metadata, separators=(",", ":")), "utf-8")
    temp.replace(receipt)
    return metadata


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--model", required=True, type=Path)
    parser.add_argument("--source", required=True, type=Path)
    parser.add_argument("--output", required=True, type=Path)
    parser.add_argument("--workers", type=int, default=2)
    parser.add_argument("--only", type=int)
    args = parser.parse_args()
    args.output.mkdir(parents=True, exist_ok=True)
    media_path = Path(__file__).resolve().parent.parent / "catalog" / "media.json"
    media = json.loads(media_path.read_text("utf-8"))
    sources = sorted(set(media.values()))[:args.only]
    override_path = media_path.with_name("cutout-overrides.json")
    overrides = json.loads(override_path.read_text("utf-8"))
    session = bg_model(args.model)

    def run(name):
        try:
            return name, process(name, args.source, args.output, session, overrides.get(Path(name).stem)), None
        except Exception as issue:
            return name, None, str(issue)

    winner, failures = {}, {}
    with ThreadPoolExecutor(max_workers=args.workers) as pool:
        for count, (name, item, failure) in enumerate(pool.map(run, sources), 1):
            if failure:
                failures[name] = failure
                print("REVIEW", name, failure, flush=True)
            else:
                winner[name] = item
            if count % 25 == 0 or count == len(sources):
                print("CUTOUTS", count, "of", len(sources), "ready", len(winner), "review", len(failures), flush=True)

    result = {"source_count": len(sources), "files": winner, "failures": failures}
    temp = args.output / "manifest.partial"
    temp.write_text(json.dumps(result, ensure_ascii=False, separators=(",", ":")), "utf-8")
    temp.replace(args.output / "manifest.json")
    if failures:
        sys.exit(2)


if __name__ == "__main__":
    main()
