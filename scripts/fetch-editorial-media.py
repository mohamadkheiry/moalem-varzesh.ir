"""Cache local WebP copies of the article photos published by the source store."""
import argparse
from concurrent.futures import ThreadPoolExecutor
from hashlib import sha256
from html import unescape
from io import BytesIO
import json
from pathlib import Path
import re
from urllib.parse import urlsplit
from urllib.request import Request, urlopen

from PIL import Image, ImageOps

parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument("cache", type=Path)
args = parser.parse_args()
args.cache.mkdir(parents=True, exist_ok=True)
source = Path(__file__).resolve().parent.parent / "catalog" / "editorial.json"
articles = json.loads(source.read_text("utf-8"))["posts"]
urls = sorted({
    unescape(url)
    for row in articles
    for url in re.findall(r"""<img\b[^>]*?\bsrc=["']([^"']+)["']""", row["content"], re.I)
    if urlsplit(url).hostname in ("moalem-varzesh.ir", "www.moalem-varzesh.ir")
    and urlsplit(url).scheme == "https"
    and urlsplit(url).path.startswith("/wp-content/uploads/")
})

def download(url):
    name = sha256(url.encode()).hexdigest()[:24] + ".webp"
    dest = args.cache / name
    try:
        if not dest.exists():
            request = Request(url, headers={"User-Agent": "Mozilla/5.0"})
            with urlopen(request, timeout=18) as response:
                original = response.read(8 * 1024 * 1024)
            with Image.open(BytesIO(original)) as image:
                picture = ImageOps.exif_transpose(image).convert("RGB")
                picture.thumbnail((1280, 1280), Image.Resampling.LANCZOS)
                picture.save(dest, "WEBP", quality=88, method=4)
        with Image.open(dest) as image:
            return url, {"file": name, "width": image.width, "height": image.height}
    except (OSError, ValueError) as issue:
        print(type(issue).__name__, url)
        return url, None

with ThreadPoolExecutor(max_workers=4) as pool:
    results = dict(pool.map(download, urls))
(args.cache / "manifest.json").write_text(json.dumps(results, ensure_ascii=False), "utf-8")
print(f"Archived {sum(item is not None for item in results.values())} of {len(results)} article images.")
