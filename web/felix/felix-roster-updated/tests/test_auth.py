"""Test prototype HTTP Basic authentication on a temporary application copy."""
import base64
from pathlib import Path
import shutil
import socket
import subprocess
import tempfile
import time
import urllib.error
import urllib.request

ROOT = Path(__file__).resolve().parents[1]
with tempfile.TemporaryDirectory(prefix='felix-auth-') as directory:
    for source in ROOT.glob('*.php'):
        shutil.copy2(source, Path(directory) / source.name)
    shutil.copytree(ROOT / 'data', Path(directory) / 'data')
    with socket.socket() as sock:
        sock.bind(('127.0.0.1', 0))
        port = sock.getsockname()[1]
    server = subprocess.Popen(['php', '-S', f'127.0.0.1:{port}', '-t', directory], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    def request(path, credentials=None):
        headers = {}
        if credentials:
            headers['Authorization'] = 'Basic ' + base64.b64encode(credentials.encode()).decode()
        try:
            response = urllib.request.urlopen(urllib.request.Request(f'http://127.0.0.1:{port}/{path}', headers=headers))
        except urllib.error.HTTPError as error:
            response = error
        with response:
            return response.status, response.headers, response.read().decode()
    try:
        for attempt in range(50):
            try:
                request('index.php')
                break
            except urllib.error.URLError:
                time.sleep(0.1)
        for path in ['index.php', 'list_files.php', 'get_roster.php', 'save_roster.php', 'get_log.php']:
            status, headers, body = request(path)
            assert status == 401, f'{path}: expected authentication challenge, got {status}'
            assert headers.get('WWW-Authenticate', '').startswith('Basic ')
            assert request(path, 'Elek:wrong')[0] == 401
        assert request('index.php', 'Unknown:12345')[0] == 401
        for user in ['Elek', 'Kecsu']:
            status, headers, body = request('index.php', user + ':12345')
            assert status == 200
            assert f'Hi {user}!' in body
            role = {'Elek': 'Administrator', 'Kecsu': 'Crowd marshall'}[user]
            assert f'<span class="role-badge">{role}</span>' in body, 'Missing role beside greeting'
            assert request('list_files.php', user + ':12345')[0] == 200
            assert request('get_roster.php?file=2026-09-24-project1.csv', user + ':12345')[0] == 200
            assert request('save_roster.php', user + ':12345')[0] == 400
            assert request('get_log.php?file=2026-09-24-project1.csv', user + ':12345')[0] == 200
        print('PASS: anonymous/wrong credentials rejected on all app endpoints; both users authenticate and receive their own greeting.')
    finally:
        server.terminate()
        server.wait()
