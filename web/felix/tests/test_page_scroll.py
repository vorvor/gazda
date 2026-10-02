"""Check real Felix CSS with synthetic tables; never access live roster data."""
import functools
from http.server import SimpleHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path
import re
import sys
import tempfile
import threading

from playwright.sync_api import sync_playwright

ROOT = Path(__file__).resolve().parents[1]
if len(sys.argv) > 1:
    ROOT = ROOT / sys.argv[1]
style = re.search(r'<style>(.*?)</style>', (ROOT / 'index.php').read_text(), re.S)
assert style is not None
css = style.group(1)
rows = ''.join('<tr><td>Test participant</td><td>Test details</td></tr>' for _ in range(80))
with tempfile.TemporaryDirectory(prefix='felix-page-scroll-') as directory:
    fixture = '<!doctype html><meta name="viewport" content="width=device-width, initial-scale=1">'
    fixture += '<style>' + css + '</style><main class="app">'
    fixture += '<header class="head">Roster controls</header>'
    for name in ['roster', 'log']:
        fixture += f'<div class="table-scroll" id="{name}"><table><tbody>{rows}</tbody></table></div>'
    fixture += '</main>'
    Path(directory, 'index.html').write_text(fixture)
    server = ThreadingHTTPServer(('127.0.0.1', 0), functools.partial(SimpleHTTPRequestHandler, directory=directory))
    worker = threading.Thread(target=server.serve_forever, daemon=True)
    worker.start()
    try:
        with sync_playwright() as playwright:
            browser = playwright.chromium.launch(headless=True, args=['--no-sandbox'])
            for width in [390, 1280]:
                page = browser.new_page(viewport={'width': width, 'height': 720})
                page.goto(f'http://127.0.0.1:{server.server_port}/')
                for table in page.locator('.table-scroll').all():
                    overflow = table.evaluate('(el) => [getComputedStyle(el).overflowX, getComputedStyle(el).overflowY]')
                    assert overflow == ['visible', 'visible'], (width, overflow)
                    table.evaluate('(el) => { el.scrollLeft = 100; el.scrollTop = 100; }')
                    assert table.evaluate('(el) => el.scrollLeft === 0 && el.scrollTop === 0')
                page.evaluate('window.scrollTo(0, 400)')
                assert page.evaluate('window.scrollY') == 400
                assert page.locator('.head').evaluate('(el) => el.getBoundingClientRect().bottom < 0'), 'Header must scroll with the page'
                if width == 390:
                    page.evaluate('window.scrollTo(150, 400)')
                    assert page.evaluate('window.scrollX') > 0
                page.close()
            browser.close()
        print('PASS: roster and log tables use page scrolling on mobile and desktop.')
    finally:
        server.shutdown()
        server.server_close()
        worker.join()
