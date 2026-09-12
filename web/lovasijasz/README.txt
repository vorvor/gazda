Lovasharc — static heritage website
==================================

Open index.html directly, or serve this folder with any static web server.
When the Drupal web directory is the document root, use /lovasijasz/index.html.
No Drupal modules, configuration changes, JavaScript framework, CDN, or build
step are required to view the generated site. No contact form backend is implied:
contact actions use telephone and email links.

Contents
--------
35 generated HTML pages, including the front page, seven main-menu sections,
association information, archived news, a jubilee article, book chapters and
long-form writings. The filterable photo gallery contains 99 original photos.
The image-only EGY MEG NEM JELENT CIKK source is restored at cenzurazatlan.html.
Az egyesület célja, feladata is directly linked from the association sidebar
and is available at iras-94ce092157.html.
The original menu's Kelemen Zsolt and Fotógaléria labels had no destination;
these new pages organize material actually available elsewhere on the source.

Design: forest green, warm parchment, brass accents, editorial serif headings,
original equestrian photography, responsive layouts and keyboard-accessible
navigation and gallery. CSS and JavaScript are shared, local files. Images are
optimized to WebP; originals and the downloadable PPS presentation are retained.

The homepage follows design.jpeg with a darker forest/charcoal palette, antique
gold accents, a full-width original riding photograph, four image cards, a
burgundy anniversary band, three editorial columns and a photo-backed contact
section. Its rendering is in scripts/home_reference.py and its isolated styling
is in assets/home-reference.css. It still uses only original website wording.
The 34 subpages were verified byte-for-byte unchanged by the homepage redesign.
Homepage browser checks cover 320, 390, 768, 1024, 1280 and 1440px widths; results
are in source/homepage-verification.json.
Every subpage has an illustrated header using source photography, book artwork,
or historical illustrations. The association pages reuse the grazing-horse
background; the anniversary uses the original parchment illustration. Derived
crops remove unused background space without altering the original files.

Source and accuracy
-------------------
Source: http://www.lovasharc.hu/
47 source HTML documents and 181 assets were downloaded. The old site declares
ISO-8859-2; the generated site uses UTF-8 with Hungarian diacritics preserved.
Framesets are mapped to meaningful standalone pages. Legacy layout, scripts,
font formatting and decorative filler are removed. Text and photos remain the
property of their original rights holders. Ensure publication permission before
redistributing to a new public domain.

Public copy now uses only wording from a fresh live crawl, including headings,
captions and navigation. Added marketing copy, summaries and explanatory notices
were removed. Source paragraph text is retained, with whitespace normalization
and removal of legacy x-spacers and repetitive SEO padding. Developer comparison
reports are kept in source/ rather than presented as public editorial text.
The old forrasok.html URL now presents a TARTALOM page using source labels.
Training dates and news remain historical source content, not current offers.
Telephone numbers, email addresses and account details are not verified.
The source email LOVASHARC@GMAIL.HU is intentionally preserved, not silently
changed to gmail.com. Missing source destinations are listed in
source/live-comparison.json. No biographies, events or missing articles were invented.

Files
-----
assets/site.css              Shared responsive design
assets/editorial.css         Original-artwork hero and subpage compositions
assets/source-only.css       Layout adjustments for unshortened source copy
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
scripts/source_only.py       Source-only renderer and public-copy audit
scripts/audit_live.py        Unbounded-depth live crawl and comparison
source/live-pages.json       Refreshed source text, frames, links and images
source/source-only-audit.json Source coverage and non-source text audit
source/text-provenance.json  Original text fragments verified in output pages
scripts/verify.py            Link, coverage and browser tests

Rebuilding (optional developer tooling)
--------------------------------------
Python packages used: beautifulsoup4 4.15.0, Pillow 12.3.0, Playwright 1.62.0.
Install them in an isolated virtual environment, not the system Python.
Run scripts/build.py using that environment to regenerate HTML and optimized
images. Scraping is not required to rebuild. Run scripts/audit_live.py to refresh
the live snapshot consumed by the source-only renderer. The earlier rendering
implementation in build.py is retained as migration reference; its command-line
entry point now delegates to source_only.py.

Verification
------------
All generated pages were tested with Chromium at 1440px, 768px, 390px and 320px widths.
Every page is checked for a visible hero image with alternative text.
All 47 live source documents are mapped to local pages, with frame-only fragments
folded into their parent pages. Original source text fragments are checked against
the corresponding output, and public text is checked against the live corpus.
All 35 pages are reachable from the homepage. Intermittent Chromium renderer
crashes required isolated retries; every retry is recorded in verification.json.
Checks cover local links and fragments, source-page coverage, image loading,
Hungarian language metadata, one h1 per page, no replacement characters, no
horizontal overflow and no uncaught JavaScript errors. Interaction checks cover
mobile menu open/Escape, gallery filter/reset, and lightbox open/next/Escape.
Screenshots of desktop/mobile homepages and article views were visually reviewed.
The verification script uses the environment's installed Chromium headless binary;
update its executable_path if running on a different machine.

The site is independent of Drupal. No Drupal cache rebuild or database change
is needed. Unrelated workspace files were left untouched. Nothing was committed.
