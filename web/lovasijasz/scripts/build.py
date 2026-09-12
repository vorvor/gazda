"""Generate portable UTF-8 pages from the archived, legacy Hungarian website."""
if __name__ == '__main__':
    # Public builds now enforce verbatim live-source copy; the prior design
    # implementation below is retained only as a migration reference.
    from source_only import main
    main()
    raise SystemExit(0)

import json, re, hashlib, html
from pathlib import Path
from urllib.parse import urljoin, urlsplit, unquote, quote
from bs4 import BeautifulSoup, Comment
from PIL import Image, ImageOps
ROOT = Path(__file__).resolve().parents[1]
BASE = 'http://www.lovasharc.hu/'
PAGES = json.loads((ROOT/'source/pages.json').read_text())
ASSETS = json.loads((ROOT/'source/assets.json').read_text())
E = html.escape

def source(suffix): return BASE + 'index_elemei/' + suffix
ASSOC = source('lhse.html.html')
TRAIN = source('INTENZIV LOVASKEPZES/intenziv lovaskepzes 2.html')
NEWS = source('LH AKTUAL/LH AKTUAL mezo.html')
WRITINGS = source('irasok/irasok.html')
CONTACT = source('Untitled-2.html')
ANNO = source('anno.html')
NAV = [('egyesulet.html','Egyesület'),('lovaskepzes.html','Intenzív lovas képzés'),('aktualis.html','Lovasharc aktuális'),('irasok.html','Írások'),('kelemen-zsolt.html','Kelemen Zsolt'),('fotogaleria.html','Fotógaléria'),('elerhetosegek.html','Elérhetőségek')]
ROUTES = {BASE:'index.html',BASE+'index.html':'index.html',ASSOC:'egyesulet.html',TRAIN:'lovaskepzes.html',NEWS:'aktualis.html',WRITINGS:'irasok.html',CONTACT:'elerhetosegek.html',ANNO:'jubileum.html'}
TITLES = {ASSOC:'Egyesület',TRAIN:'Intenzív lovas képzés',NEWS:'Lovasharc aktuális',WRITINGS:'Írások',CONTACT:'Elérhetőségek',ANNO:'Negyed évszázad a hagyomány szolgálatában',source('egyesulet/egyesulet celja.html'):'Az egyesület célja, feladata',source('egyesulet/lovas kepzesi redszer.html'):'Lovas képzési rendszer',source('egyesulet/vizsga.html'):'A vizsga',source('egyesulet/reszvetel.html'):'Részvétel az egyesület életében',source('dr szebenyi/szk 1.html'):'Kislovak a hadviselésben',source('dr szebenyi/szk tartalom.html'):'Kislovak a hadviselésben – tartalom',source('konyv/hunmagyarharcmuveszet.html'):'Hun-Magyar Harcművészet',source('konyv/konyv.html'):'Hun-Magyar Harcművészet – tartalom',source('irasok/nakos.html'):'Lövésteszt lovasíjász vértre',source('Untitled-3.html'):'Egyesületi hírek – archívum'}
for page in PAGES.values():
    for link in page['links']:
        u = link['url'].split('#')[0]
        if u not in TITLES and len(link['text']) > 7:
            TITLES[u] = re.sub(r'\s+', ' ',link['text']).strip()
for u,p in PAGES.items():
    if u not in ROUTES and len(p['text']) > 180:
        ROUTES[u] = 'iras-' + hashlib.sha256(u.encode()).hexdigest()[:10]+'.html'
        TITLES.setdefault(u, re.sub(r'\s+',' ',p['text'].split('\n')[0])[:110])
# Framesets become the modern parent pages, not empty documents.
ROUTES.update({source('lhse.html'):'egyesulet.html',source('INTENZIV LOVASKEPZES/INTENZIV LOVASKEPZES.html'):'lovaskepzes.html',source('LH AKTUAL/LH AKTUAL felso.html'):'aktualis.html',source('LH AKTUAL/LH AKTUAL.html'):'aktualis.html',source('UntitledFrameset-8.html'):ROUTES[source('Untitled-3.html')],source('dr szebenyi/szk keret 2.html'):ROUTES[source('dr szebenyi/szk 1.html')],source('LHSE AKTUAL/cenzurazatlan.html'):'fotogaleria.html'})
OPT = {}
(ROOT/'assets/photos').mkdir(exist_ok=True)
for u,path in ASSETS.items():
    if not path or Path(path).suffix.lower() not in ['.jpg','.jpeg','.png','.gif']: continue
    try:
        image = ImageOps.exif_transpose(Image.open(ROOT/path)).convert('RGB')
        image.thumbnail((1800,1600))
        target = 'assets/photos/' + Path(path).stem + '.webp'
        image.save(ROOT/target,'WEBP',quality=85)
        OPT[u] = target
    except Exception: pass

def image(suffix): return OPT[source(suffix)]
HERO = image('hatterek/hunpancel j1.jpg')
RIDE = image('INTENZIV LOVASKEPZES/ilk1.JPG')
LOGO = image('dr szebenyi/ther korb.jpg') if source('dr szebenyi/ther korb.jpg') in OPT else ''
# Locate the source's circular mounted-archer emblem without guessing its URL.
for u in OPT:
    if u.endswith('ther korb.jpg'): LOGO = OPT[u]

# Reuse original backgrounds as editorial artwork, cropping only empty space.
DESIGN_IMAGES = {}
for name, suffix, box in [
    ('meadow', 'egyesulet/HATTER 007.jpg', (0, 900, 981, 1750)),
    ('parchment', 'hatterek/Anno 003.jpg', (0, 0, 690, 552)),
]:
    target = f'assets/photos/design-{name}.webp'
    with Image.open(ROOT / ASSETS[source(suffix)]) as original:
        original.crop(box).convert('RGB').save(ROOT / target, 'WEBP', quality=90)
    DESIGN_IMAGES[name] = target

def heading_art(title):
    """Select source-specific artwork without mistaking old text banners for photos."""
    meadow = (DESIGN_IMAGES['meadow'], 'Legelő lovak – az eredeti egyesületi oldal háttérképe', 'photo')
    parchment = (DESIGN_IMAGES['parchment'], 'Lovasábrázolás – az eredeti jubileumi oldal pergamenillusztrációja', 'illustration')
    selected = {
        'Egyesület': meadow,
        'Intenzív lovas képzés': (image('INTENZIV LOVASKEPZES/HIRDFOTOK k.jpg'), 'Lovas gyakorlatok – az eredeti képzési oldal fotómontázsa', 'illustration'),
        'Lovasharc aktuális': (image('top (28)b.jpg'), 'Archív lovas fénykép az eredeti főoldalról', 'photo'),
        'Írások': (image('konyv/ny.jpg'), 'Illusztráció a Hun-Magyar Harcművészet című kiadványból', 'illustration'),
        'Kelemen Zsolt': (image('konyv/1 hmhes eszkozei/k.zs..jpg'), 'Archív illusztráció a Hun-Magyar Harcművészet könyv eszközökről szóló fejezetéből', 'illustration'),
        'Fotógaléria': (image('top (28)b.jpg'), 'Az eredeti főoldal archív lovas fényképe', 'photo'),
        'Elérhetőségek': meadow,
        'Források és archív információk': parchment,
        TITLES[ANNO]: parchment,
    }
    if title in selected: return selected[title]
    u = next((u for u in PAGES if TITLES.get(u) == title), None)
    if u and '/egyesulet/' in u: return meadow
    if u:
        for candidate in PAGES[u]['images']:
            if candidate not in OPT: continue
            if any(token in candidate.lower() for token in ['hatter', 'cimsor', 'cimkonyv', 'cg ', 'fejlec', 'felso sor', 'tartalom', 'tabor 25', 'jubileumi verseny']): continue
            with Image.open(ROOT / OPT[candidate]) as original:
                width, height = original.size
            if width >= 250 and height >= 250 and width / height < 3.5:
                return (OPT[candidate], 'Eredeti forrásillusztráció – ' + title, 'illustration')
    return parchment

def local_link(value, base):
    absolute = urljoin(base,value)
    parsed = urlsplit(absolute); clean = absolute.split('#')[0]
    if clean in ROUTES: return ROUTES[clean] + ('#'+parsed.fragment if parsed.fragment else '')
    if clean in ASSETS and ASSETS[clean]: return ASSETS[clean]
    if parsed.scheme not in ['http','https','mailto','tel','']: return ''
    return quote(absolute,safe=':/?=&%+#@')

def content(u):
    soup = BeautifulSoup(PAGES[u]['html'],'html.parser')
    for el in soup(['script','style','head','iframe','frame','frameset','object','embed','map','area']): el.decompose()
    for comment in soup.find_all(string=lambda x:isinstance(x,Comment)): comment.extract()
    # Remove old graphical mastheads; retain article pictures and semantic anchors.
    for img in list(soup.find_all('img')):
        src = urljoin(u,img.get('src','')); path = OPT.get(src)
        if not path: img.decompose(); continue
        with Image.open(ROOT/path) as im: w,h = im.size
        if w/h > 4 or any(s in src.lower() for s in ['cimsor','fejlec','felso sor','hatter','jelkezz','tanulj','erdeklod','kieg.jpg','irasok.jpg','irasok vege']): img.decompose(); continue
        img.attrs = {'src':path,'alt':img.get('alt') or 'Archív illusztráció – '+TITLES.get(u,'Lovasharc'),'loading':'lazy','width':str(w),'height':str(h)}
    for tag in list(soup.find_all(True)):
        if tag.name == 'img': continue
        attrs = {}
        if tag.get('id') or tag.get('name'): attrs['id'] = tag.get('id',tag.get('name'))
        if tag.name == 'a' and tag.get('href'):
            href = local_link(tag['href'],u)
            if href: attrs['href'] = href
        if tag.name in ['td','th']:
            for key in ['colspan','rowspan']:
                if tag.get(key): attrs[key] = tag[key]
        tag.attrs = attrs
        if tag.name in ['html','body','font','span','center','tbody','table','tr','td','th']:
            if 'id' in attrs: tag.name='div'
            else: tag.unwrap()
    for text in list(soup.find_all(string=True)):
        if re.fullmatch(r'[\s.xX*…_\-–—|]+',str(text)): text.replace_with(' ')
    for tag in list(soup.find_all(['p','div','b','strong','a','blockquote']))[::-1]:
        if tag.parent is not None and not tag.get_text(strip=True) and not tag.find('img') and not tag.get('id'): tag.decompose()
    return '<div class="source-body">'+str(soup)+'</div>'

def header(active):
    links=''.join(f'<a href="{href}"'+(' aria-current="page"' if active==href else '')+f'>{label}</a>' for href,label in NAV)
    return f'''<a class="skip" href="#main">Ugrás a tartalomra</a><div class="topline"><div class="container"><span>Lovasíjász Hagyományőrző Sportegyesület</span><span>Hagyomány · Természet · Közösség</span></div></div><header class="site-header"><div class="container header-inner"><a class="brand" href="index.html" aria-label="Lovasharc – főoldal">{f'<img src="{LOGO}" alt="" width="49" height="49">' if LOGO else ''}<span><strong>LOVASHARC</strong><small>A magyar lovas hagyomány</small></span></a><button class="menu-toggle" aria-expanded="false" aria-controls="navigation">Menü ☰</button><nav class="navigation" id="navigation" aria-label="Főmenü">{links}</nav></div></header>'''

def footer():
    return '''<section class="contact-band"><div class="container"><div><span class="eyebrow">Találkozzunk a természetben</span><h2>A megismerés a találkozással kezdődik.</h2><p>Képzéseinkről és a részvétel lehetőségeiről érdeklődj személyesen.</p></div><a class="button" href="elerhetosegek.html">Kapcsolatfelvétel <span aria-hidden="true">↗</span></a></div></section><footer class="site-footer"><div class="container"><div class="footer-top"><div><a class="footer-brand" href="index.html">LOVASHARC</a><p>Lovasíjász Hagyományőrző Sportegyesület<br>A hagyományos Hun-Magyar lovaskultúra és harcművészet megismerése, gyakorlása és népszerűsítése.</p></div><div class="footer-links"><a href="egyesulet.html">Egyesület</a><a href="lovaskepzes.html">Lovas képzés</a><a href="irasok.html">Írások</a><a href="elerhetosegek.html">Kapcsolat</a></div></div><div class="footer-bottom"><span>Szövegek és archív fotók: lovasharc.hu · Statikus feldolgozás</span><a href="forrasok.html">Források és archív információk ↗</a></div></div></footer>'''

def write(filename,title,body,active=None,description=''):
    desc = description or 'Lovasíjász Hagyományőrző Sportegyesület. Lovaglás, íjászat, lovasharc és a magyar lovas hagyomány.'
    output=f'<!doctype html>\n<html lang="hu"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="description" content="{E(desc,quote=True)}"><meta name="theme-color" content="#182f26"><title>{E(title)} | Lovasharc</title><link rel="stylesheet" href="assets/site.css"><script defer src="assets/site.js"></script></head><body>{header(active or filename)}<main id="main">{body}</main>{footer()}</body></html>\n'
    output = output.replace('<link rel="stylesheet" href="assets/site.css">', '<link rel="stylesheet" href="assets/site.css"><link rel="stylesheet" href="assets/editorial.css">')
    if filename == 'index.html':
        output = output.replace('alt="Lovasíjász lövésre emelt íjjal, természetes környezetben" width="1800" height="1200"', 'alt="Páncélos lovas – az eredeti Lovasharc főoldal háttérképe" width="945" height="516"')
        output = output.replace('Lovasíjászat · Az egyesület fotóarchívumából', 'Lovas hagyomány · Az eredeti honlap háttérképe')
    (ROOT/filename).write_text(output,encoding='utf-8')

def heading(title,desc='',label='Lovasharc',parent=None):
    crumb = f' / <a href="{parent[0]}">{parent[1]}</a>' if parent else ''
    path, alt, treatment = heading_art(title)
    with Image.open(ROOT / path) as original:
        width, height = original.size
    return f'<section class="page-heading page-heading--illustrated"><div class="container"><div class="breadcrumbs"><a href="index.html">Főoldal</a>{crumb} / {E(title)}</div><div class="heading-composition"><div class="heading-copy"><span class="eyebrow">{E(label)}</span><h1>{E(title)}</h1>{f"<p>{E(desc)}</p>" if desc else ""}</div><figure class="heading-art heading-art--{treatment}"><img src="{path}" alt="{E(alt, quote=True)}" width="{width}" height="{height}" fetchpriority="high"><figcaption>Az eredeti honlap képi örökségéből</figcaption></figure></div></div></section>'

def related(items):
    return '<div class="related-list">'+''.join(f'<a href="{href}">{E(label)} <span aria-hidden="true">↗</span></a>' for href,label in items)+'</div>'

def article(u,desc='',before='',after='',active=None):
    filename=ROUTES[u]; title=TITLES[u]
    if 'egyesulet/' in u or u==ASSOC:
        sidebar=[('egyesulet.html','Az egyesületről')]+[(ROUTES[x],TITLES[x]) for x in PAGES if '/egyesulet/' in x and x in ROUTES]
        active='egyesulet.html'
    else: sidebar=[('irasok.html','Írások és kiadványok'),('lovaskepzes.html','Intenzív lovas képzés'),('aktualis.html','Aktuális és archív hírek'),('elerhetosegek.html','Elérhetőségek')]
    body=heading(title,desc,'Hagyomány és tudás')+f'<div class="container page-layout"><aside class="sidebar"><div class="sidebar-inner"><h2>Felfedezés</h2><nav aria-label="Kapcsolódó oldalak">'+''.join(f'<a href="{href}">{E(label)}</a>' for href,label in sidebar)+f'</nav><p class="source-note">Az eredeti honlap archív tartalma. A történeti szövegek a szerzők nézeteit tükrözik.<br><a href="{quote(u,safe=":/")}">Eredeti forrás ↗</a></p></div></aside><article class="prose">{before}{content(u)}{after}</article></div>'
    write(filename,title,body,active,desc)

# Front page: an editorial composition, using only the association's own photography.
home=f'''<section class="hero"><div class="hero-copy"><span class="eyebrow">Lovaglás · Íjászat · Hagyományőrzés</span><h1>Élő hagyomány.<br><em>Szabadon,</em><br>lóháton.</h1><p>A hagyományos Hun-Magyar lovaskultúra megismerése és gyakorlása. Ló és ember kapcsolata, természetes környezetben.</p><div class="actions"><a class="button" href="lovaskepzes.html">Ismerd meg a képzést <span aria-hidden="true">↗</span></a><a class="text-link" href="egyesulet.html">Az egyesületről <span aria-hidden="true">→</span></a></div></div><div class="hero-image"><img src="{HERO}" alt="Lovasíjász lövésre emelt íjjal, természetes környezetben" width="1800" height="1200" fetchpriority="high"><span class="photo-note">Lovasíjászat · Az egyesület fotóarchívumából</span></div></section>
<section class="anniversary"><div class="container anniversary-inner"><span class="seal">25 év</span><p><strong>Negyed évszázad a lovas hagyomány szolgálatában.</strong><br>Az egyesület 2025-ös jubileumára megjelent visszatekintés.</p><a class="text-link" href="jubileum.html">A történetünk <span aria-hidden="true">↗</span></a></div></section>
<section class="container section intro"><div><span class="eyebrow">Az egyesület</span><h2>Nem csupán lovaglás.<br>Egy szemléletmód.</h2><hr class="small-rule"></div><div class="intro-copy"><p>„Nem vagyunk »lovarda« és nem vagyunk »üzleti vállalkozás«. Közhasznú kulturális és sportegyesületként működünk.”</p><p>Lovaglás és íjászat, lótartás és lovas életmód. Az egyesület munkájának alapja a Kelemen Zsolt szakmai irányításával végzett gyakorlati, kísérleti régészeti kutatómunka.</p><a class="text-link" href="egyesulet.html">Ismerd meg az egyesületet <span aria-hidden="true">↗</span></a></div></section>
<section class="container offering"><div class="offering-photo"><img src="{RIDE}" alt="Lovasok közös gyakorlása az egyesületben" width="1800" height="1200" loading="lazy"></div><div class="offering-copy"><span class="eyebrow">Intenzív lovas képzés</span><h2>Az első lépésektől<br>a közös élményekig.</h2><p>Ismerkedés a lovaglás alapjaival, kiválóan képzett lovakkal, természetes körülmények között.</p><div class="facts"><div><strong>5 / 10</strong><small>alkalmas képzés</small></div><div><strong>8 éves kortól</strong><small>javasolt részvétel</small></div></div><a class="button" href="lovaskepzes.html">A képzés részletei <span aria-hidden="true">↗</span></a><p class="note" style="margin-top:18px">Archív képzési tájékoztató. Az aktuális lehetőségekről érdeklődj az egyesületnél.</p></div></section>
<section class="container section"><div class="section-head"><div><span class="eyebrow">Írások és gondolatok</span><h2>A tudás tovább él.</h2></div><a class="text-link" href="irasok.html">Minden írás <span aria-hidden="true">↗</span></a></div><div class="journal">'''
for category,title,desc,url in [('Kiadvány','Hun-Magyar Harcművészet','Az íjászat hagyományos módszere – Kelemen Zsolt és alkotógárdája',source('konyv/hunmagyarharcmuveszet.html')),('Lovas kultúra','Kislovak a hadviselésben','Sir Walter Gilbey műve, Dr. Szebényi Tibor fordításában',source('dr szebenyi/szk 1.html')),('Visszatekintés','Egyesületünk negyed évszázada','Gondolatok a jubileum és az elmúlt évek munkája kapcsán',ANNO)]:
    home+=f'<a class="journal-row" href="{ROUTES[url]}"><span class="category">{category}</span><div><h3>{title}</h3><p>{desc}</p></div><span aria-hidden="true">↗</span></a>'
home+='</div></section><section class="container section" style="padding-top:0"><div class="section-head"><div><span class="eyebrow">Az egyesület képekben</span><h2>Kint, a természetben.</h2></div><a class="text-link" href="fotogaleria.html">Fotógaléria <span aria-hidden="true">↗</span></a></div><div class="gallery-preview">'
for suffix,alt in [('ilk3.JPG','Lovas zászlóval a mezőn'),('ilk6.JPG','Lovaglás a vízben'),('ilk05.JPG','Közös gyakorlatok a lovakkal')]: home+=f'<a href="fotogaleria.html"><img src="{image("INTENZIV LOVASKEPZES/"+suffix)}" alt="{alt}" width="600" height="450" loading="lazy"></a>'
home+='</div></section>'
write('index.html','Élő hagyomány, szabadon, lóháton',home)
article(ASSOC,'Lovaglás, íjászat és lovas életmód. Közhasznú kulturális és sportegyesület, a tagság közös munkájára építve.')
article(TRAIN,'A lovaglás alapjai természetes körülmények között, egyéni és csoportos képzéseken.',before='<div class="notice"><strong>Archív képzési tájékoztató · 2024</strong><p>Az eredeti kiírás 2024. szeptember 12-i indulást említ. Az alábbi feltételek és időpontok nem minősülnek aktuális ajánlatnak. A most elérhető képzésekről érdeklődj az egyesületnél.</p><a href="elerhetosegek.html">Érdeklődés a képzésről →</a></div>',active='lovaskepzes.html')
article(ANNO,'Visszatekintés a Lovasíjász Hagyományőrző Sportegyesület munkájára.',before='<div class="notice">Archív írás a 2025-ös jubileum előkészítéséről. Az eredeti szöveg időhivatkozásait változatlanul őrizzük.</div>',active='aktualis.html')
# Dedicated news page keeps obsolete dates visibly archival.
body=heading('Lovasharc aktuális','Hírek, közlemények és visszatekintések az eredeti honlapról.','Egyesületi élet')+'<section class="container section"><div class="notice">Archív tartalmak: a forrásoldal 2024–2025-ös közléseit őrizzük. Aktuális időpontokról és a támogatás módjáról közvetlenül az egyesületnél érdeklődj.</div><div class="journal">'
for category,title,desc,href in [('2025 · Jubileum',TITLES[ANNO],'Visszatekintés az egyesület negyed évszázados munkájára.','jubileum.html'),('2024 · Képzés','Intenzív lovas képzés','Az eredeti képzési felhívás és a részvétel feltételei.','lovaskepzes.html'),('Archívum','Egyesületi hírek','Korábbi rendezvények, táborok és közlemények.',ROUTES[source('Untitled-3.html')])]: body+=f'<a class="journal-row" href="{href}"><span class="category">{category}</span><div><h3>{title}</h3><p>{desc}</p></div><span aria-hidden="true">↗</span></a>'
body+='</div><div class="prose" style="margin-top:45px"><h2>Az egyesület támogatása</h2>'+content(NEWS)+'<p class="note">Archív bankszámlaadat. Utalás előtt kérjük, ellenőrizd az egyesületnél.</p></div></section>'
write('aktualis.html','Lovasharc aktuális',body)
article(WRITINGS,'Könyvek, tanulmányok és szakmai írások a lovaskultúráról, íjászatról és hagyományőrzésről.',before='<div class="notice">Az írások és a könyvrészletek az eredeti szerzők szövegei. A Környezetismeret előadás helyben letölthető PPS fájlként (PowerPoint bemutató) érhető el.</div>',active='irasok.html')
# The original Kelemen Zsolt menu entry has no destination: do not invent a biography.
body=heading('Kelemen Zsolt','Alapító, elnök, szakmai irányító, szervező és oktató.','Ember a hagyomány mögött')+f'<div class="container page-layout"><aside class="sidebar"><h2>Kapcsolódó tartalmak</h2><a href="egyesulet.html">Az egyesület</a><a href="irasok.html">Írások és kiadványok</a><a href="elerhetosegek.html">Elérhetőségek</a></aside><article class="prose"><h2>Kutatás, gyakorlat, oktatás.</h2><p>Az egyesület képzési anyagának és módszereinek alapját az egyesület által, Kelemen Zsolt szakmai irányítása alatt végzett gyakorlati/kísérleti régészeti kutatómunka képezi, melynek célja a hagyományos HUN-MAGYAR lovaskultúra és harcművészet megismerése, gyakorlása és népszerűsítése.</p><h2>Írások és közreműködés</h2>'+related([(ROUTES[source('konyv/hunmagyarharcmuveszet.html')],'Hun-Magyar Harcművészet – Az íjászat hagyományos módszere'),(ASSETS[source('irasok/KORNYEZET 2016..pps')],'Környezetismeret – előadás letöltése (PPS)'),('jubileum.html','Jubileumi visszatekintés')])+'<h2>Személyes kapcsolat</h2><p><a href="tel:+36205822992">06 - 20 / 582 29 92</a><br><a href="mailto:lovasharc@gmail.hu">LOVASHARC@GMAIL.HU</a></p><div class="notice">Az eredeti „Kelemen Zsolt” menüpont nem tartalmazott hivatkozást. Ez az oldal az egyesületi bemutatkozásban, az írások között és a kapcsolatoldalon elérhető információkat rendezi egybe; nem önálló életrajz.</div></article></div>'
write('kelemen-zsolt.html','Kelemen Zsolt',body)
body=heading('Elérhetőségek','Képzés, részvétel, egyesületi élet – keress bennünket az alábbi elérhetőségeken.','Lépj kapcsolatba velünk')+'<div class="container contact-grid">'
for name,role,phone,tel in [('Kelemen Gabriella','Az elnökség tagja, szakmai vezető, szervező és oktató','06 - 30 / 683 31 84','+36306833184'),('Gróf Gábor','Alapító, az elnökség tagja, szervező, információs menedzser','06 - 30 / 522 24 99','+36305222499'),('Kelemen Zsolt','Alapító, elnök, szakmai irányító, szervező és oktató','06 - 20 / 582 29 92','+36205822992')]: body+=f'<section class="contact-person"><span class="eyebrow">Egyesületi kapcsolat</span><h2>{name}</h2><p>{role}</p><a href="tel:{tel}">{phone} ↗</a><a href="mailto:lovasharc@gmail.hu">LOVASHARC@GMAIL.HU ↗</a></section>'
body+='</div><div class="container notice">Az elérhetőségek az eredeti honlapról származnak; működésüket nem ellenőriztük. Az e-mail-címet a forrás szerinti GMAIL.HU végződéssel őriztük meg. Látogatás előtt egyeztess az egyesülettel.</div>'
write('elerhetosegek.html','Elérhetőségek',body)
# Generate all substantive nested content, including book chapters and long articles.
for u,p in PAGES.items():
    if u in ROUTES and u not in [BASE,BASE+'index.html',ASSOC,TRAIN,NEWS,WRITINGS,CONTACT,ANNO] and len(p['text'])>180:
        article(u, before='<div class="notice">Archív forrásszöveg. A benne szereplő időpontok, árak és részvételi feltételek a közlés időszakára vonatkoznak.</div>',active='egyesulet.html' if '/egyesulet/' in u else 'irasok.html')
# Real photographs from the source, grouped by their original archive collections.
photos=[]
for u,path in ASSETS.items():
    if u not in OPT: continue
    name=unquote(urlsplit(u).path).split('/')[-1]
    category = 'kepzes' if re.match(r'ilk\d',name,re.I) else 'rendezvenyek' if name.startswith('jiv ') or name.startswith('jiv14') else 'tabor' if name.startswith('tabor ') else None
    if category: photos.append((u,path,category))
labels={'kepzes':'Lovas képzés','rendezvenyek':'Egyesületi rendezvények','tabor':'Tábori pillanatok'}
body=heading('Fotógaléria','Lovak, lovasok és közös élmények az egyesület eredeti fotóarchívumából.','Közel a természethez')+'<section class="container section"><p class="note">Az eredeti főmenü fotógaléria-felirata nem volt kattintható. A válogatás a képzési és az egyesületi archív oldalakon hivatkozott fényképekből készült.</p><div class="gallery-toolbar" role="group" aria-label="Galéria szűrése"><button class="filter" data-filter="all" aria-pressed="true">Összes kép</button>'+''.join(f'<button class="filter" data-filter="{key}" aria-pressed="false">{label}</button>' for key,label in labels.items())+f'</div><p class="gallery-count" aria-live="polite">{len(photos)} fénykép</p><div class="gallery-grid">'
for i,(u,path,category) in enumerate(photos,1):
    thumb='assets/photos/'+Path(path).stem+'-thumb.webp'
    with Image.open(ROOT/OPT[u]) as im:
        im.thumbnail((700,700));im.save(ROOT/thumb,'WEBP',quality=80)
    caption=f'{labels[category]} · {i:02}'
    body+=f'<figure class="gallery-item" data-category="{category}"><a href="{OPT[u]}" aria-label="{caption} – nagyítás"><img src="{thumb}" alt="{caption} – eredeti archív fénykép" width="700" height="467" loading="lazy"></a><figcaption>{caption}</figcaption></figure>'
body+='</div><dialog id="lightbox" aria-label="Nagyított fénykép"><button class="lightbox-prev" aria-label="Előző kép">← Előző</button><button class="lightbox-next" aria-label="Következő kép">Következő →</button><button class="lightbox-close" aria-label="Nagyítás bezárása">Bezárás ×</button><img alt=""><p aria-live="polite"></p></dialog></section>'
write('fotogaleria.html','Fotógaléria',body)
errors=json.loads((ROOT/'source/errors.json').read_text())
body=heading('Források és archív információk','A statikus feldolgozás tartalmi eredete és ismert korlátai.','Átlátható feldolgozás')+'<section class="container section prose"><h2>Az eredeti honlap megőrzése</h2><p>Forrás: <a href="http://www.lovasharc.hu/">www.lovasharc.hu</a>. A szövegek magyar nyelvű, UTF-8 kódolású átiratai. A régi keretes oldalszerkezet helyett önálló, reszponzív HTML-oldalak készültek. A szövegek és fotók az eredeti jogosultakhoz tartoznak.</p><p>Az archív tartalmak nem jelentenek friss esemény- vagy képzési ajánlatot. A történeti és szakmai állításokat az eredeti szerzőknek tulajdonítjuk; nem függetlenül ellenőrzött tényállításként közöljük.</p><h2>Menüpontok és forráshiányok</h2><p>A Kelemen Zsolt és Fotógaléria feliratokhoz a forrás főoldalán nem tartozott hivatkozás. Ezek az aloldalak kizárólag a honlap más részein elérhető anyagokat rendezik egybe. A nem hivatkozott „Támogatók” felirathoz nem készült kitalált tartalom.</p><h2>Nem elérhető eredeti hivatkozások</h2><ul>'+''.join(f'<li>{E(unquote(item["url"]))} — {E(item["error"])}</li>' for item in errors)+'</ul><h2>Feldolgozott forrásoldalak</h2><ul>'+''.join(f'<li><a href="{ROUTES[u]}">{E(TITLES.get(u,"Főoldal"))}</a> — <a href="{quote(u,safe=":/")}">eredeti oldal</a></li>' for u in PAGES if u in ROUTES and len(PAGES[u]['text'])>180)+'</ul></section>'
write('forrasok.html','Források és archív információk',body)
manifest={'source':'http://www.lovasharc.hu/','source_pages':len(PAGES),'assets_downloaded':sum(bool(v) for v in ASSETS.values()),'gallery_photos':len(photos),'html_pages':sorted(p.name for p in ROOT.glob('*.html')),'routes':ROUTES,'errors':errors}
(ROOT/'source/build-manifest.json').write_text(json.dumps(manifest,ensure_ascii=False,indent=2))
print(json.dumps({k:v for k,v in manifest.items() if k not in ['routes','errors','html_pages']},ensure_ascii=False));print('HTML pages:',len(manifest['html_pages']))
