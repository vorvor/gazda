#!/usr/bin/env python3
"""Five-photo inventory mechanics; recognition and Hungarian wording stay human/LLM-owned."""
import argparse
import csv
from datetime import datetime
import fcntl
import hashlib
import io
import json
import os
from pathlib import Path
import re
import tempfile
from contextlib import contextmanager

from PIL import Image

ROOT = Path('/workspace/web/gazdabolt_full_stock')
WORK = Path('/workspace/tmp/gazdabolt-product-photo-csv')
FIELDS = ['unique_id', 'termék neve', 'termék típusa']


def require(condition, message):
    if not condition:
        raise ValueError(message)


def digest(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()


def read_json(path):
    def unique(pairs):
        result = {}
        for key, value in pairs:
            require(key not in result, f'Duplicate JSON key: {key}')
            result[key] = value
        return result
    return json.loads(Path(path).read_text(encoding='utf-8'), object_pairs_hook=unique)


def publish(path, payload):
    """Publish a flushed complete file without overwriting an existing target."""
    fd, temporary = tempfile.mkstemp(prefix='.inventory-', dir=path.parent)
    try:
        with os.fdopen(fd, 'wb') as stream:
            stream.write(payload)
            stream.flush()
            os.fsync(stream.fileno())
        os.link(temporary, path)
    finally:
        os.unlink(temporary)
    fd = os.open(path.parent, os.O_RDONLY)
    try:
        os.fsync(fd)
    finally:
        os.close(fd)


def eligible(root):
    return sorted(p for p in root.glob('*.jpg')
                  if p.is_file() and not p.name.startswith('processed_'))


@contextmanager
def locked(root):
    # Linux directory lock: no lock file in the public stock directory.
    fd = os.open(root, os.O_RDONLY)
    try:
        fcntl.flock(fd, fcntl.LOCK_EX | fcntl.LOCK_NB)
        yield
    finally:
        os.close(fd)


def select(root=ROOT, work=WORK):
    root, work = Path(root).resolve(), Path(work).resolve()
    with locked(root):
        paths = eligible(root)
        require(len(paths) >= 5, 'Fewer than five eligible JPG photos; no batch selected')
        photos = []
        for path in paths:
            require(not path.is_symlink(), f'Symlink photo refused: {path.name}')
            require(re.fullmatch(r'\d{8}_\d{6}\.jpg', path.name),
                    f'Unexpected filename; do not normalize it: {path.name}')
            with Image.open(path) as image:
                taken = image.getexif().get_ifd(34665).get(36867)
            if not isinstance(taken, str):
                raise ValueError(f'Missing EXIF DateTimeOriginal: {path.name}')
            require(re.fullmatch(r'\d{4}:\d{2}:\d{2} \d{2}:\d{2}:\d{2}', taken),
                    f'Invalid EXIF timestamp format: {path.name}')
            datetime.strptime(taken, '%Y:%m:%d %H:%M:%S')
            photos.append({'name': path.name, 'taken': taken})
        photos.sort(key=lambda p: (p['taken'], p['name']))
        photos = photos[:5]
        for photo in photos:
            photo['sha256'] = digest(root / photo['name'])
        batch = f"products_{Path(photos[0]['name']).stem}_{Path(photos[-1]['name']).stem.split('_')[1]}"
        folder = work / batch
        folder.mkdir(parents=True, exist_ok=True)
        manifest = folder / 'manifest.json'
        data = {'root': str(root), 'batch': batch, 'eligible': [p.name for p in paths], 'photos': photos}
        if manifest.exists():
            require(read_json(manifest) == data, 'Existing manifest differs; use a fresh work directory')
        else:
            publish(manifest, json.dumps(data, ensure_ascii=False, indent=2).encode('utf-8'))
        return {'manifest': str(manifest), 'photos': [dict(p, path=str(root / p['name'])) for p in photos]}


def finalize(manifest, observations):
    manifest = Path(manifest)
    data = read_json(manifest)
    root = Path(data['root'])
    with locked(root):
        require(len(data['photos']) == 5, 'Manifest must contain exactly five photos')
        receipt = manifest.parent / 'prepared.json'
        require(not receipt.is_symlink(), 'Receipt symlink refused')
        obs = {}
        for source in observations:
            part = read_json(source)
            require(isinstance(part, dict) and not set(part).intersection(obs), 'Duplicate photo keys or invalid JSON mapping')
            obs.update(part)
        stems = [Path(p['name']).stem for p in data['photos']]
        require(len(set(stems)) == 5, 'Manifest repeats photos')
        require(data['batch'] == f"products_{stems[0]}_{stems[-1].split('_')[1]}", 'Invalid output filename')
        require(set(obs) == set(stems), 'Observations must cover exactly the selected five photos')
        rows, counts, pairs = [], {}, []
        for photo, stem in zip(data['photos'], stems):
            name = photo['name']
            require(re.fullmatch(r'\d{8}_\d{6}\.jpg', name), 'Invalid source filename')
            a, b = root / name, root / ('processed_' + name)
            require(not a.is_symlink() and not b.is_symlink(), 'Photo symlink refused')
            if a.exists():
                require(a.is_file() and digest(a) == photo['sha256'], f'Source changed: {name}')
                require(not b.exists(), f'Rename target exists: {b.name}')
            else:
                require(receipt.is_file() and b.is_file() and digest(b) == photo['sha256'], f'Missing/changed source: {name}')
            entries = obs[stem]
            require(isinstance(entries, list) and entries, f'Empty/unreviewed observations: {stem}')
            require(all(isinstance(x, list) and len(x) == 2 and all(isinstance(v, str) and v.strip() for v in x) for x in entries), 'Expected [Hungarian name, Hungarian type] pairs')
            require(len({tuple(x) for x in entries}) == len(entries), f'Duplicate observation: {stem}')
            counts[stem] = len(entries)
            rows.extend([f'{stem}-{i:03d}', n, t] for i, (n, t) in enumerate(entries, 1))
            pairs.append((a, b, photo['sha256']))
        renamed = {a.name for a, b, h in pairs if not a.exists()}
        require([p.name for p in eligible(root)] == [n for n in data['eligible'] if n not in renamed],
                'Eligible files changed; stop and review selection')
        output = root / 'processed'
        require(not output.is_symlink(), 'Output directory symlink refused')
        output.mkdir(exist_ok=True)
        csv_path = output / (data['batch'] + '.csv')
        notes = output / (data['batch'] + '_notes.txt')
        ids = {r[0] for r in rows}
        for previous in output.glob('*.csv'):
            if previous == csv_path and receipt.exists():
                continue
            with previous.open(encoding='utf-8-sig', newline='') as stream:
                for row in csv.DictReader(stream):
                    require(not ids.intersection([row.get('unique_id'), row.get('unique id')]), f'ID collision in {previous.name}')
        stream = io.StringIO(newline='')
        writer = csv.writer(stream)
        writer.writerow(FIELDS)
        writer.writerows(rows)
        csv_bytes = stream.getvalue().encode('utf-8')
        note_bytes = ('EXIF DateTimeOriginal szerinti öt fénykép. Az azonosító a forrásfájl neve és háromjegyű sorszám.\n' + '\n'.join(f"{p['name']} | {p['taken']} | {counts[Path(p['name']).stem]}" for p in data['photos']) + '\nKépenkénti termékmegfigyelések, nem készletmennyiségek vagy igazolt cikkszámok. Képek között nincs összevonás. A méretleírások becslések, kivéve az olvasható címkeadatokat.\n').encode('utf-8')
        checkpoint = {'manifest': digest(manifest), 'csv': hashlib.sha256(csv_bytes).hexdigest(),
                      'notes': hashlib.sha256(note_bytes).hexdigest()}
        if receipt.exists():
            require(read_json(receipt) == checkpoint, 'Prepared batch changed; do not edit observations after publication')
        else:
            require(not any(p.exists() or p.is_symlink() for p in (csv_path, notes)), 'Batch output already exists; do not overwrite it')
            publish(receipt, json.dumps(checkpoint).encode('utf-8'))
        for target, payload in ((csv_path, csv_bytes), (notes, note_bytes)):
            require(not target.is_symlink(), 'Output symlink refused')
            if not target.exists():
                publish(target, payload)
            require(target.read_bytes() == payload, f'Output mismatch: {target}; no further renames')
        with csv_path.open(encoding='utf-8', newline='') as stream:
            require(list(csv.reader(stream)) == [FIELDS] + rows, 'CSV read-back failed; photos not renamed')
        for a, b, expected in pairs:
            if a.exists():
                require(not b.exists(), f'Rename target exists: {b}')
                require(digest(a) == expected, f'Source changed before rename: {a}')
                a.rename(b)
            require(digest(b) == expected, f'Rename hash mismatch: {b}')
        return {'csv': str(csv_path), 'rows': len(rows), 'per_photo': counts, 'renamed': [b.name for a, b, h in pairs], 'verified': True}


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    commands = parser.add_subparsers(dest='command', required=True)
    plan = commands.add_parser('select', help='Select exactly five; write only a private manifest')
    plan.add_argument('--root', type=Path, default=ROOT)
    plan.add_argument('--work-dir', type=Path, default=WORK)
    finish = commands.add_parser('finalize', help='Validate observations, export CSV, then rename selected sources')
    finish.add_argument('manifest', type=Path)
    finish.add_argument('observations', nargs='+', type=Path)
    args = parser.parse_args()
    try:
        result = select(args.root, args.work_dir) if args.command == 'select' else finalize(args.manifest, args.observations)
    except (ValueError, OSError, KeyError) as error:
        parser.exit(1, f'ERROR: {error}\n')
    print(json.dumps(result, ensure_ascii=False, indent=2))


if __name__ == '__main__':
    main()
