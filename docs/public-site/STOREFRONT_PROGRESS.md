# Storefront implementation progress

**Canonical tracker** for the Games Spot public storefront (`storefront-qwik/` + Laravel `storefront/v1` API).  
**Spec (requirements):** [`README.md`](./README.md) · **API contract:** [`API.md`](./API.md)

| | |
|---|---|
| **Last updated** | 2026-07-04 |
| **Phase** | Phase 1 MVP — COD launch path |
| **Overall** | Core shop loop **done**; Sprint 1–2 launch hygiene **done**; **i18n / RTL v1 done**; homepage + SEO pack **done**; maintenance gate **done**; **Fawry online payments v1 done** |

**Status legend:** ✅ Done · 🟡 Partial · ⬜ Not started

---

## Summary

| Area | Status |
|------|--------|
| Storefront API (`routes/storefront.php`) | 🟡 Most endpoints done; coupons/wishlist/server cart N/A |
| Qwik shop (catalog → checkout → account) | 🟡 End-to-end COD + Fawry works |
| Header / footer spec | 🟡 Core wired; wishlist, policies missing; mini-cart done |
| i18n / RTL (AR + EN) | ✅ |
| Online payments (Fawry) | ✅ | Pluggable gateway layer; hosted FawryPay.checkout; webhook + return confirm |
| SEO launch pack (sitemap, legal, breadcrumbs) | ✅ Legal, robots/sitemap, PDP breadcrumbs + gallery, canonical/hreflang |
| Automated tests | 🟡 API feature tests incl. checkout E2E; no Qwik tests |

---

## Backend — Storefront API

| Item | Status | Notes |
|------|--------|-------|
| Versioned API `/api/storefront/v1` | ✅ | `routes/storefront.php`, `throttle:storefront` only (no `throttle:api`); read/write budgets |
| Settings, locations, categories | ✅ | `SettingsApiService`, `CatalogService` |
| Products list + detail + search | ✅ | Filters: category, brand, `q`, `in_stock_only`; sort: name, price, newest |
| Per-store availability | ✅ | All active locations; maps URL + coords |
| Cart validate (price + stock) | ✅ | Fulfillment `location_id` stock check |
| Checkout → POS transaction | ✅ | Idempotent `storefront_order_id`, guest + auth |
| Payment webhook + return + session | ✅ | `PaymentGatewayManager`, `FawryPaymentGateway`, `/payments/fawry/*` |
| Sanctum auth (Contact) | ✅ | Register, login, logout, forgot/reset password |
| Account profile, address, orders | ✅ | Invoice print URL for paid orders |
| Reward points API | ✅ | Balance + validate redeem |
| Contact form API | ✅ | Emails business SMTP user |
| Add-customer (in-store signup) | ✅ | `POST /customers/add`, geo + phone validation |
| Phone countries + geo states | ✅ | `PhoneCountryController`, `GeoController` |
| Storefront sale pricing on variations | ✅ | `storefront_sale_price_inc_tax`, `StorefrontPricing` |
| Order confirmation email | ✅ | `StorefrontMailService` |
| CORS / `STOREFRONT_URL` | ✅ | Documented in `API.md` |
| Catalog translations (`product_translations`, etc.) | ✅ | Overlay via `StorefrontContentPresenter`; strict AR list filter |
| `X-Content-Locale` middleware | ✅ | `ResolveStorefrontContentLocale` on API group |
| Storefront translations admin | ✅ | `/storefront/translations/{products,categories,brands}` — POS unchanged |
| Bilingual storefront settings (announcement, sale badge, RP name) | ✅ | EN + AR fields on `/storefront/settings` only |

---

## Frontend — Qwik routes (`storefront-qwik/src/routes/`)

| Route | Status | Notes |
|-------|--------|-------|
| `/` → `/en/` or `/ar/` | ✅ | `Accept-Language` redirect |
| `/[lang]/` Homepage | ✅ | Hero, featured categories, featured products, SEO |
| `/[lang]/products` Shop PLP | ✅ | Sort, in-stock filter, `?q=`; `X-Content-Locale` |
| `/[lang]/category/[slug]` | ✅ | Category PLP + pagination + locale filter |
| `/[lang]/products/[slug]` PDP | ✅ | Gallery + thumbs, breadcrumbs + JSON-LD, variations, cart, availability |
| `/[lang]/cart` | ✅ | Qty stepper, remove, subtotal, i18n |
| `/[lang]/checkout` | ✅ | COD + Fawry method picker, reward redeem |
| `/[lang]/checkout/payment` | ✅ | Lazy-load Fawry SDK, hosted checkout |
| `/[lang]/checkout/payment/return` | ✅ | Server-confirmed return + Pay-at-Fawry reference |
| `/[lang]/login`, register, forgot/reset | ✅ | Phone validation, Sanctum token in `localStorage` |
| `/[lang]/account/*` | ✅ | Dashboard, profile, orders, detail, invoice print |
| `/[lang]/contact` | ✅ | Form + branches + map |
| `/[lang]/about`, `/[lang]/faq` | ✅ | Locale modules (EN + AR) + FAQ JSON-LD |
| `/[lang]/terms-and-conditions`, privacy, return | ✅ | Legal copy EN + AR |
| `/[lang]/add-customer` | ✅ | Standalone in-store signup (no site shell) |
| `/[lang]/maintenance` | ✅ | 503 + noindex when `maintenance_mode`; redirects shop routes; `/add-customer` exempt |
| `robots.txt`, `sitemap.xml` | ✅ | Locale-prefixed disallow + per-locale product URLs |
| Wishlist, dedicated `/search` page | ⬜ | |

---

## Commerce & UX components

| Item | Status | Key paths |
|------|--------|-----------|
| Client cart (`localStorage`) | ✅ | `lib/cart-context.tsx` |
| Add to cart (PLP + PDP) | ✅ | `product-card.tsx`, PDP |
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
| Coupons / promo codes | ⬜ | |
| Cart price refresh from API | ⬜ | Validate on checkout only |

---

## Header & footer

| Item | Status | Notes |
|------|--------|-------|
| Logo, announcement bar | ✅ | `site-header.tsx` |
| Search → `/products?q=` + autocomplete | ✅ | `header-search.tsx` → `GET /search` |
| Categories drawer | ✅ | Top-level; not full nested tree |
| Main nav (shop, contact, FAQ, about, external trackers) | ✅ | `lib/header-nav.ts` |
| Cart badge + subtotal + mini-cart dropdown | ✅ | `mini-cart.tsx` |
| Account link / name | ✅ | |
| Language switcher AR/EN | ✅ | Flag dropdown; `LanguageSwitcher` in header + maintenance page |
| Wishlist | ⬜ | |
| Footer contact, social, shop links | ✅ | `site-footer.tsx` |
| Footer policies, newsletter, payment icons | 🟡 Policies linked; newsletter/payment icons open | |

---

## Admin (POS back office)

| Item | Status | Path |
|------|--------|------|
| Storefront settings page | ✅ | `/storefront/settings`, `StorefrontSettingController` |
| Selling locations, COD, shipping, maintenance | ✅ | |
| Gateway FawryPay (merchant code, security key, staging) | ✅ | `/storefront/settings`; webhook URL shown in admin |
| Theme accent, sale badge, card availability toggle | ✅ | |
| Online sale price on products (POS forms) | ✅ | Variation + single product fields |
| Storefront display address on locations | ✅ | Used in public locations API |

---

## SEO & performance

| Item | Status | Notes |
|------|--------|-------|
| Route `head` (title, description, OG) | ✅ | Public routes via `withStorefrontThemeHead` + `publicSeoLinks` |
| JSON-LD (WebSite, Product, FAQPage, BreadcrumbList) | ✅ | Home, PDP (+ breadcrumbs), FAQ |
| `noindex` on cart, checkout, account, auth | ✅ | |
| Canonical / hreflang | ✅ | Public pages; RouterHead skips default when route sets canonical |
| Breadcrumbs (UI + schema) | ✅ | Contact, FAQ, legal, PDP (`Breadcrumbs` + BreadcrumbList) |
| `robots.txt` / `sitemap.xml` | ✅ | Dynamic routes `robots.txt`, `sitemap.xml` (products + categories from API) |
| Qwik lazy chunks / per-route CSS | 🟡 | Ongoing per project rules |

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
| Frontend (Qwik) tests | ⬜ |

---

## Recommended next (priority order)

1. **Additional payment gateways** — Paymob / MyFatoorah via `PaymentGatewayManager`.
2. **Cart price refresh from API** — re-price on cart page, not only checkout.
3. **Wishlist** — header icon + API when scoped.

---

## Changelog (recent)

| Date | Change |
|------|--------|
| 2026-07-04 | Guest cart merge on login (guest/user localStorage keys); header mini-cart dropdown with line items, remove, view cart + checkout. |
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
