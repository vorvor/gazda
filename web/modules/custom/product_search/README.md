# Product Search Drupal module

Provides `/search-product` with a text input and AJAX product search.

Before the user types anything, the page renders the `products` View display `page_1` normally, so all products can appear under the input field.

When the user types, the AJAX endpoint searches product nodes by:

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
