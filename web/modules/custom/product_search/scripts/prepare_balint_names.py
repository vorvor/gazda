"""Populate reviewed short titles for the Bálint stock export."""
import csv
import io
from pathlib import Path
import shutil

path = Path('/workspace/web/balint_full_stock/products.csv')
raw = path.read_bytes()
reader = csv.DictReader(io.StringIO(raw.decode('utf-8-sig'), newline=''))
fields = list(reader.fieldnames or [])
rows = list(reader)
# Specific phrases precede broad ones; the full original name stays untouched.
phrases = [
    ('kábelkötegelő', 'Kábelkötegelő'), ('kábelrögzítő', 'Kábelrögzítő'),
    ('sorkapocsléc', 'Sorkapocsléc'), ('szigetelőszalag', 'Szigetelőszalag'),
    ('elosztóadapter', 'Elosztóadapter'), ('kábeldob', 'Kábeldob'),
    ('hosszabbító', 'Hosszabbító'), ('könyökidom', 'Könyökidom'), ('csőtoldó', 'Csőtoldó'),
    ('asztali lámpa', 'Asztali lámpa'), ('éjjeli fény', 'Éjjeli fény'),
    ('mozgásérzékelő', 'Mozgásérzékelő'), ('hajólámpa', 'Hajólámpa'),
    ('mennyezeti lámpa', 'Mennyezeti lámpa'), ('led panel', 'LED panel'),
    ('usb lámpa', 'USB lámpa'), ('lámpatest', 'Lámpatest'),
    ('okos led fényforrás', 'Okos LED fényforrás'), ('fényforrás', 'Fényforrás'),
    ('reflektor', 'Reflektor'), ('gyertyaizzó', 'Gyertyaizzó'),
    ('gömbizzó', 'Gömbizzó'), ('dekorációs izzó', 'Dekorációs izzó'),
    ('filament izzó', 'Filament izzó'), ('led izzó', 'LED izzó'),
    ('szellőzőrács', 'Szellőzőrács'), ('fejlámpa', 'Fejlámpa'),
    ('időkapcsoló', 'Időkapcsoló'), ('kismegszakító', 'Kismegszakító'),
    ('csatlakozóaljzat', 'Csatlakozóaljzat'), ('munkalámpa', 'Munkalámpa'),
    ('ceruzaizzó', 'Ceruzaizzó'), ('spotizzó', 'Spotizzó'),
    ('biztosítófoglalat', 'Biztosítófoglalat'), ('biztosítóbetét', 'Biztosítóbetét'),
    ('fényfüzér', 'Fényfüzér'), ('kapcsoló', 'Kapcsoló'), ('dugvilla', 'Dugvilla'),
    ('lengőaljzat', 'Lengőaljzat'), ('szerelvénydoboz', 'Szerelvénydoboz'),
    ('vezeték', 'Vezeték'), ('keresőlámpa', 'Keresőlámpa'),
    ('kötődoboz', 'Kötődoboz'), ('lámpafoglalat', 'Lámpafoglalat'),
    ('ventilátor', 'Ventilátor'),
]
column = 'rövid terméknév'
assert len(rows) == 107
if column not in fields:
    fields.append(column)
for row in rows:
    if not row.get(column):
        matches = [title for phrase, title in phrases if phrase in row['product strict name'].lower()]
        assert matches, row['product strict name']
        row[column] = matches[0]
backup = path.with_suffix('.csv.before-short-names.bak')
if not backup.exists():
    shutil.copy2(path, backup)
output = io.StringIO(newline='')
writer = csv.DictWriter(output, fieldnames=fields, lineterminator='\r\n' if b'\r\n' in raw else '\n')
writer.writeheader()
writer.writerows(rows)
path.write_bytes(output.getvalue().encode('utf-8-sig' if raw.startswith(b'\xef\xbb\xbf') else 'utf-8'))
original = list(csv.DictReader(backup.open(encoding='utf-8-sig', newline='')))
updated = list(csv.DictReader(path.open(encoding='utf-8-sig', newline='')))
assert len(original) == len(updated) == 107
assert all(all(new[key] == value for key, value in old.items()) for old, new in zip(original, updated))
assert all(row[column] for row in updated)
print('Verified 107 short titles; original columns and row order preserved. Backup:', backup)
