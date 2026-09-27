import json,requests,xml.etree.ElementTree as ET
from bs4 import BeautifulSoup
from pathlib import Path
R=Path('/workspace/nextro-audit');d=json.loads((R/'technical.json').read_text());out={}
urls=['https://nextro.hu/sitemap.xml','https://nextro.hu/kosar/','https://nextro.hu/penztar/','https://nextro.hu/fiokom/','https://nextro.hu/bolt/?orderby=price']
out['checks']=[]
for u in urls:
 r=requests.get(u,timeout=30);s=BeautifulSoup(r.content,'html.parser');out['checks'].append({'url':u,'status':r.status_code,'final':r.url,'redirects':[(h.status_code,h.headers.get('Location')) for h in r.history],'title':s.title.get_text() if s.title else None,'robots':[m.get('content') for m in s.select('meta[name=robots]')],'canonical':[m.get('href') for m in s.select('link[rel=canonical]')]})
try:
 r=requests.get('https://www.googleapis.com/pagespeedonline/v5/runPagespeed',params={'url':'https://nextro.hu/','strategy':'mobile','category':'performance'},timeout=65);out['pagespeed']={'status':r.status_code,'body':r.json()};(R/'evidence/pagespeed-api.json').write_text(json.dumps(out['pagespeed'],indent=2))
except Exception as e:out['pagespeed']={'error':str(e)}
for p in d['pages']:
 schema=[]
 def walk(x):
  if isinstance(x,dict):
   if '@type' in x:schema.append(x)
   for v in x.values():walk(v)
  elif isinstance(x,list):
   for v in x:walk(v)
 walk(p['schema'])
 p['schema_types_flat']=[x['@type'] for x in schema]
 if p['url']=='https://nextro.hu/':
  out['organization']=[x for x in schema if x.get('@type')=='Organization'];home=BeautifulSoup(Path(p['body_file']).read_bytes(),'html.parser');out['favicon']=[x.attrs for x in home.select('link[rel*=icon]')];out['home_scripts']=p['js'];out['home_images']=p['images']
 if '22-kw-75' in p['url']:out['priced_product_schema']=[x for x in schema if x.get('@type') in ['Product','Offer','AggregateOffer']]
out['product_schema_counts']={p['url']:p['schema_types_flat'].count('Product') for p in d['pages'] if '/termek/' in p['url']}
out['description_missing_count']=len(d['summary']['missing_meta_description'])
out['price_products']=[{'url':p['url'],'price':BeautifulSoup(Path(p['body_file']).read_bytes(),'html.parser').select_one('.summary .price').get_text(' ',strip=True) if BeautifulSoup(Path(p['body_file']).read_bytes(),'html.parser').select_one('.summary .price') else None} for p in d['pages'] if '/termek/' in p['url']]
(R/'supplement.json').write_text(json.dumps(out,ensure_ascii=False,indent=2))
print(json.dumps({k:v for k,v in out.items() if k not in ['home_images','home_scripts','organization','pagespeed']},ensure_ascii=False,indent=2));print('PAGESPEED',out.get('pagespeed',{}).get('status'),str(out.get('pagespeed',{}))[:500]);print('ORG',json.dumps(out['organization'],ensure_ascii=False))
