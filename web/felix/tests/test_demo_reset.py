"""Isolated end-to-end destructive demo reset; never modifies live storage."""
import csv
import json
import os
from pathlib import Path
import subprocess
import tempfile
import unittest

ROOT = Path(__file__).resolve().parents[1]
DATES = ['2026-10-05', '2026-10-06', '2026-10-07']


def read_csv(path):
    with path.open() as stream:
        return list(csv.DictReader(stream))


class DemoResetTest(unittest.TestCase):
    def test_canonical_reset_is_explicit_backed_up_and_repeatable(self):
        with tempfile.TemporaryDirectory(prefix='felix-demo-') as tmp:
            base = Path(tmp)
            data = base / 'data'
            data.mkdir()
            registry = base / 'registry'
            env = dict(os.environ, FELIX_DATA_DIR=str(data), FELIX_CSV_DIR=str(registry),
                       FELIX_DEMO_BACKUP_DIR=str(base / 'backups'))
            seed = 'require ' + json.dumps(str(ROOT / 'assignments_db.php')) + ''';
$db=felixAssignmentsDb(); $db->beginTransaction();
foreach ([2,7,11,19,25,31,40,51,67] as $id) $db->people[]=['id'=>$id,'demo_key'=>'test-'.$id,'name'=>'Same name','city'=>'Budapest','fictional'=>1];
$db->ensureDay('2020-01-01','Old'); $db->commit();'''
            subprocess.run(['php', '-r', seed], env=env, check=True)
            old = data / '2020-01-01-old.csv'
            old.write_text('id,name\nlegacy,Old person\n')
            sample = data / 'sample.csv'
            sample.write_text('inactive sample\n')
            (data / 'meta.json').write_text('{"2020-01-01-old.csv":{"session":"checkout"}}')
            def run(*args):
                return subprocess.run(['php', str(ROOT / 'demo_reset.php'), *args], env=env, capture_output=True, text=True)
            def tables():
                gen = registry / (registry / 'CURRENT').read_text().strip()
                return {name: read_csv(gen / (name + '.csv')) for name in ['people','days','assignments','sources']}
            before = tables()
            refused = run()
            self.assertNotEqual(refused.returncode, 0)
            self.assertEqual(tables(), before)
            unsafe_env = dict(env, FELIX_DEMO_BACKUP_DIR=str(ROOT))
            unsafe = subprocess.run(['php', str(ROOT / 'demo_reset.php'), '--reset'], env=unsafe_env, capture_output=True, text=True)
            self.assertNotEqual(unsafe.returncode, 0)
            self.assertIn('outside webroot', unsafe.stderr)
            self.assertEqual(tables(), before)
            self.assertTrue(old.exists())
            # Refuse an incomplete backup before touching existing active files.
            (data / 'unsafe-link').symlink_to(sample)
            incomplete = run('--reset')
            self.assertNotEqual(incomplete.returncode, 0)
            self.assertIn('Symlink', incomplete.stderr)
            self.assertEqual(tables(), before)
            self.assertTrue(old.exists())
            (data / 'unsafe-link').unlink()
            result = run('--reset')
            self.assertEqual(result.returncode, 0, result.stdout + result.stderr)
            report = json.loads(result.stdout)
            backup = Path(report['backup'])
            self.assertEqual((backup / 'data' / old.name).read_bytes(), old_bytes := b'id,name\nlegacy,Old person\n')
            self.assertFalse(old.exists())
            self.assertEqual(sample.read_text(), 'inactive sample\n')
            after = tables()
            self.assertEqual(after['people'], before['people'])
            self.assertEqual([d['date'] for d in after['days']], DATES)
            ids = {p['id'] for p in after['people']}
            self.assertEqual({a['person_id'] for a in after['assignments']}, ids)
            self.assertEqual(len(after['assignments']), 18)
            meta = json.loads((data / 'meta.json').read_text())
            all_rows = []
            for i, date in enumerate(DATES):
                filename = date + '-forgatas.csv'
                rows = read_csv(data / filename)
                all_rows.extend(rows)
                self.assertEqual(len(rows), 6)
                self.assertTrue(all(r['id'].isdigit() and r['id'] in ids for r in rows))
                self.assertEqual(meta[filename]['source'], 'assignments')
                self.assertEqual(meta[filename]['session'], 'checkin' if i == 0 else 'checkout')
                self.assertEqual(meta[filename]['closed'], i == 2)
                for r in rows:
                    self.assertFalse(r['cancelled'] == '1' and r['arrived'] == '1')
                    self.assertFalse(r['checkedOut'] == '1' and r['arrived'] != '1')
                    if i == 2 and r['cancelled'] != '1': self.assertEqual(r['checkedOut'], '1')
            for field in ['cancelled','late','arrived','checkedOut']:
                self.assertIn('1', {r[field] for r in all_rows})
            for field in ['notes','allergy','flag']:
                self.assertTrue(any(r[field] for r in all_rows))
            self.assertEqual(len(after['sources']), 18)
            self.assertTrue(all(s['legacy_id'] == s['person_id'] for s in after['sources']))
            stable = {p.name: p.read_bytes() for p in data.glob('*.csv')}
            for _ in range(2):
                sync = subprocess.run(['php', str(ROOT / 'setup_assignments.php')], env=env, capture_output=True, text=True)
                self.assertEqual(sync.returncode, 0, sync.stderr)
                self.assertEqual(tables(), after)
            repeated = run('--reset')
            self.assertEqual(repeated.returncode, 0, repeated.stderr)
            self.assertEqual(tables(), after)
            self.assertEqual({p.name: p.read_bytes() for p in data.glob('*.csv')}, stable)


if __name__ == '__main__': unittest.main(verbosity=2)
