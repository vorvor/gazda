"""Regression checks for editable HTML structure and legacy anchors."""
import json
import re
from collections import Counter
from pathlib import Path
import unittest
from bs4 import BeautifulSoup
from html_structure import decorate_structure

ROOT = Path(__file__).resolve().parents[1]

class StructureTest(unittest.TestCase):
    def test_every_div_has_a_class_and_unique_id(self):
        failures = []
        for path in sorted(ROOT.glob('*.html')):
            soup = BeautifulSoup(path.read_text(), 'html.parser')
            missing = soup.select('div:not([id]), div:not([class])')
            if missing:
                failures.append(f'{path.name}: {len(missing)} incomplete divs')
            ids = [tag['id'] for tag in soup.select('[id]')]
            duplicate = [value for value, count in Counter(ids).items() if count > 1]
            if duplicate:
                failures.append(f'{path.name}: duplicate IDs {duplicate}')
        self.assertEqual(failures, [])

    def test_existing_text_links_images_and_anchors_are_preserved(self):
        baseline_path = Path('/tmp/lovas-structure-baseline.json')
        if not baseline_path.exists():
            self.skipTest('Pre-change comparison snapshot is not available')
        baseline = json.loads(baseline_path.read_text())
        for filename, original in baseline.items():
            with self.subTest(page=filename):
                soup = BeautifulSoup((ROOT/filename).read_text(), 'html.parser')
                self.assertEqual(re.sub(r'\s+', '', soup.get_text()), re.sub(r'\s+', '', original['text']))
                self.assertTrue(set(original['ids']).issubset(tag['id'] for tag in soup.select('[id]')))
                self.assertTrue(set(original['links']).issubset(tag['href'] for tag in soup.select('[href]')))
                self.assertEqual(original['images'], [tag['src'] for tag in soup.select('img[src]')])

    def test_decorating_twice_is_stable(self):
        for path in ROOT.glob('*.html'):
            output = path.read_text()
            self.assertEqual(decorate_structure(output, path.name), output, path.name)

    def test_duplicate_original_ids_are_not_silently_rewritten(self):
        with self.assertRaises(ValueError):
            decorate_structure('<html><body><div id="old"></div><div id="old"></div></body></html>', 'test.html')

if __name__ == '__main__':
    unittest.main()
