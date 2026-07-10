# Storefront implementation progress

**Canonical tracker** for the Games Spot public storefront (`storefront-qwik/` + Laravel `storefront/v1` API).  
**Spec (requirements):** [`README.md`](./README.md) · **API contract:** [`API.md`](./API.md)

| | |
|---|---|
| **Last updated** | 2026-07-11 |
| **Phase** | Phase 1 MVP — COD launch path |
| **Overall** | Core shop loop **done**; Sprint 1–2 launch hygiene **done**; **i18n / RTL v1 done**; homepage + SEO pack **done**; maintenance gate **done**; **Fawry online payments v1 done**; footer payment icons + newsletter providers **done** |

**Status legend:** ✅ Done · 🟡 Partial · ⬜ Not started

---

## Summary

| Area | Status |
|------|--------|
| Storefront API (`routes/storefront.php`) | ✅ | Includes coupons validate + cart/checkout coupon totals |
| Qwik shop (catalog → checkout → account) | 🟡 End-to-end COD + Fawry works |
| Header / footer spec | 🟡 Core wired; wishlist, policies, payment icons, newsletter, cookie consent done |
| i18n / RTL (AR + EN) | ✅ |
| Online payments (Fawry) | ✅ | Pluggable gateway layer; hosted FawryPay.checkout; webhook + return confirm |
| SEO launch pack (sitemap, legal, breadcrumbs) | ✅ Legal, robots/sitemap, PDP breadcrumbs + gallery, canonical/hreflang |
| Automated tests | 🟡 API feature tests incl. checkout E2E + `CouponTest`; no Qwik tests |

---

## Backend — Storefront API

| Item | Status | Notes |
|------|--------|-------|
| Versioned API `/api/storefront/v1` | ✅ | `routes/storefront.php`, `throttle:storefront` only (no `throttle:api`); read/write budgets |
| Settings, locations, categories | ✅ | `SettingsApiService`, `CatalogService` |
| Products list + detail + search | ✅ | Filters: category, brand (`brand_id` / `brand_slug`), `q`, `in_stock_only`; sort: name, price, newest; detail embeds `related_products[]` + `rating` + brand `slug` |
| Brands list + show | ✅ | `GET /brands`, `GET /brands/{slug}`; `brands.slug` migration; locale-strict AR |
| Product reviews API | ✅ | Submit (auth + purchase), list approved, eligibility; POS moderate |
| Per-store availability | ✅ | All active locations; maps URL + coords |
| Cart validate (price + stock) | ✅ | Fulfillment `location_id` stock check |
| Checkout → POS transaction | ✅ | Idempotent `storefront_order_id`, guest + auth |
| Payment webhook + return + session | ✅ | `PaymentGatewayManager`, `FawryPaymentGateway`, `/payments/fawry/*` |
| Sanctum auth (Contact) | ✅ | Register, login, logout, forgot/reset password; 30-day token TTL, reset revokes sessions |
| Account profile, address, orders | ✅ | Invoice print URL for paid orders |
| Reward points API | ✅ | Balance + validate redeem |
| Contact form API | ✅ | Emails business SMTP user |
| Newsletter subscribe API | ✅ | Pluggable Mailchimp/MailerLite/AWeber; Turnstile when configured |
| Add-customer (in-store signup) | ✅ | `POST /customers/add`, geo + phone validation |
| Phone countries + geo states | ✅ | `PhoneCountryController`, `GeoController` |
| Storefront sale pricing on variations | ✅ | `storefront_sale_price_inc_tax`, `StorefrontPricing` |
| Order confirmation email | ✅ | `StorefrontMailService` |
| CORS / `STOREFRONT_URL` | ✅ | Documented in `API.md` |
| Product description HTML sanitization | ✅ | `StorefrontHtmlSanitizer` on PDP API responses |
| Catalog translations (`product_translations`, etc.) | ✅ | Overlay via `StorefrontContentPresenter`; strict AR list filter |
| `X-Content-Locale` middleware | ✅ | `ResolveStorefrontContentLocale` on API group |
| Storefront translations admin | ✅ | `/storefront/translations/{products,categories,brands}` — POS unchanged |
| Bilingual storefront settings (announcement, sale badge, RP name) | ✅ | EN + AR fields on `/storefront/settings` only |
| Wishlist API (list, add, remove, merge) | ✅ | `WishlistService`, `storefront_wishlist_items` |
| Promo codes (`coupons`, `coupon_redemptions`) | ✅ | Settings: show at checkout + allow stacking; multi-code API; POS admin `/coupons` |

---

## Frontend — Qwik routes (`storefront-qwik/src/routes/`)

| Route | Status | Notes |
|-------|--------|-------|
| `/` → `/en/` or `/ar/` | ✅ | `Accept-Language` redirect |
| `/[lang]/` Homepage | ✅ | Hero, promo banners, featured categories, featured products, recently viewed, SEO |
| `/[lang]/products` Shop PLP | ✅ | Sort, in-stock filter, `?q=`; `X-Content-Locale` |
| `/[lang]/category/[slug]` | ✅ | Category PLP + promo banners + pagination + locale filter |
| `/[lang]/brands` | ✅ | Brand index (sellable brands with slug) |
| `/[lang]/brands/[slug]` | ✅ | Brand PLP + sort/stock toolbar + pagination; `brand_slug` filter |
| `/[lang]/products/[slug]` PDP | ✅ | Gallery + thumbs, breadcrumbs + JSON-LD (+ aggregateRating), variations, cart, availability, related, recently viewed, reviews, share; brand links to `/brands/{slug}` |
| `/[lang]/cart` | ✅ | Qty stepper, remove, subtotal, promo picker + manual code, i18n |
| `/[lang]/checkout` | ✅ | COD + Fawry method picker, promo picker + manual code, reward redeem |
| `/[lang]/checkout/payment` | ✅ | Lazy-load Fawry SDK, hosted checkout |
| `/[lang]/checkout/payment/return` | ✅ | Server-confirmed return + Pay-at-Fawry reference |
| `/[lang]/login`, register, forgot/reset | ✅ | Phone validation, Sanctum token in `localStorage`; Turnstile when configured; 30-day TTL; session-expired toast on 401 |
| `/[lang]/account/*` | ✅ | Dashboard, profile, orders, detail, invoice print |
| `/[lang]/contact` | ✅ | Form + branches + map; Turnstile when configured; link to store locator |
| `/[lang]/stores` | ✅ | Store locator: map + branch list (call / directions / pickup); `GET /locations` |
| `/[lang]/about`, `/[lang]/faq` | ✅ | Locale modules (EN + AR) + FAQ JSON-LD |
| `/[lang]/terms-and-conditions`, privacy, return | ✅ | Legal copy EN + AR |
| `/[lang]/add-customer` | ✅ | Standalone in-store signup (no site shell) |
| `/[lang]/maintenance` | ✅ | 503 + noindex when `maintenance_mode`; redirects shop routes; `/add-customer` exempt |
| `robots.txt`, `sitemap.xml` | ✅ | Locale-prefixed disallow + per-locale product URLs |
| Wishlist, dedicated `/search` page | ✅ | Wishlist done; `/[lang]/search` full results + header submit |

---

## Commerce & UX components

| Item | Status | Key paths |
|------|--------|-----------|
| Client cart (`localStorage`) | ✅ | `lib/cart-context.tsx` |
| Add to cart (PLP + PDP) | ✅ | `product-card.tsx`, PDP |
| Related products on PDP | ✅ | Same category → brand fill via detail API; `ProductCard` grid |
| Share buttons on PDP | ✅ | Native share + copy link + WhatsApp / Facebook / X; `product-share-buttons.tsx` |
| Recently viewed | ✅ | Client localStorage per locale; PDP + homepage; `recently-viewed.tsx` |
| Reviews & ratings | ✅ | Moderated; purchase-gated submit; PDP list/form; card stars; POS `/product-reviews` |
| Quantity stepper | ✅ | `components/ui/quantity-stepper.tsx` |
| Sale badge on cards | ✅ | Settings-driven `sale_badge` |
| OOS: disabled add-to-cart + optional card availability | ✅ | `catalog.show_availability_on_cards` |
| Availability modal | ✅ | `availability-modal.tsx`, `availability-check-button.tsx` |
| Pending / loading feedback | ✅ | `pending-context`, `with-pending-feedback` |
| Theme accent across SPA nav | ✅ | `lib/theme.ts`, `site-shell-context.tsx` |
| 4-column product grid (responsive) | ✅ | `.product-grid` in `global.css` |
| Reward points (account + checkout) | ✅ | `reward-points-summary.tsx`, `reward-points-redeem.tsx` |
| Phone input + dial code | ✅ | `PhoneInputWithDialCode` |
| Mini-cart dropdown | ✅ | `components/layout/mini-cart.tsx`, header trigger |
| Guest cart merge on login | ✅ | `lib/cart-actions.ts` guest/user keys + `cart-context.tsx` merge on auth |
| Coupons / promo codes | ✅ | Settings-gated; login required; picker + cart/checkout |
| Cart price refresh from API | ✅ | `/cart` inspect + auto-remove OOS; checkout guard with max qty notice |
| Wishlist (guest + account) | ✅ | `wishlist-context.tsx`, `header-wishlist.tsx`, `wishlist-toggle.tsx`, `/wishlist` |

---

## Header & footer

| Item | Status | Notes |
|------|--------|-------|
| Logo, announcement bar | ✅ | `site-header.tsx` |
| Search → `/search?q=` + autocomplete | ✅ | `header-search.tsx` → dedicated `/search` + `GET /search` autocomplete |
| Categories drawer | ✅ | Top-level; not full nested tree |
| Brands nav + footer | ✅ | Header nav + footer shop link → `/brands` |
| Main nav (shop, stores, contact, FAQ, about, external trackers) | ✅ | `lib/header-nav.ts` |
| Cart badge + subtotal + mini-cart dropdown | ✅ | `mini-cart.tsx` |
| Account link / name | ✅ | |
| Language switcher AR/EN | ✅ | Flag dropdown; `LanguageSwitcher` in header + maintenance page |
| Wishlist | ✅ | Header heart + badge; guest localStorage; merge on login; PLP/PDP toggle; `/wishlist` page |
| Footer contact, social, shop links | ✅ | `site-footer.tsx` (includes store locator) |
| Footer policies, newsletter, payment icons | ✅ | Newsletter: Mailchimp/MailerLite/AWeber via settings; icons via `payment_icons` |
| Cookie / consent banner | ✅ | Client localStorage; Accept all / Necessary only; privacy link; `cookie-consent-banner.tsx` |

---

## Admin (POS back office)

| Item | Status | Path |
|------|--------|------|
| Storefront settings page | ✅ | `/storefront/settings`, `StorefrontSettingController` |
| Selling locations, COD, shipping, maintenance | ✅ | |
| Gateway FawryPay (merchant code, security key, staging) | ✅ | `/storefront/settings`; webhook URL shown in admin |
| Cloudflare Turnstile (site + secret key) | ✅ | `/storefront/settings`; encrypted secret; contact + register when both set |
| Theme accent, sale badge, card availability toggle | ✅ | |
| Footer payment icons (upload / URL) | ✅ | `/storefront/settings` → `payment_icons`; public `GET /settings` |
| Promotional banners (home / category) | ✅ | `/storefront/settings` → Banners tab; `banners[]` on settings; Qwik home + category |
| Newsletter (Mailchimp / MailerLite / AWeber) | ✅ | `/storefront/settings` Newsletter tab; `POST /newsletter/subscribe`; encrypted secrets |
| Product reviews moderation | ✅ | `/product-reviews` DataTables approve/reject; `product_review.*` permissions |
| Online sale price on products (POS forms) | ✅ | Variation + single product fields |
| Storefront display address on locations | ✅ | Used in public locations API |

---

## SEO & performance

| Item | Status | Notes |
|------|--------|-------|
| Route `head` (title, description, OG) | ✅ | Public routes via `withStorefrontThemeHead` + `publicSeoLinks` |
| JSON-LD (WebSite, Product, FAQPage, BreadcrumbList, Store ItemList) | ✅ | Home, PDP (+ breadcrumbs), FAQ, store locator |
| `noindex` on cart, checkout, account, auth | ✅ | |
| Canonical / hreflang | ✅ | Public pages; RouterHead skips default when route sets canonical |
| Breadcrumbs (UI + schema) | ✅ | Contact, FAQ, legal, PDP (`Breadcrumbs` + BreadcrumbList) |
| `robots.txt` / `sitemap.xml` | ✅ | Dynamic routes `robots.txt`, `sitemap.xml` (products + categories from API) |
| Qwik lazy chunks / per-route CSS | 🟡 | Ongoing per project rules |
| CSP + security headers (production) | ✅ | `plugin@security.ts`; nonce + strict-dynamic; Cloudflare Turnstile allowed when enabled; skipped in dev |
| PDP HTML sanitization (DOMPurify) | ✅ | `SanitizedHtml` + API `StorefrontHtmlSanitizer` |
| Safe JSON-LD serialization | ✅ | `serializeJsonLd` escapes `<`/`>`/`&` |

---

## Tests (`tests/Feature/Storefront/`, `tests/Unit/`)

| Suite | Status |
|-------|--------|
| Ping, catalog, auth, password reset | ✅ |
| Product search autocomplete API | ✅ | `GET /search` |
| Settings / locations email obfuscation | ✅ |
| Availability structure + all locations | ✅ |
| Cart validate at fulfillment location | ✅ |
| Category slug API | ✅ |
| Contact form | ✅ |
| Add-customer + geo + phone | ✅ |
| Reward points | ✅ |
| Invoice print URL (unit) | ✅ |
| Checkout E2E feature test | ✅ | `StorefrontCheckoutTest` |
| Fawry checkout + webhook | ✅ | `FawryPaymentTest`, `FawryPaymentGatewayTest` |
| Wishlist API | ✅ | `WishlistTest` |
| Product reviews API | ✅ | `ProductReviewTest` |
| Brand slug API | ✅ | `BrandSlugApiTest` |
| HTML sanitizer (unit) | ✅ | `StorefrontHtmlSanitizerTest` |
| Frontend (Qwik) tests | ⬜ |

---

## Recommended next (priority order)

1. Returns / cancel order (larger post-purchase)
2. Auto-generate brand `slug` on POS create/update (new brands after migration)

---

## Changelog (recent)

| Date | Change |
|------|--------|
| 2026-07-11 | PDP share buttons: native Web Share, copy link, WhatsApp / Facebook / X. |
| 2026-07-11 | Brand pages: `brands.slug`, `GET /brands` + `/brands/{slug}`, products `brand_slug`, Qwik `/brands` + `/brands/[slug]` PLP, nav/footer/sitemap, PDP brand link. |
| 2026-07-11 | Cookie consent banner: Accept all / Necessary only, privacy link, localStorage; `hasAnalyticsConsent()` for future tags. |
| 2026-07-11 | Promotional banners: admin Banners tab (home/category), `GET /settings` → `banners[]`, Qwik home + category render. |
| 2026-07-11 | Product reviews & ratings: moderated submit (auth + purchase), public list, PDP/PLP stars, POS approve/reject, denormalized product rating. |
| 2026-07-11 | Recently viewed: per-locale localStorage history; PDP records + grid; homepage section when non-empty. |
| 2026-07-11 | Store locator `/[lang]/stores`: map + branch list from `GET /locations`, nav/footer/sitemap, Store ItemList JSON-LD. |
| 2026-07-10 | Related products on PDP: `related_products[]` on product detail (category then brand), Qwik section with ProductCard grid. |
| 2026-07-10 | Dedicated `/[lang]/search` page: full product results (sort/stock/pagination), header submit + “view all” → `/search?q=`, `noindex` + robots disallow. |
| 2026-07-10 | Newsletter signup: pluggable Mailchimp/MailerLite/AWeber, admin settings, `POST /newsletter/subscribe`, footer form + Turnstile when configured. |
| 2026-07-10 | Footer payment icons: admin setting (upload or URL), `GET /settings` → `payment_icons[]`, Qwik footer render. |
| 2026-07-05 | Sanctum sessions: 30-day token TTL (`STOREFRONT_SANCTUM_EXPIRATION_MINUTES`), revoke all tokens on password reset, client 401 handler + toast. |
| 2026-07-05 | Cloudflare Turnstile: admin settings (site + encrypted secret), server verify on contact/register when configured, Qwik widget + CSP, API tests. |
| 2026-07-05 | XSS/CSP: CSP + security headers (`plugin@security.ts`), DOMPurify on PDP, API HTML sanitizer, safe JSON-LD. |
| 2026-07-05 | Harden auth/wishlist: reset token expiry, auth rate limit, wishlist caps + batch merge. |
| 2026-07-05 | Cart inspect API (`resolve: true`); auto-remove OOS lines; checkout blocked with per-item max quantity notice. |
| 2026-07-05 | Cart page re-prices via `POST /cart/validate` on load/qty change; stock errors surfaced inline. |
| 2026-07-04 | Guest cart merge on login (guest/user localStorage keys); header mini-cart dropdown with line items, remove, view cart + checkout. |
| 2026-07-05 | Promo codes v1: `coupons` domain, POS admin `/coupons`, `POST /coupons/validate`, cart/checkout coupon totals + redemption, Qwik cart/checkout UI, `CouponTest`, API.md. |
| 2026-07-05 | Promo codes require storefront login: API rejects guest `coupon_code`; cart/checkout hide coupon field for guests. |
| 2026-07-05 | Promo code picker: `POST /coupons/available`, fancy selectable list on cart/checkout (code + savings), tests + API.md. |
| 2026-07-05 | Promo code storefront settings: enable/disable checkout field; single vs stacked coupons per order; Qwik UI + tests. |
| 2026-07-04 | Fawry Pay v1: pluggable `PaymentGatewayManager`, signed checkout session, webhook/return confirm, Qwik payment routes, admin Fawry settings, tests. |
| 2026-07-04 | Maintenance mode gate: redirect to `/[lang]/maintenance/` (503, noindex); `/add-customer` exempt; EN/AR copy + language switcher. |
| 2026-07-04 | Homepage hero + featured categories; PDP gallery/thumbs + breadcrumbs/JSON-LD; canonical/hreflang on public pages. |
| 2026-07-04 | Wire remaining UI chrome (checkout, auth, account, contact, cart, PDP, add-customer, about) through `en.json`/`ar.json`; about page locale content module. |
| 2026-07-04 | Fix SPA pagination (trailing-slash URLs); stop 429s on shell loaders (drop double throttle, higher GET budget, 30s SSR cache for settings/categories). |
| 2026-07-01 | Sprint 2: PLP sort/in-stock toolbar (`product-list-toolbar`), header search autocomplete (`GET /search`), search API tests. |
| 2026-07-01 | Sprint 1 launch hygiene: legal pages (terms, privacy, return), footer policy links, dynamic robots.txt + sitemap.xml, checkout E2E tests. |
| 2026-06-30 | Progress tracker created; reflects Phase 1 MVP state through reward points, contact, add-customer, invoice print, OOS card actions, 4-col grid, theme SPA fix. |

---

## How agents should update this file

See [`.cursor/rules/storefront-progress-tracker.mdc`](../../.cursor/rules/storefront-progress-tracker.mdc).

When you **complete**, **partially ship**, or **explicitly defer** storefront work:

1. Update the relevant table row(s) and **Last updated** date.
2. Adjust **Summary** and **Recommended next** if priorities changed.
3. Add a line to **Changelog**.
4. Do **not** duplicate the full feature spec here — link to `README.md` for requirements.
