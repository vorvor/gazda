"""Source-only static edition: all visible copy comes from the live crawl."""
import json, re, hashlib, html
from pathlib import Path
from urllib.parse import urljoin, urlsplit, unquote, quote
from bs4 import BeautifulSoup, Comment, Doctype
from PIL import Image
ROOT=Path(__file__).resolve().parents[1]
BASE='http://www.lovasharc.hu/'
E=html.escape

def norm(value): return re.sub(r'\s+',' ',value).strip()
def decorative(value): return not re.search(r'[^\s.xX*…_\-–—|]',value)
def padding(value):
    return not value.strip() or bool(re.fullmatch(r'[\sxX]+', value)) or value.lower().count('lovasharc') > 40
def src(path): return BASE+'index_elemei/'+path

def main():
    pages=json.loads((ROOT/'source/live-pages.json').read_text())
    assets=json.loads((ROOT/'source/assets.json').read_text())
    previous=json.loads((ROOT/'source/build-manifest.json').read_text())
    routes=dict(previous['routes'])
    original_files=set(previous['html_pages'])
    assoc=src('lhse.html.html'); train=src('INTENZIV LOVASKEPZES/intenziv lovaskepzes 2.html')
    news=src('LH AKTUAL/LH AKTUAL mezo.html'); writings=src('irasok/irasok.html')
    contact=src('Untitled-2.html'); anno=src('anno.html'); archive=src('Untitled-3.html')
    cenz=src('LHSE AKTUAL/cenzurazatlan.html'); book=src('dr szebenyi/szk 1.html')
    routes[cenz]='cenzurazatlan.html'
    # Frame mastheads and navigation belong to the modern parent, not blank pages.
    supplements={
        'UntitledFrame-4.html':'egyesulet.html','UntitledFrame-1.html':'egyesulet.html',
        'UntitledFrame-2.html':'egyesulet.html','UntitledFrame-5.html':'egyesulet.html',
        'INTENZIV LOVASKEPZES/intenziv lovaskepzes 1.html':'lovaskepzes.html',
        'LH AKTUAL/LH AKTUAL.html':'aktualis.html','LH AKTUAL/LH AKTUAL cimsor.html':'aktualis.html',
        'dr szebenyi/szk fejlec.html':routes[book], 'UntitledFrame-9.html':routes[archive],
    }
    for u,filename in supplements.items(): routes[src(u)]=filename
    for u in pages:
        routes.setdefault(u,'iras-'+hashlib.sha256(u.encode()).hexdigest()[:10]+'.html')
    titles={BASE:'LOVASHARC.HU',assoc:'EGYESÜLET',train:'INTENZÍV LOVASKÉPZÉS',news:'LOVASHARC AKTUÁLIS',writings:'ÍRÁSOK',contact:'ELÉRHETŐSÉGEK',anno:'2025-ben ünnepeljük a LOVASÍJÁSZ HAGYOMÁNYŐRZŐ SPORTEGYESÜLET alakulásának 25 éves évfordulóját!',archive:'LHSE AKTUÁLIS',book:'KISLOVAK A HADVISELÉSBEN',cenz:'EGY MEG NEM JELENT CIKK'}
    for page in pages.values():
        for link in page['links']:
            u=link['url'].split('#')[0]; label=norm(link['text'])
            if u not in titles and label and not decorative(label): titles[u]=label
    titles[src('egyesulet/egyesulet celja.html')]='Az egyesület célja, feladata'
    for u,p in pages.items():
        if u not in titles:
            meaningful=[norm(s) for s in p['text'].splitlines() if not decorative(s)]
            titles[u]=meaningful[0] if meaningful else 'LOVASHARC.HU'
    nav=[('egyesulet.html','EGYESÜLET'),('lovaskepzes.html','INTENZÍV LOVASKÉPZÉS'),('aktualis.html','LOVASHARC AKTUÁLIS'),('irasok.html','ÍRÁSOK'),('kelemen-zsolt.html','KELEMEN ZSOLT'),('fotogaleria.html','FOTÓGALÉRIA'),('elerhetosegek.html','ELÉRHETŐSÉGEK')]
    optimized={u:'assets/photos/'+Path(v).stem+'.webp' for u,v in assets.items() if v and (ROOT/('assets/photos/'+Path(v).stem+'.webp')).exists()}
    def photo(suffix): return optimized[src(suffix)]
    def link(value,u):
        absolute=unquote(urljoin(u,value)); target=absolute.split('#')[0]; fragment=urlsplit(absolute).fragment
        if target in routes:return routes[target]+('#'+fragment if fragment else '')
        if assets.get(target):return optimized.get(target,assets[target])
        if urlsplit(absolute).scheme not in ['http','https','mailto','tel']:return ''
        return quote(absolute,safe=':/?=&%+#@')
    source_records={}
    image_records={}
    def content(u):
        soup=BeautifulSoup(pages[u]['html'],'html.parser')
        for el in soup(['script','style','head','iframe','frame','frameset','object','embed']):el.decompose()
        for node in list(soup.find_all(string=lambda s:isinstance(s,(Comment,Doctype)))):node.extract()
        # Image maps become accessible image links, retaining every available target.
        extra=[]
        for area in soup.select('area[href]'):
            target=unquote(urljoin(u,area['href']))
            if target in optimized:extra.append(target)
        for el in soup(['map','area']):el.decompose()
        for text in list(soup.find_all(string=True)):
            if padding(str(text)):text.replace_with(' ')
        source_records[u]=[norm(str(t)) for t in soup.find_all(string=True) if norm(str(t))]
        used=[]
        for img in list(soup.find_all('img')):
            original=unquote(urljoin(u,img.get('src','')))
            if original not in optimized:
                # Failed source images are recorded by the crawl, not invented.
                img.decompose();continue
            used.append(original)
            with Image.open(ROOT/optimized[original]) as im:w,h=im.size
            img.attrs={'src':optimized[original],'alt':titles[u],'width':str(w),'height':str(h),'loading':'lazy'}
        # Original background pictures are preserved as illustrations as well.
        for original in pages[u]['images']:
            if original in optimized and original not in used:extra.append(original)
        for tag in list(soup.find_all(True)):
            if tag.name=='img':continue
            attrs={}
            if tag.get('id') or tag.get('name'):attrs['id']=tag.get('id',tag.get('name'))
            if tag.name=='a' and tag.get('href'):
                target=link(tag['href'],u)
                if target:attrs['href']=target
            tag.attrs=attrs
            if tag.name in ['html','body','font','span','center','tbody','table','tr','td','th']:
                if 'id' in attrs:tag.name='div'
                else:tag.unwrap()
        for tag in list(soup.find_all(['p','div','b','strong','a','blockquote']))[::-1]:
            if tag.parent is not None and not tag.get_text(strip=True) and not tag.find('img') and not tag.get('id'):tag.decompose()
        extras=''
        for original in dict.fromkeys(extra):
            if original in used:continue
            used.append(original)
            extras+=f'<a href="{optimized[original]}"><img src="{optimized[original]}" alt="{E(titles[u],quote=True)}" loading="lazy"></a>'
        image_records[u]=used
        return f'<div class="source-body" data-source="{E(u,quote=True)}">{soup}{extras}</div>'
    def excerpt(u,starts):
        soup=BeautifulSoup(pages[u]['html'],'html.parser')
        for text in list(soup.find_all(string=True)):
            if padding(str(text)):text.replace_with(' ')
        for p in soup.find_all('p'):
            text=norm(p.get_text(' ',strip=True))
            if text.startswith(starts):return text
        raise ValueError('Missing exact source paragraph: '+starts)
    def img(path,title,kind='illustration'):
        with Image.open(ROOT/path) as original:w,h=original.size
        return f'<figure class="heading-art heading-art--{kind}"><img src="{path}" alt="{E(title,quote=True)}" width="{w}" height="{h}" fetchpriority="high"></figure>'
    def art(u):
        if u in [assoc,contact] or '/egyesulet/' in u:return 'assets/photos/design-meadow.webp','photo'
        if u==anno:return 'assets/photos/design-parchment.webp','illustration'
        if u==train:return photo('INTENZIV LOVASKEPZES/HIRDFOTOK k.jpg'),'illustration'
        if u==writings:return photo('konyv/ny.jpg'),'illustration'
        if u in [news,archive]:return photo('top (28)b.jpg'),'photo'
        for original in pages.get(u,{}).get('images',[]):
            if original not in optimized:continue
            with Image.open(ROOT/optimized[original]) as im:w,h=im.size
            if w>=250 and h>=250 and w/h<3.5:return optimized[original],'illustration'
        return 'assets/photos/design-parchment.webp','illustration'
    def heading(title,u,artwork=None):
        path,kind=artwork or art(u)
        return f'<section class="page-heading page-heading--illustrated"><div class="container"><div class="breadcrumbs"><a href="index.html">LOVASHARC.HU</a><span aria-hidden="true"> / </span><span>{E(title)}</span></div><div class="heading-composition"><div class="heading-copy"><h1>{E(title)}</h1></div>{img(path,title,kind)}</div></div></section>'
    def navigation(active):
        return ''.join(f'<a href="{href}"'+(' aria-current="page"' if href==active else '')+f'>{label}</a>' for href,label in nav)
    def related(items):
        return '<div class="related-list">'+''.join(f'<a href="{href}">{E(label)}</a>' for href,label in items)+'</div>'
    def write(filename,title,body):
        header=f'<a class="skip" href="#main">LOVASHARC.HU</a><div class="topline"><div class="container"><span>Lovasíjász Hagyományőrző Sportegyesület</span></div></div><header class="site-header"><div class="container header-inner"><a class="brand" href="index.html"><span><strong>LOVASHARC.HU</strong><small>Lovasíjász Hagyományőrző Sportegyesület</small></span></a><button class="menu-toggle" aria-label="TARTALOM" aria-expanded="false" aria-controls="navigation">☰</button><nav class="navigation" id="navigation" aria-label="TARTALOM">{navigation(filename)}</nav></div></header>'
        footer='<section class="contact-band"><div class="container"><div><h2>ELÉRHETŐSÉGEK</h2><p>LOVASHARC@GMAIL.HU</p></div><a class="button" href="elerhetosegek.html">KAPCSOLAT</a></div></section><footer class="site-footer"><div class="container"><div class="footer-top"><a class="footer-brand" href="index.html">LOVASHARC.HU</a><div class="footer-links">'+navigation(filename)+'</div></div><div class="footer-bottom"><span>Lovasíjász Hagyományőrző Sportegyesület</span><a href="forrasok.html">TARTALOM</a></div></div></footer>'
        output=f'<!doctype html>\n<html lang="hu"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="description" content="{E(title,quote=True)}"><meta name="theme-color" content="#182f26"><title>{E(title)} | LOVASHARC.HU</title><link rel="stylesheet" href="assets/site.css"><link rel="stylesheet" href="assets/editorial.css"><link rel="stylesheet" href="assets/source-only.css"><script defer src="assets/site.js"></script></head><body>{header}<main id="main">{body}</main>{footer}</body></html>\n'
        output=re.sub(r'[ \t]+\n','\n',output.replace('\r\n','\n'))
        if filename=='index.html':
            output=output.replace('</head>', '<link rel="stylesheet" href="assets/home-reference.css"></head>')
            output=output.replace('<body>', '<body class="home-reference">')
            output=output.replace('<a class="brand" href="index.html"><span>', f'<a class="brand" href="index.html"><img src="{photo("egyesulet/lhse korb.jpg")}" alt="" width="58" height="58"><span>')
        (ROOT/filename).write_text(output,encoding='utf-8')
    # Preserve existing article URLs while folding their source frames into them.
    groups={}
    for u,p in pages.items():
        if u in [BASE,BASE+'index.html']:continue
        if any(a.get('kind')=='frame' for a in p['links']):continue
        groups.setdefault(routes[u],[]).append(u)
    output_titles={}
    primary={v:k for k,v in routes.items() if k in [assoc,train,news,writings,contact,anno,archive,book,cenz]}
    for filename,urls in groups.items():
        u=primary.get(filename,urls[0]);title=titles[u];output_titles[filename]=title
        urls=sorted(urls,key=lambda x:x!=u)
        local_nav=''
        if u==assoc or '/egyesulet/' in u:
            local_nav=''.join(f'<a href="{link(item["url"],src("UntitledFrame-5.html"))}">{E(titles.get(item["url"],item["text"]))}</a>' for item in pages[src('UntitledFrame-5.html')]['links'] if item['text'])
        body=heading(title,u)+'<div class="container page-layout"><aside class="sidebar"><div class="sidebar-inner"><h2>TARTALOM</h2><nav aria-label="TARTALOM">'+(local_nav or navigation(filename))+'</nav><a href="forrasok.html">TARTALOM</a></div></aside><article class="prose">'+''.join(content(x) for x in urls)+'</article></div>'
        write(filename,title,body)
    # Homepage excerpts are complete source paragraphs, not paraphrases.
    anniversary=norm(pages[BASE]['text'].split('.\n',1)[1])
    learn=excerpt(assoc,'Mit tanulhat')
    learn_body=excerpt(assoc,'Elsősorban')
    from home_reference import render
    home=render(photo,routes,src,learn,learn_body,anniversary)
    write('index.html','LOVASHARC.HU',home)
    # No invented biography: reuse the exact association and contact paragraphs.
    contact_soup=BeautifulSoup(content(contact),'html.parser')
    paragraphs=contact_soup.find_all('p')
    kz=[]
    for i,p in enumerate(paragraphs):
        if 'Kelemen Zsolt' in p.get_text():kz=[str(p),str(paragraphs[i+1])];break
    bio=learn_body[learn_body.index('Az egyesület képzési anyagának'):]
    body=heading('KELEMEN ZSOLT',assoc,(photo('konyv/1 hmhes eszkozei/k.zs..jpg'),'illustration'))+'<div class="container section prose"><p>'+E(bio)+'</p>'+''.join(kz)+related([(routes[src('konyv/hunmagyarharcmuveszet.html')],titles[src('konyv/hunmagyarharcmuveszet.html')])])+'</div>'
    write('kelemen-zsolt.html','KELEMEN ZSOLT',body)
    # Keep all image-map photographs, including the previously omitted interview gallery.
    gallery=[]
    for u in optimized:
        name=urlsplit(u).path.split('/')[-1]
        category='kepzes' if re.match(r'ilk\d',name,re.I) else 'tabor' if name.startswith('tabor ') else 'egyeb' if name.startswith(('jiv ','jiv14','foto ','v0','v1')) else None
        if category:gallery.append((u,category))
    labels={'all':'FOTÓGALÉRIA','kepzes':'INTENZÍV LOVASKÉPZÉS','tabor':'TÁBORFOTÓK','egyeb':'LHSE AKTUÁLIS'}
    body=heading('FOTÓGALÉRIA',archive)+'<section class="container section"><div class="gallery-toolbar" role="group" aria-label="FOTÓGALÉRIA">'+''.join(f'<button class="filter" data-filter="{key}" aria-pressed="{str(key=="all").lower()}">{label}</button>' for key,label in labels.items())+'</div><div class="gallery-grid">'
    for u,category in gallery:
        path=optimized[u];thumb='assets/photos/'+Path(path).stem+'-thumb.webp'
        if not (ROOT/thumb).exists():
            with Image.open(ROOT/path) as image:image.thumbnail((700,700));image.save(ROOT/thumb,'WEBP',quality=80)
        caption=labels[category]
        body+=f'<figure class="gallery-item" data-category="{category}"><a href="{path}" aria-label="{caption}"><img src="{thumb}" alt="{caption}" loading="lazy"></a><figcaption>{caption}</figcaption></figure>'
    body+='</div><dialog id="lightbox" aria-label="FOTÓGALÉRIA"><button class="lightbox-prev" aria-label="FOTÓGALÉRIA">←</button><button class="lightbox-next" aria-label="FOTÓGALÉRIA">→</button><button class="lightbox-close" aria-label="FOTÓGALÉRIA">×</button><img alt=""><p aria-live="polite"></p></dialog></section>'
    write('fotogaleria.html','FOTÓGALÉRIA',body)
    # Retain the old utility URL as an original-wording contents page.
    listing=[('index.html','LOVASHARC.HU')]+nav+[(f,t) for f,t in output_titles.items() if f not in dict(nav)]
    write('forrasok.html','TARTALOM',heading('TARTALOM',anno)+'<section class="container section">'+related(listing)+'</section>')
    # Audit report is developer data, not generated public prose.
    corpus_parts=[]
    for p in pages.values():
        source_soup=BeautifulSoup(p['html'],'html.parser')
        corpus_parts.append(re.sub(r'\s+','',source_soup.get_text()))
        for node in list(source_soup.find_all(string=True)):
            if padding(str(node)):node.replace_with(' ')
        corpus_parts.append(re.sub(r'\s+','',source_soup.get_text()))
    corpus=' '.join(corpus_parts)
    violations=[]
    for f in ROOT.glob('*.html'):
        soup=BeautifulSoup(f.read_text(),'html.parser')
        for tag in soup(['script','style','head']):tag.decompose()
        for node in soup.find_all(string=True):
            if isinstance(node,(Comment,Doctype)):continue
            text=norm(str(node))
            if text and re.search(r'[^\W\d_]',text) and re.sub(r'\s+','',text) not in corpus:
                violations.append({'page':f.name,'text':text})
        for el in soup.select('[alt],[aria-label]'):
            for attr in ['alt','aria-label']:
                text=norm(el.get(attr,''))
                if text and re.sub(r'\s+','',text) not in corpus:violations.append({'page':f.name,'attribute':attr,'text':text})
    coverage=[]
    for u,p in pages.items():
        filename=routes[u]
        coverage.append({'source':u,'local':filename,'kind':'frameset' if any(a.get('kind')=='frame' for a in p['links']) else 'content','source_text_nodes':len(source_records.get(u,[])),'preserved_images':len(image_records.get(u,[]))})
    manifest={'source':BASE,'source_pages':len(pages),'assets_downloaded':sum(bool(v) for v in assets.values()),'gallery_photos':len(gallery),'html_pages':sorted(p.name for p in ROOT.glob('*.html')),'routes':routes,'errors':json.loads((ROOT/'source/live-comparison.json').read_text())['unavailable']}
    (ROOT/'source/build-manifest.json').write_text(json.dumps(manifest,ensure_ascii=False,indent=2))
    report={'live_pages':len(pages),'html_pages':len(manifest['html_pages']),'new_local_pages':sorted(set(manifest['html_pages'])-original_files),'coverage':coverage,'non_source_text':violations}
    (ROOT/'source/source-only-audit.json').write_text(json.dumps(report,ensure_ascii=False,indent=2))
    (ROOT/'source/text-provenance.json').write_text(json.dumps(source_records,ensure_ascii=False,indent=2))
    print(json.dumps({k:v for k,v in report.items() if k not in ['coverage','non_source_text']},ensure_ascii=False,indent=2))
    print('Non-source text:',len(violations))
    for violation in violations[:10]:print(violation['page'],violation['text'][:250])
    if violations:raise SystemExit('Source-only text audit failed')
if __name__=='__main__':main()
