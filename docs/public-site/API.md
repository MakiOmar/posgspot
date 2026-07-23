# Storefront API v1

**Progress tracker:** [`STOREFRONT_PROGRESS.md`](./STOREFRONT_PROGRESS.md)

Base URL: `/api/storefront/v1`

All responses use the envelope:

```json
{
  "success": true,
  "data": {},
  "meta": {}
}
```

Errors:

```json
{
  "success": false,
  "message": "Error description",
  "errors": {}
}
```

## Content locale

Send the active storefront language on catalog and settings requests:

| Header | Values | Default |
|--------|--------|---------|
| `X-Content-Locale` | `en`, `ar` | `en` |

The API resolves this via `ResolveStorefrontContentLocale` middleware. Catalog list/search endpoints return **only** products/categories with content for the requested locale (no fallback to English on `ar`). Product detail returns **404** when Arabic is requested but no `ar` translation row exists.

The Qwik storefront sets this header from the URL prefix (`/en/…`, `/ar/…`) on every `storefrontFetch` call.

### Localized settings fields

`GET /settings` resolves these strings for the requested locale:

- `announcement.message`
- `sale_badge.text`
- `reward_points.name`

Stored in admin as `{ "en": "…", "ar": "…" }` on **Storefront Settings** (`/storefront/settings`). Legacy single-string values are treated as `en` on read.

Public `GET /settings` also exposes:

- `cod_enabled`
- `online_payments.enabled`, `online_payments.provider`, `online_payments.label` (no secrets)
- `couriers.bosta.enabled` — true when Bosta is enabled **and** an API key is stored (no key exposed); checkout uses this to collect Bosta `district_id`
- `promo_codes.enabled_at_checkout`, `promo_codes.allow_stacking` (configured under **Storefront Settings** in POS)
- `payment_icons[]` — `{ label, icon_url }` for footer payment method icons (upload or external URL under **Storefront Settings → Footer payment icons**)
- `favicon_url` — absolute URL for the browser tab icon (**Storefront Settings → Appearance → Favicon**); null when unset (Qwik falls back to `/favicon.svg`)
- `footer` — `{ contact_title, columns[] }` editable footer menus (**Storefront Settings → Footer**). Public payload is locale-resolved: `contact_title` string + up to 3 `columns[]` of `{ id, title, links: [{ id, label, url }] }`. Column 1 on the Qwik site is business locations from `GET /locations` (not this object).
- `banners[]` — enabled promotional banners `{ id, placement (home|category), category_slug, title, link, image_url }` (Storefront Settings → Banners); titles localized via `X-Content-Locale`
- `newsletter.enabled` — true when a provider is enabled and credentials are configured (no secrets exposed)
- `repair.lookup_enabled`, `repair.lookup_by_mobile` — public repair status lookup flags (no PII)

### Homepage sections (`GET /homepage`)

Returns `{ sections: [{ id, type, settings }] }` for **enabled** sections only (order = display order). Locale via `X-Content-Locale`.

| type | settings (presented) |
|------|----------------------|
| *(all types)* | Section row also has `layout_width`: `boxed` (default) \| `full` (viewport width with side margins) |
| `hero_slider` | `slides[]` → `{ id, image_url, href, kicker, title }` |
| `promo_tiles` | `tiles[]` → `{ id, image_url, href, label }` |
| `video` | `source` (`self`\|`youtube`\|`vimeo`), `url`, `poster` (self only), `title`, `embed_url` (youtube/vimeo) |
| `trust_badges` | `items[]` → `{ id, icon_kind (image\|svg), icon_url, icon_color, title, description }` (icons are media URLs only; no inline `svg_markup` in API). Qwik inlines SVG via sanitized fetch + `currentColor` / `icon_color` (`RemoteSvg`); rasters stay `<img>`. |
| `promo_banners` | `max` (legacy: clients use `settings.banners` placement=home) |
| `promo_banner` | Compositional: `logo_url`, `top_title`, `main_title`, colors, `background_color`, `border_radius` / `border_color` / `border_thickness`, `min_height`, `image_url` + `image_position` `{top,right,bottom,left,width}`, `button` `{label,link,colors,border_radius,show_arrow,arrow_color,position}`. Insert multiple to stack. |
| `featured_products` | `per_page` → client calls `GET /products?featured=1` |
| `top_categories` | `limit` |
| `category_shelves` | `limit`, `products_per_shelf` → `GET /categories/homepage-shelves` (legacy flag-based) |
| `category_shelf` | `category_id`, `products_per_shelf`, resolved `shelf` (same shape as homepage-shelves item). Insert multiple for multiple categories. |
| `brand_slider` | `limit` |
| `bestsellers` | `per_page`, `in_stock_only`, `style` (`grid` \| `horizontal`) |
| `recently_viewed` | `limit` (client localStorage) |

Unknown types should be skipped by clients.

`POST /checkout` with `payment_method: "fawry"` when online payments are enabled returns:

```json
{
  "id": 123,
  "storefront_order_id": "web-…",
  "payment_status": "due",
  "payment": {
    "provider": "fawry",
    "sdk_url": "https://www.atfawry.com/atfawry/plugin/assets/payments/js/fawrypay-payments.js",
    "return_url": "https://{STOREFRONT_URL}/en/checkout/payment/return/?order=…",
    "locale": "en",
    "charge": {
      "merchantCode": "…",
      "merchantRefNum": "…",
      "signature": "…",
      "chargeItems": [],
      "returnUrl": "…"
    }
  }
}
```

Register Fawry webhook URL: `{APP_URL}/api/storefront/v1/payments/fawry/webhook`

Configure merchant code + security key under **Storefront Settings → Payment gateway → FawryPay**.

## Public endpoints

| Method | Path | Description |
|--------|------|-------------|
| GET | `/ping` | Health check |
| GET | `/settings` | Business + storefront public settings (includes `sale_badge`, `catalog.show_availability_on_cards`, `payment_icons`, `favicon_url`, `footer`, `banners`, `couriers.bosta.enabled`, `digital.enabled`) |
| GET | `/homepage` | Ordered enabled homepage sections (`type` + presented `settings`) for Qwik / mobile; catalog data still from product/category/brand endpoints |
| GET | `/locations` | Active POS branches for public display (**excludes** storefront selling locations). `?selling_only=1` returns only selling locations (checkout pickup). `address` uses **Storefront display address** when set. Includes `is_selling_location`, `enable_pickup`, coords, `maps_url`, `email_encoded`. Powers footer, contact, store locator. |
| GET | `/geo/countries` | Country list for address forms |
| GET | `/geo/states/{countryCode}` | States / governorates for a country |
| GET | `/geo/bosta-districts?state=` | Bosta districts for a governorate (`state` code) when Bosta is enabled+keyed; otherwise `{ city_code, city_name, districts: [] }`. Labels follow `X-Content-Locale`. |
| GET | `/categories` | Category tree (`id`, `name`, `slug`, `image_url`, nested `sub_categories`) |
| GET | `/categories/homepage-shelves` | Categories with `show_on_homepage_shelf` (background `banner_image_url`, mid product `banner_fg_image_url`, heading/kicker/title/CTA from POS category edit). Use each `slug` with `/products?category_slug=` for the shelf grid |
| GET | `/categories/{slug}` | Single category by slug (404 if unknown); includes `image_url` |
| GET | `/brands` | Brands with sellable products in public selling locations (`id`, `name`, `slug`, `image_url`). Locale-filtered: AR requires a brand translation row. |
| GET | `/brands/{slug}` | Single brand by EN `slug` (404 if unknown or no locale content); includes `image_url` |
| GET | `/products` | Product listing (empty if no selling locations); filter via `category_id` / `category_slug`, `brand_id` / `brand_slug`, `featured=1` (POS `is_storefront_featured`); sort: `default`, `name`, `price_asc`, `price_desc`, `newest`, `bestsellers` |
| GET | `/products/{idOrSlug}` | Product detail (`description` HTML is sanitized server-side). Includes `related_products[]` (ProductSummary shape, up to 8): same category/subcategory first, then same brand fill; excludes self; locale-filtered like list/search. Includes `rating: { average, count }` from approved reviews. ProductSummary list rows also include `rating_average` / `rating_count`. Brand object includes `slug` when available. |
| GET | `/products/{idOrSlug}/reviews` | Approved reviews only (paginated). Each item: `id`, `rating`, `title`, `body`, `is_verified_purchase`, `author_name` (masked), timestamps. |
| GET | `/products/{idOrSlug}/reviews/eligibility` | **Auth required.** `{ can_review, already_reviewed, reason }` — reasons: `not_purchased`, `pending`, `already_reviewed`, `not_found`. |
| POST | `/products/{idOrSlug}/reviews` | **Auth required.** Body `{ rating (1–5), title?, body (10–2000) }`. Requires a final sell of the product for the contact. Creates/updates as `pending` (rejected rows may be resubmitted). |
| GET | `/products/{id}/availability?variation_id=` | Per-store stock modal — stock across **all active business locations** (incl. out-of-stock), not only public selling locations. Each location row includes `address`, `latitude`, `longitude`, and a ready `maps_url` (lat/lng preferred, address fallback). Coordinates are set per location in **Settings → Business Locations** |
| GET | `/search?q=&limit=` | Search autocomplete (header dropdown). Full results UI is the Qwik `/[lang]/search` page using `GET /products?q=`. |
| POST | `/contact` | Public contact form — emails the business inbox (`mail_username` when it is a valid email, else From address). Transport is system Mailgun/SMTP when the business uses superadmin email settings, otherwise per-business SMTP. Optional `turnstile_token` when Turnstile is enabled in storefront settings |
| POST | `/repair/status` | Public repair lookup — body `{ search_type: job_sheet_no\|invoice_no\|mobile_num, search_number, serial_no? }`. Scoped to storefront business. Returns `{ repairs[] }` with status, device info, and activity timeline (no customer PII). `mobile_num` only when `repair.lookup_by_mobile` is true. 404 when no match; 503 when repair module unavailable. |
| GET | `/digital/games?platform=&page=` | Digital games catalog (Accounts proxy). `platform` = `4` (PS4) or `5` (PS5). Returns normalized `games[]` + POS `skus` map. 503 when `digital.enabled` is off. |
| GET | `/digital/games/{id}` | Single game detail + POS `skus`. |
| GET | `/digital/card-categories` | Gift card categories + POS `skus`. |
| POST | `/digital/check-stock` | Accounts game stock check — body `{ game_id, type: primary\|secondary, platform: 4\|5 }`. |
| POST | `/digital/check-card-stock` | Accounts card stock check — body `{ card_category_id }`. |
| POST | `/newsletter/subscribe` | Footer newsletter signup — body `{ "email", optional "turnstile_token" }`. Requires newsletter enabled + provider credentials in Storefront Settings. Providers: Mailchimp, MailerLite, AWeber. Returns `{ status, message }` (`subscribed` / `pending` / `already_subscribed`). |
| POST | `/coupons/validate` | Validate a promo code against cart lines — body `{ "code", "items[]", optional "location_id", optional "coupon_codes[]" (already applied when stacking) }`. **Requires storefront customer auth** (Bearer token). Respects storefront settings `promo_codes.enabled_at_checkout` and `allow_stacking`. Returns `coupon`, `coupons[]`, `coupon_discount`, `shipping`, `total`, `stack_with_reward_points`. |
| POST | `/coupons/available` | List promo codes the signed-in customer can apply to the current cart — body `{ "items[]", optional "exclude_codes[]" (already applied when stacking) }`. **Requires auth.** Returns `{ coupons[] }` with `code`, `name`, `label`, `total_savings`, `discount_amount`, `free_shipping`, etc. Empty when checkout promos disabled or none eligible. |
| POST | `/cart/validate` | Revalidate cart lines (price + stock). Optional `coupon_code` or `coupon_codes[]` returns adjusted totals — **coupons require auth** and respect promo-code storefront settings. When `location_id` is sent, stock is checked at that fulfillment store only; otherwise stock is summed across all selling locations. Pass `resolve: true` to inspect lines without failing — response includes `line_status[]` with `max_quantity` per variation. **Shipping (zone engine):** optional `destination` (`country`, `state`/governorate, `city`) + optional `shipping_rate_id`. Response includes `shipping`, `shipping_rate`, `available_rates[]` (`id`, `method_type`, `title`, `amount`, `eta_label`), `hide_rates_until_address`, and `digital_only`. When every line has `digital.kind`, quoting returns a single free `method_type: digital` rate (no address required; `hide_rates_until_address` is false). Mixed carts skip digital lines for weight/qty. Rate ids are signed; checkout re-quotes and rejects stale/tampered ids. Free-shipping coupons force delivery rate amounts to `0`. |
| POST | `/checkout` | Create order (idempotent). **Requires `shipping_rate_id`** (from cart validate — for digital-only carts use the free `digital` rate id). Optional `coupon_code` or `coupon_codes[]` (**logged-in customers only**; settings-controlled; re-validated server-side; writes `coupon_redemptions` on success). `payment_method`: `cod`, `fawry`, or `card` (alias for `fawry`). Fawry responses include a signed `payment` block for hosted checkout. Pickup rates (`local_pickup`) use `location_id` for branch stock; digital rates skip physical address / Bosta district. Delivery persists method title + `storefront_shipping_meta`. For Bosta fulfillment, include `shipping_address.district_id` (and optional `district_label`) from `GET /geo/bosta-districts`. Optional per-line `digital` meta (`kind`, `game_id`/`type`/`platform` or `card_category_id`, `line_key`, `title`, `price`) queues Accounts allocation **after** `payment_status=paid` (no secrets at checkout). |
| POST | `/payments/{provider}/webhook` | Payment gateway server callback (Fawry: JSON body + signature) |
| POST | `/payments/{provider}/return` | Verify customer return URL payload after hosted checkout |
| POST | `/payments/{provider}/session` | Rebuild signed payment session for an existing pending order (`storefront_order_id`, optional `locale`) |

## Auth (Sanctum bearer token on `Contact`)

| Method | Path | Description |
|--------|------|-------------|
| POST | `/auth/register` | Register customer. Optional `turnstile_token` when Turnstile is enabled in storefront settings |
| POST | `/auth/login` | Login |
| POST | `/auth/logout` | Logout (auth required) |
| POST | `/auth/forgot-password` | Request reset (email contains link to `{STOREFRONT_URL}/reset-password?email=&token=`) |
| POST | `/auth/reset-password` | Reset password (`email`, `token`, `password`, `password_confirmation`) |

## Account (auth required)

| Method | Path | Description |
|--------|------|-------------|
| GET | `/account/profile` | Profile |
| PUT | `/account/profile` | Update profile |
| PUT | `/account/address` | Update address |
| GET | `/account/orders` | Order history |
| GET | `/account/orders/{id}` | Order detail (lines, shipping address, fulfillment location). When `payment_status` is `paid`, includes `invoice_print_url` — same POS invoice page with `print_on_load=true`. Lines include `slug` and `image_url` when available (for reorder → cart). Also returns `shipping_method`, `shipping_carrier`, `shipping_tracking_number`, `shipping_tracking_url` when set. When paid + allocated **and** storefront setting `digital.expose_credentials_to_customer` is on, includes `digital_deliveries[]` (`kind`, `title`, `account_email`/`account_password` or `code`). When that setting is off, secrets stay on POS staff note only. Response includes `is_quotation` when checkout created a draft quotation (`digital.pos_document_type=quotation`). |
| GET | `/account/orders/{id}/invoice` | Paid-order invoice print URL only (fallback when detail omits `invoice_print_url`) |

## Wishlist (auth required)

| Method | Path | Description |
|--------|------|-------------|
| GET | `/wishlist` | List saved products + `count` (product summaries for active locale) |
| POST | `/wishlist` | Add product — body `{ "product_id": 123 }`; returns updated list |
| POST | `/wishlist/merge` | Merge guest product ids on login — body `{ "product_ids": [1, 2] }` |
| DELETE | `/wishlist/{productId}` | Remove product; returns updated list |

Response shape:

```json
{
  "items": [/* ProductSummary[] */],
  "count": 2
}
```

Invalid or unavailable products return **422** on add.

Limits (configurable via `config/storefront.php` / env):

| Setting | Env | Default |
|---------|-----|---------|
| Max saved items per customer | `STOREFRONT_WISHLIST_MAX_ITEMS` | 100 |
| Max IDs per merge request | `STOREFRONT_WISHLIST_MERGE_MAX_IDS` | 100 |

## Admin

Back-office: **Settings → Storefront Settings** (`/storefront/settings`)

- **Import / export** — `GET /storefront/settings/export` downloads a **ZIP** (`storefront_bundle` v2) with `manifest.json` + `media/`. Includes: settings (secrets redacted), business location `storefront_address` overlays, shipping classes/zones/methods, storefront/both channel coupons, category shelf + brand image overlays, product featured / shipping class / variation sale prices, and catalog translations. `POST /storefront/settings/import` accepts that ZIP or a legacy settings-only JSON. Catalog overlays/translations match by slug/SKU (parents must already exist). **Excluded:** orders, wishlist, reviews, coupon redemptions, shipments, digital fulfillments, and secret values (blank secrets preserve current).
- Select selling locations (catalog is empty when none selected)
- COD, **shipping zones** (governorate matching, flat / free / pickup methods; digital-only free rate), announcement, gateway (FawryPay: merchant code, security key, staging), contact/social
- **Shipping classes** + optional product `shipping_class_id` / weight for per-class and per-kg flat costs
- **Couriers** (optional Bosta API key; staging defaults **off** / production) — create shipment via `POST /deliveries/bulk` when marking shipped with carrier `bosta`; requires checkout `district_id`; public settings expose `couriers.bosta.enabled` only
- **Digital catalog** — enable flag, Accounts store profile ID, POS product IDs for primary/secondary/gift-card lines; `pos_document_type` (`sell` \| `quotation`); `expose_credentials_to_customer` (default true; when false secrets stay on POS staff note only). Public `GET /settings` exposes `digital.enabled` only
- **Footer payment icons** (`payment_icons`) — label + uploaded image or external URL; public API returns `{ label, icon_url }`
- **Footer menus** (`footer`) — contact column title + up to 3 link columns (EN/AR labels, path or absolute URL); public API returns locale-resolved titles/labels. Qwik footer column 1 lists non-selling active locations from `GET /locations` (display address + Visit us / Call us) and social/WhatsApp; columns 2–4 render `footer.columns`.
- **Promotional banners** (`banners`) — homepage / category image banners (upload or URL + link + EN/AR title); public API returns enabled rows only
- **Homepage sections** (`homepage_sections`) — ordered section builder on Storefront Settings → Homepage tab; public `GET /homepage` returns enabled sections only (`hero_slider`, `promo_tiles`, `video`, `trust_badges`, `promo_banner` compositional, `promo_banners` legacy, `featured_products`, `top_categories`, `category_shelves` legacy, `category_shelf`, `brand_slider`, `bestsellers`, `recently_viewed`). Prefer `promo_banner` (logo/titles/bg/border/positioned image/CTA) over legacy `promo_banners`, and `category_shelf` over legacy `category_shelves`. Hero/promo/video/banner/trust media live in section settings; featured still uses product flags. Media uploads go through the **storefront media library** (`uploads/storefront_library/{business_id}/`, checksum-deduped; POS: `GET/POST /storefront/media`, `DELETE /storefront/media/{id}`; section `image` fields store library-relative paths or legacy `storefront_homepage` filenames). Trust badge icons are **file URLs only** in the API (no `svg_markup` — avoids settings OOM). The Qwik storefront fetches those SVG URLs, sanitizes them, and inlines markup via `RemoteSvg` + `icon_color` / `currentColor` (falls back to `<img>`). Cross-origin browser fetch still needs ACAO on `/uploads/storefront_*` (see CORS note below); SSR fetch does not.
- **Homepage shelves** — edit on product **Categories** (`/taxonomies?type=product`): enable shelf, sort, side banner image, heading, banner copy/button/link; public `GET /categories/homepage-shelves`
- **Product “Featured on storefront”** — `products.is_storefront_featured`; filter with `GET /products?featured=1`
- **Category / brand images** — optional `image` upload on POS category/brand forms; public API exposes `image_url`
- **Newsletter** (`newsletter`) — enable + provider (`mailchimp` / `mailerlite` / `aweber`) + encrypted API credentials; public `GET /settings` exposes `newsletter.enabled` only
- **Cloudflare Turnstile** (`turnstile.site_key`, encrypted `turnstile.secret_key`) — when both are set, contact, registration, and newsletter require verification; public `GET /settings` exposes `turnstile.enabled` and `turnstile.site_key` only (never the secret)
- Theme accent color (`theme.accent_color`, 6-digit hex) — drives the Qwik `--gs-accent` CSS variable
- **Favicon** (`favicon.image` / `favicon.url`) — upload under `uploads/storefront_favicon/` or external URL; public `GET /settings` exposes `favicon_url` only
- Public `GET /settings` exposes `contact.email_encoded` (base64) instead of a raw email; the Qwik storefront decodes it client-side only (anti-harvesting)
- Public `GET /locations` lists active branches **excluding** selling locations (`?selling_only=1` for checkout); uses `email_encoded` per location (no raw `email` field)

**Promo codes (POS admin):** Settings → **Promo codes** (`/coupons`) — separate from automatic POS **Discounts**. Storefront visibility/stacking: **Storefront Settings → Promo codes (storefront checkout)** (`enabled_at_checkout`, `allow_stacking`). Types: percentage off order, fixed amount off order, free shipping. Limits: date window, min eligible subtotal, max discount cap, global/per-customer usage, first-order-only, exclude sale items, channel (`storefront` / `pos` / `both`). Permissions: `coupon.access`, `coupon.create`, `coupon.delete`.

### Checkout totals order

Sale price → **coupon** → shipping (zone quote; free-shipping coupon zeros delivery rates; free-shipping method min amount uses cart subtotal) → reward points → payment. Coupon + reward points stacking is controlled per coupon (`stack_with_reward_points`, default allowed).

## Notes

- Independent of WooCommerce module
- `storefront_order_id` on transactions for checkout idempotency
- CORS: configure `CORS_ALLOWED_ORIGINS` in `.env` (Laravel API only — `api/*` / Sanctum). That does **not** cover static `/uploads/…` files. Trust-badge SVG **masks** need ACAO on the file response:
  - **Apache / LiteSpeed:** `public/uploads/storefront_homepage/.htaccess` and `public/uploads/storefront_library/.htaccess` set `Access-Control-Allow-Origin: *`. Deploy those files, then **purge LiteSpeed Cache** (and any CDN) for `/uploads/storefront_*` — cached copies often omit the new headers.
  - **Nginx:**

```nginx
location ~* ^/uploads/storefront_(homepage|library)/ {
    add_header Access-Control-Allow-Origin "*" always;
    add_header Access-Control-Allow-Methods "GET, HEAD, OPTIONS" always;
    if ($request_method = OPTIONS) { return 204; }
}
```

  Verify: `curl -sI -H "Origin: https://new.gamesspoteg.com" "https://YOUR-POS/uploads/storefront_homepage/….svg"` must show `Access-Control-Allow-Origin`.
- Storefront site URL for reset emails: `STOREFRONT_URL` in `.env` (defaults to `APP_URL`, then `http://localhost:5173`)
- **Transactional mail (system-wide Mailgun API):** set `MAIL_MAILER=mailgun`, `MAILGUN_DOMAIN`, `MAILGUN_SECRET`, optional `MAILGUN_ENDPOINT` (`api.mailgun.net` or `api.eu.mailgun.net`), plus `MAIL_FROM_*`. Requires `symfony/mailgun-mailer` + `symfony/http-client`. Businesses that enable **Use superadmin email settings** send via this transport (From name/address can still come from business settings). Otherwise per-business SMTP in Business Settings is used. See root `.env.example`. Smoke test: `php artisan tinker` then `Mail::raw('…', fn ($m) => $m->to('you@example.com')->subject('Test'));` (authorize sandbox recipients in the Mailgun dashboard).
- Digital allocate when a sell becomes **paid** (any POS path via `updatePaymentStatus`): Accounts `receiveOrder`, credentials on Staff note + sell line. Retry: `php artisan storefront:fulfill-digital` (optional `--transaction=ID`)
- Rate limit (`throttle:storefront`, per IP): reads (GET/HEAD) use `STOREFRONT_RATE_LIMIT_READ` (default **600**/min); writes use `STOREFRONT_RATE_LIMIT` (default **120**/min). The Qwik SSR process also caches settings/categories for ~30s so layout loaders do not hit Laravel on every navigation.
- Auth endpoints (`/auth/register`, `/auth/login`, `/auth/forgot-password`, `/auth/reset-password`) use a tighter `throttle:storefront-auth` budget (`STOREFRONT_AUTH_RATE_LIMIT`, default **20**/min per IP).
- Password reset tokens expire after `STOREFRONT_PASSWORD_RESET_EXPIRE_MINUTES` (default **60**).
- Customer Sanctum bearer tokens expire after `STOREFRONT_SANCTUM_EXPIRATION_MINUTES` (default **43200** = 30 days). Password reset revokes all active storefront tokens; a new login also replaces any prior token (single active session).
- Qwik storefront (production): CSP via `src/routes/plugin@security.ts` — nonce + `strict-dynamic`, allows Fawry/Google Fonts/Maps, YouTube/Vimeo embeds (`frame-src`), and HTTPS media for self-hosted video; set `PUBLIC_CSP_REPORT_ONLY=true` to test without enforcing. See `storefront-qwik/.env.example`.
