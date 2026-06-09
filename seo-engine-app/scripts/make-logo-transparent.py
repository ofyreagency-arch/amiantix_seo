#!/usr/bin/env python3
"""Remove near-black background from logo PNG."""

from pathlib import Path

from PIL import Image

ROOT = Path(__file__).resolve().parents[1]
SRC = ROOT / "public" / "images" / "logo-skerpix.png"
DST = ROOT / "public" / "images" / "logo-skerpix.png"

img = Image.open(SRC).convert("RGBA")
pixels = img.load()
width, height = img.size

for y in range(height):
    for x in range(width):
        r, g, b, a = pixels[x, y]
        if r < 35 and g < 35 and b < 35:
            pixels[x, y] = (0, 0, 0, 0)

img.save(DST, "PNG")
print(f"Saved transparent logo: {DST}")
