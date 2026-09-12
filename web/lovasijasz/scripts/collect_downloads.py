import json, hashlib
from pathlib import Path
from urllib.parse import urlsplit, unquote
from scrape import ROOT, fetch
p = json.loads((ROOT/'source/pages.json').read_text())
a = json.loads((ROOT/'source/assets.json').read_text())
errors = json.loads((ROOT/'source/errors.json').read_text())
for page in p.values():
    for link in page['links']:
        u = link['url']; ext = Path(unquote(urlsplit(u).path)).suffix.lower()
        if ext not in ['.jpg','.jpeg','.png','.pdf','.pps'] or u in a or urlsplit(u).netloc != 'www.lovasharc.hu': continue
        try:
            raw, mime = fetch(u)
            dest = 'assets/original/' + hashlib.sha256(u.encode()).hexdigest()[:16]+ext
            (ROOT/dest).write_bytes(raw); a[u] = dest
            print(u, len(raw))
        except Exception as e: errors.append({'url':u,'error':str(e)})
(ROOT/'source/assets.json').write_text(json.dumps(a,ensure_ascii=False,indent=2))
(ROOT/'source/errors.json').write_text(json.dumps(errors,ensure_ascii=False,indent=2))
from PIL import Image, ImageDraw
photos = [(u,v) for u,v in a.items() if '/ilk' in u]
im = Image.new('RGB',(900,((len(photos)+2)//3)*210),'white'); draw = ImageDraw.Draw(im)
for i,(u,v) in enumerate(photos):
    pic = Image.open(ROOT/v).convert('RGB'); print(u, pic.size); pic.thumbnail((290,180)); im.paste(pic,(i%3*300,i//3*210)); draw.text((i%3*300,i//3*210+185),u.split('/')[-1],fill='black')
im.save('/tmp/lovasharc-photos.jpg')
