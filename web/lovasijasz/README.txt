Lovasharc — static heritage website
==================================

Open index.html directly, or serve this folder with any static web server.
When the Drupal web directory is the document root, use /lovasijasz/index.html.
No Drupal modules, configuration changes, JavaScript framework, CDN, or build
step are required to view the generated site. No contact form backend is implied:
contact actions use telephone and email links.

Contents
--------
34 generated HTML pages, including the front page, seven main-menu sections,
association information, archived news, a jubilee article, book chapters and
long-form writings. The filterable photo gallery contains 78 original photos.
The original menu's Kelemen Zsolt and Fotógaléria labels had no destination;
these new pages organize material actually available elsewhere on the source.

Design: forest green, warm parchment, brass accents, editorial serif headings,
original equestrian photography, responsive layouts and keyboard-accessible
navigation and gallery. CSS and JavaScript are shared, local files. Images are
optimized to WebP; originals and the downloadable PPS presentation are retained.

Source and accuracy
-------------------
Source: http://www.lovasharc.hu/
47 source HTML documents and 181 assets were downloaded. The old site declares
ISO-8859-2; the generated site uses UTF-8 with Hungarian diacritics preserved.
Framesets are mapped to meaningful standalone pages. Legacy layout, scripts,
font formatting and decorative filler are removed. Text and photos remain the
property of their original rights holders. Ensure publication permission before
redistributing to a new public domain.

Training dates and news are archival, not current offers. The 2024 training
announcement and 2025 anniversary are labeled accordingly. Historical and
professional claims are attributed to the original authors, not independently
endorsed. Telephone numbers, email addresses and account details are not verified.
The source email LOVASHARC@GMAIL.HU is intentionally preserved, not silently
changed to gmail.com. Missing source destinations are listed in forrasok.html and
source/errors.json. No biographies, events or missing articles were invented.

Files
-----
assets/site.css              Shared responsive design
assets/site.js               Mobile navigation, gallery filters and lightbox
assets/photos/               Optimized local images and gallery thumbnails
assets/original/             Original downloaded files
source/pages.json            Source HTML, text, links and images per URL
source/assets.json           Original URL-to-local-asset mapping
source/errors.json           Source fetch failures
source/build-manifest.json   Page routes, file inventory and measured totals
source/verification.json     Automated verification result
scripts/scrape.py            Public source crawler
scripts/collect_downloads.py Additional linked images and presentation download
scripts/build.py             Rebuild from the archived source (offline)
scripts/verify.py            Link, coverage and browser tests

Rebuilding (optional developer tooling)
--------------------------------------
Python packages used: beautifulsoup4 4.15.0, Pillow 12.3.0, Playwright 1.62.0.
Install them in an isolated virtual environment, not the system Python.
Run scripts/build.py using that environment to regenerate HTML and optimized
images. Scraping is not required to rebuild. Rerunning scripts/scrape.py and
scripts/collect_downloads.py refreshes the archived source from the public site.

Verification
------------
All generated pages were tested with Chromium at 1440px and 390px widths.
Checks cover local links and fragments, source-page coverage, image loading,
Hungarian language metadata, one h1 per page, no replacement characters, no
horizontal overflow and no uncaught JavaScript errors. Interaction checks cover
mobile menu open/Escape, gallery filter/reset, and lightbox open/next/Escape.
Screenshots of desktop/mobile homepages and article views were visually reviewed.
The verification script uses the environment's installed Chromium headless binary;
update its executable_path if running on a different machine.

The site is independent of Drupal. No Drupal cache rebuild or database change
is needed. Unrelated workspace files were left untouched. Nothing was committed.
