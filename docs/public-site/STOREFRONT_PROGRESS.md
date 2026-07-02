# Storefront implementation progress

**Canonical tracker** for the Games Spot public storefront (`storefront-qwik/` + Laravel `storefront/v1` API).  
**Spec (requirements):** [`README.md`](./README.md) · **API contract:** [`API.md`](./API.md)

| | |
|---|---|
| **Last updated** | 2026-07-01 |
| **Phase** | Phase 1 MVP — COD launch path |
| **Overall** | Core shop loop **done**; Sprint 1–2 launch hygiene **done**; i18n and card payments **open** |

**Status legend:** ✅ Done · 🟡 Partial · ⬜ Not started

---

## Summary

| Area | Status |
|------|--------|
| Storefront API (`routes/storefront.php`) | 🟡 Most endpoints done; coupons/wishlist/server cart N/A |
| Qwik shop (catalog → checkout → account) | 🟡 End-to-end COD works |
| Header / footer spec | 🟡 Core wired; wishlist, mini-cart, policies missing |
| i18n / RTL (AR + EN) | ⬜ |
| Online card payments (checkout UI) | ⬜ Webhook exists; UI COD-only |
| SEO launch pack (sitemap, legal, breadcrumbs) | 🟡 Legal pages + robots/sitemap done; PDP breadcrumbs open |
| Automated tests | 🟡 API feature tests incl. checkout E2E; no Qwik tests |

---

## Backend — Storefront API

| Item | Status | Notes |
|------|--------|-------|
| Versioned API `/api/storefront/v1` | ✅ | `routes/storefront.php`, throttle + business middleware |
| Settings, locations, categories | ✅ | `SettingsApiService`, `CatalogService` |
| Products list + detail + search | ✅ | Filters: category, brand, `q`, `in_stock_only`; sort: name, price, newest |
| Per-store availability | ✅ | All active locations; maps URL + coords |
| Cart validate (price + stock) | ✅ | Fulfillment `location_id` stock check |
| Checkout → POS transaction | ✅ | Idempotent `storefront_order_id`, guest + auth |
| Payment webhook route | 🟡 | `PaymentWebhookController`; no checkout card flow |
| Sanctum auth (Contact) | ✅ | Register, login, logout, forgot/reset password |
| Account profile, address, orders | ✅ | Invoice print URL for paid orders |
| Reward points API | ✅ | Balance + validate redeem |
| Contact form API | ✅ | Emails business SMTP user |
| Add-customer (in-store signup) | ✅ | `POST /customers/add`, geo + phone validation |
| Phone countries + geo states | ✅ | `PhoneCountryController`, `GeoController` |
| Storefront sale pricing on variations | ✅ | `storefront_sale_price_inc_tax`, `StorefrontPricing` |
| Order confirmation email | ✅ | `StorefrontMailService` |
| CORS / `STOREFRONT_URL` | ✅ | Documented in `API.md` |

---

## Frontend — Qwik routes (`storefront-qwik/src/routes/`)

| Route | Status | Notes |
|-------|--------|-------|
| `/` Homepage | 🟡 | Product grid; no hero / featured categories yet |
| `/products` Shop PLP | ✅ | Sort, in-stock filter, `?q=`; toolbar wired to API |
| `/category/[slug]` | ✅ | Category PLP + pagination + sort / in-stock filters |
| `/products/[slug]` PDP | 🟡 | Variations, qty stepper, add-to-cart, availability modal; **single hero image** |
| `/cart` | ✅ | Qty stepper, remove, subtotal |
| `/checkout` | 🟡 | Guest + auth, stock validation, shipping, **COD only**, reward redeem |
| `/login`, `/register`, forgot/reset | ✅ | Phone validation, Sanctum token in `localStorage` |
| `/account/*` | ✅ | Dashboard, profile, orders, detail, invoice print |
| `/contact` | ✅ | Form + branches + map |
| `/about`, `/faq` | ✅ | Static content + FAQ JSON-LD |
| `/terms-and-conditions`, `/privacy-policy`, `/return-policy` | ✅ | Legal copy from gamesspoteg.com |
| `/add-customer` | ✅ | Standalone in-store signup (no site shell) |
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
| Mini-cart dropdown | ⬜ | Header links to `/cart` only |
| Guest cart merge on login | ⬜ | |
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
| Cart badge + subtotal | ✅ | |
| Account link / name | ✅ | |
| Language switcher AR/EN | ⬜ | API exposes `locales`; no UI |
| Wishlist | ⬜ | |
| Footer contact, social, shop links | ✅ | `site-footer.tsx` |
| Footer policies, newsletter, payment icons | 🟡 Policies linked; newsletter/payment icons open | |

---

## Admin (POS back office)

| Item | Status | Path |
|------|--------|------|
| Storefront settings page | ✅ | `/storefront/settings`, `StorefrontSettingController` |
| Selling locations, COD, shipping, maintenance | ✅ | |
| Gateway provider + API key (stored) | 🟡 | Not wired to Qwik checkout |
| Theme accent, sale badge, card availability toggle | ✅ | |
| Online sale price on products (POS forms) | ✅ | Variation + single product fields |
| Storefront display address on locations | ✅ | Used in public locations API |

---

## SEO & performance

| Item | Status | Notes |
|------|--------|-------|
| Route `head` (title, description, OG) | 🟡 | Most routes; not full `storefront-seo.mdc` checklist |
| JSON-LD (WebSite, Product, FAQPage) | 🟡 | Home, PDP, FAQ |
| `noindex` on cart, checkout, account, auth | ✅ | |
| Canonical / hreflang | ⬜ | |
| Breadcrumbs (UI + schema) | 🟡 | Contact, FAQ only |
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
| Frontend (Qwik) tests | ⬜ |

---

## Recommended next (priority order)

1. **Arabic / English + RTL** — language switcher + translations (Egypt market).
2. **Online payments** — checkout gateway UI + webhook marks paid (invoice print already works).
3. **Homepage + SEO** — hero, featured categories; PDP breadcrumbs + gallery.
4. **Maintenance mode gate** — respect `maintenance_mode` from settings in Qwik shell.

---

## Changelog (recent)

| Date | Change |
|------|--------|
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
