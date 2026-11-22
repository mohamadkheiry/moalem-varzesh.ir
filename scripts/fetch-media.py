#!/usr/bin/env python3
"""Download the public media listed in the checked-in catalog snapshot."""
from concurrent.futures import ThreadPoolExecutor
from pathlib import Path
from urllib.parse import quote, urlsplit
from urllib.request import Request, urlopen
import argparse
import json
import re
import time

def download(entry, destination):
    url, name = entry
    parsed = urlsplit(url)
    if parsed.scheme != "https" or parsed.hostname not in ("moalem-varzesh.ir", "www.moalem-varzesh.ir"):
        raise ValueError(f"Unexpected image origin: {url}")
    if not re.fullmatch(r"[0-9a-f]{24}\.(webp|jpg|jpeg|png|gif|avif)", name):
        raise ValueError(f"Unexpected image filename: {name}")
    target = destination / name
    if target.is_file() and target.stat().st_size:
        return
    for attempt in range(4):
        try:
            request = Request(quote(url, safe=":/%?&=+"), headers={"User-Agent": "Mozilla/5.0 (catalog migration)"})
            with urlopen(request, timeout=30) as response:
                if not response.headers.get_content_type().startswith("image/"):
                    raise ValueError(f"Unexpected media type for {url}")
                data = response.read()
            temporary = target.with_suffix(".part")
            temporary.write_bytes(data)
            temporary.replace(target)
            return
        except OSError:
            if attempt == 3:
                raise
            time.sleep(attempt + 1)

def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("destination", type=Path)
    args = parser.parse_args()
    args.destination.mkdir(parents=True, exist_ok=True)
    catalog = Path(__file__).resolve().parent.parent / "catalog" / "media.json"
    images = json.loads(catalog.read_text(encoding="utf-8"))
    with ThreadPoolExecutor(max_workers=8) as executor:
        for _ in executor.map(lambda row: download(row, args.destination), images.items()):
            pass
    print(f"Verified {len(images)} media file paths in {args.destination}")

if __name__ == "__main__":
    main()
