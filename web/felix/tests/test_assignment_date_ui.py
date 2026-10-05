"""Browser UI contract tests with explicit API fixtures; no live DB writes."""
import json
import shutil
import socket
import subprocess
import tempfile
import time
from pathlib import Path
from urllib.parse import parse_qs, urlparse
from playwright.sync_api import sync_playwright

ROOT = Path(__file__).resolve().parents[1]
with tempfile.TemporaryDirectory(prefix='felix-date-ui-') as directory:
    root = Path(directory)
    for source in ROOT.glob('*.php'):
        shutil.copy2(source, root / source.name)
    shutil.copytree(ROOT / 'data', root / 'data')
    sessions = root / 'sessions'
    sessions.mkdir()
    (sessions / 'sess_dateuitest1234567890').write_text('user|s:4:"Elek";csrf|s:64:"' + 'a' * 64 + '";')
    with socket.socket() as sock:
        sock.bind(('127.0.0.1', 0))
        port = sock.getsockname()[1]
    base = f'http://127.0.0.1:{port}/'
    server = subprocess.Popen(['php', '-d', 'session.save_path=' + str(sessions), '-S', f'127.0.0.1:{port}', '-t', str(root)], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    try:
        with sync_playwright() as p:
            browser = p.chromium.launch(headless=True, args=['--no-sandbox'])
            context = browser.new_context(viewport={'width': 390, 'height': 844})
            context.add_cookies([{'name': 'FELIXSESSID', 'value': 'dateuitest1234567890', 'url': base}])
            people = [{'id': 1, 'name': 'Teszt Anna', 'city': 'Budapest', 'fictional': 1}]
            assigned = {'2026-11-12': people[:], '2026-11-13': []}
            posts = []
            def api(route):
                params = parse_qs(urlparse(route.request.url).query)
                if route.request.method == 'POST':
                    params = parse_qs(route.request.post_data)
                    date = params['date'][0]
                    posts.append(date)
                    assigned[date] = people[:] if params['action'][0] == 'assign' else []
                    route.fulfill(json={'ok': True})
                    return
                date = params.get('date', ['2026-11-12'])[0]
                route.fulfill(json={'day': {'id': 1, 'date': date, 'title': 'Forgatási nap'}, 'people': people, 'assigned': assigned.get(date, []), 'days': [{'date': d, 'title': 'Forgatási nap'} for d in assigned]})
            context.route('**/assignments_api.php*', api)
            def files_api(route):
                files = [{'file': '2026-09-24-project1.csv', 'date': '2026-09-24', 'label': '2026. 09. 24. · Project1', 'session': 'checkin', 'closed': False}]
                files += [{'file': date + '-forgatas.csv', 'date': date, 'assignment_date': date, 'source': 'assignments', 'label': date + ' · Forgatás', 'session': 'checkin', 'closed': False} for date, persons in assigned.items() if persons]
                route.fulfill(json=files)
            def roster_api(route):
                filename = parse_qs(urlparse(route.request.url).query)['file'][0]
                rows = [{'id': str(person['id']), 'name': person['name'], 'allergy': '', 'cls': '', 'time': '', 'arrived': False, 'late': False, 'cancelled': False, 'reason': '', 'notes': '', 'checkedOut': False, 'flag': ''} for person in assigned.get(filename[:10], [])]
                if filename == '2026-09-24-project1.csv':
                    rows = [{'id': 'legacy', 'name': 'Korábbi névsor', 'arrived': False}]
                route.fulfill(json={'rows': rows, 'session': 'checkin', 'closed': False})
            context.route('**/list_files.php', files_api)
            context.route('**/get_roster.php*', roster_api)
            page = context.new_page()
            errors = []
            page.on('pageerror', lambda error: errors.append(str(error)))
            for attempt in range(50):
                try:
                    page.goto(base + 'assignments.php?date=2026-11-12')
                    break
                except Exception:
                    if attempt == 49: raise
                    time.sleep(.1)
            assert page.locator('#filming-date').count() == 1, 'Missing filming date picker'
            page.locator('#assigned .person').wait_for()
            assert page.locator('#filming-date').input_value() == '2026-11-12'
            page.locator('#filming-date').fill('2026-11-13')
            page.wait_for_function("document.querySelector('#count').textContent === '0 fő'")
            assert 'Teszt Anna' not in page.locator('#assigned').inner_text()
            page.locator('#people button').click()
            page.locator('#assigned .person').wait_for()
            assert posts == ['2026-11-13']
            page.reload()
            page.locator('#assigned .person').wait_for()
            assert page.locator('#filming-date').input_value() == '2026-11-13'
            page.locator('#back-to-roster').click()
            page.locator('#roster-body tr[data-id="1"]').wait_for()
            assert page.locator('#roster-view input[type="date"]').count() == 0, 'Roster must not have a date picker'
            assert page.locator('.file-tab.active').get_attribute('data-file') == '2026-11-13-forgatas.csv'
            assert page.locator('#roster-body .cell-name').inner_text() == 'Teszt Anna'
            assert page.locator('#file-tabs .file-tab').count() == 3
            page.reload()
            page.locator('#roster-body tr[data-id="1"]').wait_for()
            assert page.locator('.file-tab.active').get_attribute('data-file') == '2026-11-13-forgatas.csv'
            page.locator('[data-file="2026-09-24-project1.csv"]').click()
            page.locator('#roster-body tr[data-id="legacy"]').wait_for()
            assert page.locator('#roster-body .cell-name').inner_text() == 'Korábbi névsor'
            page.locator('[data-file="2026-11-12-forgatas.csv"]').click()
            page.locator('#roster-body tr[data-id="1"]').wait_for()
            assert 'date=2026-11-12' in page.locator('#assignments-link').get_attribute('href')
            page.reload()
            page.locator('#roster-body tr[data-id="1"]').wait_for()
            assert page.locator('.file-tab.active').get_attribute('data-file') == '2026-11-12-forgatas.csv'
            assert not errors, errors
            browser.close()
        print('PASS: assignment date picker, generated roster tabs, no roster date picker, selected tab survives reload, legacy tabs preserved.')
    finally:
        server.terminate()
        server.wait()
