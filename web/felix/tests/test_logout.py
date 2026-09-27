"""Verify logout with browser-cached Basic credentials, on an isolated app copy."""
import base64
from pathlib import Path
import shutil
import socket
import subprocess
import tempfile
import time
import urllib.request
from playwright.sync_api import sync_playwright

ROOT = Path(__file__).resolve().parents[1]
with tempfile.TemporaryDirectory(prefix='felix-logout-') as directory:
    for source in ROOT.glob('*.php'):
        shutil.copy2(source, Path(directory) / source.name)
    shutil.copytree(ROOT / 'data', Path(directory) / 'data')
    with socket.socket() as sock:
        sock.bind(('127.0.0.1', 0))
        port = sock.getsockname()[1]
    url = f'http://127.0.0.1:{port}'
    server = subprocess.Popen(['php','-S',f'127.0.0.1:{port}','-t',directory], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    try:
        for attempt in range(50):
            try:
                req = urllib.request.Request(url + '/index.php', headers={'Authorization':'Basic ' + base64.b64encode(b'Elek:12345').decode()})
                urllib.request.urlopen(req).close()
                break
            except OSError:
                time.sleep(0.1)
        with sync_playwright() as playwright:
            browser = playwright.chromium.launch(args=['--no-sandbox'])
            page = browser.new_page(http_credentials={'username':'Elek','password':'12345'})
            page.goto(url)
            page.wait_for_function('ROSTER.length > 0')
            logout = page.get_by_role('button', name='Log out', exact=True)
            assert logout.count() >= 1, 'Missing Log out button'
            assert page.request.post(url + '/logout.php', form={'csrf':'invalid'}).status == 403
            logout.filter(visible=True).click()
            page.wait_for_url('**/login.php')
            assert page.locator('input[name="username"]').is_visible()
            for endpoint in ['list_files.php','get_roster.php','save_roster.php','get_log.php?scope=all']:
                assert page.request.get(url + '/' + endpoint).status == 401
            page.goto(url + '/index.php')
            page.wait_for_url('**/login.php')
            csrf = page.locator('input[name="csrf"]').input_value()
            assert page.request.post(url + '/login.php', form={'username':'Kecsu','password':'wrong','csrf':csrf}).status == 400
            response = page.request.post(url + '/login.php', form={'username':'Kecsu','password':'12345','csrf':csrf})
            assert response.status == 200
            page.goto(url + '/index.php')
            page.wait_for_function('ROSTER.length > 0')
            assert 'Hi Kecsu!' in page.locator('#roster-view .head').inner_text()
            page.get_by_role('button', name='All logs', exact=True).click()
            page.get_by_role('button', name='Log out', exact=True).filter(visible=True).click()
            page.wait_for_url('**/login.php')
            assert page.request.get(url + '/list_files.php').status == 401
            browser.close()
            print('PASS: logout from roster/log view, cached-credential blocking, protected endpoints, CSRF rejection, and explicit re-login as another user.')
    finally:
        server.terminate()
        server.wait()
