from report_engine import *
from collections import Counter
T=json.loads((ROOT/'technical.json').read_text()); A=json.loads((ROOT/'aggregates.json').read_text()); X=json.loads((ROOT/'supplement.json').read_text()); F=json.loads((ROOT/'hu'/'findings.json').read_text())
# Cover.
story.append(Spacer(1,72));p('FÜGGETLEN • BIZONYÍTÉKOKRA ÉPÜLŐ • MEGVALÓSÍTHATÓ','kicker')
p('nextro.hu<br/>Teljes weboldal-<br/>audit','cover');story.append(Spacer(1,20))
p('SEO · Webdesign · UX/UI · Akadálymentesség<br/>Teljesítmény · Tartalom · Konverzió','coverbody');story.append(Spacer(1,42))
p('A weboldal tulajdonosa,<br/>fejlesztői és marketingcsapata részére','coverbody');story.append(Spacer(1,30))
p('A vizsgálat időpontja: 2026. szeptember 22.<br/>Nyilvános weboldal · Asztali és mobilos vizsgálat','coverbody');story.append(Spacer(1,32))
p('Az alapvető üzleti bemutatkozás hiteles és világos szerkezetű.<br/>A közvetlen lehetőség az értékesítési folyamat befejezése,<br/>az olvashatóság javítása és a szakértelem igazolható bemutatása.','coverbody')
# Contents.
page('Tartalomjegyzék')
toc=TableOfContents();toc.levelStyles=[ParagraphStyle('toc',fontName='Audit',fontSize=9.5,leading=14,spaceBefore=2,textColor=NAVY,leftIndent=0,firstLineIndent=0,rightIndent=24)];story.append(toc)
story.append(Spacer(1,18));callout('Az összefoglaló használata','Először a vezetői összefoglalót és a záró prioritásokat olvassa el. A megállapítások azonosítói (F01–F24) kapcsolják össze az észrevételeket a megvalósítási ütemtervvel. A képernyőképek a vizsgálat dátumához kötött bizonyítékok, nem látványtervek. A weboldal magyar idézetei változatlanul szerepelnek; az elemzés is magyar nyelvű.')
p('A prioritás üzleti és megvalósítási mérlegelés eredménye. A KRITIKUS bevezetési/üzemkészségi akadályt; a MAGAS jelentős hozzáférési, bizalmi vagy konverziós akadályt; a KÖZEPES érdemi fejlesztést; az ALACSONY finomítást jelöl. A ráfordítás relatív terjedelmet jelez, nem idő- vagy költségajánlat.','small')
# Executive.
page('1. Vezetői összefoglaló',1,kicker='ÜZLETI NÉZŐPONT')
p('A NEXTRO felkészült épületgépészeti vállalkozásként jelenik meg, egységes sötétkék/zöld arculattal, széles szolgáltatáskínálattal, országos működést hangsúlyozó üzenettel és jól látható elérhetőségekkel. A szolgáltatási és ágazati áttekintők hasznos műszaki magyarázatokat és gyakori kérdéseket tartalmaznak. Az alapvető feltérképezhetőség, a kanonikus URL-ek, a HTTPS-normalizálás és a valódi 404-es válasz rendelkezésre áll.')
callout('Általános állapot: jó bemutatkozó alapok, befejezetlen értékesítési folyamat','A weboldal nem tekinthető teljesen késznek a konverzióközpontú népszerűsítésre, amíg a szabályzatok helykitöltői, a termékek vásárlási/ajánlatkérési állapotai és a nyilvánvaló olvasási akadályok nem rendeződnek. Forgalmi, helyezési vagy konverziósarány-adatok nem álltak rendelkezésre; az értékelés a megfigyelt tartalomra és működésre épül.',RED)
h('Legfőbb erősségek')
b('Világos szolgáltatási spektrum: a telepítés, az automatika, a beüzemelés, a karbantartás, a felújítás és a saját szűrőgyártás nem pusztán felsorolásként, hanem magyarázattal szerepel.')
b('Bizalmi alapok: cégjegyzék- és adóadatok, külön székhely- és raktárcím, működő telefonos/e-mailes hivatkozások, dokumentált munkavégzésre vonatkozó állítások és látható megkeresési útvonal.')
b('Technikailag hozzáférhető tartalom: a fő kínálat a nyers HTML-ben is szerepel; mind a 29 feltérképezett tartalmi oldal 200-as választ adott, címmel és kanonikus címkével. A mintában vizsgált navigáció nem vezetett HTTP-hibához.')
h('Legfontosabb gyengeségek')
b('Az adatvédelmi, az általános szerződési és a szállítási oldalak helykitöltők, miközben az űrlapok ezek elfogadását kérik. A termékek szállítási fülein belső „[HIÁNYZIK: …]” megjegyzés látható (F01).')
b('A bolt kilenc autótöltő-változatot kínál, de csak egynek van látható ára és kosárba helyezési lehetősége; hét meghirdetett légtechnikai kategória üres (F02–F03).')
b('A sötét nyitópaneleken megjelenő sötét szöveg, a félreérthető főoldali hivatkozások és egy 2.62 MB-os étteremkép elkerülhető hozzáférési, navigációs és betöltési terheket okoznak (F04–F06).')
b('A referenciaoldalak névtelen projekttípusokat írnak le, nem dokumentált esettanulmányokat. Az üzleti szolgáltatások részletes oldalai még nincsenek kidolgozva (F07–F08).')
h('A legnagyobb lehetőség és a várható hatás')
p('Tegye könnyebben ellenőrizhetővé a NEXTRO szakszerű kivitelezési képességét, és egyszerűbbé a vásárlást vagy az ajánlatkérést. A befejezetlen lépések javítása várhatóan csökkenti a bizonytalanságot; az olvasható felületek és a kontextust megőrző ajánlatkérési útvonalak javíthatják a hozzáférést; a részletesebb szolgáltatási oldalak relevánsabbak lehetnek a célzott keresésekre. Ezek várható irányhatások, nem helyezési, bevételi vagy százalékos növekedési előrejelzések.')
page('Vezetői prioritások: ebben a sorrendben érdemes lépni',kicker='A TÍZ LEGFONTOSABB TEENDŐ')
table(['Sorrend / prioritás','Teendő','Várható üzleti hatás'],[
['1 · KRITIKUS','Az adatvédelmi, szerződési és szállítási helykitöltők cseréje; a belső termékfül-megjegyzések eltávolítása. F01','A tájékozott döntés lehetőségének helyreállítása, az azonnali bizalmi/üzemkészségi kockázat csökkentése.'],
['2 · MAGAS','Minden termék vásárlási vagy ajánlatkérési módjának, árának és elérhetőségének jóváhagyása. F02','A bizonytalanság megszüntetése a vásárlási döntésnél.'],
['3 · MAGAS','A szövegkontraszt, valamint a név nélküli és csak színnel jelölt hivatkozások javítása a közös sablonokban. F04, F23','A bizonyító állítások, a navigáció és a kapcsolatfelvétel több látogató számára válik használhatóvá.'],
['4 · MAGAS','A főoldalra visszamutató hivatkozások, álhivatkozások és kontextust elveszítő kártyák javítása. F05','A látogatók elérik a felület által ígért információt.'],
['5 · MAGAS','Az üres bolti útvonalak helyett hasznos, ajánlatkérésre épülő kategóriaoldalak létrehozása, vagy a befejezetlen navigáció elrejtése. F03','Az értékesítési zsákutcák elkerülése.'],
['6 · MAGAS','Az étterem- és nyitóképek, valamint a hajtás alatti betöltés optimalizálása. F06','Kevesebb átvitt adat és kisebb kitettség a lassabb hálózatoknak.'],
['7 · MAGAS','A hozzájárulási állapotok, az űrlapkézbesítés és a tranzakciós folyamatok ellenőrzése jóváhagyott tesztkörnyezetben. F15, F24','Igazolhatóvá válik a megkeresések, engedélyek és rendelések megbízható kezelése.'],
['8 · MAGAS','Bizonyítékokra épülő esettanulmányok közzététele és a céges állítások igazolása. F08','A beszerzői bizalom erősítése.'],
['9 · MAGAS','Érdemi, kiemelt szolgáltatási részoldalak és kölcsönös hivatkozások létrehozása. F07','Jobb illeszkedés a keresési szándékhoz és kontextust megőrző megkeresések.'],
['10 · KÖZEPES','A keresési kivonatok, kategóriacímsorok és webhelytérképek rendbetétele; a mobilos ajánlatkérés egyszerűsítése. F09–F12, F14','Jobb megtalálhatóság és kisebb felhasználói erőfeszítés.']],[1.15,3.1,2.5])
p('A hatás minőségi értékelés. Nem kaptunk analitikai, Search Console-, hirdetési, CRM-, fizetési konfigurációs vagy szervernapló-adatokat, illetve ellenőrzött kulcsszóvolumeneket. Pénzügyi célok kijelölése előtt rögzíteni kell a kiinduló állapotot.','small')
# scope.
page('Hatókör, módszerek és korlátok',0,kicker='A SZÁMOK ÉRTELMEZÉSE ELŐTT OLVASANDÓ')
table(['Vizsgálati terület','A ténylegesen elvégzett vizsgálat'],[
['HTTP és SEO','29 nyilvános tartalmi oldal: főoldal, hat fő navigációs céloldal, öt szabályzati/céginformációs oldal, nyolc kategória és kilenc termék. Mentettük a nyers HTML-t, metaadatokat, címsorokat, képeket, hivatkozásokat és JSON-LD-t.'],
['Felfedezés és útvonalak','robots.txt; sitemap.xml átirányítás; webhelytérkép-index és három alárendelt webhelytérkép; hoszt-/protokollváltozatok; egy záróperjel-változat; UTM- és bolti rendezési paraméterek; egy szándékosan nem létező URL. A kosár/fiók és az üres munkamenetű pénztár külön vizsgálatot kapott.'],
['Böngésző / vizuális vizsgálat','Asztali nézet 1280 × 720; mobil 390 × 844; szúrópróbaszerű áttördelési ellenőrzések 320 px-en. A főoldal, szolgáltatás/ágazat/referencia/bemutatkozás/kapcsolat/bolt, kategória- és terméksablonok, szabályzatok, kosár és 404 vizsgálata.'],
['Interakció / akadálymentesség','Menünyitás/-zárás, sütik elutasítása, termék szállítási füle, ágazati kártyák Enter/kattintás viselkedése, tartalomra ugró hivatkozás aktiválása, lapozható galéria fókusza és űrlapcímkék/érvényességi metaadatok. axe-core 4.10.3 a főoldalon, a szolgáltatásoknál, a kapcsolatnál és az árazott terméknél.'],
['Teljesítmény','Böngészős Navigation/Resource Timing és pufferelt LCP/elrendezéseltolódási bejegyzések; nyers válaszfejlécek és kiválasztott erőforrások. Egy asztali minta részletes bemutatása, nem mérési eloszlás.'],
['PDF-bizonyítékok','Mentett oldalkivonatok, képernyőképek, HTTP-választörzsek, géppel olvasható feltérképezési és akadálymentességi eredmények. Az eredeti képernyőképek az itt használt jelölt másolatok mellett is megmaradnak.']],[1.2,4.8])
h('Amit szándékosan nem végeztünk el')
p('Nem küldtünk megkeresést, hírlevél-feliratkozást, bejelentkezést, fióklétrehozást, fizetést vagy rendelést. Nem módosítottunk éles tartalmat vagy beállítást. A végponttól végpontig történő levélkézbesítés, a pénztári fizetés, a CRM-átadás és a szerveroldali validáció nem ellenőrzött. A natív űrlapérvényességet élő beküldés nélkül vizsgáltuk. Nem történt behatolásteszt, teljes körű külsőhivatkozás-vizsgálat vagy hivatalos WCAG-megfelelőségi tanúsítás.')
h('Bizonyítékjelölések és figyelmeztetések')
p('Az <b>ellenőrzött</b> mentett HTTP-/DOM-/böngészős bizonyítékban megfigyelt tényt jelent. Az <b>értékelés</b> a bizonyíték szakértői értelmezése. A <b>nem ellenőrzött</b> olyan megnevezett teszt vagy tény, amelyhez további hozzáférés szükséges. A kezdeti, külső szövegkinyerő egy régi karbantartási oldalt adott vissza; az ezt követő élő böngészős vizsgálat és öt HTTP user-agent változat az élő weboldalt adta. Ez az eltérés nem bizonyít cloakingot vagy aktuális karbantartási leállást.')
p('A nyers feltérképezés összevont webhelytérkép-hely mezője oldal- és képcímeket egyaránt tartalmaz. Ez a jelentés a javított XML-számot használja: <b>25 egyedi oldal-URL</b> (26 bejegyzés) és 26 egyedi kép-URL. A képcímeket nem számítja tévesen oldalaknak.','small')
# Technical.
page('2. Technikai SEO',2,kicker='FELTÉRKÉPEZÉS, INDEXELÉS ÉS URL-VISELKEDÉS')
table(['Ellenőrzés','Ellenőrzött eredmény','Értelmezés / következő lépés'],[
['Tartalmi válaszok','29/29 feltérképezett tartalmi oldal 200-as választ adott; 29 egyedi, szokásos belső navigációs cél ellenőrzésekor nem volt HTTP-hiba.','Egészséges minta. Ez nem teljes körű garancia a külső hivatkozásokra, lekérdezéses műveletekre, erőforrásokra és minden lehetséges URL-re.'],
['robots.txt','Elérhető; tiltja az admin-/napló-/feltöltési segédútvonalakat és a kosárba helyezési lekérdezéseket; engedi az admin-ajax elérését. Megadja a sitemap_index.xml fájlt.','Nem volt megfigyelhető teljes webhelyre kiterjedő feltérképezési tiltás. Az ismétlődő User-agent csoportok önmagukban nem hibák.'],
['Webhelytérképek','Az index, valamint az oldal-/termék-/termékkategória-térképek lekérve. sitemap.xml → 301 → sitemap_index.xml. 25 egyedi oldal-URL.','F12: a noindex kosár-/fiókútvonalak és az üres munkamenetben átirányító pénztár is szerepel. A bolt két térképen is megtalálható.'],
['Indexelhetőség','Mind a 29 tartalmi oldal index/follow; a külön vizsgált kosár/fiók noindex/follow beállítású.','Az indexelhető helykitöltők és tartalomszegény kategóriák nem hasznos keresési céloldalak. A tényleges Google-indexelés nem ellenőrzött.'],
['Kanonikus URL-ek','Mind a 29 tartalmi oldalon van kanonikus címke. A tesztelt UTM-es és shop-orderby oldalak a tiszta alap-URL-t adják kanonikusnak.','Jó alap a duplikáció kezelésére. A kanonikus jelölés javaslat, nem ígéret arra, hogy a keresők elfogadják.'],
['HTTPS / hoszt','A HTTP non-www és HTTPS www egyaránt 301-gyel a HTTPS non-www változatra irányít. A HTTP www két 301-es lépést tesz.','A preferált hoszt következetes. A kétlépéses útvonal összevonása alacsony prioritású javítás (F21).'],
['Záró perjel','/szolgaltatasok → 301 → /szolgaltatasok/. A mintában vizsgált belső URL-ek záró perjelesek.','A vizsgált eset következetes; ez nem teljes körű útvonal-normalizálási teszt.'],
['404-kezelés','A szándékosan nem létező útvonal 404-et és noindex/follow értéket ad, főoldali/kapcsolati továbblépési hivatkozásokkal.','Helyes állapotkód és hasznos továbblépés, nem puha 404.'],
['Segédútvonalak','Az üres kosár egyértelmű üres állapot. A /penztar/ kosár nélkül 302-vel a /kosar/ címre irányít.','Várható viselkedés üres munkamenetben; a befejezett vásárlást nem teszteltük.']],[1.05,2.5,2.45])
p('404-teszt URL-je: https://nextro.hu/technical-audit-not-found-20260922-7e21/','tiny')
page('Technikai SEO: metaadatok és szemantika',kicker='AMIT A HTML IGAZOL')
table(['Terület','Megállapítás','Javasolt kezelés'],[
['Címek és leírások','A 29 oldalas mintában nincs hiányzó vagy pontosan ismétlődő cím. Tizenhét metaleírás hiányzik: mind a kilenc terméknél és mind a nyolc kategóriánál.','A jó főoldali és fő menüponti metaadatok megtartása. Az értékesítési kivonatok befejezése és az „Archives” eltávolítása (F10).'],
['Címsorszerkezet','23 oldalon egy H1 van; hat üres kategóriának nincs. A főoldal/kapcsolat helyenként kihagyja a H2-t; az egyenrangú ágazatok vegyes H2/H3 szintűek.','A szemantikai sablonok és az egyenrangú szintek javítása (F11). A több címsorszint nem automatikus rangsorolási büntetés.'],
['Belső hivatkozások','A navigáció egy kattintással eléri a fő gyűjtőoldalakat. Négy főoldali további információs hivatkozás visszatér a főoldalra; a látszólagos ágazati linkek span elemek.','F05: célok javítása és kontextusfüggő hivatkozások bevezetése a megfelelő szolgáltatási/ágazati szakaszokhoz.'],
['Árva oldalak / elérhetőség','A feltérképezés és a webhelytérkép együttesében nem azonosítottunk szokásos kereskedelmi árva oldalt. A segédútvonalak szerepelnek a térképen.','A nyilvános feltérképezés nem bizonyítja a hivatkozás nélküli oldalak hiányát. Később összevetendő a CMS-export, a naplók és a Search Console.'],
['Strukturált adatok','Értelmezhető JSON-LD található: WebPage/WebSite/Organization/morzsanavigáció, Product/Offer csak az árazott terméken.','Az ellenőrzött entitásadatok bővítése; az ajánlatkéréses termékekhez tilos kitalált ajánlatot megadni (F16). A bővített találatokra való jogosultságot nem teszteltük.'],
['Képek szöveges alternatívái','288 img elem a feltérképezésben; nulla hiányzó alt attribútum; 11 üres alt érték.','A szándékosan dekoratív/pixelképek üres alt értéke nem hiba. Az információhordozó képeket és az általános, ismétlődő referenciaképleírásokat kontextusukban kell felülvizsgálni.'],
['Nyelv / közösségi megosztás / ikonok','A HTML nyelve hu. OG- és Twitter-kártya-metaadatok vannak; a bolt/kapcsolat/kategóriák esetén nincs og:image. Favicon/apple ikonhivatkozások léteznek.','A megmaradt idegen nyelvű felület fordítása és célszerű előnézetek kialakítása (F20, F22). A fájlnevek önmagukban nem bizonyítanak ikonméret-hibát.'],
['JavaScript SEO','Az alapvető kínálat, hivatkozások, gyakori kérdések és termékszövegek a nyers HTML-ben is szerepelnek, nem csak a renderelt alkalmazásban.','Az érdemi hivatkozások maradjanak a elemek. A számlálók a forrásban nulláról indulnak, de a nézetbe kerülés által indított animáció után 21/18/500+ értéket értek el; nem minősítjük őket hibásnak.']],[1.1,2.5,2.4])
h('Duplikáció és paraméterkockázat')
p('A kilenc autótöltő-oldal jogosan különbözik teljesítmény és kábelkialakítás szerint, de leírásuk jelentős része közös. Érdemi választási útmutatóval és specifikációkkal kell bővíteni őket; a termékváltozatok összevonása csak keresleti és migrációs elemzés után mérlegelendő. A valóban különböző termékeket nem szabad automatikusan a boltra kanonizálni. Az orderby=price és egy UTM-kérés tiszta kanonikus URL-je pozitívum; a szűrők/keresés/lapozás tágabb körét nem teszteltük teljeskörűen.')
# performance.
page('3. Teljesítményaudit',3,kicker='MÉRÉSEK, NEM BECSÜLT PONTSZÁMOK')
callout('Egy gyorsnak tűnő laborminta nem jelent Core Web Vitals-megfelelést','A PageSpeed Insights API-kísérlet HTTP 429-et adott (kvótatúllépés). Nem áll rendelkezésre Lighthouse-pontszám, CrUX valós felhasználói eloszlás vagy Search Console Core Web Vitals-jelentés. INP-mérés nem történt. Az alábbi értékek egyetlen, mesterséges lassítás nélküli asztali böngészős megfigyelést mutatnak, nem valós felhasználói percentiliseket.')
table(['Mutató / megfigyelés','Rögzített érték','Jelentés / korlát'],[
['Asztali nézetablak','1280 × 720 CSS px','Nem alkalmaztunk hálózati/CPU-lassítást; nem szabályozott, hideg gyorsítótáras, többfutásos összehasonlító mérés.'],
['Navigációs TTFB','315.5 ms a navigáció kezdetétől','A böngésző responseStart értéke. A navigáció előkészítését is tartalmazza; nem tisztán szerverfeldolgozási mérés.'],
['LCP-jelölt','1,084 ms','A gyűjtéskor utolsó pufferelt bejegyzés, a background-pattern-2.svg elemhez kapcsolódva. Későbbi változások vagy valós látogatások eltérhetnek.'],
['Megfigyelt elrendezéseltolódások','0.002466 összeg a friss bevitelhez kötődő bejegyzések nélkül','Rövid megfigyelés; nem teljes élettartamra/munkamenetablakra kiterjedő CLS-audit és nem valós felhasználói CLS-érték.'],
['A load esemény vége','2,779.7 ms a navigáció kezdetétől','A load esemény nem jelent „teljes interaktivitást”, és nem teljes teljesítményértékelés.'],
['Dokumentumméret','286,100 dekódolt bájt; 52,597 tömörített választörzsbájt','A navigációs bejegyzés gzip tömörítést rögzít. A HTML átvitele a böngésző által jelzett többlettel együtt: 52,897 bájt.'],
['Ismert erőforrás-átvitel','5,559,340 bájt 65 rögzített erőforrás-bejegyzésből','Nem tartalmazza a beillesztett axe kódot, és csak megfigyelhető átviteli méreteket tartalmaz. A más eredetű erőforrások nullái további bájtokat rejthetnek.'],
['Ismert dokumentum + erőforrások','5,612,237 bájt (~5.61 MB, decimális)','Részleges oldalbetöltési adatmennyiség a gyűjtéskor, a teljes oldal végiggörgetése előtt, nem a teljesen végiggörgetett oldal összesített mérete.'],
['Blokkolás / szkriptek','21 megjelenítést blokkoló bejegyzés; 20 szkript által indított kérés','Körülbelül 330 kB dekódolt, látható méretű szkripttörzs. A darabszám nem bizonyít használaton kívüli kódot vagy főszálterhelést.']],[1.25,1.65,3.1])
p('Egy későbbi, 390 px-es mobilminta már felmelegített/újrahasznált böngészővel készült, ezért nem használjuk összehasonlítható sebességmérésként. A beillesztett hibafigyelő a később mintavételezett navigációknál nem rögzített megfigyelt futásidejű kivételt; a konzolüzeneteket, más eredetű hibákat és az összes interakciós hibát nem gyűjtöttük teljeskörűen.','small')
page('Teljesítmény: előbb az adatméret, aztán a pontszámok',kicker='F06 · F19')
table(['Megfigyelt erőforrás','Kódolt választörzs bájtban','Miért érdemel figyelmet'],[
['megoldas-etterem.png','2,624,647','A legnagyobb rögzített főoldali erőforrás. A PNG-formátumú étteremfotó erős jelölt formátumváltásra és átméretezésre.'],
['hero-legkezelo-gepterem.jpg','645,151','Nagy kezdeti vizuális erőforrás; optimalizálni kell, de a valódi nyitóképet nem szabad késleltetve betölteni.'],
['megoldas-ipar-2.jpg','545,178','A hajtás alatti ipari kép CSS-háttérként korán lekérődik.'],
['megoldas-nagykonyha-2.jpg','336,917','Nézetablakhoz igazodó méretek használata, a betöltés elhalasztása a szükséges pillanatig.'],
['legtechnika-szellozo-kozeli.jpg','306,614','A közösen használt illusztráció/kép takarékosabban is kiszolgálható.'],
['Helyi betűkészletfájlok','20,468 + 12,764 átvitt bájt','Két WOFF2 Latin/Latin-ext kérés volt megfigyelhető; a helyi kiszolgálás pozitívum. A font-display ellenőrzése nem történt meg.']],[2.5,1.0,2.5])
h('Ami már jól működik')
p('A mintában vizsgált CSS/JS-válaszok gzip tömörítésűek, Cache-Control max-age=10368000 értékkel. A böngésző HTTP/2-t használ. Minifikált stíluslapcsomagok jelen vannak. A termékképek általában saját méretadatokat tartalmaznak, és többnek van srcset attribútuma; a főoldali referencia-/termékképek img elemei loading=lazy beállításúak. Ezek érdemi alapok, ezért a „nincs tömörítés/gyorsítótárazás/késleltetett betöltés” diagnózis helytelen lenne.')
h('Amin változtatni kell')
b('Először a nagy étteremképet kell konvertálni és átméretezni. A jelentést hordozó CSS-hátterek helyett lehetőség szerint reszponzív képeket használjon, és a képernyőn kívüli ágazati/háttérképek betöltését halassza el. A ténylegesen megtakarított bájtokat mérni kell; nem feltételezünk megtakarítási százalékot.')
b('Erőforrások eltávolítása előtt sablononként mérni kell a CSS/JS kihasználtságát. A főoldali bolti modulok, űrlapok, hozzájárulás-kezelés és külső integrációk indokolnak bizonyos kódot, de nem feltétlenül minden közös erőforrást. A használaton kívüli CSS-t, a hosszú feladatokat és a teljes blokkolási időt nem mértük.')
b('A névtelen látogatók HTML-gyorsítótárazását körültekintően kell felmérni: a főoldal no-cache/no-store értéket ad, miközben a mintában vizsgált statikus erőforrások élettartama hosszú; egy feltételes HTML-kérés mégis 304-et adott. Ez nem tárja fel a teljes eredeti szerver-/bővítmény-gyorsítótárazási architektúrát. Kosarat, fiókot, pénztárat és személyre szabott oldalt tilos nyilvánosan gyorsítótárazni.')
b('A változtatások után ismételt, hideg gyorsítótáras mobilos/asztali laborteszteket kell futtatni, és időben gyűjteni a valós felhasználói LCP/CLS/INP értékeket. A hozzájárulási állapotokat és a szolgáltatási/terméksablonokat külön kell kezelni, hogy a főoldal javulása ne rejtse el a pénztár romlását.')
p('A teljes erőforrás-URL-ek és fejlécek az evidence/performance-desktop.json és technical.json fájlban maradtak meg. A külső MailerLite/Barion erőforrás-időzítési méretek nulla értékűek/nem átláthatók voltak; ezeket nem tekintjük valóban költségmentes átvitelnek.','small')
