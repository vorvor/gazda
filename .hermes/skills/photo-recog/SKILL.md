---
name: gazdabolt-stock-photo-batch
description: Process a batch of Gazdabolt stock photos into product CSV
version: 1.0.0
metadata:
  hermes:
    tags: [gazdabolt, images, csv]
---
---
name: gazdabolt-product-photo-csv
---

# Gazdabolt Stock Photo Batch

## When to Use

Use when processing unprocessed product photos from
/workspace/web/gazdabolt_full_stock/.

## Procedure

1. Ignore files whose filename starts with `processed_`.
2. Ignore files inside the `processed/` output directory.
3. Select the 5 oldest photos by photo capture date/EXIF when available.
   Fall back to file modification time only if necessary.
4. Inspect each photo and identify as many distinct products as reasonably visible.
5. Do not invent brands, models or exact variants when they are unreadable.
6. Use Hungarian product names and product types.
7. Write one CSV to:
   /workspace/web/gazdabolt_full_stock/processed/
8. Columns:
  - unique_id
  - product_name
  - product_type
9. Verify the CSV before modifying source filenames.
10. Only after successful processing, prefix processed source photos
    with `processed_`.

## Pitfalls

- Never process already processed images.
- Never rename an image before its data is safely written.
- Do not treat unreadable packaging text as fact.
- Avoid duplicate IDs.

## Verification

Confirm:
- exactly the intended five source images were handled;
- CSV exists and parses correctly;
- product names are Hungarian;
- source images have the processed_ prefix.

## Output policy

This is a batch-processing workflow.

Do not narrate your work.
Do not explain tool calls.
Do not report intermediate progress.
Do not list recognized products in chat.
Do not reproduce CSV contents in the response.

Perform the work silently.

Final response must be exactly one line:

OK | CSV:<path> | IMAGES:<count> | PRODUCTS:<count>

On failure:

ERROR | <brief reason>
