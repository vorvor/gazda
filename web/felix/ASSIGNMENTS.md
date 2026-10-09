# Felix CSV storage

`assignments.php` uses the existing login; only the authenticated user **Elek**
can change assignments. Writes require the existing CSRF token. Other signed-in
users may read `filming_day.php`. All active assignment, setup and sync paths
use CSV only. `assignments_db.php` is a compatibility filename, not a SQL adapter.
No database driver, server or credential configuration is used at runtime.

## Location and schema

Default private storage: `/workspace/private/felix-csv` (outside `web/`).
Override `FELIX_CSV_DIR` and `FELIX_DATA_DIR` together for isolated deployments/tests.
Keep the registry outside the document root and grant the PHP service account
read/write access (directory mode 0750; CSV files 0640). Do not expose backups.

`CURRENT` points to an immutable `generation-<random>` directory containing:

* `people.csv`: `id,demo_key,name,city,fictional` — stable positive numeric IDs;
  names are attributes and **never unique keys**. Exported IDs are retained.
* `days.csv`: `id,date,title` — unique valid calendar dates.
* `assignments.csv`: `day_id,person_id,assigned_by,created_at` — unique pairs,
  validated references to people and days.
* `sources.csv`: `file,legacy_id,name,person_id,identity_key,ambiguity` — explicit
  mapping from original roster rows to common IDs. Original check-in IDs and
  all original CSV state remain unchanged; no extra roster column is required.

Writers share the roster `data/.save.lock`, reload after locking, validate
references, stage and fsync CSVs, then atomically rename `CURRENT`. Readers load
one complete generation. Prior generations are retained for recovery (no automatic
pruning). Roster saves retain their existing staged atomic replacement and audit
history. Assignment edits and roster saves serialize on the same lock. Reported
assignment/roster I/O failures restore roster/meta/log snapshots and do not publish
the new registry generation. A process/power crash between roster and registry
publication is **not** a crash-atomic multi-file transaction; retain backups and
inspect both before operator recovery. Never manually edit a published generation.

## Synchronization and identity

```sh
php web/felix/setup_assignments.php
# Equivalent backwards-compatible command:
php web/felix/sync_names_db.php
```

These commands scan only active dated `data/YYYY-MM-DD-*.csv` files, not root
sample copies, archives or logs. They are additive, preserve existing records,
validate duplicate IDs within each source file, and are safe to repeat. Run after
operator imports/identity edits to manual rosters. They do not rewrite rosters,
change check-in state, seed demos, or delete historical assignments. Consequently
an explicit sync can restore a planning link still present in a manual roster.

Legacy IDs repeated with the same name across related daily rosters use the same
mapping. Different IDs remain separate even when names match. Reused IDs with
different names are assigned separate provisional identities and reported;
operators must resolve genuine name changes versus ID reuse explicitly, never
by automatic name deduplication. Assignment-generated IDs refer directly to the
common registry only if their names agree. Conflicts remain separately preserved.

`php web/felix/sync_assignment_rosters.php` explicitly projects all nonempty
assignment days into generated `*-forgatas.csv` rosters. It is **not** part of
migration/setup and may add generated files; use deliberately. Existing manual
roster filename collisions and closed membership changes are rejected.

## Explicit canonical demo reset

```sh
php web/felix/demo_reset.php --reset
web/felix/.venv/bin/python web/felix/tests/test_demo_reset.py
```

This separate **CLI-only, destructive opt-in** command is not part of normal
setup. Missing/other arguments refuse mutation; web requests return 404. Run in
an operator maintenance window: writers share `.save.lock`, but existing roster
read endpoints can observe intermediate files during the reset.

The tool keeps every existing canonical person and ID, including equal names.
It replaces only active dated roster CSVs and registry days/assignments/sources;
root samples, archives, inactive files, code and existing audit history remain.
Each person appears on two of three dates. All generated roster IDs and source
mappings are the canonical numeric IDs. Assignment provenance and roster data are
deterministic across repeated resets; audit timestamps and backup names are not.
The third date is initialized closed through the existing metadata schema; this
is a seeded state, not a fabricated historical operator close event.

Before deletion, it copies and SHA-256 verifies the complete data directory,
registry (including retained generations), and logs outside the webroot. Default
backup parent: `/workspace/private/felix-backups`; tests may override
`FELIX_DEMO_BACKUP_DIR`. Symlinked backup contents and backup destinations within
the webroot/data/registry are refused. A manifest records paths and old counts.
Reported failures restore affected roster/meta/log files and `CURRENT`; this is
not power-loss/crash atomic. Recovery: stop writers, restore backup `data/`,
`logs/`, and `registry/` to the manifest locations, then verify `CURRENT` and rows.
Keep the existing `.save.lock` inode in place while any process might use it.

Executed live reset backup:
`/workspace/private/felix-backups/demo-20261004-182251-f1194170`

Verified result: **24 original people, 3 days, 48 assignments, 48 canonical source
mappings**, replacing 5 active rosters. Every person appears twice, with 16 people
on each day:

| Date | Session | Closed | Arrived | Late | Cancelled | Checked out |
|---|---|---|---|---|---|---|
| 2026-10-05 | checkin | no | 8 | 3 | 3 | 0 |
| 2026-10-06 | checkout | no | 13 | 4 | 3 | 5 |
| 2026-10-07 | checkout | yes | 13 | 4 | 3 | 13 |

Demo rows also cover vegan/gluten diets, all three flags and Hungarian notes.
Cancelled people are not arrived or checked out; checkout implies arrival;
all noncancelled people on the closed day are checked out. Live readback matched
every roster ID/name and source mapping to the unchanged master list. Two live
normal setup runs preserved all people, days, assignments and mappings exactly.
The isolated regression additionally uses noncontiguous IDs and equal names,
checks backup preservation, repeated reset stability and coherent demo states.
All 12 Python test scripts and both PHP test scripts passed; top-level PHP lint
passed. Historical migration counts below describe the earlier state, not this
demo.

## Completed migration

Backup: `/workspace/private/felix-backups/20261004-114705/` contains the pre-change
app and `mysql-export.json`. The export used SELECT only; MySQL remains untouched
as a backup, not a runtime fallback. Import was performed once with:

```sh
php web/felix/sync_names_db.php /workspace/private/felix-backups/20261004-114705/mysql-export.json
```

An export argument is accepted only for an empty registry. Setup thereafter
requires no SQL access. Migrated 12 existing people, 4 existing days and 8 existing
assignments plus all **30 source rows from 5 active rosters**. Result: **24 people,
6 days, 32 unique assignments, 30 source mappings**. All original roster,
metadata and log bytes were verified unchanged.

Preserved provisional collisions:
* `A-1042`: Kovács Judit → 13; Maya Torres → 21.
* `A-1045`: Diego Marín → 16; Nagy Ádám → 22.

The requested `.filming-summary` element was already absent in the starting
`index.php`; tests verify it stays absent. The admin link and date tabs remain.

## Verification

### Personal cards and QR codes

The roster, assignment pool/assigned cards, and both audit-log views show a
small personal-card icon beside each person name. The shared `person_qr.php`
component opens a responsive, keyboard-accessible dialog with the name, existing
ID, photo placeholder (no upload), and a large scan-ready SVG.
Codes contain UTF-8 JSON with string `id` and `name` fields. Audit codes use the
identity recorded in that historical entry. No identifiers or stored rows are
changed; scanning displays identity text, not a check-in action or login token.

The authenticated, GET-only `person_card.php` loads current saved registry fields
(ID, name, city, fictional marker), each linked active daily roster's saved fields,
and assignment dates/titles/provenance. Daily values remain separated by source
file: they are not inferred to be permanent personal attributes. Internal demo
keys and identity matching keys are not displayed. No credentials are returned.
Roster and audit requests resolve through `sources.csv` using source filename
and legacy ID (with the recorded name as a stale-identity guard), never by name
alone or by treating a legacy ID as a canonical ID. Missing/ambiguous mappings
show an explicit unavailable message rather than another person's data. Missing,
archived, symlinked or replaced roster rows are not folded into current details.
Cards show saved state, not unsaved edits or a reconstruction of audit history.
All text is rendered as text, requests bypass caches, and closing/switching cards
invalidates pending responses. Card reads do not change roster or registry rows.

```sh
web/felix/.venv/bin/python web/felix/tests/test_person_card.py
```

Generation stays in the browser using the locally vendored MIT-licensed
`qrcode-generator` 1.4.4 (`person_qr_vendor.php`); no person data is sent to a QR
service. The QR integration test decodes rendered images and requires Playwright,
Pillow, and zxing-cpp in the existing test virtualenv:

```sh
uv pip install --python web/felix/.venv/bin/python pillow zxing-cpp
web/felix/.venv/bin/python web/felix/tests/test_person_qr.py
```

```sh
php web/felix/tests/test_csv_store.php
php web/felix/tests/test_csv_sync.php
python3 web/felix/tests/verify_csv_migration.py /workspace/private/felix-backups/20261004-114705
web/felix/.venv/bin/python web/felix/tests/test_assignment_dates.py
web/felix/.venv/bin/python web/felix/tests/test_assignment_rosters.py
web/felix/.venv/bin/python web/felix/tests/test_assignments.py
web/felix/.venv/bin/python web/felix/tests/test_assignment_date_ui.py
```

Integration tests use private temporary CSV fixtures and deliberately nonexistent
`FELIX_DB_CONFIG`; they never connect to or modify MySQL. Browser tests exercise
real add/reload/remove, date creation, navigation to generated roster tabs and
mobile layout. Run remaining `tests/test_*.py` scripts individually for auth,
logout/CSRF, audit, flags, translations, scroll and undo regressions.
