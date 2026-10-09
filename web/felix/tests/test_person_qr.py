"""QR UI integration: isolated PHP server, synthetic people, real QR decoding."""
import json
import os
import shutil
import socket
import subprocess
import tempfile
import time
from pathlib import Path
from playwright.sync_api import sync_playwright

ROOT = Path(__file__).resolve().parents[1]
PERSON = {'id': '42', 'name': 'Őri Ágnes <Anna>', 'city': '', 'fictional': 0}
with tempfile.TemporaryDirectory(prefix='felix-qr-') as directory:
    root = Path(directory)
    for source in ROOT.glob('*.php'):
        shutil.copy2(source, root / source.name)
    (root / 'sessions').mkdir()
    (root / 'sessions/sess_qrtest1234567890').write_text('user|s:4:"Elek";csrf|s:64:"' + 'a' * 64 + '";')
    with socket.socket() as sock:
        sock.bind(('127.0.0.1', 0))
        port = sock.getsockname()[1]
    base = f'http://127.0.0.1:{port}/'
    server = subprocess.Popen(['php', '-d', 'session.save_path=' + str(root / 'sessions'), '-S', f'127.0.0.1:{port}', '-t', str(root)], env=dict(os.environ, FELIX_CSV_DIR=str(root / 'registry'), FELIX_DATA_DIR=str(root / 'data')), stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    try:
        with sync_playwright() as p:
            browser = p.chromium.launch(args=['--no-sandbox'])
            context = browser.new_context(viewport={'width': 390, 'height': 844})
            context.add_cookies([{'name': 'FELIXSESSID', 'value': 'qrtest1234567890', 'url': base}])
            context.route('**/list_files.php', lambda r: r.fulfill(json=[{'file':'2026-10-05-test.csv','date':'2026-10-05','label':'Test','session':'checkin','closed':True}]))
            context.route('**/get_roster.php*', lambda r: r.fulfill(json={'rows':[PERSON], 'session':'checkin','closed':True}))
            context.route('**/assignments_api.php*', lambda r: r.fulfill(json={'day':{'date':'2026-10-05','title':'Test'},'people':[PERSON,dict(PERSON,id='43')], 'assigned':[PERSON]}))
            context.route('**/get_log.php*', lambda r: r.fulfill(json={'records':[{'event_id':'e','timestamp':'2026-10-05','user':'Elek','action':'save','person_id':PERSON['id'],'person_name':PERSON['name'],'field':'notes','old_value':'','new_value':'test','project':'test'}]}))
            page = context.new_page()
            errors, writes = [], []
            page.on('pageerror', lambda e: errors.append(str(e)))
            page.on('request', lambda r: writes.append(r.url) if r.method == 'POST' else None)
            for attempt in range(50):
                try:
                    page.goto(base + 'index.php')
                    break
                except Exception:
                    if attempt == 49: raise
                    time.sleep(.1)
            page.locator('#roster-body .cell-name').wait_for()
            assert page.locator('#roster-body .person-qr').count() == 1, 'Missing QR beside roster name'
            def check_qr(selector, expected=PERSON):
                button = page.locator(selector).first
                assert button.locator('svg').count() == 1
                button.click()
                dialog = page.locator('#person-qr-dialog')
                assert dialog.is_visible()
                assert dialog.locator('.person-qr-name').inner_text() == expected['name']
                assert dialog.locator('.person-qr-id').inner_text() == 'ID: ' + expected['id']
                box = dialog.bounding_box()
                assert box['x'] >= 0 and box['x'] + box['width'] <= page.viewport_size['width']
                # Decode the actual rendered large QR, not a declared data attribute.
                import zxingcpp
                from PIL import Image
                import io
                image = Image.open(io.BytesIO(dialog.locator('.person-qr-large').screenshot()))
                decoded = zxingcpp.read_barcode(image)
                assert decoded and json.loads(decoded.text) == {'id':expected['id'],'name':expected['name']}
                page.keyboard.press('Escape')
                assert not dialog.is_visible()
                assert button.evaluate('(el) => el === document.activeElement')
            check_qr('#roster-body .person-qr')
            assert page.locator('#roster-body .is-selected').count() == 0
            for trigger in ['#view-log-btn', '#all-logs-btn']:
                page.locator(trigger).click()
                page.locator('#log-body tr').wait_for()
                check_qr('#log-body .person-qr')
                page.locator('#log-back-btn').click()
            page.goto(base + 'assignments.php?date=2026-10-05')
            page.locator('#assigned .person').wait_for()
            assert page.locator('#people .person-qr').count() == 2
            check_qr('#people .person-qr')
            check_qr('#people .person:nth-child(2) .person-qr', dict(PERSON,id='43'))
            check_qr('#assigned .person-qr')
            page.set_viewport_size({'width':1280,'height':900})
            check_qr('#assigned .person-qr')
            page.locator('#assigned .person-qr').click()
            page.locator('#person-qr-dialog .person-qr-close').click()
            assert not page.locator('#person-qr-dialog').is_visible()
            assert not errors, errors
            assert not writes, writes
            browser.close()
        print('PASS: roster, both logs, assignment pool/assigned QR; Unicode decoding, duplicate names, closed day, mobile/desktop dialog, keyboard/focus, no writes.')
    finally:
        server.terminate()
        server.wait()
