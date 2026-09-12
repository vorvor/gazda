"""Homepage-only browser verification and subpage regression boundary."""
import json, hashlib
from pathlib import Path
from urllib.parse import urlsplit,unquote
from bs4 import BeautifulSoup
from playwright.sync_api import sync_playwright
ROOT=Path(__file__).resolve().parents[1]
errors=[]; retries=[]
before=json.loads(Path('/tmp/lovas-subpages-before.json').read_text())
changed=[name for name,digest in before.items() if hashlib.sha256((ROOT/name).read_bytes()).hexdigest()!=digest]
if changed:errors.append({'changed_subpages':changed})
soup=BeautifulSoup((ROOT/'index.html').read_text(),'html.parser')
for item in soup.select('[href],[src]'):
    value=item.get('href',item.get('src',''));url=urlsplit(value)
    if url.scheme or url.netloc:continue
    if url.path and not (ROOT/unquote(url.path)).exists():errors.append('Missing '+value)
if len(soup.find_all('h1'))!=1:errors.append('Expected one h1')
audit=json.loads((ROOT/'source/source-only-audit.json').read_text())
if audit['non_source_text']:errors.append('Non-source text found')
with sync_playwright() as p:
    for width in [320,390,768,1024,1280,1440]:
        for attempt in range(3):
            browser=p.chromium.launch(headless=True,executable_path='/opt/hermes/.playwright/chromium_headless_shell-1243/chrome-headless-shell-linux64/chrome-headless-shell',args=['--no-sandbox'])
            page=browser.new_page(viewport={'width':width,'height':1000})
            page.on('pageerror',lambda error:errors.append(str(error)))
            try:
                page.goto((ROOT/'index.html').as_uri())
                page.locator('.site-footer').scroll_into_view_if_needed()
                page.evaluate('window.scrollTo(0,0)')
                page.locator('img').evaluate_all('(imgs)=>imgs.forEach(i=>i.loading="eager")')
                page.wait_for_function('[...document.images].every(i=>i.complete)',timeout=15000)
                if page.evaluate('document.documentElement.scrollWidth>innerWidth+1'):errors.append(f'Overflow at {width}')
                bad=page.locator('img').evaluate_all('(imgs)=>imgs.filter(i=>!i.naturalWidth).map(i=>i.src)')
                if bad:errors.append({'width':width,'broken':bad})
                assert page.locator('.reference-card').count()==4
                assert page.locator('.hero-image img').is_visible()
                if width<=1180:
                    page.locator('.menu-toggle').click()
                    assert page.locator('#navigation').is_visible()
                    page.keyboard.press('Escape')
                    assert not page.locator('#navigation').is_visible()
                page.screenshot(path=f'/tmp/lovas-reference-{width}.png',full_page=True)
                # Every prominent call to action has a real, local destination.
                for a in page.locator('.reference-card > a').all():
                    assert (ROOT/a.get_attribute('href')).exists()
                browser.close()
                break
            except Exception as error:
                browser.close()
                if 'Page crashed' not in str(error) or attempt==2:raise
                retries.append({'width':width,'attempt':attempt+1})
report={'page':'index.html','reference':'design.jpeg','viewports':[320,390,768,1024,1280,1440],'unchanged_subpages':len(before),'changed_subpages':changed,'source_text_violations':len(audit['non_source_text']),'errors':errors,'renderer_retries':retries}
(ROOT/'source/homepage-verification.json').write_text(json.dumps(report,indent=2))
print(json.dumps(report,indent=2))
raise SystemExit(bool(errors))
