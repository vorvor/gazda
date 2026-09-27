import json, collections, xml.etree.ElementTree as ET
from pathlib import Path
from urllib.parse import urlsplit
R=Path('/workspace/nextro-audit');d=json.loads((R/'technical.json').read_text())
ns={'s':'http://www.sitemaps.org/schemas/sitemap/0.9','i':'http://www.google.com/schemas/sitemap-image/1.1'}
page_urls=[];image_urls=[]
for sm in d['sitemaps']:
 t=ET.parse(sm['body_file'])
 page_urls.extend(x.text for x in t.findall('s:url/s:loc',ns))
 image_urls.extend(x.text for x in t.findall('s:url/i:image/i:loc',ns))
print('Sitemap page URLs',len(page_urls),'unique',len(set(page_urls)),'image references',len(image_urls),'unique',len(set(image_urls)))
print('Sitemap not in crawl',set(page_urls)-{p['url'] for p in d['pages']})
for p in d['pages']:
 print('\nPAGE',p['url'],'\nTITLE',p['title'],'\nMETA',p['meta'].get('description'),'\nHEADINGS',[(h['level'],h['text']) for h in p['headings'][:3]])
 if any(k in p['url'] for k in ['impresszum','szallitasi','aszf','adatkezelesi','cookie']):print('TEXT',p['raw_html_text'][:18000])
 if p['url']=='https://nextro.hu/' or '22-kw-75' in p['url'] or '11-kw-5-' in p['url']:print('SCHEMA',json.dumps(p['schema'],ensure_ascii=False)); print('MICRO',p['microdata_types'])
p=json.loads((R/'evidence/performance-desktop.json').read_text());res=[x for x in p['resources'] if 'axe' not in x['name']]
print('\nPERFORMANCE NAV',p['nav'])
print('RESOURCE COUNT',len(res),'transfer bytes',sum(x['transferSize'] for x in res),'encoded bytes',sum(x['encodedBodySize'] for x in res),'decoded bytes',sum(x['decodedBodySize'] for x in res))
print('RESOURCE TYPES',dict(collections.Counter(x['initiatorType'] for x in res)))
print('LARGEST',[(x['name'],x['transferSize'],x['decodedBodySize'],x['duration'],x.get('renderBlockingStatus')) for x in sorted(res,key=lambda x:x['decodedBodySize'],reverse=True)[:15]])
print('THIRD PARTY',[(x['name'],x['transferSize']) for x in res if urlsplit(x['name']).hostname!='nextro.hu'])
print('FONT',[(x['name'],x['transferSize']) for x in res if any(e in x['name'] for e in ['woff','font'])])
print('BLOCKING',[(x['name'],x['duration']) for x in res if x.get('renderBlockingStatus')=='blocking'])
print('HEADERS',d['pages'][0]['headers'])
print('CACHING',d['cache_tests'])
out={'sitemap_page_count':len(page_urls),'sitemap_unique_pages':len(set(page_urls)),'sitemap_pages':sorted(set(page_urls)),'sitemap_unique_images':len(set(image_urls)),'resource_count':len(res),'transfer_bytes':sum(x['transferSize'] for x in res),'encoded_bytes':sum(x['encodedBodySize'] for x in res),'decoded_bytes':sum(x['decodedBodySize'] for x in res),'resource_types':dict(collections.Counter(x['initiatorType'] for x in res))}
(R/'aggregates.json').write_text(json.dumps(out,indent=2))
