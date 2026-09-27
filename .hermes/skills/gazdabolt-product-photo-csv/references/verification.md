# Maintenance and verification (not part of normal batch execution)

## Proven workflow retained
The session repeatedly used Pillow EXIF DateTimeOriginal sorting with filename ties, full-image inspection plus original-pixel crops, per-photo JSON observation lists, stem-NNN IDs, a separately named batch CSV, CSV read-back, then processed_ renames and SHA-256 checks. Preserve these, rather than reconstructing a new workflow.

The user deliberately changed only the batch contract: exactly five photos, Hungarian product descriptions/types, and `unique_id,termék neve,termék típusa` headers. English historical CSVs remain untouched. Semantic product recognition is not automated by the helper.

Deterministic work now in scripts/batch.py: nonrecursive eligible-file discovery, EXIF ordering, fixed-five selection, stable manifest/hashes, merging JSON fragments in capture order, ID generation, input/schema/duplicate/old-ID validation, CSV quoting/UTF-8 output, provenance notes, collision checks, publication/read-back and source renames. It does not translate, infer products, count physical stock or merge the historical master CSV.

The reusable helper adds a small private preparation checkpoint to the previous finalizer so an IO interruption can resume without overwriting a valid CSV. Atomic publication prevents partial CSVs from being treated as complete. Linux directory locking serializes cooperating helper calls; it cannot protect against arbitrary external/manual edits.

## Offline tests
Use `terminal` from `/workspace`:

`uv run --with pillow python -m unittest discover -s .hermes/skills/gazdabolt-product-photo-csv/tests -v`

All tests construct tiny JPEGs with synthetic EXIF in disposable temporary directories. They exercise the real selector/finalizer and CLI with fixture roots, not live stock. Never run `select` or `finalize` against the default root just to verify this skill.

Coverage includes timestamp/filename disagreement and ties; processed_ exclusion; exact-five selection; missing/invalid timestamps; Hungarian CSV encoding/quoting and unchanged IDs; JSON fragment coverage/duplicates; historical header compatibility; source/selection changes; output/rename collisions; publication failure/corruption; hash preservation; interrupted-rename recovery and repeated finalize; unchanged unrelated files.

## Project discovery
This is a project-owned skill at `.hermes/skills/gazdabolt-product-photo-csv/`, not a copy in the global profile. Hermes discovers project-local skills in trusted projects on session startup. If the project is not already trusted, the user can run `hermes skills trust /workspace` and start a new session. Skill creation/testing does not silently change project trust or global configuration. Documentation source: https://hermes-agent.nousresearch.com/docs/user-guide/features/skills/ (Project-Local Skills).

Do not commit, stage files, modify AGENTS.md or run Drupal cache rebuilds as part of this skill's maintenance unless explicitly requested.
