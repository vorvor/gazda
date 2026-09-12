"""Archive public source pages and assets; never execute source scripts."""
import json, re, hashlib, time
from pathlib import Path
from urllib.request import urlopen, Request
from urllib.parse import urljoin, urlsplit, unquote, quote
from bs4 import BeautifulSoup
ROOT = Path(__file__).resolve().parents[1]
BASE = 'http://www.lovasharc.hu/'

def fetch(url):
    url = quote(unquote(url), safe=':/?=&%+#')
    with urlopen(Request(url, headers={'User-Agent': 'LovasharcStaticArchive/1.0'}), timeout=30) as r:
        return r.read(), r.headers.get('Content-Type', '')

def main():
    out = ROOT / 'source'; out.mkdir(exist_ok=True)
    assets = ROOT / 'assets' / 'original'; assets.mkdir(parents=True, exist_ok=True)
    queue = [(BASE, 0)]; pages = {}; media = {}; errors = []
    while queue and len(pages) < 100:
        url, depth = queue.pop(0)
        if url in pages: continue
        try:
            raw, mime = fetch(url)
            if 'html' not in mime: continue
            try: text = raw.decode('utf-8')
            except UnicodeDecodeError: text = raw.decode('iso-8859-2')
            soup = BeautifulSoup(text, 'html.parser')
            links = [{'text': a.get_text(' ', strip=True), 'url': urljoin(url, a.get('href', a.get('src', '')))} for a in soup.select('a[href], area[href], frame[src], iframe[src]')]
            images = list(dict.fromkeys(urljoin(url, el[attr]) for el in soup.find_all(True) for attr in ['src', 'background'] if el.get(attr) and (el.name == 'img' or attr == 'background')))
            for el in soup(['script', 'style', 'head']): el.decompose()
            pages[url] = {'url': url, 'depth': depth, 'html': str(soup), 'text': soup.get_text('\n', strip=True), 'links': links, 'images': images}
            (out / 'pages.json').write_text(json.dumps(pages, ensure_ascii=False, indent=2))
            print('PAGE', depth, url, len(pages[url]['text']), flush=True)
            for link in links:
                target = link['url'].split('#')[0]
                if urlsplit(target).netloc == 'www.lovasharc.hu' and (urlsplit(target).path.lower().endswith(('.html', '.htm')) or target == BASE) and depth < 5:
                    queue.append((target, depth + 1))
            for img in images:
                if urlsplit(img).netloc == 'www.lovasharc.hu': media[img] = None
        except Exception as e: errors.append({'url': url, 'error': str(e)})
    for url in media:
        try:
            raw, mime = fetch(url)
            ext = Path(unquote(urlsplit(url).path)).suffix.lower()
            if ext not in ['.jpg', '.jpeg', '.png', '.gif', '.webp']: continue
            name = hashlib.sha256(url.encode()).hexdigest()[:16] + ext
            (assets / name).write_bytes(raw)
            media[url] = 'assets/original/' + name
        except Exception as e: errors.append({'url': url, 'error': str(e)})
    (out / 'assets.json').write_text(json.dumps(media, ensure_ascii=False, indent=2))
    (out / 'errors.json').write_text(json.dumps(errors, ensure_ascii=False, indent=2))
    print(json.dumps({'pages':len(pages), 'assets':sum(bool(v) for v in media.values()), 'errors':errors}, ensure_ascii=False), flush=True)

if __name__ == '__main__': main()
