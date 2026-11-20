# flxlm-og: share images for flxlocalmedia.com

Makes the Open Graph / Twitter card image for every public page of flxlocalmedia.com:
one 1200 x 630 PNG per page in `assets/images/og/<key>.png`. The style is bold: a huge
heavy Poppins headline, a gold Kaushan Script eyebrow tilted -3deg, real imagery from the
theme (the page's own photo, the station logo in the station's colors, the client hero
photo), one big proof number where the page prints one, a gold bar along the bottom and
the small FLX logo in the bottom-left corner. Client stories show the hero photo, the
short hero quote (post meta `hero_quote`, verbatim), the person's name and a dark
translucent play button.

The theme (shswanson/flxlocalmedia) prints the tags. This folder only makes the
files the tags point to.

## What each piece does

* `bold-copy.json` holds the headline, proof number, imagery and layout for every card.
  Headlines come from each page's own H1 or lead and numbers from figures printed on that
  page. `*word*` is drawn in gold. Change a card here, then re-run.
* `export-manifest.php` loads WordPress and asks the theme for the page list
  (`flxlm_seo_og_manifest()` in `inc/seo.php`): each key, its URL and the station colors
  and logos.
* `generate.mjs` draws each card in Chrome at 2x, shrinks the headline until it fits, then
  calls `postprocess.py`.
* `postprocess.py` shrinks the 2x picture to 1200 x 630 and keeps the file under 300 KB.

## Run it

Needs: PHP with the site's database (a staging copy is fine), node 18+, the
`playwright` package, Google Chrome, python3 with Pillow, and internet (Poppins and
Kaushan Script come from Google Fonts).

```
php export-manifest.php /path/to/wordpress next.flxlocalmedia.com > manifest.json
node generate.mjs --manifest manifest.json --out /path/to/flxlocalmedia/assets/images/og
# images are read from the theme in the manifest; add --theme /path/to/flxlocalmedia if the manifest came from another machine
```

Useful options:

* `--only mix-985,home` makes just those images (keys from `bold-copy.json`).
* `--list` prints every key and exits.
* `--keep` keeps the 2x renders in `<out>/_2x`.
* `CHROME_PATH=...` points at Chrome if it is not in the macOS default place.
* `NODE_PATH=...` if playwright is installed somewhere else (for example `~/fldn-theme/node_modules`).

The run stops if Poppins or Kaushan Script does not load, so no image is made with a stand-in font.

## File names

`assets/images/og/<key>.png`, where the key comes from the page:

| Page | Key |
|---|---|
| Home | `home` |
| Station | station slug (`mix-985`, `the-wall`, ...) |
| Product | product slug (`fldn`, `flx-finest`, ...) |
| Other page | page slug (`about`, `contact`, `geneva`, `why-radio`, `radio`, ...) |
| Testimonial | `testimonial-<slug>` |
| Case study | page slug (`fldn-seo`, ...) |
| Job | `job-<slug>` |
| Anything without its own image | `default` |

A page with no file falls back to `default.png`, so a new page never shows a broken
image. Run the generator after adding a page, a station, a product or a testimonial,
or after changing a station's color or logo, then commit the new PNGs in the theme repo.

## Not made on purpose

Pages that stay out of search have no image: the unlisted pricing page, the internal
brand document, the duplicate `methodology-2`, hidden testimonials, and `/resources/`
(it redirects to the home page until it has content).
