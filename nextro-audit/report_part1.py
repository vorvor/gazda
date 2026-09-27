from report_engine import *
from collections import Counter
T=json.loads((ROOT/'technical.json').read_text()); A=json.loads((ROOT/'aggregates.json').read_text()); X=json.loads((ROOT/'supplement.json').read_text()); F=json.loads((ROOT/'findings.json').read_text())
# Cover.
story.append(Spacer(1,72));p('INDEPENDENT • EVIDENCE-LED • ACTIONABLE','kicker')
p('nextro.hu<br/>Full website<br/>audit','cover');story.append(Spacer(1,20))
p('SEO · Web design · UX/UI · Accessibility<br/>Performance · Content · Conversion','coverbody');story.append(Spacer(1,42))
p('Prepared for the website owner,<br/>development and marketing teams','coverbody');story.append(Spacer(1,30))
p('Inspection date: 22 September 2026<br/>Public website · Desktop and mobile review','coverbody');story.append(Spacer(1,32))
p('The core business story is credible and clearly structured.<br/>The immediate opportunity is to finish the commercial journey,<br/>repair readability and turn expertise into verifiable proof.','coverbody')
# Contents.
page('Contents')
toc=TableOfContents();toc.levelStyles=[ParagraphStyle('toc',fontName='Audit',fontSize=9.5,leading=14,spaceBefore=2,textColor=NAVY,leftIndent=0,firstLineIndent=0,rightIndent=24)];story.append(toc)
story.append(Spacer(1,18));callout('How to use this report','Read the executive summary and final priorities first. Use the finding IDs (F01–F24) to connect observations to the implementation roadmap. Screenshots are dated inspection evidence, not proposed designs. All website quotations are retained in Hungarian; analysis is in English.')
p('Priority is a business/implementation judgment. CRITICAL denotes a launch/readiness blocker; HIGH denotes a substantial access, trust or conversion obstacle; MEDIUM denotes meaningful improvement; LOW denotes polish. Effort is relative scope, not a time or cost quotation.','small')
# Executive.
page('1. Executive summary',1,kicker='BUSINESS VIEW')
p('NEXTRO presents a capable HVAC business with a coherent navy/green identity, a broad service offer, a nationwide operating message and prominently published contact details. The service and industry overviews contain useful technical explanations and FAQs. Basic crawlability, canonical URLs, HTTPS normalization and a real 404 response are in place.')
callout('Overall condition: a sound brochure foundation, an unfinished commercial journey','The site should not be treated as fully ready for conversion-focused promotion until its policy placeholders, product purchase/quotation states and obvious reading barriers are resolved. No traffic, ranking or conversion-rate data was available; this judgment is based on observed content and behavior.',RED)
h('Strongest aspects')
b('Clear service breadth: installation, controls, commissioning, maintenance, renovation and in-house filter manufacture are explained rather than merely named.')
b('Trust foundations: company registration/tax details, distinct headquarters and warehouse addresses, working phone/email links, documented-work claims and a visible enquiry route.')
b('Technically accessible content: the main offer is present in raw HTML; all 29 content pages crawled returned 200, with titles and canonical tags. Sampled navigation did not lead to an HTTP error.')
h('Most important weaknesses')
b('The privacy, terms and shipping pages are placeholders, while forms ask visitors to accept them. Product shipping tabs expose an internal “[HIÁNYZIK: …]” note (F01).')
b('The shop offers nine EV-charger variants, but only one has a visible price/add-to-cart action; seven advertised HVAC categories are empty (F02–F03).')
b('Dark text on dark hero panels, ambiguous homepage links and a 2.62 MB restaurant image create avoidable access, navigation and loading costs (F04–F06).')
b('Reference pages describe anonymous project types, not documented case studies. Commercial service detail pages are not yet developed (F07–F08).')
h('Biggest opportunity and expected impact')
p('Make NEXTRO’s specialist delivery capability easier to verify and easier to buy or enquire about. Fixing the unfinished steps should reduce uncertainty; readable interfaces and contextual quote paths should improve access; deeper service pages should improve relevance to qualified searches. These are expected directional benefits—not forecasts of ranking, revenue or percentage uplift.')
page('Executive priorities: act in this order',kicker='THE TEN MOST IMPORTANT ACTIONS')
table(['Order / priority','Action','Expected business effect'],[
['1 · CRITICAL','Replace privacy, terms and shipping placeholders; remove internal product-tab notes. F01','Restore informed decision-making and reduce immediate trust/readiness risk.'],
['2 · HIGH','Approve purchase vs quotation mode, price and availability for every product. F02','Remove ambiguity at the buying decision.'],
['3 · HIGH','Fix text contrast and unnamed/color-only links in shared templates. F04, F23','Make proof points, navigation and contact usable by more visitors.'],
['4 · HIGH','Repair homepage self-links, pseudo-links and context-losing cards. F05','Help visitors reach the information the interface promises.'],
['5 · HIGH','Replace empty shop routes with useful quote-led category pages or unpublish unfinished navigation. F03','Avoid commercial dead ends.'],
['6 · HIGH','Optimize restaurant/hero images and below-fold loading. F06','Reduce transferred data and slower-network exposure.'],
['7 · HIGH','Verify consent states, form delivery and transaction flows in an approved test environment. F15, F24','Prove that enquiries, permissions and orders are handled reliably.'],
['8 · HIGH','Publish evidence-backed case studies and validate company claims. F08','Support procurement confidence.'],
['9 · HIGH','Create substantive priority service detail pages and cross-links. F07','Improve search-intent fit and contextual enquiries.'],
['10 · MEDIUM','Finish snippets, category headings and sitemap hygiene; simplify the mobile enquiry. F09–F12, F14','Polish discoverability and reduce effort.']],[1.15,3.1,2.5])
p('Impact is qualitative. No analytics, Search Console, advertising, CRM, payment configuration, server logs or verified keyword-volume data was supplied. Establish a baseline before assigning financial targets.','small')
# scope.
page('Scope, methods and limitations',0,kicker='READ BEFORE INTERPRETING THE NUMBERS')
table(['Workstream','What was actually inspected'],[
['HTTP and SEO','29 public content pages: homepage, six primary navigation destinations, five policy/company-information pages, eight categories and nine products. Raw HTML, metadata, headings, images, links and JSON-LD were saved.'],
['Discovery and routes','robots.txt; sitemap.xml redirect; sitemap index and three child sitemaps; host/protocol variants; one slash variant; UTM and shop-sort parameters; a deliberately nonexistent URL. Cart/account and empty-session checkout were probed separately.'],
['Browser / visual','Desktop at 1280 × 720; mobile at 390 × 844; narrow reflow spot checks at 320 px. Homepage, service/sector/reference/about/contact/shop, category and product templates, policies, cart and 404 were inspected.'],
['Interaction / accessibility','Menu open/close, cookie rejection, product shipping tab, industry-card Enter/click behavior, skip-link activation, carousel focus and form labels/validity metadata. axe-core 4.10.3 on home, services, contact and the priced product.'],
['Performance','Browser Navigation/Resource Timing and buffered LCP/layout-shift entries; raw response headers and selected assets. One desktop sample is reported in detail, not a benchmark distribution.'],
['PDF evidence','Saved page extracts, screenshots, HTTP bodies, machine-readable crawl and accessibility output. Original screenshots are retained alongside the annotated copies used here.']],[1.2,4.8])
h('What was deliberately not done')
p('No enquiry, newsletter subscription, login, account creation, payment or order was submitted. No production content or settings were changed. End-to-end mail delivery, checkout payment, CRM handoff and server-side validation remain unverified. Native form validity was inspected without a live submission. No penetration test, exhaustive external-link test or formal WCAG conformance certification was performed.')
h('Evidence labels and cautions')
p('<b>Verified</b> means observed in saved HTTP/DOM/browser evidence. <b>Assessment</b> is an expert interpretation of that evidence. <b>Unverified</b> is a named test or fact still requiring access. The initial third-party text extractor returned an old maintenance page; subsequent live browser and five HTTP user-agent variants returned the live site. That discrepancy is not evidence of cloaking or a current maintenance outage.')
p('The raw crawl’s combined sitemap-location field contains page and image locations. This report uses the corrected XML count: <b>25 unique page URLs</b> (26 entries) and 26 unique image URLs. It does not misreport image locations as pages.','small')
# Technical.
page('2. Technical SEO',2,kicker='CRAWLING, INDEXING AND URL BEHAVIOR')
table(['Check','Verified result','Interpretation / next step'],[
['Content responses','29/29 crawled content pages returned 200; 29 unique ordinary internal navigation targets checked, none with HTTP errors.','Healthy sample. This is not an exhaustive guarantee covering external links, query actions, assets and every possible URL.'],
['robots.txt','Accessible; disallows admin/log/upload utility paths and add-to-cart queries; permits admin-ajax. Advertises sitemap_index.xml.','No sitewide crawl block observed. Repeated User-agent groups are not by themselves an error.'],
['Sitemaps','Index plus page/product/product-category maps fetched. sitemap.xml → 301 → sitemap_index.xml. 25 unique page URLs.','F12: cart/account noindex routes and empty-session redirecting checkout are included. Shop is duplicated across two maps.'],
['Indexability','All 29 content pages show index/follow; cart/account separately show noindex/follow.','Indexable placeholders and thin categories are not useful search destinations. Actual Google inclusion is unverified.'],
['Canonical URLs','All 29 content pages have canonical tags. Tested UTM and shop-orderby pages canonicalize to the clean base URL.','Good duplicate-control foundation. Canonical is a hint, not a promise that engines accept it.'],
['HTTPS / host','HTTP non-www and HTTPS www each 301 to HTTPS non-www. HTTP www takes two 301 hops.','Preferred host is consistent. Combine the two-hop route as a low-priority improvement (F21).'],
['Trailing slash','/szolgaltatasok → 301 → /szolgaltatasok/. Internal sampled URLs use trailing slashes.','Consistent in the tested case; not an exhaustive route normalization test.'],
['404 handling','The deliberately nonexistent path returns 404 and noindex/follow, with home/contact recovery links.','Correct status and useful recovery, not a soft 404.'],
['Utility routes','Empty cart is a clear empty state. /penztar/ redirects 302 to /kosar/ without a cart.','Expected empty-session behavior; a completed purchase was not tested.']],[1.05,2.5,2.45])
p('404 test URL: https://nextro.hu/technical-audit-not-found-20260922-7e21/','tiny')
page('Technical SEO: metadata and semantics',kicker='WHAT THE HTML CONFIRMS')
table(['Area','Finding','Recommended treatment'],[
['Titles and descriptions','No missing or exact-duplicate titles in the 29-page sample. Seventeen missing meta descriptions: all nine products and all eight categories.','Keep good main-page metadata. Finish commercial snippets and remove “Archives” (F10).'],
['Heading structure','23 pages have one H1; six empty categories have none. Homepage/contact skip H2 in places; sector peers use mixed H2/H3.','Repair semantic templates and peer levels (F11). Multiple levels are not an automatic ranking penalty.'],
['Internal linking','Navigation reaches the major hubs in one click. Four homepage learn-more links return home; apparent industry links are spans.','F05: repair destinations and introduce contextual links to matching service/sector sections.'],
['Orphans / reachability','No ordinary commercial orphan identified within the union of this crawl and the sitemap. Utility routes appear in the map.','A public crawl cannot prove absence of unlinked pages. Reconcile CMS export, logs and Search Console later.'],
['Structured data','Parseable JSON-LD present: WebPage/WebSite/Organization/breadcrumbs, with Product/Offer on only the priced product.','Enrich verified entity data; quote-only products must not receive invented offers (F16). Rich-result eligibility not tested.'],
['Image alternatives','288 img elements across the crawl; zero missing alt attributes; 11 empty alt values.','Do not treat intentional decorative/pixel empty alt as a defect. Review informative imagery and generic repeated reference-image descriptions contextually.'],
['Language / social / icons','HTML language is hu. OG and Twitter-card metadata are present; shop/contact/categories have no og:image. Favicon/apple icon links exist.','Translate residual UI and complete purposeful previews (F20, F22). File names alone do not prove icon dimension errors.'],
['JavaScript SEO','Core offer, links, FAQs and product text are in raw HTML, not only in a rendered app.','Keep meaningful links as anchors. Counters start at zero in source but reached 21/18/500+ after viewport-triggered animation; they are not reported as broken.']],[1.1,2.5,2.4])
h('Duplication and parameter risk')
p('The nine EV-charger pages legitimately differ by power and cable configuration but share substantial descriptive copy. Add meaningful selection guidance and specifications; consider variants only after search-demand and migration analysis. Do not blindly canonicalize real distinct products to the shop. The clean canonical on orderby=price and a UTM request is positive; broader filters/search/pagination were not exhaustively tested.')
# performance.
page('3. Performance audit',3,kicker='MEASUREMENTS, NOT ESTIMATED SCORES')
callout('A fast-looking lab sample is not a Core Web Vitals pass','The PageSpeed Insights API attempt returned HTTP 429 (quota exceeded). No Lighthouse score, CrUX field distribution or Search Console Core Web Vitals report was obtained. INP was not measured. The figures below are one unthrottled desktop browser observation, not real-user percentiles.')
table(['Metric / observation','Recorded value','Meaning / limitation'],[
['Desktop viewport','1280 × 720 CSS px','No network/CPU throttling applied; not a controlled cold-cache multi-run benchmark.'],
['Navigation TTFB','315.5 ms from navigation start','Browser responseStart. Includes navigation setup; not a pure server-processing measurement.'],
['LCP candidate','1,084 ms','Last buffered entry at collection, associated with background-pattern-2.svg. Later changes or field visits may differ.'],
['Observed layout shifts','0.002466 sum excluding recent-input entries','Short observation; not a lifetime/session-window CLS audit or a field CLS value.'],
['Load event end','2,779.7 ms from navigation start','Load event is not “fully interactive” or a complete performance verdict.'],
['Document size','286,100 decoded bytes; 52,597 compressed body bytes','Navigation entry records gzip. HTML transfer including browser-reported overhead: 52,897 bytes.'],
['Known resource transfer','5,559,340 bytes across 65 recorded resource entries','Excludes injected axe and includes only observable transfer sizes. Cross-origin zeros may hide additional bytes.'],
['Known document + resources','5,612,237 bytes (~5.61 MB, decimal)','A partial page-load payload at collection, before full-page scrolling, not an exhaustive fully scrolled page weight.'],
['Blocking / scripts','21 render-blocking entries; 20 script-initiated requests','About 330 kB decoded visible-size script bodies. Counts do not establish unused code or main-thread cost.']],[1.25,1.65,3.1])
p('A later 390 px mobile sample was warm/reused-browser and therefore is not used as a comparable speed benchmark. No observed runtime exceptions were recorded by the injected error listener on the sampled later navigations; console messages, cross-origin errors and all interaction failures were not exhaustively captured.','small')
page('Performance: fix bytes before chasing scores',kicker='F06 · F19')
table(['Observed resource','Encoded body bytes','Why it deserves attention'],[
['megoldas-etterem.png','2,624,647','Largest recorded homepage resource. A photographic restaurant image in PNG is a strong format/resize candidate.'],
['hero-legkezelo-gepterem.jpg','645,151','Large initial visual asset; optimize without lazy-loading the real hero.'],
['megoldas-ipar-2.jpg','545,178','Below-fold industry imagery is requested early as CSS background content.'],
['megoldas-nagykonyha-2.jpg','336,917','Use viewport-appropriate dimensions and defer until needed.'],
['legtechnika-szellozo-kozeli.jpg','306,614','Shared illustration/image can be served more economically.'],
['Local font files','20,468 + 12,764 transfer bytes','Two WOFF2 Latin/Latin-ext requests observed; local delivery is a positive. Font-display was not validated.']],[2.5,1.0,2.5])
h('What is already working')
p('Sampled CSS/JS responses are gzipped and have Cache-Control max-age=10368000. The browser uses HTTP/2. Minified stylesheet bundles are present. Product images generally carry intrinsic dimensions and several have srcset; homepage reference/product img elements use loading=lazy. These are meaningful foundations, so a “no compression/no caching/no lazy loading” diagnosis would be incorrect.')
h('What should change')
b('Convert and resize the large restaurant image first. Replace meaningful CSS backgrounds with responsive images where practical, and gate offscreen industry/background loading. Measure actual saved bytes; no savings percentage is assumed.')
b('Profile per-template CSS/JS coverage before removing assets. Homepage shop widgets, forms, consent and third-party integrations justify some code, but not necessarily every shared asset. Unused CSS, long tasks and total blocking time were not measured.')
b('Assess anonymous HTML caching carefully: the homepage returns no-cache/no-store while sampled static assets have long lifetimes; a conditional HTML request still returned 304. This does not reveal the entire origin/plugin cache architecture. Do not cache carts, accounts, checkout or personalized pages publicly.')
b('After changes run repeated cold mobile/desktop lab tests and collect real-user LCP/CLS/INP over time. Separate consent states and service/product templates so a homepage improvement does not conceal a checkout regression.')
p('Complete resource URLs and headers are retained in evidence/performance-desktop.json and technical.json. Third-party MailerLite/Barion resource timing sizes were zero/opaque; those entries are not counted as genuinely zero-cost transfers.','small')
