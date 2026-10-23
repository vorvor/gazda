# Product Search Drupal module

Provides `/search-product` with a text input and AJAX product search.

Before the user types anything, the page renders the `products` View display `page_1` normally, so all products can appear under the input field.

When the user types, the CSRF-protected AJAX `POST` endpoint searches product
nodes by:

- node title
- `field_description`
- referenced term names in `field_tags` from vocabulary `tags`
- referenced term names in `field_category` from vocabulary `product_category`

It then passes the matching node IDs to the View as a contextual argument.

Searches also read `web/gazdabolt_full_stock/merged_products.csv` (`termék neve`)
and `web/balint_full_stock/products.csv` (`product strict name`), matching
only those name columns (case-insensitive partial matches). Spaces and
plus signs separate keywords, with any keyword matching, as in node searches.
Matching CSV names appear below the existing product results with the notice
`termék fotó nem elérhető jelenleg`. Duplicate names appear once per shop;
identical names sold by different shops remain separate cards. No nodes
are imported or modified. Both the initial keyword page and AJAX responses
include these results, and analytics counts include both sources. Empty
searches keep the original default View. Missing/unreadable CSV files or a
missing name column leave node search available. The CSV is read on each
search, so stock-file changes do not require a cache rebuild.

CSV cards use the optional `rövid terméknév` column as their title and keep
the original name underneath as the full description. Initials and colors follow
the short title; matching still uses the full original name. Empty/missing
short titles fall back to the full name. The short-name column can be edited
manually. To populate missing titles, run
`python3 web/modules/custom/product_search/scripts/distill_stock_names.py web/gazdabolt_full_stock/merged_products.csv --write`.
The script uses product nouns found in names and the existing product-type
vocabulary, preserves original columns, and creates a backup before writing.
Existing titles are preserved unless `--refresh` is explicitly supplied.

On shop node pages, the search block sends the current shop ID with each AJAX
request. Node searches additionally require that `field_shop` references that
shop; clearing the search restores the shop-specific product View. The block
cache varies by route. Each CSV is included only on its own shop's searches,
or in unscoped site-wide searches. Source definitions in `CsvProductSearch::SOURCES`
map file paths, name columns, labels, and shop aliases. Gazdabolt resolves from
`/bolt/gazdabolt`; Bálint resolves from
`/bolt/balint-es-tarsa-kft-villamossagi-szakuzlet`. Invalid or inaccessible
shop IDs are rejected rather than falling back to global results.

## Required View setup

Edit the `products` View, display `page_1`:

1. Add a contextual filter: `Content: ID`.
2. Enable `Allow multiple values` for the contextual filter.
3. The module passes matching node IDs as one argument, for example: `12+15+28`.
4. For the default no-keyword display, configure `When the filter value is NOT available` as `Display all results for the specified field`.

## Install

Copy this folder to:

```bash
web/modules/custom/product_search
```

Then run:

```bash
drush en product_search -y
drush cr
```

Visit:

```text
/search-product
```

## Search statistics

The CSRF-protected AJAX `POST` endpoint records searches longer than three
characters in the `product_search_query_log` table. It stores the normalized
search text, result count, and timestamp, but no IP address, user ID, session
ID, or other visitor identifier. Search terms are limited to 255 Unicode
characters before the product database is queried.

Analytics writes are limited to 60 qualifying searches per hour for each
client. Throttling uses Drupal's flood service with an HMAC pseudonym derived
from the client address and the site's private key; the raw address is never
stored by this module. Rows older than 90 days are pruned before a new
qualifying record is written. The table is also capped at 10,000 rows, with the
oldest rows removed first. Insert or retention failures are logged without the
search term and never prevent the search response. Cap maintenance and
insertion are serialized with Drupal's lock API.

Aggregated statistics are available at:

```text
/admin/reports/product-search-statistics
```

Access is controlled by the `view product search statistics` permission. The
report shows total searches, unique terms, searches without results, average
result counts, and the most recent search time.

After deploying this feature to an existing installation, run:

```bash
drush updb -y
drush cr
```
