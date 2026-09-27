from report_engine import *
# On-page.
page('4. On-page SEO',4,kicker='SEARCH INTENT AND PAGE-LEVEL CLARITY')
p('The core pages are written in Hungarian and have a clear HVAC theme. The largest SEO opportunity is not inserting more repeated keywords; it is matching a specific need to a substantive destination, then connecting that destination to credible evidence and a useful next step. No search volumes, rankings or competitor positions were measured.')
table(['Page / likely intent','Observed strength','Gap and recommended direction'],[
['Home / contractor discovery','Title explicitly says Légtechnika egy kézből; introduction describes industrial/commercial services.','The H1 is the slogan FRISS LEVEGŐ. TISZTA MEGOLDÁS. Retain it as a brand line but give the main heading explicit service meaning (F17).'],
['Services / evaluate provider','Ten service sections, a concrete maintenance FAQ and descriptions of commissioning/renovation.','Short summaries do not fully satisfy individual high-intent queries. Build priority detail pages and link specific cards/FAQs to them (F07).'],
['Solutions / sector fit','Hotel, restaurant, kitchen, wellness, industry and residential needs are differentiated.','The process comes before sector answers and section levels differ. Add a sector index, aligned H2s and relevant project proof.'],
['References / reduce risk','Work types and delivery scope are explained, with experience/team/filter-production claims.','No identifiable case evidence or quantified documented outcomes. Convert examples into substantiated case studies (F08).'],
['About / company validation','Foundation year, specialist team and named rating provider are stated.','Inconsistent tenure language and no verification link in main content. Add people, evidence and precise dates (F08, F20).'],
['EV category / choose charger','Power, phases, cable lengths, IP56, warranty and VAT/installation exclusions are mentioned.','No meta description; title ends Archives; products are difficult to compare and mostly unpriced (F02, F10, F13).'],
['Product / buy or request quote','Model/variant names and useful technical features are explicit.','Dense shared copy, missing snippet descriptions and inconsistent commercial states reduce decision clarity.'],
['Contact / act now','Phone, email, addresses and enquiry types are present.','Use a specific next-step explanation and contextual form defaults rather than promising an unverified response time (F09).']],[1.4,2.25,2.35])
h('Keyword use, depth and E-E-A-T')
p('Technical language such as EC-motor, forgódob-csere and szűrő nyomásesése supports topic relevance. Add plain-language explanations, maintenance deliverables, downtime planning and suitability questions rather than repetitive keyword strings. Named responsible experts, original project media, documented claims and complete policies would provide stronger experience/trust signals than additional generic assurances.')
page('On-page SEO: proposed Hungarian copy',kicker='DRAFTS TO VALIDATE WITH THE BUSINESS')
p('These are editorial proposals, not measured keyword opportunities or guaranteed search snippets. Google may rewrite snippets. Use approved service and availability claims; do not promise price, stock or turnaround until confirmed.')
table(['URL / field','Suggested replacement'],[
['/ · title','Légtechnika: telepítés, felújítás, karbantartás | NEXTRO'],
['/ · H1','Légtechnikai rendszerek telepítése és karbantartása egy kézből'],
['/ · description','Légkezelők telepítése, automatika, felújítás és karbantartás saját szakembergárdával. NEXTRO: 2005 óta, Budapesten és országosan. Kérjen ajánlatot.'],
['/szolgaltatasok/ · H1','Légtechnikai szolgáltatások a tervezéstől a karbantartásig'],
['/szolgaltatasok/ · description','Légkezelő telepítés, beüzemelés, karbantartás, forgódob-csere és egyedi szűrőgyártás. Ismerje meg a NEXTRO szolgáltatásait, és kérjen ajánlatot.'],
['/megoldasok/ · title','Légtechnika hoteleknek, iparnak és otthonoknak | NEXTRO'],
['/bolt/ · title','Teltonika autótöltők és légtechnikai ajánlatkérés | NEXTRO'],
['/termekkategoria/autotoltok/ · title','Teltonika EVC2 autótöltők: 7,4–22 kW | NEXTRO'],
['/termekkategoria/autotoltok/ · description','Hasonlítsa össze a Teltonika EVC2 autótöltők teljesítményét és kábelváltozatait. 7,4, 11 és 22 kW, 36 hónap garancia. Kérjen segítséget a választáshoz.'],
['11 kW / 5 m product · title','Teltonika EVC2 11 kW autótöltő, 5 m kábel | NEXTRO'],
['11 kW / 5 m product · description','Háromfázisú Teltonika EVC2 11 kW autótöltő 5 m beépített kábellel, app- és NFC-vezérléssel. Kérjen ajánlatot a készülékre és a telepítésre.'],
['/rolunk/ · H1','NEXTRO Kft. – légtechnika saját szakembergárdával, 2005 óta']],[1.8,4.2])
h('A useful service-page outline')
p('<b>H1:</b> Légkezelő karbantartás • <b>H2:</b> Mikor és kinek ajánlott? • <b>H2:</b> Mit tartalmaz a karbantartás? • <b>H2:</b> Felmérés, ütemezés és dokumentáció • <b>H2:</b> Kapcsolódó referenciák • <b>H2:</b> Gyakori kérdések • <b>H2:</b> Karbantartási ajánlat kérése. Link back to the hub, relevant filter supply and an actual reference. Do not create thin pages for every synonym.')
p('Product example URL: https://nextro.hu/termek/teltonika-evc2-okos-ac-autotolto-11-kw-5-m-beepitett-toltokabellel/','tiny')
# IA.
page('5. Information architecture',5,kicker='MAKE THE NEXT CLICK SPECIFIC')
h('Current structure and reachability')
p('The top-level menu is understandable: Szolgáltatások, Megoldások, Referenciák, Rólunk, Webshop, Kapcsolat. Each hub is one navigation click away from the homepage. Shop categories are exposed from navigation and the shop; a product can be reached directly from the homepage carousel/grid or via shop → product. Contact is consistently available. These are good foundations, not a case for a completely new navigation system.')
p('The weakness is depth: ten service cards all lead to the same overview, while six industry cards expose non-link “Megoldások →” text. Four benefit CTAs return to the current homepage. Important commercial information can be close in clicks but still difficult to reach semantically because the destination does not preserve the visitor’s choice (F05, F07).')
table(['Proposed grouping','Contents / behavior'],[
['Services hub','Installation; automation; commissioning; maintenance; renovation/rotor replacement; removal/replacement; filter manufacture. Start with detailed pages for the strongest, evidence-backed offers.'],
['Sector solutions','Hotels, hospitality/kitchens, wellness, industrial and residential. Add a sector index immediately; use anchors until each sector can justify a substantive page.'],
['References','A filterable or clearly grouped case-study list linked to the relevant service and sector, with real project evidence.'],
['Products and enquiries','Clearly separate buy-online EV chargers from quote-led HVAC/filter ranges. A quotation range should collect specifications, not show a zero-product store state.'],
['About and contact','Company/team/qualifications and verification links; a direct quote path, phone and address-role information. Keep policies consistently in the footer.']],[1.5,4.5])
h('Concrete linking changes')
b('Homepage “KARBANTARTÁS” → services maintenance anchor now; a dedicated maintenance page later. Carry that service selection into the contact form.')
b('Homepage Hotel card → /megoldasok/#hotel or a substantive hotel detail page. Use an actual anchor with “Szállodai légtechnikai megoldások” as the accessible purpose.')
b('Filter manufacture section → useful /termekkategoria/szurok/ quote-led page with dimensions/type/quantity guidance, not an empty product list.')
b('Each approved case study → the service used and a “Hasonló feladatra kérek ajánlatot” enquiry carrying case context.')
p('Do not change established URLs merely to improve their appearance. Existing Hungarian slugs are readable. If consolidating product variants or introducing new routes, keep a mapping and deliberate redirects/canonicals. Public discovery cannot exclude unknown orphan pages outside the sampled link/sitemap graph.','small')
# UX.
page('6. UX audit: first-time visitor journeys',6,kicker='WHERE PEOPLE MAY HESITATE')
table(['Journey step','What happens on Nextro','Likely hesitation / improvement'],[
['1 · Arrive from search','The page is a real Hungarian HVAC business, with logo, core navigation and a quote CTA. Cookie controls may cover substantial mobile space.','A slogan alone is less explicit than a service promise. Explain offer/audience above the fold and keep consent concise and accessible.'],
['2 · Choose a relevant service','Services hub has ten useful summaries; homepage cards all use the same destination.','The user must search again after clicking. Land at the matching section and develop true detail pages.'],
['3 · Assess fit and trust','Services/solutions FAQs explain work; references describe generic industrial/hotel/kitchen jobs.','Procurement users cannot verify a comparable completed project. Add approved cases, dates, scope and evidence.'],
['4 · Contact the company','Quote CTA reaches /kapcsolat/; phone/email and a labelled form are available.','Form burden and incomplete policies interrupt the last step. Simplify required fields and complete disclosures before promoting the funnel.'],
['5 · Buy a charger','Shop lists nine variants; one has a price/add-to-cart control; eight show read-more.','Unclear stock/quote mode, uncertain delivery cost and internal shipping notes weaken purchase confidence. Fix readiness before advertising.'],
['6 · Urgent mobile repair','Mobile navigation works, but the desktop phone strip disappears.','A call-oriented visitor needs immediate contact, not a long scroll or an exploratory shop journey. Add a comfortable call action.']],[1.3,2.35,2.35])
h('Interaction patterns: positives and friction')
p('Working skip navigation, labelled forms, large primary hero buttons and recognizable top-level naming support usability. Industry cards do respond to Enter by flipping, so they are not keyboard-inert; the problem is the link-like “Megoldások →” text that does not navigate. Carousel controls show visible focus. No universal “keyboard navigation is broken” claim is justified.')
p('Hover/reveal cards, an animated service ticker, a reference carousel and shop hover controls add interaction variety. Reduce hidden information and motion where it does not support a task. Ensure touch users can see the same information, and verify reduced-motion behavior in a separate test; it was not formally measured here.')
h('Recommended journey test after fixes')
p('Ask unfamiliar users to find maintenance scope, verify a relevant project, request a filter quotation and compare 11 kW versus 22 kW chargers. Observe wrong turns and unclear language before running an A/B test. No user-testing sessions or task-completion rates were collected in this audit.')
page('UX: enquiry form and feedback',kicker='F01 · F09 · F24')
image('mobile-form.png','Contact form at 390 px. Labels are visible and associated; several required fields and a long message/terms sequence increase the effort before sending. Screenshot records the inspected layout, not a form submission.',maxheight=270)
table(['Observed detail','Meaning and concrete change'],[
['Correctly associated labels','Keep them. Do not replace them with placeholder-only inputs. First/last name, email, phone, company, subject, type and message are individually identified.'],
['Both email and phone marked required','The plugin exposes aria-required=true; HTML native required is not used for most fields. This is not proof that server/plugin validation is absent. Make phone optional unless callback is selected.'],
['Phone input is type=text','No inputmode/autocomplete was present. Use type=tel and autocomplete=tel; add name/email/organization autocomplete tokens for faster completion.'],
['Blank native validity check','The unchecked policy checkbox made native checkValidity() false. Plugin error presentation and backend validation were not tested with a live submission.'],
['Policy acceptance and vague response timing','Visitors are asked to accept unfinished documents. Complete F01 first. Explain next steps and publish only a response expectation the team can meet.'],
['Technical message prompt','Offer examples: town/site, service needed, equipment/model, urgency and preferred contact. Add optional attachments only if handling, storage and privacy are ready.']],[1.65,4.35])
p('A safe authorized QA plan should cover empty/invalid values, focus moving to errors, aria-describedby/announcements, network failures, duplicate-send prevention, success confirmation and actual lead delivery. No customer request or test message was sent from the public site.','small')
# UI.
page('7. UI audit',7,kicker='READABILITY AND CONSISTENCY BEFORE COSMETIC REDESIGN')
annotate('services-desktop.png','services-annotated.png',[((680,340,1178,465),'1 · LOW-CONTRAST PROOF')])
image('services-annotated.png','E1 · https://nextro.hu/szolgaltatasok/ · Desktop 1280 × 720. The checklist inherits dark body text on a navy panel. Automated contrast measurement for these lines is 1.82:1. The outlined area identifies the affected component.',maxheight=245)
h('Visual hierarchy and typography')
p('The modern sans-serif family (Wix Madefor Display), generous whitespace, aligned card layouts and consistent navy/green identity generally support a professional impression. Main headings have strong scale. The service hero’s most important proof points, however, are visually suppressed by contrast—not by a lack of content. Fixing semantic text colors will have more immediate value than replacing the typeface (F04).')
p('Service-card paragraphs measured 14 px on the 390 px viewport while general text/form inputs were 16 px. Fourteen pixels is not automatically a WCAG failure, but dense technical copy is harder to scan at that size. Prefer a comfortable body scale, use short paragraphs/bullets and restrict long desktop text blocks to a readable measure.')
h('Components and spacing')
b('Buttons: preserve one clear quote/purchase primary action; distinguish secondary navigation. Some green/navy button text measured just below 4.5:1. Apply consistent tokens to all states, not isolated overrides.')
b('Cards and images: standardize product media frames and baseline alignment. Varied lifestyle crops can make nominally similar products feel inconsistent and hide variant differences (F13).')
b('Icons and links: decorative icons can remain decorative, but clickable containers must have names. Inline links need persistent non-color identification; repair the service overlay anchor (F23).')
b('Visual rhythm: reduce repetitive benefits and oversized repeated intro sections before changing the entire grid. The existing brand palette and layout system are worth retaining.')
# Design.
page('8. Professional web design evaluation',8,kicker='FROM TEMPLATE POLISH TO DEMONSTRABLE EXPERTISE')
image('references-content.png','E2 · https://nextro.hu/referenciak/ · Product filter imagery leads into generic project descriptions. The issue is relevance and proof, not image ownership: this audit does not establish whether any imagery is stock, commissioned or AI-generated.',maxheight=225)
h('Brand coherence and perceived quality')
p('The navy panels, green accents, line illustrations and generous spacing create a recognizably technical and current visual language. NEXTRO does not need a redesign solely to “look modern.” The perceived-quality gap comes from unfinished policy content, low-contrast proof text, generic project evidence and mixed-language ecommerce components. These are consistency and credibility problems, not a subjective need for a new style.')
h('A concrete homepage redesign direction')
table(['Sequence','Design decision'],[
['1 · Explicit promise','A descriptive HVAC H1, short audience/service statement, one quote CTA and a secondary phone/service link. Retain the slogan as supporting brand copy.'],
['2 · Choose a path','Group service entry points by installation, maintenance/renovation and filters; preserve access to the complete offer.'],
['3 · Proof','Show three real projects with sector, scope and one documented result. Replace anonymous claims with permission-cleared evidence.'],
['4 · Fit and process','A compact sector selection, four clear work stages and concise team/quality assurance. Avoid repeating the same benefit three times.'],
['5 · Commercial next step','Contextual enquiry or product selection; a short contact section with completed policy links. Keep the shop as a separate, coherent subjourney.']],[1.35,4.65])
p('Treat decorative overlays/tickers as optional. They should not reduce contrast, inflate image cost or make the site’s actual offer harder to read. Establish a small component specification for buttons, cards, media crops, headings, form states and light/dark surfaces; verify it on one service and one product template before wider rollout.')
# Accessibility.
page('9. Accessibility audit',9,kicker='WCAG-RELATED FINDINGS; NOT A CONFORMANCE CERTIFICATION')
p('Automated scans used axe-core 4.10.3 against WCAG 2 A/AA, 2.1 AA and 2.2 AA rule tags. Home/services were scanned on desktop; contact/priced product on a mobile viewport. Automated checks detect only part of accessibility. The counts below are node instances in specific snapshots, not a count of unique sitewide defects.')
table(['Snapshot','Automated violation types / instances'],[
['Homepage','Color contrast: 4 nodes.'],
['Services','Color contrast: 12; inline link distinguished only by color: 1; unnamed link: 1.'],
['Contact','Color contrast: 5; inline link distinguished only by color: 2.'],
['Priced product','Color contrast: 7. No other violation type returned in this snapshot.']],[1.3,4.7])
table(['Severity / WCAG relevance','Concrete evidence','Fix / verification'],[
['HIGH · 1.4.3 Contrast','Service checklist #3a4a57 on #0f1f2f = 1.82:1 at 16 px. Active nav = 2.71:1; contact links = 3.49:1.','F04: correct theme tokens; verify 4.5:1 normal / 3:1 large text in all states.'],
['HIGH · 2.4.4 Link purpose / 4.1.2 Name','A focusable .gspb-containerlink on services has no text, aria-label or other accessible name.','F23: remove redundant overlay or label the real intended action; confirm screen-reader output.'],
['HIGH · 1.4.1 Use of color','Service/contact inline links lack underlines and sufficient distinction from surrounding text.','F23: persistent underline or another non-color cue; do not rely on hover alone.'],
['MEDIUM · 1.3.1 Relationships','Missing category H1s, mixed peer sector levels and H1→H3 jumps make the outline inconsistent.','F11: logical heading hierarchy. Missing H1 alone is not a blanket conformance verdict.'],
['MEDIUM · input purpose / mobile ergonomics','Phone field uses generic text input; personal-data inputs lack autocomplete. Menu box is 18 × 18 px.','F09/F14: appropriate autocomplete/type and larger target area. Target-spacing exceptions mean the small menu box is not here declared a proven 2.5.8 failure.']],[1.4,2.35,2.25])
page('Accessibility: strengths and remaining tests',kicker='KEEP WHAT ALREADY WORKS')
h('Verified positives')
b('The visible-on-focus “Ugrás a tartalomra” skip link received focus, showed a 2 px outline and moved focus to MAIN#main when activated.')
b('Form controls have associated text labels; newsletter email has an accessible label. Main HTML documents declare Hungarian language.')
b('All 288 crawled img elements have an alt attribute. Empty values occur on 11 instances and may be appropriate for decoration/tracking; do not replace them with redundant text indiscriminately.')
b('Carousel previous/next/dot controls have accessible names and visible focus in the tested sequence. Industry cards can be focused and toggled with Enter, although their displayed pseudo-links still need repair.')
b('Tested major mobile layouts did not show horizontal document overflow at 390 px; homepage and priced product also fit 320 px. The viewport configuration permits zoom rather than explicitly disabling it.')
h('Manual checks still needed')
table(['Area','Required follow-up'],[
['Screen readers','NVDA/Firefox and VoiceOver/Safari journeys: heading outline, menu state, product tabs, card links, form errors and success announcements. No screen-reader session was conducted.'],
['Keyboard / overlays','Full tab order, focus trap/return, Escape close behavior, dropdown submenus, cookie settings and product gallery. The partial keyboard test does not prove every modal is accessible.'],
['Reflow / scaling','200% text zoom and 400% browser zoom; landscape and additional phone/tablet widths. The 320 px spot check is not a complete reflow test.'],
['Motion','Reduced-motion preference for counters, ticker, flip cards and carousel; user controls for persistent moving content where required.'],
['Form feedback','Authorized staging tests for validation association, required instructions, error summaries, aria-live messages and network failure recovery.'],
['Images / graphics','Contextual review of decorative vs informative backgrounds and generic alt text; contrast over images must be checked in all responsive crops, not only by automated rules.']],[1.3,4.7])
p('Use WCAG 2.2 AA as the implementation target, with human testing after automated fixes. The absence of a rule violation in one snapshot is not proof of compliance. Legal applicability and formal certification are outside this audit.','small')
# Mobile.
page('10. Mobile audit',10,kicker='390 × 844 VIEWPORT; 320 PX SPOT CHECKS')
imagepair(['mobile-cookie.png','mobile-menu.png'],['E3 · First-visit cookie panel occupies a substantial part of the 390 px viewport. Accept/reject/settings buttons are present and measured 350 × 45 px.','E4 · Opened mobile menu is legible and exposes the six principal destinations. The trigger itself has an 18 × 18 px measured box.'],maxheight=300)
h('What works')
p('The menu opened and closed, the major page layouts stacked, form inputs remained readable at 16 px and no horizontal overflow was found on homepage, services, contact, shop and sampled product at 390 px. Homepage and the priced product also fit 320 px. The primary hero CTA is a comfortable size; the shop category list wraps within the viewport.')
h('Mobile-specific friction')
b('Consent: the long banner delays reading the offer, even though rejecting is available. Shorten the first-layer explanation and move detail into accessible settings/policy content without weakening choice (F15).')
b('Urgent contact: the desktop phone strip is hidden. On the measured mobile homepage, visible phone links appeared far down the document. Add immediate call/quote access rather than assuming desktop contact prominence carries over (F14).')
b('Navigation: enlarge the menu and close hit areas. Add aria-expanded state if the theme does not expose it, and verify state announcement and return focus rather than inferring behavior from appearance.')
b('Length and scanning: the homepage snapshot is 17,155 px tall, services 9,019, contact 5,568 and shop 5,752. These are viewport-specific layout observations, not performance scores. Consolidate repetition and add in-page indexes (F17).')
page('Mobile: detailed recommendations',kicker='DESIGN FOR TOUCH, NOT JUST A NARROW COLUMN')
table(['Component','Observed behavior / risk','Mobile improvement'],[
['Hero','Brand slogan and CTA fit; subtle preheading/proof text is difficult to see against dark imagery.','Use an explicit readable service promise; maintain contrast in the mobile crop. Do not spend the full first screen on decorative spacing.'],
['Industry cards','Copy says to move the cursor over the card; Enter flips on desktop, but the displayed solution arrow is not a real link.','Use visible summaries or tap-expanding content with explicit state and a separate navigable CTA. Replace cursor-specific instructions.'],
['Services','Stacked summaries use 14 px paragraph text and many similar blocks.','Increase comfortable reading scale where possible, add jump navigation and link to detail rather than repeating full summaries.'],
['Products','Lifestyle image plus long title pushes buying context down; power/cable differences require reading dense names.','Show key selection attributes early; place approved price/availability or quote action with the product summary.'],
['Forms','Multiple stacked fields, telephone mandatory via ARIA and generic text keyboard metadata.','Use appropriate input types/autofill, fewer necessary fields and contextual defaults. Keep a persistent label and clear error feedback.'],
['Sticky controls','No useful persistent mobile call action was visible in the sampled homepage view.','If adding a bottom bar, reserve safe-area space and verify it does not cover cookie controls, validation errors, product tabs or the submit button.'],
['Cookies / popups','Consent is the main observed overlay; newsletter component exists. A complete popup timing audit was not performed.','Check clean/returning sessions, settings reopening and any delayed promotions across devices; keep decline and close controls usable.']],[1.15,2.4,2.45])
h('Release acceptance')
p('At 320, 390 and 430 px, verify a first-time visitor can dismiss or configure consent, open/close navigation, reach a selected service, identify a credible reference and contact the company. Then compare charger variants and reach an honest purchase or quote state. Test with touch and keyboard, in portrait/landscape and with text zoom. Do not substitute a desktop screenshot squeezed to phone width for this journey test.')
# Content.
page('11. Content quality audit',11,kicker='MAKE EXPERTISE SPECIFIC, CONSISTENT AND EVIDENCED')
p('The better passages are concrete: service FAQs describe filter replacement, heat-exchanger cleaning, controls checks and documentation; sector copy recognizes hospitality disruption and kitchen make-up air. The recurring weakness is unsupported breadth or repeated reassurance rather than poor grammar overall. Editing should preserve engineering specificity while making customer decisions easier.')
table(['Observed example','Why it falls short','Suggested improvement'],[
['“Contact us now”; “Price:”; “Archives”','Template English appears in Hungarian commercial journeys.','Use “Telepítési ajánlatot kérek”, “Ár” and customer-facing category titles. F20.'],
['“Közel 20 év” vs “20 éve” vs “21 év”','Tenure statements are inconsistent; the About text says foundation was at the end of 2005.','Use stable “2005 óta” unless a precise anniversary claim is intentionally dated.'],
['“Tudjon meg többet” → homepage','Generic wording is coupled to a non-progressing destination.','Use “Légkezelő karbantartás részletei” or “Ismerje meg szakembergárdánkat”, with the matching link.'],
['“vigye a kurzort a kártyára”','Desktop interaction instructions are not appropriate to a touch-first visitor.','Prefer visible information and a real “Szállodai megoldások” link; explain tap/expand only if necessary.'],
['Anonymous Ipari üzem / Szálloda references','Descriptions summarize work types but do not verify experience.','Add approved project/date/site context, challenge, delivered scope, photos and measured results only where documented.'],
['“rövid időn belül” / general guarantees','The prospect cannot infer response timing or warranty conditions.','Publish an achievable response window only after internal approval; link to specific warranty/scope conditions.'],
['Shared dense charger paragraph','IP ratings, voltages and control functions are hard to compare.','Split into “Kinek ajánlott?”, power/network/cable table, included items, installation requirements and warranty/shipping sections.']],[1.65,1.85,2.5])
h('Example of more customer-oriented wording')
p('<b>For maintenance:</b> “Üzemelő légkezelő karbantartására van szüksége? Felmérjük a rendszer állapotát, egyeztetjük a leállási lehetőségeket, és dokumentált karbantartási tervet készítünk. Írja meg a helyszínt, a berendezés típusát és a tapasztalt hibát.” Validate the promised scope with the technical team before publication.')
p('Do not manufacture testimonials, logos, qualifications, warranty terms or efficiency savings to fill the gaps. CompanyWall ranking, partner counts and annual filter-production statements were observed as site claims, not independently verified facts.','small')
# CRO.
page('12. Conversion effectiveness',12,kicker='TWO FUNNELS NEED TWO CLEAR PROMISES')
h('Likely primary goals')
p('<b>Service funnel:</b> qualified quote requests, urgent service calls and filter enquiries. <b>Commerce funnel:</b> direct EV-charger orders where products are configured for purchase, with installation quotations as a related service. Newsletter subscription is secondary. These goals are inferred from the UI; business targets and channel economics were not supplied.')
table(['Conversion lever','Current observation','Specific recommendation'],[
['CTA clarity','Repeated Ajánlatot kérek works well on service pages; product cards mostly say Tovább olvasom.','Keep explicit service actions; use purchase or model-specific quote wording consistently (F02).'],
['Context preservation','Service cards funnel to the general hub; the product contact panel leads to the generic contact page.','Prefill or visibly summarize selected service/model in the enquiry. Let the user edit it.'],
['Trust at decision time','Claims of team size, experience, guarantees and AA+ rating; few verifiable project specifics.','Place approved case evidence, warranty scope and accountable company information beside the decision (F08, F18).'],
['Form effort','Eight data fields plus policy acceptance, with phone and email both marked required.','Offer minimal initial contact and progressive qualification. Complete policies before requiring acceptance (F01, F09).'],
['Purchase certainty','One listed price; unclear delivery costs/timing and an internal shipping note.','Business sign-off of prices, stock/lead times, VAT, installation, shipping, returns and payment rules (F01–F02).'],
['Competing actions','Shop controls expose wishlist, comparison and quick view; homepage also promotes newsletter/products.','Keep secondary tools visually secondary. Test whether a simple variant table serves a nine-item range better than multiple overlays.'],
['Outcome measurement','No analytics/CRM/payment access; live submissions intentionally not made.','Consent-aware events plus authorized end-to-end QA; judge qualified leads and completed orders, not only button clicks (F24).']],[1.2,2.35,2.45])
h('What to measure after implementation')
p('Track service/product entry page → quote start → valid successful submission → qualified lead; separate phone/email clicks from confirmed enquiries. For ecommerce track product views, variant choices, add-to-cart, checkout start, payment outcome and fulfilment issues. Review mobile versus desktop and traffic source under approved privacy rules. Establish baseline and sample sufficiency before an experiment; no conversion-rate estimate is justified from this inspection.')
