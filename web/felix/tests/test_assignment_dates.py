"""Real HTTP/CSV date tests; only temporary fixtures mutate."""
import json
import os
from pathlib import Path
import re
import socket
import subprocess
import tempfile
import time
import unittest
import urllib.error
import urllib.parse
import urllib.request
import uuid
from typing import Any
from datetime import datetime
from zoneinfo import ZoneInfo

ROOT = Path(__file__).resolve().parents[1]


class AssignmentDatesTest(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.tmp = tempfile.TemporaryDirectory(prefix='felix-dates-')
        cls.addClassCleanup(cls.tmp.cleanup)
        cls.app = Path(cls.tmp.name)
        cls.env = dict(os.environ, FELIX_DB_CONFIG='/nonexistent/no-database', FELIX_CSV_DIR=str(cls.app / 'registry'), FELIX_DATA_DIR=str(cls.app / 'data'))
        for source in ROOT.glob('*.php'):
            (cls.app / source.name).write_text(source.read_text())
        cls.php("$db->beginTransaction(); for($i=1;$i<=12;$i++) $db->people[]=['id'=>$i,'demo_key'=>'test-'.$i,'name'=>$i===1?'Almavirág Anna':'Person '.$i,'city'=>'Budapest','fictional'=>1]; $db->commit();")
        for user in ['Elek', 'Kecsu']:
            (cls.app / ('sess_test' + user.lower() + '1234567890')).write_text(f'user|s:{len(user)}:"{user}";csrf|s:64:"' + 'a' * 64 + '";')
        with socket.socket() as sock:
            sock.bind(('127.0.0.1', 0))
            port = sock.getsockname()[1]
        cls.base = f'http://127.0.0.1:{port}/'
        cls.server = subprocess.Popen(['php', '-d', 'session.save_path=' + cls.tmp.name, '-S', f'127.0.0.1:{port}', '-t', cls.tmp.name], env=cls.env, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
        cls.addClassCleanup(cls.stop_server)
        for _ in range(60):
            try:
                cls.request('assignments_api.php', user=None)
                break
            except urllib.error.URLError:
                time.sleep(.05)
        else:
            raise RuntimeError('PHP server failed to start')

    @classmethod
    def stop_server(cls):
        cls.server.terminate()
        cls.server.wait(timeout=10)

    @classmethod
    def php(cls, code):
        code = 'require ' + json.dumps(str(cls.app / 'assignments_db.php')) + '; $db=felixAssignmentsDb(); ' + code
        subprocess.run(['php', '-r', code], env=cls.env, check=True, capture_output=True)

    def setUp(self):
        import shutil
        for directory in ['data', 'logs']:
            shutil.rmtree(self.app / directory, ignore_errors=True)
        (self.app / 'data').mkdir()
        self.php('$db->beginTransaction(); $db->assignments=[]; $db->days=[]; $db->sources=[]; $db->commit();')

    @classmethod
    def request(cls, path, user: str | None = 'Elek', data=None) -> tuple[int, Any]:
        headers = {'Cookie': 'FELIXSESSID=test' + user.lower() + '1234567890'} if user else {}
        payload = urllib.parse.urlencode(data).encode() if data is not None else None
        try:
            response = urllib.request.urlopen(urllib.request.Request(cls.base + path, data=payload, headers=headers))
        except urllib.error.HTTPError as error:
            response = error
        body = response.read().decode()
        try:
            body = json.loads(body)
        except ValueError:
            pass
        return response.status, body

    def post(self, date, **kwargs):
        return self.request('assignments_api.php', data={'date': date, 'person_id': '1', 'action': 'assign', 'csrf': 'a' * 64, **kwargs})

    def test_new_assignment_persistence_duplicates_and_date_isolation(self):
        for _ in range(2):
            self.assertEqual(self.post('2028-02-29')[0], 200)
        first = self.request('assignments_api.php?date=2028-02-29')[1]
        self.assertIsNotNone(first['day']['id'])
        self.assertEqual([int(p['id']) for p in first['assigned']], [1])
        self.assertEqual(self.post('2028-03-01', person_id='2')[0], 200)
        second = self.request('assignments_api.php?date=2028-03-01')[1]
        self.assertEqual([int(p['id']) for p in second['assigned']], [2])
        self.assertEqual(self.post('2028-02-29', action='remove')[0], 200)
        self.assertEqual(self.request('assignments_api.php?date=2028-02-29')[1]['assigned'], [])
        self.assertEqual(self.request('assignments_api.php?date=2028-03-01')[1], second)

    def test_summary_reader_default_dates_and_authorization(self):
        today = datetime.now(ZoneInfo('Europe/Budapest')).strftime('%Y-%m-%d')
        for endpoint in ['filming_day.php', 'assignments_api.php']:
            for suffix in ['', '?date=']:
                status, body = self.request(endpoint + suffix)
                self.assertEqual(status, 200)
                self.assertEqual(body['day']['date'], today)
                self.assertIsNone(body['day']['id'])
        self.assertEqual(self.post('2028-03-01', person_id='2')[0], 200)
        self.assertEqual(self.post('2028-02-29')[0], 200)
        self.php("$db->beginTransaction(); foreach($db->days as &$day) if($day['date']==='2028-02-29') $day['title']='Preserved title'; unset($day); $db->commit();")
        self.assertEqual(self.post('2028-02-29')[0], 200)
        status, body = self.request('filming_day.php', user='Kecsu')
        self.assertEqual(status, 200)
        self.assertEqual(set(body), {'day', 'assigned', 'days'})
        self.assertEqual(body['day']['date'], '2028-02-29')
        self.assertEqual(body['day']['title'], 'Preserved title')
        self.assertEqual([int(p['id']) for p in body['assigned']], [1])
        self.assertEqual(set(body['assigned'][0]), {'id', 'name', 'city', 'fictional'})
        self.assertEqual(body['days'], [{'date': '2028-02-29', 'title': 'Preserved title'}, {'date': '2028-03-01', 'title': 'Forgatási nap'}])
        self.assertEqual(self.request('assignments_api.php')[1]['day'], body['day'])
        selected = self.request('filming_day.php?date=2028-03-01', user='Kecsu')[1]
        self.assertEqual([int(p['id']) for p in selected['assigned']], [2])
        missing = self.request('filming_day.php?date=2030-01-01', user='Kecsu')[1]
        self.assertEqual(missing['day'], {'id': None, 'date': '2030-01-01', 'title': 'Forgatási nap'})
        self.assertEqual(missing['assigned'], [])
        for user in ['Elek', 'Kecsu']:
            self.assertEqual(self.request('filming_day.php', user=user, data={'date': '2030-01-01'})[0], 405)
        self.assertEqual(self.request('filming_day.php', user=None)[0], 401)
        for data in [None, {'action': 'assign'}]:
            self.assertEqual(self.request('assignments_api.php', user='Kecsu', data=data)[0], 403)
            self.assertEqual(self.request('assignments_api.php', user=None, data=data)[0], 401)
        for date in ['0000-01-01', '2026-02-29', '2026-04-31', 'garbage']:
            self.assertEqual(self.request('filming_day.php?date=' + date, user='Kecsu')[0], 400)
        self.assertEqual(self.request('filming_day.php?date[]=2028-02-29')[0], 400)
        self.assertEqual(self.request('filming_day.php', user='Kecsu')[1], body)

    def test_remove_and_invalid_posts_never_create_days(self):
        for _ in range(2):
            self.assertEqual(self.post('2031-01-01', action='remove')[0], 200)
        for changes in [{'person_id': '999999'}, {'person_id': '0'}, {'person_id': '-1'}, {'person_id': '1 OR 1=1'}, {'action': 'bad'}]:
            self.assertEqual(self.post('2031-01-01', **changes)[0], 400)
        self.assertEqual(self.post('2031-01-01', csrf='bad')[0], 403)
        self.assertEqual(self.request('filming_day.php')[1]['days'], [])

    def test_assignment_failure_rolls_back_new_day(self):
        # Real filesystem failure after mutation must not publish a new day.
        target = self.app / 'data/2032-01-01-forgatas.csv'
        target.mkdir()
        try:
            self.assertEqual(self.post('2032-01-01')[0], 503)
            self.assertEqual(self.request('filming_day.php')[1]['days'], [])
            self.assertIsNone(self.request('assignments_api.php?date=2032-01-01')[1]['day']['id'])
        finally:
            target.rmdir()
        self.assertEqual(self.post('2032-01-01')[0], 200)

    def test_parallel_csv_writers_preserve_all_links(self):
        from concurrent.futures import ThreadPoolExecutor
        code = '$db->beginTransaction(); usleep(20000); $day=$db->ensureDay("2029-05-01","Concurrent"); $db->assign($day["id"], PERSON, "test"); $db->commit();'
        with ThreadPoolExecutor(max_workers=8) as pool:
            list(pool.map(lambda person: self.php(code.replace('PERSON', str(person))), range(1, 9)))
        state = self.request('assignments_api.php?date=2029-05-01')[1]
        self.assertEqual(sorted(p['id'] for p in state['assigned']), list(range(1, 9)))

    def test_invalid_calendar_dates(self):
        for date in ['0000-01-01', '2026-02-29', '2026-04-31', '2026-13-01', '2026-00-12', '2026-01-00', '2026-1-01', '2026-01-01\n', '2026-01-01junk']:
            with self.subTest(date=date):
                self.assertEqual(self.request('assignments_api.php?date=' + urllib.parse.quote(date))[0], 400)
                self.assertEqual(self.post(date)[0], 400)
        self.assertEqual(self.request('assignments_api.php?date[]=2026-01-01')[0], 400)

    def test_nonexistent_date_is_read_only(self):
        status, body = self.request('assignments_api.php?date=2028-02-29')
        self.assertEqual(status, 200)
        self.assertEqual(body['day'], {'id': None, 'date': '2028-02-29', 'title': 'Forgatási nap'})
        self.assertEqual(body['assigned'], [])
        self.assertEqual(len(body['people']), 12)
        self.assertEqual(self.request('filming_day.php')[1]['days'], [])


if __name__ == '__main__':
    unittest.main(verbosity=2)
