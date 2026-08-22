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
