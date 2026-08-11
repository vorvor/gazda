# Drupal Project Map

This document is a durable orientation map for future development discussions. It describes the repository and the bootstrapped local site at commit `814ea7b8`. Re-check runtime state and configuration before relying on counts or operational status.

## 1. Project identity

- Drupal 10 site built from `drupal/recommended-project`.
- Runtime inspected successfully with Drupal `10.6.13`, PHP `8.4.24`, and a connected MySQL database.
- Site name: **Sétálj be!**
- Primary language and default content language: Hungarian (`hu`). English configuration translations also exist.
- Front page: `/search-product`.
- Public theme: `gazda`; administration theme: `claro`.
- Install profile: `standard`.
- Document root: `web/`.
- Configuration export: `config/sync/` (384 top-level YAML files at mapping time).
- Composer dependencies are installed into `vendor/`; contributed modules are installed into `web/modules/contrib/`.
- Local host development is defined in `.lando.yml`, but commands inside the development container should use PHP, Composer, and Drush directly.

## 2. Repository layout

| Path | Purpose |
| --- | --- |
| `composer.json`, `composer.lock` | PHP and Drupal dependency definitions and locked versions |
| `config/sync/` | Exported Drupal configuration; currently synchronized with active configuration |
| `web/core/` | Drupal core; do not modify directly |
| `web/modules/contrib/` | Composer-managed contributed modules; do not modify directly |
| `web/modules/custom/` | Six custom feature modules |
| `web/themes/gazda/` | Custom public theme (stored directly under `web/themes`, not `web/themes/custom`) |
| `web/sites/default/` | Site bootstrap and environment settings |
| `.lando.yml`, `.lando/` | Host-side local-development definition |
| `AGENTS.md` | Repository-specific development instructions |

The repository tracks generated/dependency code (`vendor`, Drupal core, and contributed extensions) as well as custom code and configuration. Custom module/theme code is small: 37 text files and about 1,610 lines at mapping time, excluding images and generated CSS maps.

## 3. Domain model

The site catalogs products and services offered by local shops in Szentendre. It is a discovery/catalog site rather than a webshop.

### Content types

| Bundle | Purpose and important fields | Runtime count |
| --- | --- | ---: |
| `shop` | Local merchant. Address, admin notes, business plan, admin contact, coupon toggle, description, email, featured flag, images, geolocation, logo, Google map, opening hours, phone | 3 |
| `product` | Shop product. Description, images, generated marketing text, owning shop, tags | 148 |
| `service` | Shop service. Icon, images, service description, owning shop, tags | 4 |
| `page` | Basic page with body | 1 |
| `article` | Standard article with body, image, tags, comments | 0 |

All five bundles create new revisions by default.

### Principal relationships

```text
shop 1 <--- field_shop --- many product
shop 1 <--- field_shop --- many service
product/service many --- field_tags ---> many tags terms
```

- `field_shop` is a single entity reference from products/services to shops.
- `field_tags` is a multi-value entity reference.
- Product search also has optional handling for a `field_category` reference, but no `field_category` node field is present in the exported bundle configuration. Treat that path as defensive/legacy behavior.
- Shops carry both `field_location` (Geofield) and `field_map` (Google Map Field).
- Product/shop galleries use multi-value `field_images`; shop logos and service icons are single-value image fields.

### Taxonomy

| Vocabulary | Machine name | Runtime terms |
| --- | --- | ---: |
| Product category | `product_category` | 2 |
| Címkék / Tags | `tags` | 85 |

### Media and users

- Standard media bundles exist (`audio`, `document`, `image`, `remote_video`, `video`) but contain no runtime entities.
- Roles exported: anonymous, authenticated, content editor, administrator.
- Two user entities existed at mapping time.

## 4. Main user-facing flow

1. `system.site:page.front` points to `/search-product`.
2. `product_search` handles the page and AJAX endpoint.
3. The initial render embeds the `products` View.
4. Browser-side JavaScript debounces input for 250 ms and calls `/search-product/ajax?q=...`.
5. `ProductSearchController` searches published `product` and `service` records across:
   - title;
   - product, service, and marketing description fields when their tables exist;
   - tag names;
   - product-category names when the optional field table exists;
   - referenced shop title.
6. Results are ordered by most recently changed and capped at 100 node IDs.
7. IDs are passed as a `+`-joined contextual argument to the `products` View (`page_1`).
8. An empty query restores the initially rendered result list.

Key files:

- `web/modules/custom/product_search/product_search.routing.yml`
- `web/modules/custom/product_search/src/Controller/ProductSearchController.php`
- `web/modules/custom/product_search/src/Plugin/Block/ProductSearchBlock.php`
- `web/modules/custom/product_search/js/product-search.js`
- `web/modules/custom/product_search/templates/product_search.html.twig`
- `config/sync/views.view.products.yml`

## 5. Custom modules

### `product_search`

Owns the primary catalog/search experience.

- Routes:
  - `/search-product` — public search page;
  - `/search-product/ajax` — public GET/JSON search endpoint.
- Provides `product_search_block`, placed in the Gazda theme's content region.
- Embeds Views rather than rendering entities directly.
- Adds current path/alias variables during HTML preprocessing.
- Uses dependency injection for the database connection and renderer in the controller.

### `product_tags`

Provides `product_tags_block` on full shop routes.

- Loads published products referencing the current shop.
- Collects unique referenced tag terms, sorts them, and renders links.
- Cache tags include the current shop and the product node list.
- The block is placed in Gazda's content region.

### `daily_coupon`

Adds a deterministic daily coupon to full shop and product pages when the owning shop's `field_coupon` is enabled.

- Product pages resolve their owner through `field_shop`.
- Coupon format is the uppercased first three shop-title characters plus a deterministic three-digit day-based suffix.
- The rendered element currently uses a fixed one-day cache lifetime rather than a cache context/tag aligned exactly to midnight.

### `footer_block`

Provides `footer_block`, a custom Twig-rendered footer with its own CSS and image assets.

- The block is enabled in Gazda's footer region.
- The template owns the actual footer presentation; the block class passes only placeholder content.

### `gazda_seo`

Fills empty image `alt` and `title` values with the node label.

- Runs on every node presave.
- Provides a batch admin form at `/admin/config/search/gazda-seo-bulk-update`.
- Provides Drush command `gazda-seo:update-images` (`gsu-img`).
- The module also contains unrelated experimental external-calendar API code; see risks below.

### `generate_marketing_text`

Generates missing product marketing/description text during entity presave through OpenAI's chat-completions endpoint.

- Settings form: `/admin/config/services/generate-marketing-text`.
- Model hard-coded in module code: `gpt-4o-mini`.
- Uses category/tag/title and referenced shop name to fill prompt placeholders.
- Generates only when target fields are empty.
- `generate_marketing_text.settings` is intentionally excluded from config synchronization, presumably because it contains environment-specific prompts and an API credential.

## 6. Custom Gazda theme

Path: `web/themes/gazda/`.

- Theme name: `Gazda`.
- Base theme: `claro` (unusual for a public-facing theme; changes to Claro can affect inheritance).
- Declared compatible with Drupal 9, 10, and 11.
- Regions: header, pre-content, breadcrumb, highlighted, help, content, page top/bottom, first sidebar, footer.
- Global libraries: `gazda/styles` (`css/style.css` and `css/style-mobile.css`).
- Custom templates:
  - overall HTML/page structure;
  - product full page;
  - shop full page;
  - main content block;
  - service phone View field.
- Theme preprocessing:
  - suppresses the normal page title for shop routes because the shop template renders its own title;
  - adds a transliterated `shop-*` class to the body for shop/product context;
  - adds shop-specific classes to rows in `products`, `front_for_flyer`, and `shops` Views.

Important enabled Gazda block placements:

- Header: branding and main navigation.
- Pre-content: page title.
- Highlighted: messages and local admin tasks/actions.
- Content: main content, product search, product tags, featured shops, services, and shop-admin information Views.
- Footer: custom footer, Google Analytics custom block, and shops View.

Visibility conditions in individual `block.block.gazda_*.yml` files determine where these overlapping content-region blocks actually appear.

## 7. Views and URL architecture

Business-specific Views:

- `products` — default, block, and page displays; central catalog renderer.
- `services` — service block.
- `shops` — shops block.
- `featured_shops` — featured shops block.
- `front_for_flyer` — block/page presentation.
- `manage_product_descriptions` — administrative description management.
- `shop_info_for_admin` — shop administration block.
- `shop_phone` — shop phone block.
- `tags_of_shop_by_products` — shop-derived tag data.

Pathauto patterns:

- Product: `/termek/[node:field_shop:entity:title]/[node:title]`
- Shop: `/bolt/[node:title]`
- Tag: `/tag/[term:name]`

The canonical homepage/search route remains `/search-product`.

## 8. Contributed capability areas

The enabled/required contributed ecosystem clusters around:

- Search/display: Better Exposed Filters, Views fields combine, Slick/Slick Views, Views Slideshow, Blazy, Charts.
- Location: Geocoder, Geofield, Geolocation, Google Map Field, Leaflet.
- SEO/analytics: Metatag (Open Graph and Facebook/product extensions), Pathauto, XML Sitemap, SEO Analyzer/Audit, Google Analytics, AddToAny.
- Editorial/content: Editable Fields, Field Gallery, Image Effects, Imagefield Slideshow, Office Hours, Synonyms, Font Awesome, Clipboard.js, Colorbox.
- Operations/admin: Admin Toolbar, Backup and Migrate, Config Ignore, Queue UI, Visitors.
- Engagement: Flag and anonymous flag support are Composer requirements; enabled config includes `flag` but not `flag_anon`.

Composer applies one Drupal core patch for Views aggregation with multi-column fields (Drupal issue `#2815881`).

## 9. Configuration and deployment behavior

- Active configuration and `config/sync` had no differences when mapped.
- `generate_marketing_text.settings` is ignored by Config Ignore.
- Drupal caches should be rebuilt after module/theme/config changes (`vendor/bin/drush cr`).
- Composer controls Drupal core and contributed code; never patch files under `web/core`, `web/modules/contrib`, or `vendor` directly.
- Custom extensions live in `web/modules/custom`, except the Gazda theme, which is directly under `web/themes/gazda`.
- The repository's `.lando.yml` describes host-side PHP 8.3/Apache/MySQL 8.0 services, while the inspected Hermes development container ran PHP 8.4.24. Account for this runtime difference when debugging environment-specific behavior.

## 10. Known concerns and discussion hotspots

These are orientation notes, not a complete code review.

1. **Credential exposure in custom code:** `gazda_seo.module` contains a hard-coded credential for an unrelated external API. It should be revoked/rotated and moved to protected environment/config storage; do not copy it into tickets, logs, or documentation.
2. **Marketing API secret storage:** the OpenAI key is stored in editable Drupal configuration and the form uses a normal text field. Config Ignore avoids exporting it, but key storage/display and partial-key logging should be hardened.
3. **Network work during presave:** `generate_marketing_text` performs up to two synchronous external API calls while saving a product. This can delay/fail editorial saves and is better suited to a queue.
4. **Static-service usage:** several custom modules/theme hooks use `\Drupal` statically. New service-oriented code should use dependency injection where Drupal's API shape permits it.
5. **Search query duplication:** `ProductSearchController::findProductNodeIds()` executes the built query once into an unused `$a` and then executes it again for the returned result.
6. **Development leftovers:** product-search JavaScript contains console logging; the Gazda SEO admin form prints an external API response during form build.
7. **Theme inheritance:** the public theme inherits from the administration-focused Claro theme rather than a frontend base/stable theme.
8. **Template coupling:** theme templates read nested render-array internals such as `#context` and build Google Maps URLs directly; this is more fragile than preprocessing clean values/URLs.
9. **Coupon cache boundary:** the daily coupon uses `max-age: 86400`, which does not guarantee invalidation at local midnight.
10. **Historical error log:** the latest severe database-log entry reports an older `ProductTagsBlock::l()` undefined-method error. Current source uses `Link::fromTextAndUrl()`, so the code appears corrected, but the route/block should be exercised to confirm the runtime error is gone.
11. **Limited automated-test footprint:** no custom module tests were found in the mapped custom-code tree.

## 11. Verification snapshot

At mapping time:

- Drupal bootstrapped successfully.
- Database connection succeeded.
- Active and exported configuration matched.
- Enabled custom modules: `daily_coupon`, `footer_block`, `gazda_seo`, `generate_marketing_text`, `product_search`, `product_tags`.
- Enabled themes: `claro`, `olivero`, `gazda`.
- Git branch: `main`, one commit ahead of `origin/main` before this map file was added.
- No pre-existing working-tree changes were present before this file was created.

## 12. Fast lookup guide

For future discussions, start here:

- Product discovery/search behavior: `web/modules/custom/product_search/`
- Shop tag aggregation: `web/modules/custom/product_tags/`
- Coupon behavior: `web/modules/custom/daily_coupon/`
- AI-generated copy: `web/modules/custom/generate_marketing_text/`
- Image SEO automation: `web/modules/custom/gazda_seo/`
- Public layout and node presentation: `web/themes/gazda/`
- Data model: `config/sync/node.type.*`, `field.storage.node.*`, and `field.field.node.*`
- View queries/displays: `config/sync/views.view.*`
- Block placement/visibility: `config/sync/block.block.gazda_*.yml`
- URL aliases: `config/sync/pathauto.pattern.*.yml`
- Enabled extensions: `config/sync/core.extension.yml`
- Site language/front page/theme: `config/sync/system.site.yml`, `language.*.yml`, `system.theme.yml`
