"""Exercise undo against real PHP endpoints with disposable roster fixtures."""
import base64
import csv
import json
from pathlib import Path
import shutil
import socket
import subprocess
import tempfile
import time
import urllib.request
from playwright.sync_api import sync_playwright

ROOT = Path(__file__).resolve().parents[1]
with tempfile.TemporaryDirectory(prefix='felix-undo-') as directory:
    root = Path(directory)
    for source in ROOT.glob('*.php'):
        shutil.copy2(source, root / source.name)
    (root / 'data').mkdir()
    files = ['2026-09-24-test.csv', '2026-09-25-test.csv']
    for file in files:
        with (root / 'data' / file).open('w') as stream:
            writer = csv.writer(stream)
            writer.writerow(['id','name','allergy','cls','time','arrived','late','cancelled','reason','notes','checkedOut','flag'])
            writer.writerow(['1','Test Person','','','',0,0,0,'','',0,''])
    with socket.socket() as sock:
        sock.bind(('127.0.0.1', 0))
        port = sock.getsockname()[1]
    url = f'http://127.0.0.1:{port}'
    server = subprocess.Popen(['php','-S',f'127.0.0.1:{port}','-t',directory], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    try:
        for attempt in range(50):
            try:
                request = urllib.request.Request(url + '/list_files.php', headers={'Authorization': 'Basic ' + base64.b64encode(b'Elek:12345').decode()})
                urllib.request.urlopen(request).close()
                break
            except OSError:
                time.sleep(0.1)
        with sync_playwright() as playwright:
            browser = playwright.chromium.launch(args=['--no-sandbox'])
            page = browser.new_page(http_credentials={'username':'Elek','password':'12345'})
            errors = []
            page.on('pageerror', lambda error: errors.append(str(error)))
            page.goto(url)
            page.wait_for_function('ROSTER.length > 0')
            undo = page.get_by_role('button', name='UNDO', exact=True)
            assert undo.count() == 1, 'Missing UNDO button'
            assert undo.is_disabled()
            def state():
                return page.request.get(url + '/get_roster.php', params={'file': page.evaluate('currentFile')}).json()
            def save_click(selector):
                with page.expect_response(lambda r: r.url.endswith('/save_roster.php') and r.request.method == 'POST') as response:
                    page.locator(selector).click()
                assert response.value.json()['ok']
                page.wait_for_function('!(typeof pendingSaves !== "undefined" && pendingSaves[currentFile])')
            def select():
                page.locator('tr[data-id="1"]').click()
            def undo_to(expected):
                save_click('#undo-btn')
                assert state() == expected
                assert page.evaluate('({rows:ROSTER, session:session})') == expected
            baseline = state()
            for selector in ['.flag-btn.prob','.allergy-btn.vegan','#arrived-btn']:
                select()
                save_click(selector)
                assert state() != baseline
                undo_to(baseline)
                assert undo.is_disabled()
            select()
            page.locator('#notes-input').fill('Undo this note')
            save_click('#notes-save')
            noted = state()
            save_click('.flag-btn.prio')
            undo_to(noted)
            undo_to(baseline)
            select()
            page.locator('#late-btn').click()
            page.locator('#late-time').fill('10:30')
            save_click('#late-submit')
            undo_to(baseline)
            select()
            page.locator('#cancelled-btn').click()
            page.locator('#cancel-reason').fill('Testing')
            save_click('#cancel-submit')
            undo_to(baseline)
            select()
            save_click('#arrived-btn')
            arrived = state()
            page.locator('#session-lock-btn').click()
            save_click('#session-switch-btn')
            checkout = state()
            select()
            save_click('#checkout-btn')
            undo_to(checkout)
            undo_to(arrived)
            assert page.locator('.file-tab.active .tab-state').inner_text() == 'CHECK-IN'
            page.locator(f'[data-file="{files[1]}"]').click()
            page.wait_for_function('(file) => currentFile === file && ROSTER.length && !ROSTER[0].arrived', arg=files[1])
            assert undo.is_disabled()
            page.locator(f'[data-file="{files[0]}"]').click()
            page.wait_for_function('ROSTER.length && ROSTER[0].arrived')
            undo_to(baseline)
            assert undo.is_disabled()
            # Rapid edits must reach disk in order, and duplicate clicks are not edits.
            select()
            page.evaluate("$('.flag-btn.prob').click(); $('.flag-btn.prio').click();")
            page.wait_for_function('!pendingSaves[currentFile]')
            assert state()['rows'][0]['flag'] == 'PRIO'
            save_click('.flag-btn.prio')
            prob = json.loads(json.dumps(baseline))
            prob['rows'][0]['flag'] = 'PROB'
            undo_to(prob)
            undo_to(baseline)
            assert undo.is_disabled()
            # A failed undo must remain available for retry.
            select()
            save_click('.flag-btn.prio')
            flagged = state()
            page.on('dialog', lambda dialog: dialog.accept())
            page.route('**/save_roster.php', lambda route: route.fulfill(status=500, body='Save failed'))
            undo.click()
            page.wait_for_function('!pendingSaves[currentFile]')
            assert state() == flagged
            assert page.evaluate('({rows:ROSTER, session:session})') == flagged
            assert undo.is_enabled()
            page.unroute('**/save_roster.php')
            undo_to(baseline)
            page.reload()
            page.wait_for_function('ROSTER.length > 0')
            assert undo.is_disabled()
            assert not errors, errors
            with (root / 'logs' / '2026-09-24-test-log.csv').open() as stream:
                audit = list(csv.DictReader(stream))
            assert {entry['action'] for entry in audit} >= {'undo', 'flag', 'diet', 'notes', 'arrived', 'late', 'cancelled', 'session', 'checkout'}
            assert all(entry['user'] == 'Elek' for entry in audit)
            assert any(entry['action'] == 'undo' and entry['field'] == 'session' for entry in audit)
            assert any(entry['action'] == 'undo' and entry['field'] == 'flag' and entry['new_value'] == '' for entry in audit)
            log_button = page.get_by_role('button', name='View log', exact=True)
            assert log_button.count() == 1, 'Missing current-project log button'
            page.locator('#search').fill('Test')
            log_button.click()
            page.wait_for_selector('#log-body tr')
            assert page.locator('#log-body tr').count() == len(audit)
            assert page.locator('#log-project').inner_text() == files[0]
            assert page.locator('#log-body tr').first.locator('td').nth(2).inner_text() == 'UNDO'
            assert not page.locator('#roster-body').is_visible()
            page.get_by_role('button', name='Back to roster', exact=True).click()
            assert page.locator('#search').input_value() == 'Test'
            assert page.evaluate('currentFile') == files[0]
            assert state() == baseline
            page.locator(f'[data-file="{files[1]}"]').click()
            page.wait_for_function('(file) => currentFile === file && !rosterLoading', arg=files[1])
            log_button.click()
            page.wait_for_function("document.querySelector('#log-status').textContent === 'No log records yet for this project.'")
            assert page.locator('#log-body tr').count() == 0
            assert page.locator('#log-project').inner_text() == files[1]
            page.get_by_role('button', name='Back to roster', exact=True).click()
            select()
            save_click('.flag-btn.prio')
            all_logs = page.get_by_role('button', name='All logs', exact=True)
            assert all_logs.count() == 1, 'Missing general log button'
            all_logs.click()
            page.wait_for_selector('#log-body tr')
            assert page.locator('#log-title').inner_text() == 'General log'
            assert page.locator('#log-body tr').count() == len(audit) + 1
            assert page.locator('#log-body tr').first.locator('td').last.inner_text() == files[1]
            assert files[0] in page.locator('#log-body').inner_text()
            response = page.request.get(url + '/get_log.php?scope=all')
            assert response.status == 200 and len(response.json()['records']) == len(audit) + 1
            page.get_by_role('button', name='Back to roster', exact=True).click()
            assert page.evaluate('currentFile') == files[1]
            assert page.request.get(url + '/get_log.php', params={'file': '../' + files[0]}).status == 400
            assert page.request.get(url + '/get_log.php', params={'file': '2026-09-24-missing.csv'}).status == 404
            page.route('**/get_log.php?*', lambda route: route.fulfill(status=500, body='Read failed'))
            log_button.click()
            page.wait_for_function("document.querySelector('#log-status').textContent.includes('Could not load')")
            page.get_by_role('button', name='Back to roster', exact=True).click()
            assert page.locator('#roster-view').is_visible()
            assert not errors, errors
            browser.close()
            print('PASS: undo/save regressions; project-scoped log viewer, newest-first records, back navigation, empty/error states, and invalid-project rejection.')
    finally:
        server.terminate()
        server.wait()
