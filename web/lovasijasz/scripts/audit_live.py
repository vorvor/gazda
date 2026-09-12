"""Re-crawl reachable live documents without depth/content-size exclusions."""
import json, hashlib
from collections import deque
from urllib.parse import urljoin, urlsplit, unquote
from bs4 import BeautifulSoup
from scrape import ROOT, BASE, fetch

def main():
    previous=json.loads((ROOT/'source/pages.json').read_text())
    queue=deque([BASE]); seen=set(); pages={}; errors=[]
    while queue:
        u=queue.popleft()
        if u in seen: continue
        seen.add(u)
        try:
            raw,mime=fetch(u)
            if 'html' not in mime: continue
            try: text=raw.decode('utf-8')
            except UnicodeDecodeError: text=raw.decode('iso-8859-2')
            soup=BeautifulSoup(text,'html.parser')
            title=soup.title.get_text(' ',strip=True) if soup.title else ''
            links=[{'text':a.get_text(' ',strip=True),'url':urljoin(u,a.get('href',a.get('src',''))),'kind':a.name} for a in soup.select('a[href],area[href],frame[src],iframe[src]')]
            images=list(dict.fromkeys(urljoin(u,el[attr]) for el in soup.find_all(True) for attr in ['src','background'] if el.get(attr) and (el.name=='img' or attr=='background')))
            for el in soup(['script','style','head']):el.decompose()
            pages[u]={'url':u,'title':title,'html':str(soup),'text':soup.get_text('\n',strip=True),'links':links,'images':images}
            for link in links:
                target=unquote(link['url'].split('#')[0])
                if urlsplit(target).netloc=='www.lovasharc.hu' and (urlsplit(target).path.lower().endswith(('.html','.htm')) or target==BASE):queue.append(target)
            print('LIVE',u,len(pages[u]['text']),flush=True)
        except Exception as e:errors.append({'url':u,'error':str(e)})
        (ROOT/'source/live-pages.json').write_text(json.dumps(pages,ensure_ascii=False,indent=2))
    assets=json.loads((ROOT/'source/assets.json').read_text())
    missing_assets=set()
    for page in pages.values():
        for target in page['images']+[a['url'] for a in page['links']]:
            ext=urlsplit(target).path.lower().rsplit('.',1)[-1]
            if ext in ['jpg','jpeg','png','gif','webp','pps','pdf'] and urlsplit(target).netloc=='www.lovasharc.hu' and not assets.get(target):missing_assets.add(target)
    for u in sorted(missing_assets):
        try:
            raw,mime=fetch(u); ext='.'+urlsplit(u).path.rsplit('.',1)[-1].lower()
            target='assets/original/'+hashlib.sha256(u.encode()).hexdigest()[:16]+ext
            (ROOT/target).write_bytes(raw);assets[u]=target
        except Exception as e:errors.append({'url':u,'error':str(e)})
    report={'live_pages':len(pages),'new_source_urls':sorted(set(pages)-set(previous)),'changed_source_urls':[u for u in pages if u in previous and pages[u]['text']!=previous[u]['text']],'unavailable':errors}
    (ROOT/'source/live-comparison.json').write_text(json.dumps(report,ensure_ascii=False,indent=2))
    (ROOT/'source/assets.json').write_text(json.dumps(assets,ensure_ascii=False,indent=2))
    print(json.dumps(report,ensure_ascii=False,indent=2))
if __name__=='__main__':main()
