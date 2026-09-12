"""Dark homepage composition based on the user's design.jpeg reference."""
from html import escape as E


def render(photo, routes, source, learn, learn_body, anniversary):
    hero = photo('cenzurazatlan/foto 004.JPG')
    emblem = photo('egyesulet/lhse korb.jpg')
    cards = [
        ('EGYESÜLET', 'Közhasznú kulturális és sportegyesületként működünk.', 'egyesulet.html', 'cenzurazatlan/foto 006.JPG'),
        ('INTENZÍV LOVASKÉPZÉS', 'A képzéseken való részvétel 8 éves kortól javasolt.', 'lovaskepzes.html', 'INTENZIV LOVASKEPZES/ilk3.JPG'),
        ('ÍRÁSOK', 'Az íjászat hagyományos módszere', 'irasok.html', 'konyv/1 hmhes eszkozei/p1 antiqe.jpg'),
        ('LOVAK', 'Ló és eszközállományunkat ennek megfelelően válogattuk.', routes[source('konyv/lokzs.html')], 'konyv/9 lo/f2555 b.jpg'),
    ]
    body = f'''<section class="hero reference-hero">
      <div class="hero-image"><img src="{hero}" alt="LOVASHARC.HU" width="1800" height="1200" fetchpriority="high"></div>
      <div class="container reference-hero-inner"><div class="hero-copy">
        <span class="eyebrow">LOVASHARC.HU</span>
        <h1>Lovasíjász<br><em>Hagyományőrző</em><br>Sportegyesület</h1>
        <p>Közhasznú kulturális és sportegyesületként működünk.</p>
        <div class="actions"><a class="button" href="egyesulet.html">EGYESÜLET <span aria-hidden="true">→</span></a><a class="button button-outline" href="lovaskepzes.html">INTENZÍV LOVASKÉPZÉS</a></div>
      </div><img class="hero-emblem" src="{emblem}" alt="" width="130" height="130"></div>
    </section>
    <section class="reference-intro container">
      <div class="section-ornament" aria-hidden="true"><span>◆</span></div>
      <h2>{E(learn)}</h2><p>{E(learn_body.split('Az egyesület képzési anyagának')[0].strip())}</p>
    </section>
    <section class="container reference-cards" aria-label="TARTALOM">'''
    for title, text, href, suffix in cards:
        body += f'''<article class="reference-card"><a href="{href}"><img src="{photo(suffix)}" alt="{E(title)}" loading="lazy" width="600" height="420"><div class="reference-card-copy"><h3>{E(title)}</h3><p>{E(text)}</p><span class="card-link" aria-hidden="true">→</span></div></a></article>'''
    body += f'''</section>
    <section class="reference-jubilee"><div class="container jubilee-inner"><div class="jubilee-year" aria-hidden="true">25</div><div class="jubilee-copy"><h2>{E(anniversary)}</h2><a class="text-link" href="jubileum.html">LOVASHARC.HU <span aria-hidden="true">→</span></a></div><img src="{photo('top (28)b.jpg')}" alt="LOVASHARC.HU" loading="lazy" width="1056" height="737"></div></section>
    <section class="container reference-features">
      <article class="reference-person"><div class="feature-heading"><h2>KELEMEN ZSOLT</h2></div><div class="person-content"><img src="{photo('konyv/1 hmhes eszkozei/k.zs..jpg')}" alt="KELEMEN ZSOLT" loading="lazy" width="407" height="677"><div><p>alapító, elnök, szakmai irányító, szervező és oktató</p><a class="text-link" href="kelemen-zsolt.html">KELEMEN ZSOLT <span aria-hidden="true">→</span></a></div></div></article>
      <article class="reference-writings"><div class="feature-heading"><h2>ÍRÁSOK</h2></div><a class="writing-feature" href="{routes[source('konyv/hunmagyarharcmuveszet.html')]}"><img src="{photo('konyv/cimkzs k.jpg')}" alt="HUN-MAGYAR HARCMŰVÉSZET" loading="lazy" width="506" height="701"><span><strong>HUN-MAGYAR HARCMŰVÉSZET</strong><span>Az íjászat hagyományos módszere</span></span></a><a class="text-link" href="irasok.html">ÍRÁSOK <span aria-hidden="true">→</span></a></article>
      <article class="reference-gallery"><div class="feature-heading"><h2>FOTÓGALÉRIA</h2></div><div class="reference-thumbnails">'''
    for suffix in ['INTENZIV LOVASKEPZES/ilk1.JPG', 'cenzurazatlan/foto 005.JPG', 'cenzurazatlan/foto 004.JPG', 'INTENZIV LOVASKEPZES/ilk05.JPG']:
        body += f'<a href="fotogaleria.html"><img src="{photo(suffix)}" alt="FOTÓGALÉRIA" width="300" height="220" loading="lazy"></a>'
    body += '''</div><a class="text-link" href="fotogaleria.html">FOTÓGALÉRIA <span aria-hidden="true">→</span></a></article></section>'''
    return body
