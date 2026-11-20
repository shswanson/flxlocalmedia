#!/usr/bin/env python3
"""Shrink a 2x render to a 1200 x 630 PNG that is small enough to share.

    postprocess.py in.png out.png

The page is drawn at 2x (2400 x 1260) so text and logo edges stay sharp, then
downsampled to 1200 x 630 with a Lanczos filter. If the PNG is over 300 KB it is
reduced to 256 colors (dithered), which keeps gradients smooth and photos fine at
this size. Prints "<bytes> <mode>" so the generator can log it.
"""
import io
import sys

from PIL import Image

MAX_BYTES = 300 * 1024


def save(img, path):
    img.save(path, format="PNG", optimize=True)


def main():
    src, dst = sys.argv[1], sys.argv[2]
    im = Image.open(src).convert("RGB")
    im = im.resize((1200, 630), Image.LANCZOS)

    buf = io.BytesIO()
    im.save(buf, format="PNG", optimize=True)
    if buf.tell() <= MAX_BYTES:
        save(im, dst)
        print(buf.tell(), "rgb")
        return

    q = im.quantize(colors=256, method=Image.Quantize.MEDIANCUT, dither=Image.Dither.FLOYDSTEINBERG)
    buf = io.BytesIO()
    q.save(buf, format="PNG", optimize=True)
    if buf.tell() > MAX_BYTES:
        # A busy photo: fewer colors until it fits.
        for colors in (192, 128, 96, 64):
            q = im.quantize(colors=colors, method=Image.Quantize.MEDIANCUT, dither=Image.Dither.FLOYDSTEINBERG)
            buf = io.BytesIO()
            q.save(buf, format="PNG", optimize=True)
            if buf.tell() <= MAX_BYTES:
                break
    save(q, dst)
    print(buf.tell(), "palette")


if __name__ == "__main__":
    main()
