---
name: gazdabolt-product-photo-csv
description: Use when exporting Gazdabolt product photos to CSV.
version: 0.1.0
platforms: [linux]
---
# Gazdabolt product-photo CSV

Use for the next **exactly five** photos in `/workspace/web/gazdabolt_full_stock/`.
This preserves the session's EXIF → visual observations → CSV → verified rename workflow.
Do not run a batch when asked only to create, review or test this skill.

## Run
Use `terminal` from `/workspace`. Python/Pillow are supplied through the already proven `uv run --with pillow`; do not change Composer or install into system Python.

1. Select once:
   `uv run --with pillow python .hermes/skills/gazdabolt-product-photo-csv/scripts/batch.py select`
   Keep the returned manifest path and five image paths. Selection writes only private work files under `/workspace/tmp/gazdabolt-product-photo-csv/`.
2. Inspect only these five photos using `vision_analyze`. Save each finished photo's observations immediately with `write_file` beside the manifest, e.g. `photo_101358.json`.
3. Review Hungarian wording, certainty and within-photo duplicates. Finalize once, passing the manifest and the five observation JSON paths explicitly:
   `uv run --with pillow python .hermes/skills/gazdabolt-product-photo-csv/scripts/batch.py finalize MANIFEST PHOTO1.json PHOTO2.json PHOTO3.json PHOTO4.json PHOTO5.json`
   Replace placeholders with returned/work-file paths. One combined observation file also works. Never hand-generate IDs, CSV or rename commands.
4. Report the returned CSV path, computed total/per-photo counts, verified renames and identification limits. Stop: no next batch, master merge, commit or Drupal cache rebuild.

## Visual recognition — model-owned
- Load each full image once; inspect shelf-by-shelf. Count distinguishable product designs, colours and clearly different sizes, not each physical copy.
- Zoom only unresolved labels/details with `vision_analyze(region=[x1,y1,x2,y2])`. Coordinates are original-image pixels, not downscaled display pixels. Use the tool's stated scale factor; crops around 1000–2000 pixels wide retained useful label detail in these runs. Overlap adjoining crops enough not to miss edge items.
- Establish orientation once. If a sideways photo impedes recognition, make one oriented inspection copy with Pillow in the manifest's private work directory; never rotate/resave the source. Use that copy's coordinates for its crops.
- Do not reload identical full views/crops without a specific unresolved question. Record findings before moving to the next photo.
- Use **Hungarian product names AND Hungarian product types**, with accents: `Zöld műanyag locsolókanna` / `Locsolókanna`, `Fehér, kerek virágcserép` / `Virágcserép`.
- Retain visible brand/model tokens literally. Otherwise use a generic Hungarian description. Mark limits explicitly: `részben takart`, `pontos típus nem olvasható`, `azonosítás bizonytalan`. Do not invent brands, capacities, formulations or precise dimensions.
- Relative sizes are visual descriptions (`kisebb`, `nagyobb`), never guessed measurements. Nested stack height, perspective and occlusion alone do not prove a separate variant.
- Merge clearly identical copies within the same photo, including synonyms accidentally describing the same item. Do not merge across photos. Attached roses, handles and hangers belong to their product unless clearly separate sale items. Exclude shelving/display hardware.

## Observation contract
Each private UTF-8 JSON is a mapping from ORIGINAL filename stem to ordered pairs:
`{"20260915_101358": [["Zöld locsolókanna", "Locsolókanna"], ["Fehér virágcserép", "Virágcserép"]]}`
This is an illustrative schema, not inventory to reuse. Substitute the selected stem and actual observations. Include every selected photo exactly once. The helper rejects missing/extra photo keys, empty/unreviewed photos, blank fields and exact duplicate rows. Semantic deduplication and Hungarian language quality remain the model's responsibility. If nothing is reliably recognizable in a photo, stop and report it rather than inserting filler.

## Deterministic contract — helper-owned
- One nonrecursive `*.jpg` discovery; ignore filename prefix `processed_`. Sort ascending by EXIF `DateTimeOriginal` (tag 36867 in Exif IFD 34665), filename tie-breaker. Never sort by mtime. Missing/invalid EXIF, malformed filenames or fewer than five: stop, no fallback or smaller batch.
- Preserve IDs exactly: `{original_filename_stem}-{sequence:03d}`, starting at `001` independently per photo, e.g. `20260915_101358-001`. These are observation IDs, not SKUs. The header changes; the ID values' convention does not.
- Merge only this batch's observation fragments. Write one comma-delimited UTF-8 CSV in `processed/`: `products_<first-original-stem>_<last-original-HHMMSS>.csv`, plus Hungarian `_notes.txt` provenance. Exact header: `unique_id,termék neve,termék típusa`.
- Preserve every earlier CSV, including `merged_products.csv`. Existing IDs are checked under both `unique_id` and historical `unique id` headers. No automatic master-CSV merging or historical translation.
- Validate all five first. Publish the complete CSV without overwrite; flush, read back and verify it before ANY rename. Add `processed_` in the source directory; verify SHA-256 matches the manifest. Unselected images are never edited.
- Finalize checks the eligible filename set has not changed (except this manifest's completed renames); it does not repeat EXIF discovery. A directory lock serializes this helper's commits. Do not run competing manual rename/edit operations.

## Recovery and economy
For five photos, analyze directly by default: avoid delegation overhead and the usage-limit failures encountered in larger fan-outs. If extraction is delegated, workers only write private JSON; only the parent finalizes. A worker failure does not imply its saved JSON is absent: inspect and validate saved work before redoing recognition. Never poll live workers.

Reuse an existing manifest and saved observations after interruption; do not select a new batch. If publication/renaming fails, rerun the identical `finalize` command after resolving the IO problem. The private `prepared.json` checkpoint permits safe completion without rewriting a matching CSV. Changed observations/output or collisions are refused: stop and report; never delete the checkpoint, overwrite old CSVs or manually rename to bypass validation.

No repeated repository/architecture discovery, directory dumps, historical CSV reads into model context, web searches, unrelated analysis or step-by-step narration. The helper handles discovery, IDs, counts, CSV parsing and verification. Use `references/verification.md` only when maintaining/testing this skill.
