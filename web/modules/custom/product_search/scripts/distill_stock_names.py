"""Add editable short Hungarian card titles without altering source columns."""
import argparse
import csv
import io
import pathlib
import re
import shutil

COLUMN = 'rövid terméknév'
PREFIX = re.compile(r'^(?:dekoratív|műanyag|fém|kerámia|zománcozott|átlátszó|dupla|fedeles|teleszkópos|általános|kerti|kézi)\s+', re.I)


def clean(kind):
    value = kind.strip()
    while PREFIX.match(value):
        value = PREFIX.sub('', value, count=1)
    return value[:1].upper() + value[1:]


def distill(name, kind, types):
    preferred = clean(kind)
    # Keep the known object when Hungarian suffixes hide the word boundary;
    # otherwise packaging such as "zsák" can win over "virágföldje".
    if preferred and re.search(r'(?<!\w)' + re.escape(preferred), name, re.I):
        return preferred
    # Match known product nouns in the actual name, not merely its category.
    # This distinguishes a lombseprű even when its source type says gereblye.
    candidates = {clean(value) for value in types}
    matches = [value for value in candidates if value and re.search(
        r'(?<!\w)' + re.escape(value) + r'(?!\w)', name, re.I)]
    if matches:
        return max(matches, key=lambda value: (len(value), value))
    # Do not guess a specific object for uncertain source classifications.
    return clean(kind) or name.strip()


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('csv', type=pathlib.Path)
    parser.add_argument('--write', action='store_true')
    parser.add_argument('--refresh', action='store_true', help='Regenerate existing short names explicitly')
    args = parser.parse_args()
    raw = args.csv.read_bytes()
    reader = csv.DictReader(io.StringIO(raw.decode('utf-8-sig'), newline=''))
    fields = list(reader.fieldnames or [])
    rows = list(reader)
    originals = [dict(row) for row in rows]
    types = sorted({row['termék típusa'] for row in rows})
    if COLUMN not in fields:
        fields.append(COLUMN)
    for row in rows:
        row[COLUMN] = ('' if args.refresh else row.get(COLUMN, '').strip()) or distill(row['termék neve'], row['termék típusa'], types)
    assert all(row[COLUMN] for row in rows)
    output = io.StringIO(newline='')
    writer = csv.DictWriter(output, fieldnames=fields)
    writer.writeheader()
    writer.writerows(rows)
    reread = list(csv.DictReader(io.StringIO(output.getvalue(), newline='')))
    assert len(reread) == len(originals)
    for old, new in zip(originals, reread):
        assert all(new[key] == value for key, value in old.items() if key != COLUMN)
    if args.write:
        backup = args.csv.with_suffix('.csv.before-short-names.bak')
        if not backup.exists():
            shutil.copy2(args.csv, backup)
        encoding = 'utf-8-sig' if raw.startswith(b'\xef\xbb\xbf') else 'utf-8'
        args.csv.write_bytes(output.getvalue().encode(encoding))
        print(f'Updated {len(rows)} rows; original columns preserved. Backup: {backup}')
    else:
        for row in rows[::100]:
            print(row[COLUMN], '<-', row['termék neve'])
        print(f'Preview: {len(rows)} rows; no files changed.')


if __name__ == '__main__':
    main()
