"""Hungarian UI regression on an isolated PHP app; never mutate live data."""
import ast
import re
import shutil
import socket
import subprocess
import tempfile
import time
from pathlib import Path
from playwright.sync_api import sync_playwright

ROOT = Path(__file__).resolve().parents[1]
match = re.search(r'http_credentials=(\{[^}]+\})', (ROOT / 'tests/test_flags.py').read_text())
assert match
credentials = ast.literal_eval(match.group(1))
with tempfile.TemporaryDirectory(prefix='felix-hu-') as directory:
    copy = Path(directory)
    for source in ROOT.glob('*.php'):
        shutil.copy2(source, copy / source.name)
    shutil.copytree(ROOT / 'data', copy / 'data')
    with socket.socket() as sock:
        sock.bind(('127.0.0.1', 0))
        port = sock.getsockname()[1]
    server = subprocess.Popen(['php', '-S', f'127.0.0.1:{port}', '-t', directory], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    try:
        with sync_playwright() as p:
            browser = p.chromium.launch(headless=True, args=['--no-sandbox'])
            page = browser.new_page(http_credentials=credentials)
            errors = []
            page.on('pageerror', lambda error: errors.append(str(error)))
            url = f'http://127.0.0.1:{port}'
            for attempt in range(50):
                try:
                    page.goto(url)
                    break
                except Exception:
                    if attempt == 49:
                        raise
                    time.sleep(.1)
            page.wait_for_function('ROSTER.length > 0')
            assert page.locator('html').get_attribute('lang') == 'hu'
            assert page.title() == 'Check in / Check out névsor'
            expected = {'#undo-btn': 'Visszavonás', '#view-log-btn': 'Napló megtekintése', '#all-logs-btn': 'Összes napló', '#arrived-btn': 'Megérkezett', '#late-btn': 'Késik', '#cancelled-btn': 'Lemondva', '#notes-save': 'Megjegyzés mentése', '#checkout-btn': 'Check out'}
            for selector, text in expected.items():
                assert page.locator(selector).inner_text() == text
            for phase, text in [('checkin', 'Check in névsor'), ('checkout', 'Check out névsor')]:
                page.evaluate('(phase) => { session = phase; updateSessionUI(); }', phase)
                assert page.locator('#title').inner_text() == text
            snapshot = page.evaluate('rosterSnapshot()')
            assert page.evaluate("displayDiet('Peanut')") == 'Földimogyoró'
            assert page.evaluate("displayFlag('DATA ERROR')") == 'Adathiba'
            assert page.evaluate("displayTime('01:05 PM')") == '13:05'
            assert page.evaluate("displayLogValue('arrived', '1')") == 'Igen'
            assert page.evaluate("displayLogValue('notes', 'Do not translate my note')") == 'Do not translate my note'
            assert page.evaluate("displayLogValue('session', 'checkout')") == 'Check out'
            assert page.evaluate('rosterSnapshot()') == snapshot
            page.locator('#all-logs-btn').click()
            page.wait_for_function("!document.getElementById('log-status').textContent.includes('betöltése')")
            assert page.locator('#log-title').inner_text() == 'Összesített napló'
            page.locator('#log-back-btn').click()
            page.locator('#search').fill('NO_SUCH_TEST_NAME')
            assert 'Nincs találat' in page.locator('#empty-state').inner_text()
            assert not errors, errors
            browser.close()
        print('PASS: Hungarian UI, English check in/check out, translated display values, unchanged stored data, no JS errors.')
    finally:
        server.terminate()
        server.wait()
