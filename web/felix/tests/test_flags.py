"""Browser regression test using an isolated copy; never writes real rosters.
Run: .venv/bin/python tests/test_flags.py
"""
import json
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
with tempfile.TemporaryDirectory(prefix='felix-flags-') as directory:
    copy = Path(directory)
    for source in ROOT.glob('*.php'):
        shutil.copy2(source, copy / source.name)
    shutil.copytree(ROOT / 'data', copy / 'data')
    with socket.socket() as sock:
        sock.bind(('127.0.0.1', 0))
        port = sock.getsockname()[1]
    url = f'http://127.0.0.1:{port}'
    server = subprocess.Popen(['php', '-S', f'127.0.0.1:{port}', '-t', directory], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    try:
        for attempt in range(50):
            try:
                request = urllib.request.Request(url + '/list_files.php', headers={
                    'Authorization': 'Basic ' + base64.b64encode(b'Elek:12345').decode()
                })
                urllib.request.urlopen(request).close()
                break
            except OSError:
                time.sleep(0.1)
        with sync_playwright() as playwright:
            browser = playwright.chromium.launch(headless=True, args=['--no-sandbox'])
            page = browser.new_page(http_credentials={'username': 'Elek', 'password': '12345'})
            errors = []
            page.on('pageerror', lambda error: errors.append(str(error)))
            page.goto(url)
            page.wait_for_function('ROSTER.length > 0')
            files = page.evaluate('FILES')
            for item in files:
                tab = page.locator(f'[data-file="{item["file"]}"]')
                expected = 'CHECK-OUT' if item['session'] == 'checkout' else 'CHECK-IN'
                assert tab.locator('.tab-state').count() == 1, 'Each day needs an explicit session label'
                assert tab.locator('.tab-state').inner_text() == expected
            for phase in ['checkin', 'checkout']:
                page.evaluate('(phase) => { session = phase; updateSessionUI(); }', phase)
                badge = page.locator('#session-badge')
                assert badge.is_visible()
                style = badge.evaluate('(el) => ({size: parseFloat(getComputedStyle(el).fontSize), weight: getComputedStyle(el).fontWeight, background: getComputedStyle(el).backgroundColor})')
                assert style['size'] >= 20 and int(style['weight']) >= 700
                assert style['background'] == ('rgb(27, 75, 71)' if phase == 'checkin' else 'rgb(43, 95, 168)')
            page.reload()
            page.wait_for_function('ROSTER.length > 0')
            target = next(item['file'] for item in files if item['session'] == 'checkin')
            page.locator(f'[data-file="{target}"]').click()
            page.wait_for_function('(file) => currentFile === file && session === "checkin" && ROSTER.length > 0', arg=target)
            page.wait_for_timeout(200)
            original = page.evaluate('JSON.parse(JSON.stringify(ROSTER))')
            person_id = original[0]['id']
            page.locator(f'tr[data-id="{person_id}"]').click()
            assert page.get_by_role('button', name='PROB', exact=True).count() == 1, 'Missing PROB flag control'
            for flag in ['PROB', 'DATA ERROR', 'PRIO']:
                with page.expect_response(lambda response: response.url.endswith('/save_roster.php') and response.request.method == 'POST') as saved:
                    page.get_by_role('button', name=flag, exact=True).click()
                assert saved.value.json()['ok'] is True
                assert page.locator('.flag-btn.active').inner_text() == flag
                assert page.locator(f'tr[data-id="{person_id}"] .cell-flag').inner_text() == flag
                data = page.request.get(url + '/get_roster.php', params={'file': target}).json()
                assert data['rows'][0]['flag'] == flag
                for before, after in zip(original, data['rows']):
                    assert {k: v for k, v in before.items() if k != 'flag'} == {k: v for k, v in after.items() if k != 'flag'}
            page.reload()
            page.wait_for_function('ROSTER.length > 0')
            page.locator(f'[data-file="{target}"]').click()
            page.wait_for_function('ROSTER.length > 0 && ROSTER[0].flag === "PRIO"')
            page.locator(f'tr[data-id="{person_id}"]').click()
            with page.expect_response(lambda response: response.url.endswith('/save_roster.php')):
                page.get_by_role('button', name='Vegan', exact=True).click()
            data = page.request.get(url + '/get_roster.php', params={'file': target}).json()
            assert data['rows'][0]['flag'] == 'PRIO'
            assert data['rows'][0]['allergy'] == 'Vegan'
            assert not errors, errors
            browser.close()
            print('PASS: all three flags save/render, survive reload, preserve existing fields, and remain independent of Diet; no browser JS errors.')
    finally:
        server.terminate()
        server.wait()
