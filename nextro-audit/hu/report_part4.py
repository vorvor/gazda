from report_engine import *
from collections import Counter
F=json.loads((ROOT/'hu'/'findings.json').read_text())
T=json.loads((ROOT/'technical.json').read_text())
counts=Counter(x['priority'] for x in F)
# Az angol adatértékek és a rendezés változatlanok; csak a megjelenítés magyar.
priority_labels={'CRITICAL':'KRITIKUS','HIGH':'MAGAS','MEDIUM':'KÖZEPES','LOW':'ALACSONY'}
effort_labels={'Quick':'Gyors','Moderate':'Mérsékelt','Significant':'Jelentős'}
impact_labels={'High':'Magas','Medium':'Közepes','Low':'Alacsony'}
page('15. Rangsorolt megvalósítási ütemterv',15,kicker='ÜZLETI FELELŐS + FEJLESZTÉS + MARKETING')
p('Az ütemterv '+str(len(F))+' különálló javaslatot tartalmaz: '+', '.join(str(counts[x])+' '+priority_labels[x] for x in ['CRITICAL','HIGH','MEDIUM','LOW'])+'. Az azonosítók a jelentés egészében változatlanok. A várható hatás minőségi becslés; a ráfordítás a megvalósítás relatív terjedelmét és függőségeit jelzi, nem vállalt időtartamot.')
table(['Szakasz','Teendők és függőségek','Az élesítés igazolása'],[
['1 · A működési felkészültség helyreállítása','F01 szabályzatok/szállítás; F02 termékállapot/ár jóváhagyása; F04/F23 hozzáférhetőségi javítások; F05 hivatkozásjavítás; F15 hozzájárulás felülvizsgálata.','Jóváhagyott tartalom; használható cselekvési lehetőségek; nincsenek szerkesztői helykitöltők; sikeres kontraszt-/névtesztek; dokumentált adatvédelmi működés.'],
['2 · Akadályok csökkentése','F03 kategóriastratégia; F06 képek; F09/F14 űrlapok/mobil; F10–F12 keresési kivonatok/címsorok/oldaltérkép; F20 nyelvezet.','Nincsenek népszerűsített zsákutcák; mért adatforgalom-csökkenés; mobil-/űrlap-minőségellenőrzés; tiszta indexelési jelzések.'],
['3 · Hitelesség építése','F07 részletes üzleti szolgáltatásoldalak; F08 esettanulmányok; F13 termékválasztás; F16/F18 üzleti/strukturált/helyi adatok; F17 főoldal egyszerűsítése.','Jóváhagyott szakértői tartalom és bizonyítékok; kontextusfüggő belső hivatkozások és ajánlatkérési átadás; következetes üzleti adatok.'],
['4 · Optimalizálás és igazolás','F19 mért erőforrás-optimalizálás; F21–F22 finomítás; F24 végponttól végpontig tartó tesztelés, alapmérés és kísérletek.','Megismételhető labor-/funkcionális eredmények és a hozzájárulást figyelembe vevő eredménymérés. Az F24 tesztelése már a kezdeti tranzakciós promóciónak is előfeltétele.']],[1.15,3.0,1.85])
h('Gyors eredmények')
b('<b>Navigáció javítása:</b> a négy főoldali önhivatkozás cseréje, az álhivatkozások valódi célponttá alakítása és stabil szolgáltatási/ágazati horgonyok hozzáadása (F05).')
b('<b>Közös stílusértékek javítása:</b> a sötét ellenőrzőlista-szöveg, a gombok/hivatkozások kontrasztja és a névtelen fedőhivatkozások javítása az oldalak egyenkénti foltozása helyett (F04, F23).')
b('<b>Nyilvánvaló sablonszövegek befejezése:</b> a Contact us now / Archives / Price: cseréje, a működési idő egységesítése „2005 óta” alakra (F10, F20).')
b('<b>SEO-jelzések rendezése:</b> metaadatok kitöltése jóváhagyott termékadatokból, kategória-H1-ek helyreállítása és a noindex segédútvonalak eltávolítása az oldaltérképből (F10–F12).')
b('<b>Mobilos bevitel javítása:</b> nagyobb menücélpontok, helyes beviteli típusok és automatikus kitöltés a teljes űrlap újraépítése nélkül (F09, F14).')
p('Az F01 azonnali kockázatcsökkentése elengedhetetlen, de a teljes szabályzati/teljesítési megoldás mérsékelt ráfordítású, mert üzleti és jogi jóváhagyást igényel. Egy helykitöltő törlése nem egyenértékű megfelelő szabályzat közzétételével.','small')
# Teendőkatalógus, minden kötelező mező kifejezett feltüntetésével.
order={'CRITICAL':0,'HIGH':1,'MEDIUM':2,'LOW':3}
ordered=sorted(F,key=lambda x:(order[x['priority']],int(x['id'][1:])))
for start in range(0,len(ordered),3):
 batch=ordered[start:start+3]
 page('Ütemterv: '+' / '.join(f['id'] for f in batch),kicker='MEGVALÓSÍTÁSI KÁRTYÁK · PRIORITÁSI SORREND')
 for f in batch:
  sub(f['id']+' · '+esc(f['issue']))
  col={'CRITICAL':'#a52b39','HIGH':'#a4501f','MEDIUM':'#1a6076','LOW':'#52616c'}[f['priority']]
  p('<font color="'+col+'"><b>'+priority_labels[f['priority']]+'</b></font>  |  <b>Ráfordítás:</b> '+effort_labels[f['effort']]+'  |  <b>Várható hatás:</b> '+impact_labels[f['impact']]+'  |  '+esc(f['category']),'small')
  p('<b>Érintett URL-ek:</b> '+esc(' • '.join(f['urls'])),'tiny')
  p('<b>Miért fontos:</b> '+esc(f['why']),'small')
  p('<b>Javasolt megoldás:</b> '+esc(f['solution']),'small')
  p('<b>Felelős:</b> '+esc(f['owner'])+'<br/><b>Elkészült, ha:</b> '+esc(f['acceptance']),'small')
  story.append(Spacer(1,9))
page('Stratégiai fejlesztések',kicker='HASZNOS RÉSZLETESSÉG, NEM MÉG TÖBB SABLONOLDAL')
table(['Kezdeményezés','Terjedelem és függőségek','A siker értékelése'],[
['Szolgáltatási igényekre épülő architektúra','Elsőként a karbantartás, a felújítás/rotorcsere és a szűrőgyártás részletezése. Mérnöki interjúk, a feladatkör/alkalmazhatóság/folyamat tartalmának összeállítása és valódi esettanulmányok összekapcsolása. F07.','Az érdeklődők egy oldalon választ kapnak az alkalmazhatóság és a feladatkör kérdéseire; a kontextusfüggő megkeresések megőrzik a szolgáltatási szándékot. Organikus/érdeklődői eredmények csak alapmérés után értékelhetők.'],
['Bizonyítékokra épülő referenciatár','Legalább három jóváhagyott projekttörténet reprezentatív ágazatokból. Dátumok, valódi fotók, megnevezett vagy átláthatóan bizalmas ügyfelek és dokumentált eredmények használata. F08.','Az értékesítés releváns esetet küldhet valódi érdeklődőnek; minden számszerű eredményhez bizonyíték és engedély tartozik.'],
['Egységes termék-/ajánlatkérési rendszer','Minden kínálati csoport kereskedelmi modelljének kiválasztása, készlet-/ár-/teljesítési adatok összehangolása, szabályzati tartalom befejezése és a termékválasztás megkönnyítése. F01–F03, F13, F16.','Minden népszerűsített termékkörhöz vásárlási vagy ajánlatkérési útvonal és ismert kereskedelmi feltételek tartoznak; az engedélyezett pénztár-/megkereséstesztek sikeresek.'],
['Újrahasználható, akadálymentes komponensek','Világos/sötét stílusérték-rendszer, címsorok, kártyahivatkozás-szemantika, termékképek, űrlapállapotok, fókusz- és mozgásszabályok kialakítása. F04, F09, F11, F14, F23.','A közös sablonok javításai több szélességen, regresszió nélkül teljesítik az automatizált és emberi teszteket.'],
['Mérésekre épülő optimalizálási program','Kép-/betöltési munkák, majd használati lefedettségre épülő CSS/JS-módosítások, dokumentált hozzájárulás és megbízható eredményesemények. F06, F15, F19, F24.','Javulnak az ismételt, hideg gyorsítótáras laboratóriumi nyomkövetések és a valós felhasználói mérések; az adatvédelmi és funkcionális tesztek továbbra is érvényesek.']],[1.45,2.65,1.9])
h('Javasolt felelősségi rend')
p('Az árakhoz, garanciákhoz, teljesítéshez, céges állításokhoz és válaszadási elvárásokhoz üzleti jóváhagyót; a sablonokhoz, teljesítményhez és strukturált adatokhoz műszaki felelőst; a szolgáltatási/esettanulmány-oldalakhoz tartalmi felelőst kell rendelni. Egyetlen megállapítási feladatlistát kell fenntartani bizonyítékkal, felelőssel, elfogadási teszttel és kiadási állapottal. Egy kijelölt személy ellenőrizze, hogy a szabályzati, termék- és űrlapfolyamatok ugyanazt közvetítik.')
p('Ne az egész webhely kozmetikai újraépítésével kezdjenek. Előbb a működési és hozzáférési akadályokat kell megszüntetni, majd egy üzleti szolgáltatás és egy termék felhasználói útját prototípussal kidolgozni. A bevált komponenseket újra kell használni, a meglévő hasznos URL-eket és tartalmakat meg kell őrizni.','small')
# Záró ajánlások.
page('16. Végső ajánlások',16,kicker='A MEGVALÓSÍTÁS JAVASOLT SORRENDJE')
p('A NEXTRO szolgáltatási bemutatása és vizuális arculata működőképes alapot ad. A webhely legnagyobb gyengeségei a befejezetlen kereskedelmi részletekben, az akadálymentes használatot gátló kontrasztban, a kétértelmű navigációban és az elégtelen bizonyítékokban összpontosulnak – nem a tartalom vagy a technikai SEO teljes hiányában. Ezeket kell rendezni, mielőtt több forgalomra költenének.')
table(['Első 10','Teendő'],[
['1','Jóváhagyott adatkezelési, szerződési és szállítási tájékoztatók közzététele; a nyilvános belső megjegyzések megszüntetése.'],
['2','Minden termékhez ellenőrzött vásárlási vagy kontextusfüggő ajánlatkérési állapot rendelése.'],
['3','A szövegkontraszt, a névtelen hivatkozások és a csak színnel jelölt szövegközi hivatkozások javítása a közös sablonokban.'],
['4','A főoldali önhivatkozások/álhivatkozások javítása, a kiválasztott szolgáltatási/ágazati kontextus megőrzése.'],
['5','Az üres bolti kategóriák cseréje hasznos, ajánlatkérésre épülő kínálati oldalakra, vagy a befejezetlen kínálat promóciójának eltávolítása.'],
['6','A nagy éttermi/nyitó-/háttérképek adatforgalmának csökkentése és a mobilos betöltés ellenőrzése.'],
['7','A hozzájárulás, valamint az engedélyezett, végponttól végpontig tartó megkeresési/pénztártesztelés befejezése a tranzakciós promóció előtt.'],
['8','Ellenőrizhető esettanulmányok, szakértői/céges bizonyítékok és az állítások aktuális forrásainak közzététele.'],
['9','Eredeti, kiemelt szolgáltatási részletoldalak létrehozása, összekapcsolásuk bizonyítékokkal és megkeresési lehetőséggel.'],
['10','A kereskedelmi metaadatok, kategóriacímsorok, oldaltérkép-rendezettség és mobilos űrlapergonómia befejezése.']],[.65,5.35])
h('Prioritások szakterületenként')
p('<b>SEO:</b> először hasznos üzleti részletesség és bizonyítékok; utána termék-/kategóriakivonatok, címsorok, oldaltérkép és pontos szervezeti adatok.<br/><b>UX/UI:</b> olvasható sötét felületek, valódi céloldali hivatkozások, rövidebb ismétlődő főoldali szöveg, kényelmes mobilos hívás-/menüelérés.<br/><b>Műszaki:</b> képadatmennyiség és feltételes betöltés, gondosan tesztelt sablonerőforrások, egyértelmű indexelési jelzések és megbízható űrlap-/pénztárműködés.<br/><b>Tartalom:</b> teljes szabályzatok, jóváhagyott állítások, névtelen referenciák helyett bizonyítékok és az angol sablonszövegek eltávolítása.<br/><b>Konverzió:</b> valós termékelérhetőség/árazás, kontextusfüggő ajánlatkérési utak, a szükséges minimumra csökkentett űrlapterhelés és működés közben igazolt ügyátadás.')
callout('Sikerkritériumok','Az új látogató értse meg a számára releváns ajánlatot, tudjon hiteles bizonyítékot ellenőrizni, ismerje a vonatkozó feltételeket, és jusson végig egy egyértelmű megkeresési vagy vásárlási folyamaton. Ezt valódi tesztekkel és jóváhagyott analitikával kell igazolni; a szebb főoldal vagy a magasabb laborpontszám nem azonos a bizonyított üzleti sikerrel.')
# Függelék.
page('A függelék · Műszaki leltár',17,kicker='29 FELTÉRKÉPEZETT TARTALMI OLDAL')
p('A nyers feltérképezésben az alábbi sorok mind HTTP 200 választ adtak, és rendelkeztek title-lel, canonical címkével és index/follow robots metaadattal. A „Meta” a leírás jelenlétét, nem a minőségét jelzi. Az üres segédútvonalak és a 404-próba külön szerepelnek a műszaki szakaszban. Az alábbi URL-ek a https://nextro.hu/ címhez képest relatívak.','small')
rows=[]
for page_rec in T['pages']:
 if '/termek/' in page_rec['url']:continue
 path=page_rec['url'].replace('https://nextro.hu','')
 rows.append([esc(path),str(sum(hh['level']=='h1' for hh in page_rec['headings'])),'Van' if page_rec['meta'].get('description') else 'Hiányzik'])
table(['Útvonal','H1-ek','Meta'],rows,[4.7,.5,.8])
page('Műszaki leltár: termékek és segédoldalak',kicker='A 29 OLDALAS TARTALOMFELTÉRKÉPEZÉS FOLYTATÁSA')
p('Az alábbi kilenc termék mind 200 választ adott, egy H1-gyel, title-lel és önmagára mutató canonical jelöléssel. Mind a kilencnél hiányzik a metaleírás. A nyolc árazatlan változat nem tartalmaz Product JSON-LD-t; az árazott 22 kW / 7.5 m változat igen. Az útvonalak a https://nextro.hu/ címhez képest relatívak.','small')
rows=[]
for page_rec in T['pages']:
 if '/termek/' not in page_rec['url']:continue
 path=page_rec['url'].replace('https://nextro.hu','')
 rows.append([esc(path),'99 999 Ft' if '22-kw-75-' in path else 'Nincs feltüntetve'])
table(['Termékútvonal','Látható ár'],rows,[4.9,1.1])
h('Külön tesztelt segédútvonalak')
table(['Útvonal','Válasz / indexelési utasítás'],[['/kosar/','200; noindex, follow'],['/fiokom/','200; noindex, follow; bejelentkezést nem kíséreltünk meg'],['/penztar/','302 a /kosar/ címre az üres munkamenetű kérésben']],[1.2,4.8])
p('Ezek a segédútvonal-próbák nem szerepelnek a 29 tartalmi oldal feltérképezési összesítésében. Ügyféladatot, hitelesítési adatot vagy éles rendelést nem küldtünk be.','small')
page('B függelék · Bizonyíték- és ellenőrzési jegyzék',18,kicker='VISSZAKÖVETHETŐSÉG ÉS ISMERETLEN TÉNYEZŐK')
table(['Bizonyítékfájl / forrás','Mit támaszt alá'],[
['technical.json + http-evidence/','Nyers feltérképezés, metaadatok, HTTP-státusz/átirányítások, canonical/robots címkék, címsorok, képattribútumok, strukturált adatok, robots- és oldaltérképválaszok, user-agent-változatok, gyorsítótárazási minták.'],
['aggregates.json','Javított, névtereket figyelembe vevő oldaltérkép-darabszámok: 25 egyedi oldal-URL, 26 oldalbejegyzés és 26 egyedi képhely. Az erőforrás-összesítések nem tartalmazzák a befecskendezett axe kódot.'],
['supplement.json','Segédoldali/noindex/pénztárműködés, sitemap.xml átirányítás, orderby canonical, árállapotok, Product/Offer adatok, alap Organization gráf és favicon-/OG-ellenőrzések.'],
['home-browser.json; services.json; megoldasok.json; referenciak.json; rolunk.json','Megjelenített szöveg, címsorvázlatok és a fő oldalak hivatkozási célpontjai. A főoldali álhivatkozások működését kézzel is kipróbáltuk.'],
['kapcsolat.json; product-priced.json; product-unpriced.json; kategória/bolt JSON','Űrlapok/címkék és megjelenített termék-/kategória-kereskedelmi tartalom; a fülek tartalmát böngészős kattintással is megerősítettük.'],
['axe-home/services/contact/product.json','Mentett axe-core 4.10.3 elemszintű eredmények. A főoldal/szolgáltatások asztali, a kapcsolat/termék mobilos vizsgálat. Egy átmenetileg üres lap tesztjét elvetettük, a termékfájlt pedig érvényes, élő oldalon végzett vizsgálatra cseréltük.'],
['performance-desktop.json; performance-mobile.json','Navigation/Resource Timing és pufferelt LCP/elrendezéseltolódások. A jelentett egyszeri megfigyelés asztali; a mobilos mérés meleg/újrahasznált környezetből származott, ezért nem összehasonlítható viszonyítási alap.'],
['pagespeed-api.json','A HTTP 429/kvótaválasz. Ebből a próbálkozásból nem állt rendelkezésre PSI-pontszám vagy CrUX valós felhasználói eredmény.'],
['mobile-layouts.json; desktop-interactions.json; képernyőképek','Nézetméretek/túlcsordulás, betűméretek, látható hibák, tisztázott számlálóműködés és tesztelt fókuszviselkedés; eredeti és jelölésekkel ellátott képernyőképes bizonyítékok.']],[2.55,3.45])
h('Nem ellenőrzött, további vizsgálatot igénylő tételek')
p('Tényleges Google-indexelés, helyezések, keresési igény, visszahivatkozások, analitika/konverziós arányok, valós felhasználói Core Web Vitals, INP, a teljes oldalélettartam CLS-e, nem használt CSS/JS lefedettsége, hosszú feladatok, háttérrendszeri gyorsítótár-architektúra, hitelesített területek, fizetés befejezése, éles e-mail-kézbesítés, hírlevél-visszaigazolás, nyilvántartási/minősítési pontosság, harmadik felek teljes hozzájárulásfüggő adatforgalma és tárolási viselkedése, valamint formális WCAG-megfelelőség. E területek egyikén sem szerepel kitalált mérőszám.')
p('Auditfájlok: /workspace/nextro-audit/ · Végleges PDF: /workspace/nextro.hu_full_website_audit_hu.pdf. A jelentés a 2026. szeptember 22-én megfigyelt nyilvános webhelyet tükrözi; a későbbi változások felülírhatnak egy megállapítást. A nyers szerveroldali Date fejléceket változatlanul megőriztük; a vizsgálati dátum a helyi auditnyilvántartásból származik.','small')
