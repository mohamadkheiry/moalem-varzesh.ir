#!/usr/bin/env python3
"""Build the WordPress thumbnail sizes for the local imported catalog."""
from concurrent.futures import ThreadPoolExecutor
from pathlib import Path
from PIL import Image, ImageOps
import argparse
import json

SIZES = {"thumbnail": 150, "woocommerce_thumbnail": 440, "woocommerce_gallery_thumbnail": 100}

def thumbnail(name, cache):
    with Image.open(cache / name) as image:
        width, height = image.size
        entry = {"width": width, "height": height, "filesize": (cache / name).stat().st_size, "sizes": {}}
        image = ImageOps.exif_transpose(image)
        image = image.convert("RGBA" if "A" in image.getbands() else "RGB")
        for kind, size in SIZES.items():
            if min(image.size) < size:
                continue
            deriv = Path(name).stem + f"-{size}x{size}.webp"
            output = cache / deriv
            if not output.exists():
                cropped = ImageOps.fit(image, (size, size), method=Image.Resampling.LANCZOS)
                cropped.save(output, "WEBP", quality=82, method=2)
            entry["sizes"][kind] = {
                "file": deriv, "width": size, "height": size,
                "mime-type": "image/webp", "filesize": output.stat().st_size,
            }
        return name, entry

def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("cache", type=Path)
    cache = parser.parse_args().cache
    media = Path(__file__).resolve().parent.parent / "catalog" / "media.json"
    names = json.loads(media.read_text(encoding="utf-8")).values()
    with ThreadPoolExecutor(max_workers=6) as workers:
        prepared = dict(workers.map(lambda n: thumbnail(n, cache), names))
    temporary = cache / "image-metadata.partial"
    temporary.write_text(json.dumps(prepared, separators=(",", ":")), encoding="utf-8")
    temporary.replace(cache / "image-metadata.json")
    print(f"Prepared thumbnail metadata for {len(prepared)} images.")

if __name__ == "__main__":
    main()
