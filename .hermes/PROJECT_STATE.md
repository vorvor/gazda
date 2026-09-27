# Current unfinished work

Photo inventory is paused for context distillation. Do not continue until requested. No Drupal feature work is in progress.

## Pending five-photo batch

- Source: `/workspace/web/gazdabolt_full_stock/`.
- Selected EXIF-chronological originals: `20260915_101711.jpg`, `20260915_101714.jpg`, `20260915_101716.jpg`, `20260915_101718.jpg`, `20260915_101720.jpg`.
- Working directory: `/workspace/tmp/gazda_batch_101711/` contains oriented previews, `manifest.json` (selection and hashes) and `observations.json` (all five photos reviewed).
- Current ledger counts: 25, 33, 30, 28, 31 respectively; these are candidate observations, not a validated final CSV.
- Verified at handoff: all five originals still exist, none have a processed_ counterpart; intended output `processed/products_20260915_101711_101720.csv` does not yet exist. No renames for this batch have occurred.
- Recent identification corrections: Verto green-pack item is a perforated rasp, not a chain; Benman Basic Cut and HCS T101D packs are saw blades, not precision screwdrivers; visible FF Group yellow box contains three pliers. Corrections are in the ledger. Remaining uncertain labels need conservative review, not invented details.

## Resume steps

1. Load `photo-product-inventory`; reload the manifest and observations from disk (tool kernels can reset).
2. Review uncertainty and cross-photo consistency, then recheck earliest eligible selection against current files. Repeated interrupted requests were treated as the same unfinished five-photo batch, not additional batches.
3. Export a new Hungarian three-column CSV plus short notes. Validate IDs against existing CSVs, schema, nonempty fields, and per-photo counts.
4. Only after validation, prefix these five originals with `processed_` and verify hashes and prior processed files.

Existing helper references: `/workspace/tmp/inventory_select.py` and `/workspace/tmp/gazda_batch_101358/export_inventory.py`. They hardcode an older eight-photo batch and old timezone notes: inspect and explicitly adapt all counts, paths, and notes before use. Current missing timezone offsets concern 101718 and 101720; 101720's EXIF time is 10:17:21, not its filename time.

## Preservation boundaries

Completed batch CSVs already exist through `products_20260915_101656_101709.csv`; do not re-export them or modify `processed/merged_products.csv` without a request. Existing unrelated changes include AGENTS.md, the staged/modified merged CSV, and `web/themes/gazda/css/cultural-programs.css`. No commit/push requested.
