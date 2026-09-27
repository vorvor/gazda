import requests, json, re, hashlib, time
from pathlib import Path
from bs4 import BeautifulSoup
from urllib.parse import urljoin,urlsplit,urldefrag
from concurrent.futures import ThreadPoolExecutor
from collections import Counter, defaultdict
from datetime import datetime,timezone
import xml.etree.ElementTree as ET
ROOT=Path('/workspace/nextro-audit'); E=ROOT/'http-evidence'; E.mkdir(exist_ok=True)
BASE='https://nextro.hu/'
HEAD={'User-Agent':'Mozilla/5.0 (compatible; ReadOnlyTechnicalAudit/1.0)','Accept-Encoding':'gzip, deflate'}
records={}
def fetch(url, headers=None, tag=''):
    start=time.monotonic()
    try:
        r=requests.get(url,headers=headers or HEAD,timeout=35)
        key=hashlib.sha256((url+tag).encode()).hexdigest()[:16]
        path=E/(key+'.body'); path.write_bytes(r.content)
        d={'url':url,'final_url':r.url,'status':r.status_code,'redirects':[{'url':h.url,'status':h.status_code,'location':h.headers.get('Location')} for h in r.history], 'headers':dict(r.headers),'elapsed_seconds':round(time.monotonic()-start,3),'decoded_bytes':len(r.content),'body_file':str(path),'sha256':hashlib.sha256(r.content).hexdigest()}
        (E/(key+'.json')).write_text(json.dumps(d,ensure_ascii=False,indent=2))
        return d,r.text
    except Exception as ex: return {'url':url,'error':str(ex)},''
def ordinary(u):
    p=urlsplit(u)
    return p.scheme in ('https','http') and p.hostname in ('nextro.hu','www.nextro.hu') and not p.query and not re.search(r'(wp-admin|wp-login|my-account|fiokom|kosar|cart|checkout|penztar|logout|wc-api|feed/)',p.path,re.I) and not re.search(r'\.(jpg|png|webp|svg|gif|pdf|zip|css|js|xml|txt|mp4)$',p.path,re.I)
def parse(d,text):
    s=BeautifulSoup(text,'html.parser'); u=d.get('final_url',d['url'])
    d['title']=s.title.get_text(' ',strip=True) if s.title else None
    d['html_lang']=s.html.get('lang') if s.html else None
    d['meta']={n.get('name') or n.get('property'):n.get('content') for n in s.select('meta[name],meta[property]')}
    d['canonicals']=[urljoin(u,n.get('href','')) for n in s.select('link[rel~=canonical]')]
    d['headings']=[{'level':n.name,'text':n.get_text(' ',strip=True)} for n in s.select('h1,h2,h3,h4,h5,h6')]
    d['schema']=[]
    for n in s.select('script[type="application/ld+json"]'):
        try:d['schema'].append(json.loads(n.get_text()))
        except Exception:d['schema'].append({'parse_error':True,'raw':n.get_text()})
    d['microdata_types']=[n.get('itemtype') for n in s.select('[itemtype]')]
    d['images']=[{k:n.get(k) for k in ['src','srcset','alt','width','height','loading','fetchpriority']} for n in s.select('img')]
    d['css']=[urljoin(u,n.get('href')) for n in s.select('link[rel~=stylesheet][href]')]
    d['js']=[{'src':urljoin(u,n.get('src')),'defer':n.has_attr('defer'),'async':n.has_attr('async')} for n in s.select('script[src]')]
    d['links']=[{'url':urldefrag(urljoin(u,n.get('href')))[0],'text':n.get_text(' ',strip=True),'rel':n.get('rel',[])} for n in s.select('a[href]')]
    d['internal_ordinary_links']=sorted({x['url'] for x in d['links'] if ordinary(x['url'])})
    d['nav_links']=[{'url':urljoin(u,n.get('href')),'text':n.get_text(' ',strip=True)} for n in s.select('nav a[href]')]
    d['forms']=[{'action':n.get('action'),'method':n.get('method'),'fields':[x.get('name') for x in n.select('input,select,textarea')]} for n in s.select('form')]
    for n in s.select('script,style,nav,header,footer'):n.decompose()
    d['raw_html_text']=s.get_text(' ',strip=True)
    d['word_count_raw_main_approx']=len(d['raw_html_text'].split())
    return d
robots, rt=fetch(BASE+'robots.txt'); robots['text']=rt
queue=re.findall(r'^Sitemap:\s*(\S+)',rt,re.M) or [BASE+'sitemap_index.xml']
sitemaps=[]; sitemap_urls=[]; seen=set()
while queue and len(seen)<30:
    u=queue.pop(0)
    if u in seen:continue
    seen.add(u); d,t=fetch(u)
    try:
        tree=ET.fromstring(t); locs=[n.text for n in tree.iter() if n.tag.endswith('}loc') or n.tag=='loc']; d['locations']=locs
        if tree.tag.endswith('sitemapindex'):queue.extend(locs)
        else:sitemap_urls.extend(locs)
    except Exception as ex:d['parse_error']=str(ex)
    sitemaps.append(d)
print('sitemaps',len(sitemaps),'URLs',len(set(sitemap_urls)),flush=True)
seed=[BASE]+[urljoin(BASE,p+'/') for p in ['szolgaltatasok','megoldasok','referenciak','rolunk','bolt','kapcsolat']]
for u in seed:
    d,t=fetch(u); records[u]=parse(d,t)
# Main pages and legal pages first, then breadth across categories/products.
candidates=set(sitemap_urls)
for d in records.values():candidates.update(d['internal_ordinary_links'])
def priority(u):
    p=urlsplit(u).path
    if re.search(r'(adat|aszf|szerzod|cookie|suti|impress|privacy)',p):return (0,p)
    if not re.search('/(termek|termekkategoria|product|product-category)/',p):return (1,p)
    if '/termekkategoria/' in p:return (2,p)
    return (3,p)
chosen=sorted([u for u in candidates if ordinary(u) and u not in records],key=priority)[:53]
def getpage(u):
    d,t=fetch(u); return u,parse(d,t)
with ThreadPoolExecutor(max_workers=3) as pool:
    for u,d in pool.map(getpage,chosen):records[u]=d; print('page',len(records),d.get('status'),u,flush=True)
# Check all unique ordinary HTML navigation targets; HEAD limits bodies beyond sample.
links=sorted({x for d in records.values() for x in d['internal_ordinary_links']})
def check(u):
    if u in records:return {k:records[u].get(k) for k in ['url','status','final_url','redirects','error']}
    try:
        r=requests.head(u,headers=HEAD,allow_redirects=True,timeout=25)
        d={'url':u,'status':r.status_code,'final_url':r.url,'method':'HEAD','redirects':[{'url':x.url,'status':x.status_code,'location':x.headers.get('Location')} for x in r.history]}
        if r.status_code>=400:
            g,_=fetch(u); d['get_confirmation']=g;d['status']=g.get('status',r.status_code)
        return d
    except Exception as ex:return {'url':u,'error':str(ex)}
with ThreadPoolExecutor(max_workers=3) as pool:checks=list(pool.map(check,links[:250]))
variants=[]
for u in ['http://nextro.hu/','http://www.nextro.hu/','https://www.nextro.hu/',BASE+'szolgaltatasok',BASE+'?utm_source=technical-audit',BASE+'technical-audit-not-found-20260922-7e21/']:
    d,t=fetch(u);variants.append(parse(d,t))
uas={'requests-default':'python-requests/2.32.5','desktop':'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36','mobile':'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 Version/18.0 Mobile/15E148 Safari/604.1','googlebot':'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)','curl':'curl/8.0.1'}
ua_results=[]
for label,ua in uas.items():
    d,t=fetch(BASE,{'User-Agent':ua,'Accept-Encoding':'gzip, deflate'},label);p=parse(d,t);p['user_agent_label']=label;ua_results.append(p)
home=records[BASE]; assets=home['css'][:3]+[x['src'] for x in home['js'][:3]]+[urljoin(BASE,x['src']) for x in home['images'] if x.get('src') and not x['src'].startswith('data:')][:4]
asset_checks=[]
for u in dict.fromkeys(assets):
    d,_=fetch(u);asset_checks.append(d)
# Repeat HTML, and conditional request to inspect caching behavior.
cache=[]
for label,h in [('repeat',HEAD),('conditional',{**HEAD,'If-Modified-Since':home.get('headers',{}).get('Last-Modified','Mon, 21 Sep 2026 00:00:00 GMT')})]:
    d,_=fetch(BASE,h,label);d['test']=label;cache.append(d)
pages=list(records.values()); titles=defaultdict(list); descriptions=defaultdict(list)
for d in pages:
    if d['title']:titles[d['title']].append(d['url'])
    if d['meta'].get('description'):descriptions[d['meta']['description']].append(d['url'])
summary={'html_pages_parsed':len(pages),'sitemaps_fetched':len(sitemaps),'unique_sitemap_urls':len(set(sitemap_urls)),'page_status_counts':dict(Counter(str(d.get('status','error')) for d in pages)),'ordinary_internal_link_targets_discovered':len(links),'ordinary_internal_link_targets_checked':len(checks),'link_errors':[d for d in checks if d.get('error') or d.get('status',0)>=400],'missing_title':[d['url'] for d in pages if not d['title']],'missing_meta_description':[d['url'] for d in pages if not d['meta'].get('description')],'missing_canonical':[d['url'] for d in pages if not d['canonicals']],'h1_counts':dict(Counter(str(sum(x['level']=='h1' for x in d['headings'])) for d in pages)),'noindex_pages':[d['url'] for d in pages if 'noindex' in d['meta'].get('robots','')],'duplicate_titles':{k:v for k,v in titles.items() if len(v)>1},'duplicate_descriptions':{k:v for k,v in descriptions.items() if len(v)>1},'image_elements':sum(len(d['images']) for d in pages),'image_elements_missing_alt':sum(x['alt'] is None for d in pages for x in d['images']),'image_elements_empty_alt':sum(x['alt']=='' for d in pages for x in d['images'])}
result={'timestamp_utc':datetime.now(timezone.utc).isoformat(),'methodology':{'requests':'GET raw HTML sample; HEAD for additional ordinary internal navigation destinations, GET confirmation on HEAD errors; max 3 concurrent','html_sample_cap':60,'link_check_cap':250,'exclusions':'No forms submitted, no actions/login/cart/checkout links, no query navigation crawled except harmless explicit UTM test; external links not checked. Timing is single-request elapsed wall time, not browser performance.'},'summary':summary,'robots':robots,'sitemaps':sitemaps,'sitemap_urls':sorted(set(sitemap_urls)),'pages':pages,'link_checks':checks,'variants':variants,'user_agent_variations':ua_results,'asset_checks':asset_checks,'cache_tests':cache}
(ROOT/'technical.json').write_text(json.dumps(result,ensure_ascii=False,indent=2))
print(json.dumps(summary,ensure_ascii=False,indent=2))
