# Public Customer Storefront — Feature Requirements

> **Implementation status (living doc):** [`STOREFRONT_PROGRESS.md`](./STOREFRONT_PROGRESS.md) — what is built vs still open. Agents and developers should keep it updated as work ships.

This document defines the features a **public-facing e-commerce website** should provide so customers get a **professional online shopping experience**, while staying integrated with the **Games Spot POS** back office (Ultimate POS).

The storefront is the customer layer; the POS remains the **single source of truth** for products, inventory, pricing, orders, customers, payments, and fulfillment.

---

## Table of Contents

1. [Overview](#overview)
2. [Technical Architecture](#technical-architecture)
   - [Mobile app (later)](#mobile-app-later)
3. [Architecture & Integration Principles](#architecture--integration-principles)
4. [Storefront Experience (Public, No Login)](#storefront-experience-public-no-login)
5. [Product Catalog & Discovery](#product-catalog--discovery)
6. [Product Detail Pages](#product-detail-pages)
   - [Store availability modal](#store-availability-modal)
7. [Shopping Cart & Checkout](#shopping-cart--checkout)
8. [Customer Accounts](#customer-accounts)
9. [Orders, Fulfillment & After-Sales](#orders-fulfillment--after-sales)
10. [Payments](#payments)
11. [Shipping & Delivery](#shipping--delivery)
12. [Promotions, Pricing & Loyalty](#promotions-pricing--loyalty)
13. [Content, Marketing & Trust](#content-marketing--trust)
14. [Support & Communication](#support--communication)
15. [Search, SEO & Performance](#search-seo--performance)
16. [Security, Privacy & Compliance](#security-privacy--compliance)
17. [Admin & POS Integration Requirements](#admin--pos-integration-requirements)
18. [Non-Functional Requirements](#non-functional-requirements)
19. [Mapping to Current POS Capabilities](#mapping-to-current-pos-capabilities)
20. [Suggested Implementation Phases](#suggested-implementation-phases)
21. [Out of Scope (Initial Web Release)](#out-of-scope-initial-web-release)

---

## Overview

### Goals

| Goal | Description |
|------|-------------|
| **Professional storefront** | Modern, fast, mobile-first shop that matches a retail brand (Games Spot). |
| **Real-time accuracy** | Product availability, prices, and order status reflect POS data. |
| **Unified operations** | Online orders flow into the same sales, inventory, and customer records as in-store POS. |
| **Self-service** | Customers can browse, buy, track orders, and manage their account without staff intervention. |
| **Scalable foundation** | Feature set supports growth: more locations, payment methods, languages, and marketing tools. |

### Target users

- **Guest shoppers** — browse and checkout without an account (optional registration).
- **Registered customers** — order history, saved addresses, wishlists, loyalty points, support tickets.
- **Returning / B2B customers** — price groups, credit limits, quotations (if enabled in POS).

### Platform

| Decision | Choice |
|----------|--------|
| **Public shop** | **Fully custom-developed** website — no WooCommerce, no WordPress. |
| **Backend** | **Laravel 11** (this POS codebase) — products, stock, orders, customers, payments. |
| **Frontend (web)** | **Qwik** + Qwik City — separate public website consuming the Storefront API. |
| **Mobile app** | **Planned later** — native iOS/Android app using the **same Storefront API** (not in initial web launch). |

### What this is not

- The existing **CRM customer portal** (`/contact/*`) — B2B reorder portal only, not the public shop.
- **WooCommerce / WordPress** — not used for online sales; no duplicate catalog or sync layer.
- **An extension of the POS admin UI** — AdminLTE/jQuery screens are staff-only; the shop has its own design and codebase.

The public storefront is a **new customer-facing application** backed by a dedicated **Storefront API** on this Laravel POS.

---

## Technical Architecture

The public website and future mobile app are **API clients** of this Laravel POS. All catalog, cart, checkout, and account logic lives in Laravel; Qwik (web) and the mobile app consume the same JSON API so business rules are never duplicated.

```
Browser  →  Qwik City (SSR/SSG)  →  Storefront API (Laravel)  →  POS services / Eloquent  →  Database
              shop.gamesspoteg.com           pos.gamesspoteg.com/api/storefront/v1/*
```

```
                    ┌─────────────────────────────────────┐
                    │     Storefront API (Laravel)        │
                    │  pos.gamesspoteg.com/api/storefront │
                    └──────────────┬──────────────────────┘
                                   │
              ┌────────────────────┼────────────────────┐
              │                    │                    │
              ▼                    ▼                    ▼
     ┌────────────────┐  ┌────────────────┐  ┌────────────────┐
     │  Qwik website  │  │  Mobile app    │  │  (future clients)│
     │  shop / www    │  │  iOS / Android │  │                │
     │  Phase 1       │  │  Phase 4       │  │                │
     └────────────────┘  └────────────────┘  └────────────────┘
```

### Stack

| Layer | Technology | Status |
|-------|------------|--------|
| **Backend / POS** | Laravel 11 (this codebase) | Existing |
| **Storefront API** | Laravel REST — `/api/storefront/v1/*`, versioned and documented | To build |
| **Public website** | **Qwik** + **Qwik City** + Tailwind CSS + Qwik signals/store + i18n (AR/EN, RTL) | Phase 1 |
| **Web auth** | Laravel **Sanctum** — SPA cookie or API token for Qwik | To build |
| **Mobile app** | Native iOS/Android → same Storefront API + **Passport** bearer tokens | Phase 4 |
| **Payments** | Gateway checkout from Qwik (and later mobile) → webhook/callback to Laravel | To build |
| **Search** | Laravel Scout + Meilisearch/Algolia, or DB search for MVP | TBD |
| **Cache** | Redis for API catalog cache; short TTL on stock endpoints | TBD |
| **Media** | Product images from POS storage / S3 — stable public URLs from API | Existing |

**Domains (example):**

- `pos.gamesspoteg.com` — staff POS & admin (existing)
- `www.gamesspoteg.com` or `shop.gamesspoteg.com` — Qwik public storefront
- API: `pos.gamesspoteg.com/api/storefront/v1/*` or `api.gamesspoteg.com`

### Project structure

| Part | Location | Role |
|------|----------|------|
| Storefront API | This repo — `Modules/Storefront` or `app/Http/Controllers/Api/Storefront/` | Products, cart, checkout, customer auth, orders |
| Public website | Separate repo or `/storefront-qwik/` in monorepo | Qwik City, Tailwind CSS, i18n |
| POS admin | Existing Laravel app | Unchanged — staff-only |
| Mobile app | Separate repo (later) | iOS/Android — consumes same API |

### Why Qwik for the web storefront

| Benefit | Relevance to Games Spot |
|---------|-------------------------|
| **Resumability** | Minimal JavaScript on first load — fast catalog and PDP on mobile networks. |
| **Qwik City SSR/SSG** | Server-rendered HTML for SEO (products, categories, meta tags, structured data). |
| **Fine-grained reactivity** | Efficient cart updates, filters, and modals (e.g. store availability) without heavy client bundles. |
| **API-first fit** | Qwik fetches from the Laravel Storefront API — same contract the mobile app will use later. |
| **Progressive enhancement** | Public pages stay fast; interactivity loads only where needed. |

### Mobile app (later)

A **native mobile app** (iOS and Android) will be built **after** the Qwik web storefront and Storefront API are stable. It is not part of the initial web launch but must be planned for from day one when designing the API.

| Requirement | Detail |
|-------------|--------|
| **Shared API** | Mobile uses the **same** `api/storefront/v1/*` endpoints as Qwik — no separate mobile-only backend. |
| **Authentication** | **Laravel Passport** OAuth2 / personal access tokens for mobile login, refresh tokens, and secure session handling (Passport is already in this POS project). |
| **Web auth** | **Laravel Sanctum** for Qwik (cookie-based SPA or token-based, depending on domain setup). |
| **Feature parity (target)** | Browse catalog, cart, checkout, order history, profile, push notifications (order status), reward points. |
| **Push notifications** | Order shipped/delivered, promotions — via FCM (Android) / APNs (iOS); Laravel queues notification jobs. |
| **Deep links** | Product and order URLs open correct screen in app (`gamesspoteg://product/{slug}`). |
| **Payments** | In-app gateway SDK or redirect to secure web checkout — gateway webhooks still hit Laravel. |
| **Offline / cache** | Cache catalog browsing where sensible; checkout and stock checks always require network. |

**API design rules (for mobile readiness from Phase 1):**

- Version all routes under `/api/storefront/v1/`.
- Use consistent JSON shapes; paginate list endpoints.
- Never assume browser cookies — support `Authorization: Bearer` on all authenticated routes.
- Document every endpoint (OpenAPI / Scribe) before mobile development starts.
- Use idempotency keys on checkout and payment endpoints.

### Excluded from this project

| Excluded | Reason |
|----------|--------|
| **WooCommerce / WordPress** | Fully custom shop; no sync layer or duplicate catalog. |
| **Extending AdminLTE for the shop** | Admin theme is not suitable for consumer e-commerce. |
| **CRM portal as the public shop** | `/contact/*` remains B2B reorder only. |

The existing WooCommerce module in this repo (if enabled) is **out of scope** for the public storefront and may be disabled or ignored for online sales once the custom site is live.

---

## Architecture & Integration Principles

| Principle | Requirement |
|-----------|-------------|
| **Single inventory source** | Stock levels come from POS `business_locations`; no duplicate stock tables on the storefront. |
| **Single product catalog** | Products, variants, categories, brands, images, and prices are read from POS via the Storefront API — no WooCommerce or external catalog. |
| **Unified customer record** | Storefront accounts map to POS `contacts` (customer type). |
| **Unified orders** | Online orders create POS `transactions` (type: sell) with correct status, location, tax, and payment lines. |
| **Idempotent API** | Checkout and payment webhooks must handle retries without duplicate orders or stock errors. |
| **Location-aware** | Support one or more fulfillment locations; show stock and delivery options per location/region. |
| **Offline-safe POS** | Storefront must not block in-store sales; use reserved stock or real-time checks with clear oversell rules. |
| **API-first, multi-client** | Storefront API serves **Qwik web now** and **mobile app later** — token-based auth, versioned routes, no web-only assumptions. |

Architecture: **Laravel Storefront API + Qwik web** (+ mobile app in Phase 4). See [Technical Architecture](#technical-architecture).

---

## Storefront Experience (Public, No Login)

Features every visitor should have without signing in.

| Feature | Description | Priority |
|---------|-------------|----------|
| **Responsive layout** | Mobile-first design; usable on phone, tablet, desktop. | Must |
| **Homepage** | Hero, featured categories, promotions, bestsellers, new arrivals, trust badges. | Must |
| **Global header** | Logo, search, category menu, **language switcher (AR/EN)**, **wishlist**, cart icon with count, account/login link. See [Header & Footer spec](./README-HEADER-FOOTER.md). | Must |
| **Global footer** | Contact info, social links, policies, newsletter signup, payment icons. See [Header & Footer spec](./README-HEADER-FOOTER.md). | Must |
| **Category navigation** | Multi-level category tree matching POS categories. | Must |
| **Language switcher** | Arabic / English in header utility cluster (always visible). | Must |
| **Currency display** | Show prices in business default currency; format per locale. | Must |
| **Store locator** | Branches, hours, map, click-to-call (uses POS `business_locations`). | Should |
| **Cookie / consent banner** | GDPR-style consent where required. | Should |

---

## Product Catalog & Discovery

| Feature | Description | POS tie-in | Priority |
|---------|-------------|------------|----------|
| **Product listing (PLP)** | Grid/list of products per category, brand, or collection. | Categories, brands, products | Must |
| **Filters** | Category, brand, price range, availability, attributes/variations. | Variations, custom fields | Must |
| **Sorting** | Price, name, newest, popularity, relevance. | — | Must |
| **Pagination / infinite scroll** | Server-side pagination for large catalogs. | — | Must |
| **Search** | Full-text search with autocomplete (name, SKU, brand, category). | Product name, SKU, barcode | Must |
| **Search suggestions** | Popular searches, recent searches, zero-result handling. | — | Should |
| **Brand pages** | Dedicated pages per brand. | Brands module | Should |
| **Collections / tags** | Curated groups (e.g. "PlayStation Deals", "New Releases"). | Custom or CMS | Should |
| **Out-of-stock visibility** | Show product but mark unavailable, or hide based on setting. | `enable_stock`, qty | Must |
| **Low-stock messaging** | "Only X left" when below threshold. | Location stock | Could |
| **Barcode / SKU display** | Visible on product page for support and B2B. | `sku`, `sub_sku` | Should |

### Product types to support

| Type | Storefront behavior |
|------|-------------------|
| **Single product** | Simple add-to-cart. |
| **Variable product** | Select options (e.g. platform, edition, region); price/stock per variation. |
| **Combo / bundle** | Show included items and bundle price; validate component stock. |
| **Digital / service** | No shipping; instant or email delivery workflow. |
| **Subscription** (if used) | Recurring billing UI tied to POS subscription module. |

> **Note:** Combo/bundle products must define explicit storefront behavior (component display, bundle pricing, stock validation across all included items). POS combo logic is the source of truth.

---

## Product Detail Pages

| Feature | Description | Priority |
|---------|-------------|----------|
| **Image gallery** | Multiple images, zoom, swipe on mobile. | Must |
| **Title & description** | Rich description, specifications, compatibility notes. | Must |
| **Price** | Regular price, sale price, price including/excluding tax (per business rules). | Must |
| **Variation selector** | Dropdowns or swatches for variable products. | Must |
| **Stock status** | In stock / out of stock / pre-order per location. | Must |
| **Check availability by store** | Button opens modal listing all store locations with in-stock (✓) or out-of-stock (✗) per branch. See [Store availability modal](#store-availability-modal) below. | Must |
| **Quantity selector** | Min/max qty, step quantity. | Must |
| **Add to cart** | With loading state and success feedback (toast). | Must |
| **Add to wishlist** | Requires account or session. | Should |
| **Share buttons** | Social share, copy link. | Could |
| **Related products** | Same category, brand, or frequently bought together. | Should |
| **Recently viewed** | Session or account-based history. | Could |
| **Reviews & ratings** | Star ratings, moderated reviews, verified purchase badge. | Should |
| **Q&A** | Customer questions on product page. | Could |
| **Structured data** | Schema.org Product JSON-LD for SEO. | Must |
| **Breadcrumbs** | Category trail for navigation and SEO. | Must |

### Store availability modal

On the **product detail page (PDP)**, customers can see whether the item is in stock at each physical store without leaving the page.

#### Trigger

| Element | Behavior |
|---------|----------|
| **"Check availability" link/button** | Placed near stock status or add-to-cart (e.g. "Check availability in stores"). Opens a modal on click. |
| **Variable products** | Availability reflects the **currently selected variation**. If no variation is selected, prompt the customer to choose one first, or show availability for the default variation. |

#### Modal content

The modal lists every **online-visible store location** from POS (`business_locations`) that participates in retail / pickup.

| Column / row field | Description |
|--------------------|-------------|
| **Location name** | Branch name (e.g. "Games Spot — Maadi"). |
| **Address** | Full street address, city, and optional landmarks. |
| **Availability icon** | **✓ (tick)** — product is in stock at this location (qty &gt; 0 and stock tracking allows sale). **✗ (cross)** — not available at this location (qty = 0, disabled for online, or out of stock). |
| **Optional: quantity hint** | "In stock" / "Out of stock" text label beside the icon; optionally "Low stock" when below threshold. |
| **Optional: actions** | Link to maps/directions, phone (click-to-call), or "Pickup from this store" when customer chooses in-store pickup at checkout. |

#### Example layout (modal)

```
┌─────────────────────────────────────────────────┐
│  Availability in stores                    [×]  │
├─────────────────────────────────────────────────┤
│  PlayStation 5 — Standard Edition               │
│  (updates when customer changes variation)      │
├─────────────────────────────────────────────────┤
│  ✓  Games Spot — Head Office                    │
│     123 Main St, Cairo                            │
│     In stock                                      │
├─────────────────────────────────────────────────┤
│  ✓  Games Spot — Maadi                          │
│     45 Road 9, Maadi, Cairo                       │
│     In stock                                      │
├─────────────────────────────────────────────────┤
│  ✗  Games Spot — Alexandria                     │
│     10 Corniche Rd, Alexandria                    │
│     Out of stock                                  │
├─────────────────────────────────────────────────┤
│                              [ Close ]          │
└─────────────────────────────────────────────────┘
```

#### UX rules

| Rule | Detail |
|------|--------|
| **Loading state** | Show skeleton or spinner while fetching per-location stock from the API. |
| **Empty / error** | If the API fails, show a friendly message and retry option; do not show stale data without indication. |
| **Accessibility** | Modal is keyboard-trap safe; ✓/✗ paired with text ("In stock" / "Out of stock") for screen readers. |
| **Mobile** | Full-width or bottom-sheet modal on small screens; list scrolls if many locations. |
| **No login required** | Available to guest and logged-in customers. |
| **Real-time data** | Stock counts come from POS per location; refresh on modal open (not cached for long periods). |

#### POS / API requirements

| Requirement | Source |
|-------------|--------|
| List of public store locations | `business_locations` (name, address, phone, map coords, `is_active`, flag for "show on storefront") |
| Stock per location for product/variation | POS inventory qty by `location_id` + `product_id` / `variation_id` |
| Respect POS stock settings | Honor `enable_stock`, allow overselling rules, and location-specific sellable qty |

**Suggested endpoint:** `GET /api/storefront/products/{id}/availability?variation_id={optional}`  
Returns an array of `{ location_id, name, address, phone, in_stock, qty_available }`.

---

## Shopping Cart & Checkout

### Cart

| Feature | Description | Priority |
|---------|-------------|----------|
| **Persistent cart** | Survives refresh; merge guest cart on login. | Must |
| **Mini cart** | Header dropdown with line items and subtotal. | Must |
| **Cart page** | Edit qty, remove items, apply coupon, estimate shipping. | Must |
| **Stock validation** | Re-check availability on cart view and before checkout. | Must |
| **Price refresh** | Update prices if changed in POS since add-to-cart. | Must |
| **Save for later** | Move items out of active cart. | Could |
| **Cart abandonment** | Email reminder (marketing integration). | Could |

### Checkout flow

| Feature | Description | Priority |
|---------|-------------|----------|
| **Guest checkout** | Buy without registration; optional account creation after payment. | Must |
| **Multi-step or one-page** | Contact → shipping → payment → review (UX choice). | Must |
| **Address book** | Saved addresses for logged-in users. | Must |
| **Billing & shipping** | Same or different addresses; validate phone/email. | Must |
| **Delivery method selection** | Pickup, standard, express (see Shipping section). | Must |
| **Order summary** | Line items, discounts, tax breakdown, shipping, total. | Must |
| **Coupon / promo code** | Validate against POS discount rules. | Must |
| **Order notes** | Gift message, delivery instructions. | Should |
| **Terms acceptance** | Checkbox for terms & privacy policy. | Must |
| **Checkout loading states** | Disable double-submit; show spinner during payment. | Must |
| **Order confirmation page** | Order number, summary, next steps, print/email receipt. | Must |
| **Confirmation email** | Sent immediately after successful order. | Must |

---

## Customer Accounts

| Feature | Description | POS tie-in | Priority |
|---------|-------------|------------|----------|
| **Registration** | Email + password or phone OTP; email verification. | Create `contacts` | Must |
| **Login / logout** | Secure session or token-based auth. | Passport / Sanctum | Must |
| **Password reset** | Email (or SMS) reset flow. | — | Must |
| **Profile** | Name, email, phone, preferences. | Contact fields | Must |
| **Address management** | CRUD shipping/billing addresses. | Contact address | Must |
| **Order history** | List past orders with status and totals. | `transactions` | Must |
| **Order detail** | Items, payments, shipment tracking, invoice download. | Transactions, shipments | Must |
| **Reorder** | One-click reorder from past order (respect current stock/price). | — | Should |
| **Wishlist / favorites** | Save products for later. | — | Should |
| **Reward points balance** | Show earned/redeemed points; redeem at checkout. | Reward points API | Should |
| **Store credit / wallet** | If enabled in POS ledger. | Contact ledger | Could |
| **Notifications preferences** | Email/SMS marketing opt-in. | — | Should |
| **Support tickets / escalations** | Submit and track complaints (CRM escalation system). | CRM module | Should |
| **Repair status lookup** | Link to existing public repair status flow. | Repair module | Could |

> **Existing partial API:** Reward validate/redeem and contact lookup already exist under Passport (`routes/api.php`). Account features should extend these patterns.

---

## Orders, Fulfillment & After-Sales

| Feature | Description | Priority |
|---------|-------------|----------|
| **Order statuses** | Pending payment → processing → shipped → delivered → completed / cancelled. | Must |
| **Status notifications** | Email/SMS on status change. | Must |
| **Shipment tracking** | Tracking number and carrier link when shipped. | Should |
| **Partial shipments** | Split order across packages (if POS supports). | Could |
| **Cancel order** | Customer cancel before fulfillment; sync cancellation to POS. | Should |
| **Return / refund request** | Initiate RMA; staff approves in POS ([refund workflow](../../REFUND_PROCESS_WORKFLOW.md)). | Should |
| **Invoice download** | PDF invoice from order detail (tokenized link like `/invoice/{token}`). | Must |
| **Pay outstanding invoice** | Pay later via secure link (`/pay/{token}` pattern). | Should |

### Order creation in POS

When checkout completes, the storefront must create a POS sale with at minimum:

- Customer (`contact_id`)
- Business location (fulfillment location)
- Line items (product, variation, qty, unit price, tax)
- Discounts and coupons
- Shipping charges as a line or fee
- Payment method and payment status
- Shipping address and delivery method
- External reference (storefront order ID for idempotency)

---

## Payments

| Feature | Description | Priority |
|---------|-------------|----------|
| **Online payment gateway** | Card payments via gateway (Stripe, PayPal, Paymob, MyFatoorah, etc.). | Must |
| **Cash on delivery (COD)** | Order created as unpaid or partial; collect on delivery. | Must |
| **Bank transfer** | Show instructions; order pending until staff confirms in POS. | Should |
| **Wallet / mobile money** | Local methods popular in Egypt (Vodafone Cash, InstaPay, etc.) as applicable. | Should |
| **Split payment** | Partial online + COD (if business allows). | Could |
| **3D Secure / SCA** | Follow gateway requirements for card security. | Must |
| **Payment failure handling** | Clear error messages; order not finalized until payment succeeds. | Must |
| **Refunds** | Processed in POS; status reflected on storefront account. | Must |
| **PCI compliance** | No raw card data on POS server; use gateway tokenization/hosted fields. | Must |

> POS already includes libraries for Stripe, PayPal, Paystack, Razorpay, MyFatoorah, and Pesapal. The storefront should use the **same business payment accounts** configured in POS where possible.

---

## Shipping & Delivery

| Feature | Description | POS tie-in | Priority |
|---------|-------------|------------|----------|
| **Shipping zones** | Rates by city, region, or country. | Custom or module | Must |
| **Flat rate / free shipping** | Threshold-based free shipping (e.g. free over X EGP). | — | Must |
| **Weight / price based rates** | If product weight is maintained in POS. | Product weight | Should |
| **Pickup in store** | Select branch; no shipping fee; stock from that location. | `business_locations` | Must |
| **Delivery time estimates** | Display expected delivery window per method. | — | Should |
| **Address validation** | Validate city/area against deliverable zones. | — | Should |
| **Shipment integration** | Optional courier API (Aramex, Bosta, etc.) for labels and tracking. | Shipments module | Could |

---

## Promotions, Pricing & Loyalty

| Feature | Description | POS tie-in | Priority |
|---------|-------------|------------|----------|
| **Sale prices** | Show strikethrough regular price when on promotion. | Selling price groups | Must |
| **Coupons** | Percentage or fixed amount; usage limits and expiry. | Discounts | Must |
| **Customer group pricing** | Logged-in B2B customers see their price group. | Customer groups | Should |
| **Reward points** | Earn on purchase; redeem at checkout. | Reward points | Should |
| **Bundles / BOGO** | Buy X get Y; bundle pricing. | Combo products | Should |
| **Flash sales / countdown** | Time-limited offers on homepage or category. | — | Could |
| **Gift cards** | Purchase and redeem gift cards. | — | Could |

---

## Content, Marketing & Trust

| Feature | Description | Priority |
|---------|-------------|----------|
| **CMS pages** | About, FAQ, Terms, Privacy, Return Policy, Shipping Policy. | Must |
| **Blog / news** | Announcements, game releases, guides (optional CMS module). | Could |
| **Promotional banners** | Admin-managed homepage and category banners. | Should |
| **Newsletter signup** | Email collection with double opt-in. | Should |
| **Social proof** | Testimonials, trust badges, secure checkout badges. | Should |
| **Live chat / WhatsApp** | Click-to-chat for pre-sales support. | Should |
| **Announcement bar** | Site-wide promo or shipping notice. | Should |

---

## Support & Communication

| Feature | Description | Priority |
|---------|-------------|----------|
| **Contact form** | General inquiries to support email/CRM. | Must |
| **Order-scoped support** | "Help with this order" from order detail. | Should |
| **Escalation / complaint tracking** | Structured complaints linked to customer and invoice ([escalation docs](../README-ESCALATION-SYSTEM.md)). | Should |
| **Transactional email** | Order confirm, shipped, delivered, password reset. | Must |
| **SMS notifications** | Optional via Twilio for order updates. | Could |
| **FAQ & self-service** | Reduce ticket volume. | Should |

---

## Search, SEO & Performance

| Feature | Description | Priority |
|---------|-------------|----------|
| **Clean URLs** | `/category/slug`, `/product/slug`. | Must |
| **Meta tags** | Title, description, OG tags per page. | Must |
| **Sitemap.xml** | Auto-generated for products, categories, pages. | Must |
| **Robots.txt** | Control indexing of cart, checkout, account. | Must |
| **Canonical URLs** | Avoid duplicate content from filters. | Must |
| **Structured data** | Product, BreadcrumbList, Organization schema. | Must |
| **Image optimization** | WebP, lazy load, responsive srcset. | Must |
| **Core Web Vitals** | LCP, INP, CLS within acceptable targets. | Must |
| **CDN** | Static assets and product images via CDN. | Should |
| **Caching** | API response cache for catalog; short TTL for stock-sensitive data. | Must |

---

## Security, Privacy & Compliance

| Feature | Description | Priority |
|---------|-------------|----------|
| **HTTPS everywhere** | TLS on all pages and API calls. | Must |
| **Authentication security** | Hashed passwords, rate limiting, lockout after failed attempts. | Must |
| **CSRF / XSS protection** | Standard framework protections on forms and API. | Must |
| **API authentication** | Passport or Sanctum for customer and server-to-server calls. | Must |
| **PII protection** | Encrypt sensitive data; minimal exposure in logs. | Must |
| **Privacy policy** | Explain data collection, cookies, third parties. | Must |
| **Account deletion** | Request deletion per privacy regulations. | Should |
| **Audit trail** | Log order and payment events (POS activity log). | Should |

---

## Admin & POS Integration Requirements

Features the **back office** must expose or support for the storefront to work.

| Capability | Description |
|------------|-------------|
| **Product API** | List/filter products with images, variations, prices, stock by location. |
| **Store availability API** | Per-product (and per-variation) stock across all storefront-visible `business_locations` for the "Check availability" modal. |
| **Category & brand API** | Tree and flat category endpoints; brand list. |
| **Cart / checkout API** | Validate cart, calculate tax/shipping, create order. |
| **Customer API** | Register, login, profile, addresses. |
| **Order API** | List orders, order detail, cancel request, tracking. |
| **Payment webhooks** | Gateway callbacks update payment status in POS. |
| **Stock reservation** | Optional hold during checkout to prevent overselling. |
| **Webhook / event bus** | POS → storefront on price change, stock out, order status update. |
| **Media URLs** | Stable public URLs for product images stored in POS/S3. |
| **Multi-location rules** | Which locations sell online; per-location stock. |
| **Settings sync** | Tax rates, currency, business name, logo, contact info. |

### Reference: existing API starting points

| Endpoint area | Status | Use for custom storefront |
|---------------|--------|----------------------------|
| Contact lookup | Implemented | Extend for customer registration/profile |
| Reward points validate/redeem | Implemented | Checkout loyalty redemption |
| Passport auth | Implemented | **Mobile app** customer login (Phase 4) |
| Sanctum | Available in Laravel | **Qwik web** customer sessions |
| Storefront catalog / cart / checkout API | **To build** | New `api/storefront/v1/*` module — primary work item |
| Legacy `api/ecom` routes | Commented out | Do not use; build fresh Storefront API instead |
| Legacy WooCommerce sync | Out of scope | Not used for public shop |

---

## Non-Functional Requirements

| Area | Target |
|------|--------|
| **Availability** | 99.9% uptime for storefront; graceful degradation if POS API is slow. |
| **Performance** | PLP < 2s LCP on 4G; PDP < 2.5s. |
| **Scalability** | Handle traffic spikes (sales, launches) via CDN and horizontal scaling. |
| **Accessibility** | WCAG 2.1 AA where feasible (forms, contrast, keyboard nav). |
| **Analytics** | Google Analytics 4 / Meta Pixel with consent; e-commerce events (view_item, purchase). |
| **Error monitoring** | Sentry or similar for frontend and API errors. |
| **Backup & recovery** | Storefront config and CMS backed up; orders safe in POS DB. |

---

## Mapping to Current POS Capabilities

| Storefront need | Already in POS | Gap / action |
|-----------------|----------------|--------------|
| Product catalog | Products, categories, brands, variations | Public read API |
| Stock by location | Inventory per `business_location` | Real-time API + oversell policy; **store availability modal** on PDP |
| Customer records | `contacts` | Registration + sync API |
| Online orders | Sales transactions | **New Storefront checkout API** → create POS sell transaction |
| Payments | Multiple gateways in POS | Storefront payment integration |
| Discounts / coupons | POS discount module | Validate coupon API |
| Reward points | Reward points + API | Checkout redemption UI |
| Shipments | Shipments on transactions | Tracking on account |
| Invoices | Tokenized invoice/pay links | Link from order history |
| Returns / refunds | Refund workflow in POS | Customer RMA request UI |
| Complaints | CRM escalation system | Customer-facing escalation form |
| Repair tracking | Public repair status page | Link from account/help |
| Combo / bundles | POS combo products | Storefront bundle UI + stock validation |
| Multilingual | Business settings | Storefront i18n files |
| CRM portal | `/contact/*` B2B portal | Separate from public shop |

---

## Suggested Implementation Phases

### Phase 1 — MVP (launch-ready Qwik shop)

- **Storefront API** v1 in Laravel (`Modules/Storefront` or dedicated route group) — mobile-ready (Bearer auth, versioning, OpenAPI docs)
- **Qwik City** public site (SSR, Tailwind, AR/EN i18n)
- Homepage, category PLP, product PDP
- **Check availability by store** modal on PDP (location list with ✓ / ✗)
- Search and filters
- Cart and guest checkout
- One payment method + COD
- Order confirmation email
- Basic customer registration and order history
- Pickup or flat-rate shipping
- POS order creation with stock decrement
- Mobile-responsive UI, core SEO

### Phase 2 — Professional retail

- Wishlist, reviews, related products
- Multiple shipping zones and pickup locations
- Coupons and sale pricing
- Reward points at checkout
- Shipment tracking and status emails
- Returns request flow
- Newsletter and promotional banners
- Arabic/English full support

### Phase 3 — Growth & optimization

- Advanced search (facets, synonyms)
- Cart abandonment emails
- Customer group / B2B pricing
- Live chat and escalation portal
- Analytics dashboards and A/B tests
- Courier integration and partial shipments
- Gift cards and subscriptions

### Phase 4 — Mobile app

- Native **iOS** and **Android** app (framework TBD — React Native, Flutter, or native)
- Reuses **Storefront API v1+** — no duplicate backend
- Passport bearer-token auth, refresh tokens, biometric login optional
- Push notifications for order updates and promotions
- Deep links to products and orders
- App store listing (Games Spot branding)

---

## Out of Scope (Initial Web Release)

These are deferred beyond the first Qwik web launch unless explicitly prioritized:

- Marketplace / multi-vendor
- Auction or bidding
- In-store POS on the website (staff functions)
- Full ERP (accounting) on the storefront
- **Native mobile apps** — planned for [Phase 4](#phase-4--mobile-app); responsive Qwik web is first
- AR / 3D product views
- Same-day hyperlocal delivery routing

---

## Related Documentation

| Document | Path |
|----------|------|
| Escalation / complaint system | [../README-ESCALATION-SYSTEM.md](../README-ESCALATION-SYSTEM.md) |
| Sales order vs order request | [../README-sales-order-vs-order-request.md](../README-sales-order-vs-order-request.md) |
| Header & footer content | [./README-HEADER-FOOTER.md](./README-HEADER-FOOTER.md) |
| Refund workflow | [../../REFUND_PROCESS_WORKFLOW.md](../../REFUND_PROCESS_WORKFLOW.md) |

> WooCommerce sync docs (`WOOCOMMERCE-*.md`) are legacy POS integration notes and **not** part of the custom public storefront plan.

---

## Document status

| Field | Value |
|-------|-------|
| **Purpose** | Planning reference for public customer e-commerce storefront |
| **Audience** | Product owners, developers, designers |
| **Last updated** | June 2026 |
| **Maintained under** | `docs/public-site/` |

When implementation starts, add companion docs in this folder (Storefront API contract, Qwik project setup, mobile app API checklist, checkout flow, deployment guide).
