from report_engine import *
from collections import Counter
F=json.loads((ROOT/'findings.json').read_text())
T=json.loads((ROOT/'technical.json').read_text())
counts=Counter(x['priority'] for x in F)
page('15. Prioritized implementation roadmap',15,kicker='BUSINESS OWNER + DEVELOPMENT + MARKETING')
p('The roadmap contains '+str(len(F))+' distinct recommendations: '+', '.join(str(counts[x])+' '+x for x in ['CRITICAL','HIGH','MEDIUM','LOW'])+'. IDs remain stable throughout the report. Expected impact is qualitative; effort describes relative implementation scope and dependencies, not a quoted duration.')
table(['Phase','Actions and dependencies','Release evidence'],[
['1 · Restore readiness','F01 policies/shipping; F02 product-state/price approval; F04/F23 access fixes; F05 link repair; F15 consent review.','Approved content; usable CTAs; no editorial placeholders; contrast/name tests pass; privacy behavior documented.'],
['2 · Remove friction','F03 category strategy; F06 images; F09/F14 forms/mobile; F10–F12 snippets/headings/sitemap; F20 language.','No advertised dead ends; measured payload reduction; mobile/form QA; clean indexing signals.'],
['3 · Build authority','F07 detailed commercial services; F08 case studies; F13 product selection; F16/F18 business/schema/local facts; F17 homepage simplification.','Approved expert content and evidence; contextual internal links and quotation handoff; consistent business data.'],
['4 · Optimize and prove','F19 measured asset work; F21–F22 polish; F24 end-to-end testing, baseline and experiments.','Repeatable lab/functional results and consent-aware outcome measurement. F24 testing must also gate initial transactional promotion.']],[1.15,3.0,1.85])
h('Quick Wins')
b('<b>Repair navigation:</b> replace four homepage self-links, convert pseudo-links into real destinations and add stable service/sector anchors (F05).')
b('<b>Correct shared tokens:</b> fix dark checklist text, button/link contrast and anonymous overlay links rather than patching every page separately (F04, F23).')
b('<b>Finish obvious template text:</b> replace Contact us now / Archives / Price: and normalize tenure to 2005 óta (F10, F20).')
b('<b>Clean SEO signals:</b> populate metadata from approved product facts, restore category H1s and remove noindex utility routes from the sitemap (F10–F12).')
b('<b>Improve mobile inputs:</b> enlarge menu targets and add correct input types/autocomplete without rebuilding the whole form (F09, F14).')
p('Immediate containment of F01 is essential, but the full policy/fulfilment solution is Moderate effort because it needs business and legal approval. Deleting a placeholder is not equivalent to publishing an adequate policy.','small')
# Action catalogue, all mandatory fields explicitly included.
order={'CRITICAL':0,'HIGH':1,'MEDIUM':2,'LOW':3}
ordered=sorted(F,key=lambda x:(order[x['priority']],int(x['id'][1:])))
for start in range(0,len(ordered),3):
 batch=ordered[start:start+3]
 page('Roadmap: '+' / '.join(f['id'] for f in batch),kicker='IMPLEMENTATION CARDS · PRIORITY ORDER')
 for f in batch:
  sub(f['id']+' · '+esc(f['issue']))
  col={'CRITICAL':'#a52b39','HIGH':'#a4501f','MEDIUM':'#1a6076','LOW':'#52616c'}[f['priority']]
  p('<font color="'+col+'"><b>'+f['priority']+'</b></font>  |  <b>Effort:</b> '+f['effort']+'  |  <b>Expected impact:</b> '+f['impact']+'  |  '+esc(f['category']),'small')
  p('<b>Affected URLs:</b> '+esc(' • '.join(f['urls'])),'tiny')
  p('<b>Why it matters:</b> '+esc(f['why']),'small')
  p('<b>Recommended solution:</b> '+esc(f['solution']),'small')
  p('<b>Owner:</b> '+esc(f['owner'])+'<br/><b>Done when:</b> '+esc(f['acceptance']),'small')
  story.append(Spacer(1,9))
page('Strategic Improvements',kicker='BUILD USEFUL DEPTH, NOT MORE TEMPLATE PAGES')
table(['Initiative','Scope and dependencies','How to judge success'],[
['Service-intent architecture','Expand maintenance, renovation/rotor replacement and filter manufacture first. Interview engineers, assemble scope/fit/process content and connect real cases. F07.','Prospects can answer suitability and scope questions on one page; contextual enquiries preserve service intent. Assess organic/lead outcomes only after a baseline.'],
['Evidence-led reference library','Create at least three approved project stories across representative sectors. Use dates, actual photos, named or transparently confidential clients and documented outcomes. F08.','Sales can send a relevant case to a real prospect; every numerical result has evidence and permission.'],
['Coherent product/quotation system','Choose the commercial model of each range, align stock/price/fulfilment data, complete policy content and make product selection easier. F01–F03, F13, F16.','Every advertised range has a purchase or quote route and known commercial terms; authorized checkout/enquiry tests succeed.'],
['Reusable accessible components','Set a light/dark token system, headings, card link semantics, product media, form states, focus and motion rules. F04, F09, F11, F14, F23.','Shared-template fixes pass automated and human tests at multiple widths without regressions.'],
['Measured optimization program','Image/loading work followed by coverage-driven CSS/JS changes, documented consent and reliable outcome events. F06, F15, F19, F24.','Repeated cold lab traces and real-user monitoring improve; privacy and functional tests remain valid.']],[1.45,2.65,1.9])
h('Suggested governance')
p('Assign a business approver to prices, warranties, fulfilment, company claims and response expectations; a technical owner to templates, performance and schema; and a content owner to service/case pages. Maintain one finding backlog with evidence, owner, acceptance test and release status. A single person should verify that the policy, product and form journeys tell the same story.')
p('Do not start with a cosmetic sitewide rebuild. Resolve readiness/access blockers first, then prototype one commercial service and one product journey. Reuse proven components and preserve existing useful URLs and content.','small')
# Final.
page('16. Final recommendations',16,kicker='RECOMMENDED ORDER OF IMPLEMENTATION')
p('NEXTRO’s service story and visual identity provide a workable foundation. The site’s largest weaknesses are concentrated in unfinished commercial details, inaccessible contrast, ambiguous navigation and insufficient proof—not a total absence of content or technical SEO. Address those weaknesses before spending on more traffic.')
table(['Top 10','Action'],[
['1','Publish approved privacy, terms and shipping information; eliminate public internal notes.'],
['2','Give every product a validated purchase or contextual quotation state.'],
['3','Fix text contrast, unnamed links and color-only inline links in shared templates.'],
['4','Repair homepage self-links/pseudo-links and preserve selected service/sector context.'],
['5','Replace empty store categories with useful quote-led ranges or remove unfinished promotion.'],
['6','Reduce the large restaurant/hero/background image payload and verify mobile loading.'],
['7','Complete consent and authorized end-to-end enquiry/checkout testing before transactional promotion.'],
['8','Publish verifiable case studies, expert/company evidence and current claim sources.'],
['9','Build original priority service detail pages and link them to proof and enquiry.'],
['10','Finish commercial metadata, category headings, sitemap hygiene and mobile form ergonomics.']],[.65,5.35])
h('Priorities by discipline')
p('<b>SEO:</b> useful commercial depth and evidence first; then product/category snippets, headings, sitemap and accurate entity data.<br/><b>UX/UI:</b> readable dark surfaces, real destination links, shorter repeated homepage narrative, comfortable mobile call/menu access.<br/><b>Technical:</b> image bytes and conditional loading, carefully tested template assets, explicit indexing signals and reliable form/checkout behavior.<br/><b>Content:</b> complete policies, approve claims, replace anonymous references with evidence and remove template English.<br/><b>Conversion:</b> honest product availability/pricing, contextual quote paths, minimal necessary form effort and operationally verified handoff.')
callout('Success criteria','A new visitor should understand the relevant offer, verify credible evidence, know the applicable terms and complete a clear enquiry or purchase journey. Verify that outcome with real tests and approved analytics; do not equate a better-looking homepage or a higher lab score with proven business success.')
# Appendix.
page('Appendix A · Technical inventory',17,kicker='29 CRAWLED CONTENT PAGES')
p('All rows below returned HTTP 200 and had a title, canonical tag and index/follow robots metadata in the raw crawl. “Meta” indicates presence of a description, not quality. Empty utility routes and the 404 probe are listed separately in the technical section. URLs below are relative to https://nextro.hu/.','small')
rows=[]
for page_rec in T['pages']:
 if '/termek/' in page_rec['url']:continue
 path=page_rec['url'].replace('https://nextro.hu','')
 rows.append([esc(path),str(sum(hh['level']=='h1' for hh in page_rec['headings'])),'Yes' if page_rec['meta'].get('description') else 'Missing'])
table(['Path','H1s','Meta'],rows,[4.7,.5,.8])
page('Technical inventory: products and utilities',kicker='CONTINUATION OF THE 29-PAGE CONTENT CRAWL')
p('All nine products below returned 200 with one H1, a title and a self-referencing canonical. All nine lack a meta description. The eight unpriced variants do not contain Product JSON-LD; the priced 22 kW / 7.5 m variant does. Paths are relative to https://nextro.hu/.','small')
rows=[]
for page_rec in T['pages']:
 if '/termek/' not in page_rec['url']:continue
 path=page_rec['url'].replace('https://nextro.hu','')
 rows.append([esc(path),'99 999 Ft' if '22-kw-75-' in path else 'Not shown'])
table(['Product path','Visible price'],rows,[4.9,1.1])
h('Separately tested utility routes')
table(['Route','Response / index directive'],[['/kosar/','200; noindex, follow'],['/fiokom/','200; noindex, follow; no login attempted'],['/penztar/','302 to /kosar/ in the empty-session request']],[1.2,4.8])
p('These utility probes are not included in the 29 content-page crawl total. No customer data, authentication or live order was submitted.','small')
page('Appendix B · Evidence and validation register',18,kicker='TRACEABILITY AND UNKNOWNS')
table(['Evidence file / source','What it supports'],[
['technical.json + http-evidence/','Raw crawl, metadata, HTTP status/redirects, canonical/robots tags, headings, image attributes, structured data, robots and sitemap responses, user-agent variations, caching samples.'],
['aggregates.json','Corrected namespace-aware sitemap counts: 25 unique page URLs, 26 page entries and 26 unique image locations. Resource aggregates exclude injected axe.'],
['supplement.json','Utility/noindex/checkout behavior, sitemap.xml redirect, orderby canonical, price states, Product/Offer data, basic Organization graph and favicon/OG checks.'],
['home-browser.json; services.json; megoldasok.json; referenciak.json; rolunk.json','Rendered text, heading outlines and main-page link destinations. Homepage pseudo-link behavior was also manually exercised.'],
['kapcsolat.json; product-priced.json; product-unpriced.json; category/shop JSON','Forms/labels and rendered product/category commercial content; tab content confirmed with a browser click.'],
['axe-home/services/contact/product.json','Saved axe-core 4.10.3 node-level results. Home/services are desktop; contact/product mobile. A transient blank-tab test was discarded and the product file was replaced with a valid live-page scan.'],
['performance-desktop.json; performance-mobile.json','Navigation/Resource Timing and buffered LCP/layout shifts. Desktop is the reported one-off observation; mobile was warm/reused and not a comparable benchmark.'],
['pagespeed-api.json','The HTTP 429/quota response. No PSI score or CrUX field result was available from this attempt.'],
['mobile-layouts.json; desktop-interactions.json; screenshots','Viewport sizes/overflow, fonts, visible errors, resolved counters and tested focus behavior; original and annotated screenshot evidence.']],[2.55,3.45])
h('Unverified items requiring follow-up')
p('Actual Google indexing, rankings, search demand, backlinks, analytics/conversion rates, field Core Web Vitals, INP, full-page lifetime CLS, unused CSS/JS coverage, long tasks, backend cache architecture, authenticated areas, payment completion, live email delivery, newsletter confirmation, registry/rating accuracy, full third-party consent payload/storage behavior and formal WCAG conformance. No metric in these areas is invented.')
p('Audit files: /workspace/nextro-audit/ · Final PDF: /workspace/nextro.hu_full_website_audit.pdf. The report reflects the public site observed on 22 September 2026; later changes may supersede a finding. Raw server Date headers are retained as received; the inspection date is the local audit record.','small')
