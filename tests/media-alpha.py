"""Validate every published pixel file against the complete source catalog and its manifest."""
from collections import Counter
from pathlib import Path
import argparse
import json

from PIL import Image

parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument("cache", type=Path)
parser.add_argument("--published", type=Path, help="WordPress mv-cutouts/v1 directory")
args = parser.parse_args()
catalog = Path(__file__).resolve().parent.parent / "catalog" / "media.json"
expected = set(json.loads(catalog.read_text("utf-8")).values())
manifest = json.loads((args.cache / "manifest.json").read_text("utf-8"))
if manifest["failures"] or set(manifest["files"]) != expected:
    raise SystemExit("The cutouts do not cover the source catalog.")

sizes = {"thumbnail": 150, "woocommerce_gallery_thumbnail": 100, "woocommerce_thumbnail": 440}
usage = Counter()
checked = 0
for item in manifest["files"].values():
    usage[item["method"]] += 1
    files = [(item["full"], 800)] + [(item["sizes"][name], width) for name, width in sizes.items()]
    for filename, width in files:
        path = (args.published or args.cache) / filename
        with Image.open(path) as image:
            image.load()
            if image.format != "WEBP" or image.size != (width, width) or image.mode != "RGBA":
                raise SystemExit(f"{filename} is not a {width}px RGBA WebP.")
            alpha = image.getchannel("A")
            levels = alpha.histogram()
            if sum(levels[:6]) < width * width * .10 or sum(levels[200:]) < width * width * .015:
                raise SystemExit(f"{filename} lacks a substantial transparent region or subject.")
            last = width - 1
            if any(alpha.getpixel(p) != 0 for p in ((0, 0), (0, last), (last, 0), (last, last))):
                raise SystemExit(f"{filename} has a filled corner.")
            checked += 1

print(f"PASS {len(expected)} catalog records, {checked} decoded WebP renditions, all with transparent alpha")
for method, count in sorted(usage.items()):
    print(f"{method}: {count}")
