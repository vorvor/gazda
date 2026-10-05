"""Read-only verification of migration coverage and preserved backup data."""
import csv
import json
import os
from pathlib import Path
import sys

APP = Path(__file__).resolve().parents[1]
REGISTRY = Path(os.environ.get('FELIX_CSV_DIR', APP.parents[1] / 'private/felix-csv'))
DATA = Path(os.environ.get('FELIX_DATA_DIR', APP / 'data'))
generation = REGISTRY / (REGISTRY / 'CURRENT').read_text().strip()
tables = {name: list(csv.DictReader((generation / (name + '.csv')).open())) for name in ['people', 'days', 'assignments', 'sources']}
people = {p['id']: p for p in tables['people']}
days = {d['date']: d['id'] for d in tables['days']}
links = {(a['day_id'], a['person_id']) for a in tables['assignments']}
sources = {(s['file'], s['legacy_id'], s['name']): s['person_id'] for s in tables['sources']}
assert len(people) == len(tables['people'])
assert len(links) == len(tables['assignments'])
count = 0
files = []
for file in sorted(DATA.glob('????-??-??-*.csv')):
    files.append(file.name)
    for row in csv.DictReader(file.open()):
        person = sources[file.name, row['id'], row['name']]
        assert person in people
        assert (days[file.name[:10]], person) in links
        count += 1
if len(sys.argv) > 1:
    backup = Path(sys.argv[1])
    for folder in ['data', 'logs']:
        for old in (backup / 'app' / folder).glob('*'):
            if old.is_file():
                assert old.read_bytes() == (APP / folder / old.name).read_bytes(), old
    exported = json.loads((backup / 'mysql-export.json').read_text())
    for person in exported['people']:
        assert all(str(value) == people[str(person['id'])][key] for key, value in person.items())
    for assignment in exported['day_assignments']:
        assert (str(assignment['day_id']), str(assignment['person_id'])) in links
print(json.dumps({'files': files, 'verified_source_rows': count, 'counts': {k: len(v) for k, v in tables.items()}, 'ambiguities': [s for s in tables['sources'] if s['ambiguity']]}, ensure_ascii=False, indent=2))
