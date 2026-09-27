"""Offline tests; all images and outputs are disposable fixtures, never stock."""
import csv
import hashlib
import importlib.util
import json
from pathlib import Path
import tempfile
import unittest
import subprocess
import sys
from unittest.mock import patch

from PIL import Image

SCRIPT = Path(__file__).resolve().parents[1] / 'scripts' / 'batch.py'


def load_helper():
    if not SCRIPT.is_file():
        raise AssertionError('Reusable batch helper has not been implemented')
    spec = importlib.util.spec_from_file_location('gazdabolt_batch', SCRIPT)
    assert spec is not None and spec.loader is not None
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


class BatchTest(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.addCleanup(self.tmp.cleanup)
        self.root = Path(self.tmp.name) / 'stock'
        self.root.mkdir()
        self.work = Path(self.tmp.name) / 'work'
        # Filename order deliberately disagrees with capture time.
        for index in range(7):
            name = f'20260915_1012{index:02d}.jpg'
            self.image(name, f'2026:09:15 10:12:{6-index:02d}')
        self.image('processed_20260915_090000.jpg', '2026:09:15 09:00:00')
        (self.root / 'processed').mkdir()
        (self.root / 'processed/merged_products.csv').write_text(
            'unique id,product name,product type\nolder-001,Earlier,Type\n')

    def image(self, name, taken=None):
        exif = Image.Exif()
        if taken is not None:
            exif[34665] = {36867: taken}
        Image.new('RGB', (8, 8), 'green').save(self.root / name, exif=exif)

    def observations(self, manifest):
        data = json.loads(Path(manifest).read_text())
        rows = {Path(p['name']).stem: [['Zöld locsolókanna', 'Locsolókanna'],
                                      ['Fehér, kerek cserép', 'Virágcserép']]
                for p in data['photos']}
        path = self.work / 'observations.json'
        path.write_text(json.dumps(rows, ensure_ascii=False), encoding='utf-8')
        return path

    def test_exact_five_exif_order_hungarian_csv_and_unchanged_bytes(self):
        helper = load_helper()
        before = {p.name: hashlib.sha256(p.read_bytes()).hexdigest()
                  for p in self.root.glob('*.jpg')}
        previous = (self.root / 'processed/merged_products.csv').read_bytes()
        result = helper.select(self.root, self.work)
        manifest = result['manifest']
        data = json.loads(Path(manifest).read_text())
        selected = [p['name'] for p in data['photos']]
        self.assertEqual(selected, [f'20260915_1012{i:02d}.jpg' for i in [6, 5, 4, 3, 2]])
        self.assertTrue(all((self.root / n).exists() for n in before))
        result = helper.finalize(manifest, [self.observations(manifest)])
        with Path(result['csv']).open(encoding='utf-8', newline='') as stream:
            reader = csv.DictReader(stream)
            self.assertEqual(reader.fieldnames, ['unique_id', 'termék neve', 'termék típusa'])
            rows = list(reader)
        self.assertEqual(len(rows), 10)
        self.assertEqual(rows[0]['unique_id'], '20260915_101206-001')
        self.assertEqual(rows[1]['termék neve'], 'Fehér, kerek cserép')
        for name, digest in before.items():
            target = self.root / ('processed_' + name if name in selected else name)
            self.assertEqual(hashlib.sha256(target.read_bytes()).hexdigest(), digest)
        self.assertEqual(previous, (self.root / 'processed/merged_products.csv').read_bytes())
        self.assertEqual(len(result['renamed']), 5)

    def test_resume_after_second_rename_fails_without_rewriting_csv(self):
        helper = load_helper()
        manifest = helper.select(self.root, self.work)['manifest']
        obs = self.observations(manifest)
        original_rename = Path.rename
        calls = []

        def fail_second(path, target):
            calls.append(path)
            if len(calls) == 2:
                raise OSError('simulated rename interruption')
            return original_rename(path, target)

        with patch.object(Path, 'rename', fail_second):
            with self.assertRaisesRegex(OSError, 'simulated'):
                helper.finalize(manifest, [obs])
        csv_path = next((self.root / 'processed').glob('products_*.csv'))
        csv_before = csv_path.read_bytes()
        csv_stat = csv_path.stat().st_mtime_ns
        result = helper.finalize(manifest, [obs])
        self.assertEqual(len(result['renamed']), 5)
        self.assertEqual(csv_before, csv_path.read_bytes())
        self.assertEqual(csv_stat, csv_path.stat().st_mtime_ns)
        self.assertEqual(helper.finalize(manifest, [obs]), result)

    def snapshot(self):
        return {str(p.relative_to(self.root)): p.read_bytes()
                for p in self.root.rglob('*') if p.is_file()}

    def prepare(self):
        helper = load_helper()
        manifest = helper.select(self.root, self.work)['manifest']
        return helper, manifest, self.observations(manifest)

    def test_ties_use_filename_and_select_is_repeatable(self):
        for p in self.root.glob('2026*.jpg'):
            self.image(p.name, '2026:09:15 10:00:00')
        helper = load_helper()
        result = helper.select(self.root, self.work)
        self.assertEqual([p['name'] for p in result['photos']],
                         [f'20260915_1012{i:02d}.jpg' for i in range(5)])
        self.assertEqual(result, helper.select(self.root, self.work))

    def test_missing_or_invalid_exif_refused_without_stock_changes(self):
        for date in [None, 'not-a-date', '2026:9:15 1:2:3']:
            with self.subTest(date=date):
                self.image('20260915_101206.jpg', date)
                before = self.snapshot()
                with self.assertRaises(ValueError):
                    load_helper().select(self.root, self.work)
                self.assertEqual(before, self.snapshot())

    def test_less_than_five_refused(self):
        for p in sorted(self.root.glob('2026*.jpg'))[:3]:
            p.unlink()
        before = self.snapshot()
        with self.assertRaisesRegex(ValueError, 'Fewer than five'):
            load_helper().select(self.root, self.work)
        self.assertEqual(before, self.snapshot())

    def test_invalid_filename_and_symlink_refused(self):
        self.image('invalid.jpg', '2026:09:15 09:00:00')
        with self.assertRaisesRegex(ValueError, 'filename'):
            load_helper().select(self.root, self.work)
        (self.root / 'invalid.jpg').unlink()
        (self.root / '20260915_000000.jpg').symlink_to(self.root / '20260915_101206.jpg')
        with self.assertRaisesRegex(ValueError, 'Symlink'):
            load_helper().select(self.root, self.work)

    def test_missing_extra_empty_duplicate_and_malformed_observations(self):
        helper, manifest, source = self.prepare()
        original = json.loads(source.read_text())
        key = next(iter(original))
        cases = [dict(list(original.items())[1:]), dict(original, extra=[['x', 'y']])]
        cases += [dict(original, **{key: value}) for value in
                  [[], [['', 'Típus']], [['Név']], [['Név', 1]], [['Név', 'Típus']] * 2]]
        before = self.snapshot()
        for data in cases:
            with self.subTest(data=data):
                source.write_text(json.dumps(data))
                with self.assertRaises(ValueError):
                    helper.finalize(manifest, [source])
                self.assertEqual(before, self.snapshot())

    def test_duplicate_photo_key_in_json_refused(self):
        helper, manifest, source = self.prepare()
        original = source.read_text()
        key = next(iter(json.loads(original)))
        source.write_text(original[:-1] + ',' + json.dumps(key) + ': [["Másik", "Típus"]]}')
        before = self.snapshot()
        with self.assertRaisesRegex(ValueError, 'Duplicate JSON key'):
            helper.finalize(manifest, [source])
        self.assertEqual(before, self.snapshot())

    def test_fragment_merge_and_overlapping_keys(self):
        helper, manifest, source = self.prepare()
        with self.assertRaisesRegex(ValueError, 'Duplicate photo keys'):
            helper.finalize(manifest, [source, source])
        items = list(json.loads(source.read_text()).items())
        other = self.work / 'fragment.json'
        source.write_text(json.dumps(dict(items[:2])))
        other.write_text(json.dumps(dict(items[2:])))
        self.assertEqual(helper.finalize(manifest, [source, other])['rows'], 10)

    def test_changed_source_refused(self):
        helper, manifest, source = self.prepare()
        (self.root / '20260915_101206.jpg').write_bytes(b'changed')
        before = self.snapshot()
        with self.assertRaisesRegex(ValueError, 'Source changed'):
            helper.finalize(manifest, [source])
        self.assertEqual(before, self.snapshot())

    def test_changed_eligible_set_refused(self):
        helper, manifest, source = self.prepare()
        self.image('20260915_000000.jpg', '2026:09:15 00:00:00')
        before = self.snapshot()
        with self.assertRaisesRegex(ValueError, 'Eligible files changed'):
            helper.finalize(manifest, [source])
        self.assertEqual(before, self.snapshot())

    def test_existing_rename_target_refused(self):
        helper, manifest, source = self.prepare()
        (self.root / 'processed_20260915_101206.jpg').write_bytes(b'never overwrite')
        before = self.snapshot()
        with self.assertRaisesRegex(ValueError, 'Rename target exists'):
            helper.finalize(manifest, [source])
        self.assertEqual(before, self.snapshot())

    def test_old_and_new_csv_id_headers_checked(self):
        helper, manifest, source = self.prepare()
        for header in ['unique id', 'unique_id']:
            with self.subTest(header=header):
                (self.root / 'processed/collision.csv').write_text(
                    header + ',name,type\n20260915_101206-001,Név,Típus\n')
                before = self.snapshot()
                with self.assertRaisesRegex(ValueError, 'ID collision'):
                    helper.finalize(manifest, [source])
                self.assertEqual(before, self.snapshot())

    def test_existing_output_without_receipt_refused(self):
        helper, manifest, source = self.prepare()
        batch = json.loads(Path(manifest).read_text())['batch']
        (self.root / 'processed' / (batch + '.csv')).write_text('unrelated data')
        before = self.snapshot()
        with self.assertRaisesRegex(ValueError, 'already exists'):
            helper.finalize(manifest, [source])
        self.assertEqual(before, self.snapshot())

    def test_csv_publication_failure_never_renames_and_can_retry(self):
        helper, manifest, source = self.prepare()
        original = helper.publish

        def fail_csv(path, payload):
            if path.suffix == '.csv':
                raise OSError('simulated disk failure')
            return original(path, payload)

        before = self.snapshot()
        with patch.object(helper, 'publish', fail_csv):
            with self.assertRaisesRegex(OSError, 'simulated'):
                helper.finalize(manifest, [source])
        self.assertEqual(before, self.snapshot())
        self.assertEqual(helper.finalize(manifest, [source])['rows'], 10)

    def test_corrupt_published_csv_refused_before_any_rename(self):
        helper, manifest, source = self.prepare()
        original = helper.publish

        def corrupt_csv(path, payload):
            original(path, b'corrupt' if path.suffix == '.csv' else payload)

        with patch.object(helper, 'publish', corrupt_csv):
            with self.assertRaisesRegex(ValueError, 'Output mismatch'):
                helper.finalize(manifest, [source])
        self.assertEqual(len(list(self.root.glob('2026*.jpg'))), 7)

    def test_cli_end_to_end_on_fixtures_only(self):
        command = [sys.executable, str(SCRIPT)]
        result = subprocess.run(command + ['select', '--root', str(self.root), '--work-dir', str(self.work)],
                                check=True, capture_output=True, text=True)
        manifest = json.loads(result.stdout)['manifest']
        source = self.observations(manifest)
        result = subprocess.run(command + ['finalize', manifest, str(source)],
                                check=True, capture_output=True, text=True)
        self.assertTrue(json.loads(result.stdout)['verified'])

    def test_prepared_observations_cannot_change_on_retry(self):
        helper, manifest, source = self.prepare()
        with patch.object(Path, 'rename', side_effect=OSError('interrupted')):
            with self.assertRaises(OSError):
                helper.finalize(manifest, [source])
        data = json.loads(source.read_text())
        data[next(iter(data))][0][0] = 'Megváltozott név'
        source.write_text(json.dumps(data))
        before = self.snapshot()
        with self.assertRaisesRegex(ValueError, 'Prepared batch changed'):
            helper.finalize(manifest, [source])
        self.assertEqual(before, self.snapshot())

    def test_concurrent_commit_lock_refuses_second_caller(self):
        helper = load_helper()
        before = self.snapshot()
        with helper.locked(self.root):
            with self.assertRaises(BlockingIOError):
                helper.select(self.root, self.work)
        self.assertEqual(before, self.snapshot())


if __name__ == '__main__':
    unittest.main()
