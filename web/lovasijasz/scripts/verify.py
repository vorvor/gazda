"""Verify generated pages, source coverage, links and actual browser behavior."""
import json, re
from pathlib import Path
from urllib.parse import urlsplit, unquote
from bs4 import BeautifulSoup
from playwright.sync_api import sync_playwright
ROOT=Path(__file__).resolve().parents[1]
errors=[]; warnings=[]
renderer_retries=[]
manifest=json.loads((ROOT/'source/build-manifest.json').read_text())
files=sorted(ROOT.glob('*.html'))
parsed={f.name:BeautifulSoup(f.read_text(),'html.parser') for f in files}
for f in files:
    soup=parsed[f.name]
    if len(soup.find_all('h1'))!=1: errors.append(f'{f.name}: expected one h1')
    if soup.html.get('lang')!='hu': errors.append(f'{f.name}: missing Hungarian language')
    if '\ufffd' in f.read_text(): errors.append(f'{f.name}: replacement character')
    hero = soup.select_one('.hero-image img' if f.name == 'index.html' else '.heading-art img')
    if not hero or not hero.get('alt'): errors.append(f'{f.name}: missing illustrated hero or alternative text')
    for el in soup.select('[href], [src]'):
        val=el.get('href',el.get('src','')); url=urlsplit(val)
        if url.scheme or url.netloc or not val: continue
        path=ROOT/unquote(url.path) if url.path else f
        if not path.exists(): errors.append(f'{f.name}: missing local target {val}')
        if url.fragment and path.suffix=='.html':
            target=parsed[path.name]
            if not target.find(id=unquote(url.fragment)): errors.append(f'{f.name}: missing fragment {val}')
    for a in soup.select('a[href]'):
        if not a.get_text(strip=True) and not a.get('aria-label') and not a.find('img'):
            warnings.append(f'{f.name}: empty link {a.get("href")}')
# Every live document is represented, including image-only pages and frames.
pages=json.loads((ROOT/'source/live-pages.json').read_text())
provenance=json.loads((ROOT/'source/text-provenance.json').read_text())
for u,p in pages.items():
    if u not in manifest['routes'] or not (ROOT/manifest['routes'][u]).exists():
        errors.append('Missing source coverage '+u)
        continue
    if u in provenance:
        source_block=parsed[manifest['routes'][u]].find(attrs={'data-source':u})
        if source_block is None:
            errors.append('Missing source content block '+u)
            continue
        actual=re.sub(r'\s+','',source_block.get_text())
        for text in provenance[u]:
            if re.sub(r'\s+','',text) not in actual:
                errors.append('Missing original text '+u+': '+text[:80])
audit=json.loads((ROOT/'source/source-only-audit.json').read_text())
if audit['non_source_text']:errors.append('Non-source public copy remains')
def navigate(path):
    global browser,page
    viewport=page.viewport_size
    for attempt in range(3):
        try:
            page.goto(path.as_uri())
            page.wait_for_load_state('load')
            return
        except Exception as error:
            if 'Page crashed' not in str(error) or attempt==2:raise
            renderer_retries.append({'page':path.name,'width':viewport['width']})
            browser.close()
            browser=p.chromium.launch(headless=True,executable_path='/opt/hermes/.playwright/chromium_headless_shell-1243/chrome-headless-shell-linux64/chrome-headless-shell',args=['--no-sandbox'])
            page=browser.new_page(viewport=viewport)
            page.on('pageerror',lambda error:errors.append('JS: '+str(error)))
with sync_playwright() as p:
    browser=p.chromium.launch(headless=True,executable_path='/opt/hermes/.playwright/chromium_headless_shell-1243/chrome-headless-shell-linux64/chrome-headless-shell',args=['--no-sandbox'])
    page=browser.new_page(viewport={'width':1440,'height':1000})
    page.on('pageerror',lambda error:errors.append('JS: '+str(error)))
    for width in [1440,768,320,390]:
        for f in files:
            browser.close()
            browser=p.chromium.launch(headless=True,executable_path='/opt/hermes/.playwright/chromium_headless_shell-1243/chrome-headless-shell-linux64/chrome-headless-shell',args=['--no-sandbox'])
            page=browser.new_page(viewport={'width':width,'height':900})
            page.on('pageerror',lambda error:errors.append('JS: '+str(error)))
            navigate(f)
            if page.evaluate('document.documentElement.scrollWidth > innerWidth + 1'): errors.append(f'{f.name}: horizontal overflow at {width}')
            bad=page.locator('img[src]').evaluate_all('(imgs)=>imgs.filter(i=>i.complete && i.naturalWidth===0).map(i=>i.getAttribute("src"))')
            if bad: errors.append(f'{f.name}: broken images {bad}')
            hero = page.locator('.hero-image img, .heading-art img')
            if not hero.is_visible(): errors.append(f'{f.name}: hidden hero at {width}')
        navigate(ROOT/'index.html')
        page.screenshot(path=f'/tmp/lovasharc-{width}.png',full_page=True)
    navigate(ROOT/'index.html')
    page.get_by_role('button',name='TARTALOM',exact=True).click()
    assert page.locator('#navigation').is_visible()
    assert page.locator('.menu-toggle').get_attribute('aria-expanded')=='true'
    page.keyboard.press('Escape')
    assert not page.locator('#navigation').is_visible()
    navigate(ROOT/'fotogaleria.html')
    page.get_by_role('button',name='INTENZÍV LOVASKÉPZÉS',exact=True).click()
    assert page.locator('.gallery-item:visible').count()==9

    page.locator('.gallery-item:visible a').first.click()
    assert page.locator('#lightbox').is_visible()
    first=page.locator('#lightbox img').get_attribute('src')
    page.keyboard.press('ArrowRight')
    assert page.locator('#lightbox img').get_attribute('src')!=first
    page.keyboard.press('Escape')
    assert not page.locator('#lightbox').is_visible()
    page.get_by_role('button',name='FOTÓGALÉRIA',exact=True).click()
    assert page.locator('.gallery-item:visible').count()==manifest['gallery_photos']
    page.screenshot(path='/tmp/lovasharc-gallery-mobile.png',full_page=False)
    navigate(ROOT/'egyesulet.html')
    page.screenshot(path='/tmp/lovasharc-article-mobile.png',full_page=True)
    page.set_viewport_size({'width':1440,'height':1000})
    page.screenshot(path='/tmp/lovasharc-article-desktop.png',full_page=False)
    browser.close()
report={'html_pages':len(files),'source_pages':len(pages),'viewport_widths':[1440,768,320,390],'gallery_photos':manifest['gallery_photos'],'interaction_tests':['mobile menu open/Escape','gallery filtering/reset','lightbox open/next/Escape'],'errors':errors,'warnings':warnings,'renderer_retries':renderer_retries}
(ROOT/'source/verification.json').write_text(json.dumps(report,ensure_ascii=False,indent=2))
print(json.dumps(report,ensure_ascii=False,indent=2))
raise SystemExit(bool(errors))
