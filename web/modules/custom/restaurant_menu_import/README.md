# Restaurant Menu Import

A deliberately basic Drupal module that creates and updates `meal` nodes from a restaurant node's `field_data_source`.

## Run

```bash
vendor/bin/drush restaurant-menu:import 540 --dry-run
vendor/bin/drush restaurant-menu:import 540
```

The importer expects:

- restaurant bundle: `restaurant`;
- source field: `field_data_source`;
- meal bundle: `meal`;
- meal fields: `field_price`, `field_description`, `field_restaurant`.

Existing meals are matched by exact title plus restaurant. Their price, description, and publication state are updated. Missing titles create published meal nodes. Meals absent from a later source are left unchanged.

## Basic source formats

- Newline-separated direct PDF URLs.
- An HTML URL with a fragment ID, such as `https://example.com/#block-menu`.
- `PDF documents from section with class .menu-downloads on https://example.com/`.
- Plain HTML pages containing Drupal Views-style `.views-row` menu markup.

PDF extraction accepts simple contiguous blocks in this shape:

```text
MEAL NAME
Description and ingredients (1, 7)
3500 HUF
```

## Deliberate limitations

- Attached-image OCR (`[image]`) is not supported.
- Complex multi-column PDFs are skipped when the parser cannot confidently pair a title and price.
- Multiple prices on one PDF line are skipped; model each priced variant separately in the source where possible.
- Existing meals are matched by restaurant and exact title, not a stable external source ID.
- Missing source meals are not unpublished or deleted.

Use `--dry-run` first. A non-zero error count means at least one source was unsupported or could not be read; existing meals remain untouched.
