"""Real CSV/HTTP tests; temporary registry, roster and log files only."""
import csv
import json
import shutil
import subprocess
import unittest
import urllib.error
import urllib.request
import re
from test_assignment_dates import AssignmentDatesTest, ROOT


class AssignmentRostersTest(AssignmentDatesTest):
    @classmethod
    def setUpClass(cls):
        super().setUpClass()
        for name in ['assignment_roster.php', 'sync_assignment_rosters.php', 'audit_log.php', 'save_roster.php', 'get_roster.php', 'list_files.php', 'set_closed.php']:
            if (ROOT / name).exists():
                text = (ROOT / name).read_text()
                # CSV fixture is isolated by environment variables.
                (cls.app / name).write_text(text)
        (cls.app / 'data').mkdir(exist_ok=True)

    def setUp(self):
        super().setUp()
        for directory in ['data', 'logs']:
            shutil.rmtree(self.app / directory, ignore_errors=True)
        (self.app / 'data').mkdir()
        self.date = '2028-02-29'
        self.file = self.date + '-forgatas.csv'

    def roster(self):
        status, body = self.request('get_roster.php?file=' + self.file)
        self.assertEqual(status, 200, body)
        return body

    def json_post(self, endpoint, payload):
        request = urllib.request.Request(self.base + endpoint, data=json.dumps(payload).encode(), headers={'Cookie': 'FELIXSESSID=testelek1234567890', 'Content-Type': 'application/json'})
        try:
            response = urllib.request.urlopen(request)
        except urllib.error.HTTPError as error:
            response = error
        return response.status, json.loads(response.read())

    def save(self, roster):
        return self.json_post('save_roster.php', dict(file=self.file, **roster))

    def test_stale_save_names_and_metadata_preservation(self):
        self.assertEqual(self.post(self.date)[0], 200)
        old = self.roster()
        old['session'] = 'checkout'
        old['rows'][0].update(notes='A, B\nC', flag='blue', arrived=True, checkedOut=True, allergy='vegan', cls='A', time='10:00', late=True, reason='bus')
        self.assertEqual(self.save(old)[0], 200)
        self.assertEqual(self.post(self.date, person_id='2')[0], 200)
        current = self.roster()
        self.assertEqual(current['session'], 'checkout')
        self.assertEqual(next(r for r in current['rows'] if r['id'] == '1'), old['rows'][0])
        self.assertEqual(self.save(old)[0], 409)
        current['rows'][0]['name'] = 'Tampered'
        self.assertEqual(self.save(current)[0], 409)
        meta = json.loads((self.app / 'data/meta.json').read_text())[self.file]
        self.assertEqual(meta['source'], 'assignments')

    def test_closed_membership_and_idempotence(self):
        self.assertEqual(self.post(self.date)[0], 200)
        self.assertEqual(self.json_post('set_closed.php', {'file': self.file, 'closed': True})[0], 200)
        self.assertEqual(self.post(self.date)[0], 200)
        self.assertEqual(self.post(self.date, person_id='2')[0], 423)
        self.assertEqual(self.post(self.date, action='remove')[0], 423)
        self.assertEqual(self.roster()['closed'], True)
        self.assertEqual([p['id'] for p in self.request('assignments_api.php?date=' + self.date)[1]['assigned']], [1])

    def test_remove_readd_empty_and_audit_history(self):
        self.assertEqual(self.post(self.date, action='remove')[0], 200)
        self.assertFalse((self.app / 'data' / self.file).exists())
        self.assertEqual(self.post(self.date)[0], 200)
        roster = self.roster()
        roster['rows'][0].update(notes='Old note', arrived=True, flag='red')
        self.assertEqual(self.save(roster)[0], 200)
        self.assertEqual(self.post(self.date, action='remove')[0], 200)
        self.assertEqual(self.roster()['rows'], [])
        project_log = self.app / 'logs' / (self.date + '-forgatas-log.csv')
        before = project_log.read_bytes()
        self.assertEqual(self.post(self.date)[0], 200)
        self.assertTrue(project_log.read_bytes().startswith(before))
        person = self.roster()['rows'][0]
        self.assertEqual(person['notes'], '')
        self.assertEqual(person['flag'], '')
        self.assertFalse(person['arrived'])
        with project_log.open() as stream:
            events = list(csv.DictReader(stream))
        self.assertEqual([e['field'] for e in events if e['action'] == 'assignment'], ['person_added', 'person_removed', 'person_added'])
        self.assertEqual(project_log.read_bytes(), (self.app / 'logs/general-log.csv').read_bytes())

    def test_collision_and_storage_failure_roll_back_db(self):
        target = self.app / 'data' / self.file
        target.write_text('id,name\n77,Manual\n')
        original = target.read_bytes()
        self.assertEqual(self.post(self.date)[0], 409)
        self.assertEqual(target.read_bytes(), original)
        self.assertIsNone(self.request('assignments_api.php?date=' + self.date)[1]['day']['id'])
        target.unlink()
        (self.app / 'logs').mkdir()
        (self.app / 'logs/general-log.csv').mkdir()
        self.assertEqual(self.post(self.date)[0], 503)
        self.assertFalse(target.exists())
        self.assertFalse((self.app / 'data/meta.json').exists())
        self.assertIsNone(self.request('assignments_api.php?date=' + self.date)[1]['day']['id'])
        (self.app / 'logs/general-log.csv').rmdir()
        self.assertEqual(self.post(self.date)[0], 200)
        originals = {p: p.read_bytes() for p in [target, self.app / 'data/meta.json', self.app / 'logs/general-log.csv']}
        project = self.app / 'logs' / (self.date + '-forgatas-log.csv')
        project.unlink()
        project.mkdir()
        self.assertEqual(self.post(self.date, person_id='2')[0], 503)
        for path, contents in originals.items():
            self.assertEqual(path.read_bytes(), contents)
        self.assertEqual([p['id'] for p in self.request('assignments_api.php?date=' + self.date)[1]['assigned']], [1])

    def test_cli_backfill_is_idempotent(self):
        self.php("$db->beginTransaction(); $day=$db->ensureDay('2028-02-29','Backfill'); $db->ensureDay('2030-01-01','Empty'); $db->assign($day['id'],1,'Elek'); $db->commit();")
        script = self.app / 'sync_assignment_rosters.php'
        first = subprocess.run(['php', str(script)], env=self.env, capture_output=True, text=True)
        self.assertEqual(first.returncode, 0, first.stdout + first.stderr)
        self.assertTrue((self.app / 'data' / self.file).exists())
        self.assertFalse((self.app / 'data/2030-01-01-forgatas.csv').exists())
        roster = self.roster()
        roster['rows'][0]['notes'] = 'Preserve backfill'
        self.assertEqual(self.save(roster)[0], 200)
        originals = {p: p.read_bytes() for d in ['data', 'logs'] for p in (self.app / d).iterdir() if p.is_file()}
        second = subprocess.run(['php', str(script)], env=self.env, capture_output=True, text=True)
        self.assertEqual(second.returncode, 0, second.stdout + second.stderr)
        for path, contents in originals.items():
            self.assertEqual(path.read_bytes(), contents)
        self.assertEqual(self.request('sync_assignment_rosters.php')[0], 404)

    def test_generation_list_get(self):
        self.assertEqual(self.post(self.date)[0], 200)
        self.assertTrue((self.app / 'data' / self.file).is_file())
        roster = self.roster()
        self.assertEqual([r['id'] for r in roster['rows']], ['1'])
        self.assertFalse(roster['rows'][0]['arrived'])
        listing = self.request('list_files.php')[1]
        self.assertEqual(listing[0]['source'], 'assignments')
        self.assertEqual(listing[0]['assignment_date'], self.date)


if __name__ == '__main__':
    unittest.main(verbosity=2)
