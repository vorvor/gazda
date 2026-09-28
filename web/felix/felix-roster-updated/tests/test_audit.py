"""Audit integration tests using disposable project data and real HTTP requests."""
import base64
import csv
import datetime
import json
from pathlib import Path
import shutil
import socket
import subprocess
import tempfile
import time
import urllib.request
import urllib.error

ROOT = Path(__file__).resolve().parents[1]
with tempfile.TemporaryDirectory(prefix='felix-audit-') as directory:
    root = Path(directory)
    for source in ROOT.glob('*.php'):
        shutil.copy2(source, root / source.name)
    (root / 'data').mkdir()
    files = ['2026-09-24-test.csv', '2026-09-25-test.csv']
    for file in files:
        with (root / 'data' / file).open('w') as stream:
            csv.writer(stream).writerows([
                ['id','name','allergy','cls','time','arrived','late','cancelled','reason','notes','checkedOut','flag'],
                ['1','Test Person','','','',0,0,0,'','',0,'']])
    with socket.socket() as sock:
        sock.bind(('127.0.0.1', 0))
        port = sock.getsockname()[1]
    server = subprocess.Popen(['php','-S',f'127.0.0.1:{port}','-t',directory], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    def request(path, body=None, user='Elek'):
        headers = {'Authorization': 'Basic ' + base64.b64encode((user + ':12345').encode()).decode(), 'Content-Type': 'application/json'}
        req = urllib.request.Request(f'http://127.0.0.1:{port}/' + path, headers=headers, data=json.dumps(body).encode() if body is not None else None)
        try:
            response = urllib.request.urlopen(req)
        except urllib.error.HTTPError as error:
            response = error
        with response:
            return response.status, json.loads(response.read())
    def log(file):
        path = root / 'logs' / (Path(file).stem + '-log.csv')
        assert path.exists(), 'Missing standalone project audit CSV'
        with path.open() as stream:
            return list(csv.DictReader(stream))
    def general_log():
        path = root / 'logs' / 'general-log.csv'
        assert path.exists(), 'Missing general audit CSV'
        with path.open() as stream:
            return list(csv.DictReader(stream))
    try:
        for attempt in range(50):
            try:
                request('list_files.php')
                break
            except urllib.error.URLError:
                time.sleep(0.1)
        original = request('get_roster.php?file=' + files[0])[1]
        changed = json.loads(json.dumps(original))
        changed['rows'][0]['flag'] = 'PRIO'
        payload = dict(changed, file=files[0], action='flag', user='Spoofed')
        assert request('save_roster.php', payload)[0] == 200
        entries = log(files[0])
        assert len(entries) == 1
        assert general_log() == entries
        entry = entries[0]
        assert entry['user'] == 'Elek' and entry['action'] == 'flag'
        assert entry['person_id'] == '1' and entry['person_name'] == 'Test Person'
        assert entry['field'] == 'flag' and entry['old_value'] == '' and entry['new_value'] == 'PRIO'
        assert datetime.datetime.fromisoformat(entry['timestamp']).utcoffset() is not None
        assert request('save_roster.php', dict(original, file=files[0], action='undo'), 'Kecsu')[0] == 200
        entries = log(files[0])
        assert len(entries) == 2 and entries[0] == entry
        assert entries[-1]['user'] == 'Kecsu' and entries[-1]['action'] == 'undo'
        assert entries[-1]['old_value'] == 'PRIO' and entries[-1]['new_value'] == ''
        assert request('save_roster.php', dict(original, file=files[1], action='notes'))[0] == 200
        assert len(log(files[0])) == 2 and log(files[1])[0]['field'] == 'no_change'
        assert general_log() == log(files[0]) + log(files[1])
        note = '=SUM(1,2)\n"A quoted note"'
        changed['rows'][0]['notes'] = note
        changed['session'] = 'checkout'
        assert request('save_roster.php', dict(changed, file=files[0], action='session'))[0] == 200
        entries = log(files[0])
        last = entries[2:]
        assert {row['field'] for row in last} == {'notes','flag','session'}
        assert len({row['event_id'] for row in last}) == 1
        assert next(row['new_value'] for row in last if row['field'] == 'notes') == "'" + note
        assert len(request('list_files.php')[1]) == 2
        assert general_log() == entries[:2] + log(files[1]) + entries[2:]
        global_before = (root / 'logs' / 'general-log.csv').read_bytes()
        before = (root / 'data' / files[0]).read_bytes()
        assert request('save_roster.php', {'file': files[0], 'rows': [None]})[0] == 400
        assert (root / 'data' / files[0]).read_bytes() == before
        # General-log failures must not leave a local-only event or changed data.
        global_path = root / 'logs' / 'general-log.csv'
        local_before = (root / 'logs' / (Path(files[0]).stem + '-log.csv')).read_bytes()
        global_path.unlink()
        global_path.mkdir()
        assert request('save_roster.php', dict(original, file=files[0]))[0] == 500
        assert (root / 'data' / files[0]).read_bytes() == before
        assert (root / 'logs' / (Path(files[0]).stem + '-log.csv')).read_bytes() == local_before
        global_path.rmdir()
        global_path.write_bytes(global_before)
        # Logging failure must not silently save unlogged changes.
        logfile = root / 'logs' / (Path(files[0]).stem + '-log.csv')
        logfile.unlink()
        logfile.mkdir()
        assert request('save_roster.php', dict(original, file=files[0]))[0] == 500
        assert (root / 'data' / files[0]).read_bytes() == before
        assert global_path.read_bytes() == global_before
        print('PASS: identical general/project audit events across dates and users, undo, escaping, and failure protection for either log.')
    finally:
        server.terminate()
        server.wait()
