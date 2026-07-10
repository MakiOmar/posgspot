# Header & Footer — Content Specification

This document defines the **content, structure, and behavior** of the global **header** and **footer** on the Games Spot public Qwik storefront. It complements the main [feature requirements](./README.md).

Categories, cart count, and store data come from the **Storefront API** (Laravel / POS). Marketing copy and legal pages may be static in Qwik or managed via a future CMS.

---

## Table of Contents

1. [Overview](#overview)
2. [Global layout zones](#global-layout-zones)
3. [Header — structure](#header--structure)
4. [Header — content items](#header--content-items)
5. [Header — navigation menu](#header--navigation-menu)
6. [Header — search](#header--search)
7. [Header — utility icons (language, wishlist, account, cart)](#header--utility-icons-language-wishlist-account-cart)
8. [Header — mobile](#header--mobile)
9. [Footer — structure](#footer--structure)
10. [Footer — content by column](#footer--content-by-column)
11. [Footer — bottom bar](#footer--bottom-bar)
12. [Data sources](#data-sources)
13. [Multilingual & RTL](#multilingual--rtl)
14. [Wireframes](#wireframes)
15. [Implementation phases](#implementation-phases)
16. [API endpoints required](#api-endpoints-required)

---

## Overview

The header and footer appear on **every public page** (except checkout minimal layout — see [Checkout header exception](#checkout-minimal-header)). They must:

- Reflect **Games Spot** branding (logo, colors, tone).
- Provide **fast access** to search, categories, cart, and account.
- Support **Arabic and English** with RTL layout for Arabic.
- Load critical content via **SSR** (Qwik City) for SEO and first paint.
- Stay **sticky** on scroll (header) without blocking page content on mobile.

| Area | Primary purpose |
|------|-----------------|
| **Announcement bar** | Promotions, shipping offers, site-wide notices |
| **Main header** | Brand, navigation, search, **language switcher**, **wishlist**, cart, account |
| **Footer** | Trust, policies, contact, newsletter, social |
| **Footer bottom** | Copyright, payment icons, legal links |

---

## Global layout zones

Vertical order on a standard page:

```
┌─────────────────────────────────────────────┐
│  Announcement bar (optional, dismissible)   │
├─────────────────────────────────────────────┤
│  Main header (sticky)                       │
├─────────────────────────────────────────────┤
│                                             │
│  Page content                               │
│                                             │
├─────────────────────────────────────────────┤
│  Footer                                     │
└─────────────────────────────────────────────┘
```

| Zone | Sticky | Phase |
|------|--------|-------|
| Announcement bar | No — scrolls away or sits above sticky header | Phase 1 (single message); Phase 2 (admin-managed) |
| Main header | Yes — sticks to top after scroll | Phase 1 |
| Footer | No | Phase 1 |

---

## Header — layout variants

The Qwik storefront supports two header layouts, selected at **build time** via env:

| Env | Values | Default |
|-----|--------|---------|
| `PUBLIC_HEADER_STYLE` | `one` \| `two` (aliases: `1`) | `two` |

Set in `storefront-qwik/.env.ssr` (dev) or `.env.production` (build). See `storefront-qwik/.env.example`.

| Style | Layout |
|-------|--------|
| **`two`** (default) | **Row 1:** logo, search, phone, account, cart. **Row 2:** Home, Shop, Categories (opens side drawer). |
| **`one`** | Single row: logo, Home, Shop, Categories button, search, phone, account, cart. Categories open in the same side drawer. |

Phone lives in the main row (no separate top contact bar). The announcement strip remains optional and admin-controlled.

---

## Header — structure

### Desktop (≥ 1024px)

Left → right:

| # | Block | Width / behavior |
|---|-------|------------------|
| 1 | Logo | Links to homepage `/` |
| 2 | Primary navigation | Horizontal mega-menu or dropdowns — categories from API |
| 3 | Search | Expanding field or always-visible bar (center or right) |
| 4 | **Utility cluster** | Grouped icons on the far end — see [Utility icons](#header--utility-icons-language-wishlist-account-cart) |

**Utility cluster** order (end of header, LTR):

| # | Icon | Phase |
|---|------|-------|
| 1 | **Language switcher** | AR / EN toggle |
| 2 | **Wishlist** | Heart icon + saved-items count badge |
| 3 | **Account** | User icon; dropdown when logged in |
| 4 | **Cart** | Cart icon + item count badge; opens mini-cart drawer |

### Checkout minimal header

On `/checkout` and `/checkout/*`:

| Show | Hide |
|------|------|
| Logo (links home) | Full category nav |
| Secure checkout label / lock icon | Search |
| **Language switcher** | Wishlist |
| Order summary link (mobile) | Mini-cart (cart is implicit) |
| | Announcement bar (optional) |

---

## Header — content items

### Logo

| Property | Detail |
|----------|--------|
| **Asset** | Games Spot logo (SVG preferred; PNG fallback) |
| **Alt text** | "Games Spot" / "جيمز سبوت" (i18n) |
| **Link** | `/` (homepage) |
| **Source** | Static asset in Qwik; optional override from business settings API (`logo_url`) |
| **Size** | Max height ~40px desktop, ~32px mobile — preserve aspect ratio |

### Announcement bar

| Content type | Example (EN) | Example (AR) |
|--------------|--------------|--------------|
| Free shipping | Free delivery on orders over 1,500 EGP | توصيل مجاني للطلبات فوق ١٥٠٠ جنيه |
| Promo | New PlayStation arrivals — Shop now | وصول جديد PlayStation — تسوق الآن |
| Store notice | Maadi branch open until 11 PM today | فرع المعادي مفتوح حتى ١١ مساءً اليوم |

| Behavior | Detail |
|----------|--------|
| Dismiss | Close (×) stores preference in `localStorage` for 24h |
| Link | Optional CTA link (e.g. `/category/deals`) |
| Priority | Phase 1: hardcoded or env config; Phase 2: CMS / admin API |

### Language switcher (header)

Always visible in the **header utility cluster** on desktop and mobile — not buried in menus only.

| Property | Detail |
|----------|--------|
| **Position** | First item in utility cluster (before wishlist, account, cart) |
| **Display (desktop)** | Compact toggle: `EN \| AR` or globe icon + dropdown with language names |
| **Display (mobile)** | Same compact toggle in the top header bar (visible without opening drawer) |
| **Labels** | English → `en` · العربية → `ar` |
| **Active state** | Current language highlighted / underlined |
| **Persistence** | Cookie + `localStorage`; survives refresh |
| **RTL** | Selecting Arabic sets `dir="rtl"` on `<html>` and mirrors layout |
| **Routes** | Locale prefix (`/en/...`, `/ar/...`) or cookie-driven — TBD in Qwik i18n setup |

| Accessibility | Detail |
|---------------|--------|
| **Aria** | `aria-label="Language"` / `aria-label="اللغة"` |
| **Keyboard** | Focusable; Enter/Space toggles or opens dropdown |

Also duplicated in the **mobile nav drawer** (below account) for convenience when the menu is open.

### Currency display

- Prices always in **EGP** (business default from POS).
- Format per locale: `1,299.00 EGP` (EN) / `١٬٢٩٩٫٠٠ ج.م` (AR) if localized numerals enabled.
- No currency switcher in Phase 1 unless POS supports multi-currency later.

---

## Header — navigation menu

Primary navigation is **driven by POS categories** via Storefront API, not hardcoded — structure mirrors the catalog.

### Top-level menu (dynamic)

| Item type | Source | Link pattern | Notes |
|-----------|--------|--------------|-------|
| **Category** | `GET /api/storefront/v1/categories` | `/category/{slug}` | Top-level POS categories with `show_in_menu = true` |
| **Subcategory** | Nested in category tree | `/category/{parent-slug}/{slug}` | Shown in dropdown / mega-menu |
| **Brands** (Phase 2) | Brands API | `/brand/{slug}` | Optional top-level "Brands" flyout |
| **Deals / Sale** | Static or collection | `/deals` or `/category/sale` | Highlight discounted products |
| **Store locations** | Static | `/stores` | List of `business_locations` |

### Suggested static links (alongside categories)

These sit in the nav or a **"More"** dropdown if the category list is long:

| Label (EN) | Label (AR) | URL | Phase |
|------------|------------|-----|-------|
| Deals | العروض | `/deals` | Phase 1 |
| New arrivals | وصل حديثاً | `/new-arrivals` | Phase 2 |
| Our stores | فروعنا | `/stores` | Phase 1 |
| Contact | اتصل بنا | `/contact` | Phase 1 |
| Track order | تتبع الطلب | `/account/orders` or `/track-order` | Phase 1 |

### Mega-menu content (desktop)

When hovering a category with children:

| Column | Content |
|--------|---------|
| **Subcategories** | Links to child categories (from API) |
| **Featured** (Phase 2) | 2–4 promoted products or banner image for that category |
| **Quick link** | "View all in {Category}" → parent category PLP |

### Navigation rules

- Maximum **7–8 visible top-level items** on desktop; overflow → "More" menu.
- Categories with zero visible products can be hidden (API filter).
- Current category trail highlighted (active state).
- Keyboard accessible: Tab, Enter, Escape to close dropdowns.

---

## Header — search

| Element | Detail |
|---------|--------|
| **Placeholder (EN)** | Search games, consoles, accessories… |
| **Placeholder (AR)** | ابحث عن ألعاب، أجهزة، إكسسوارات… |
| **Trigger** | Submit on Enter or search icon click |
| **Autocomplete** | Phase 1: product name + SKU; Phase 2: categories, brands, popular terms |
| **Results dropdown** | Up to 8 products (image, name, price); "View all results" link |
| **Empty state** | "No products found" + suggested categories |
| **Mobile** | Search icon opens full-width overlay or dedicated `/search` page |

**API:** `GET /api/storefront/v1/search?q={query}&limit=8`

---

## Header — utility icons (language, wishlist, account, cart)

The **utility cluster** sits at the end of the header (mirrored in RTL). All four items are **Must** for Phase 1.

---

### Wishlist icon

| Property | Detail |
|----------|--------|
| **Icon** | Heart outline; filled when page is wishlist or on hover (design TBD) |
| **Position** | Utility cluster — after language switcher, before account |
| **Tooltip (EN)** | Wishlist |
| **Tooltip (AR)** | قائمة الأمنيات |
| **Link** | `/account/wishlist` (or `/wishlist` redirecting to account when logged in) |
| **Badge** | Count of saved items when &gt; 0 (max display `99+`) |
| **Click (logged in)** | Navigate to wishlist page |
| **Click (guest)** | Navigate to wishlist page showing saved session items **or** prompt login to sync — see behavior below |

**Guest wishlist behavior:**

| Approach | Detail |
|----------|--------|
| **Session / localStorage** | Guest can add items; list stored locally until login |
| **On login** | Merge local wishlist into account via API |
| **Empty state** | "Save items you love" + browse deals CTA |

**Wishlist page (header destination):**

| Element | Detail |
|---------|--------|
| Product grid | Thumbnail, name, price, remove button, add to cart |
| Out of stock | Show item greyed with "Notify me" optional (Phase 2) |
| Empty | Heart illustration + link to homepage |

**API:**

- `GET /api/storefront/v1/wishlist` — list + count for badge
- `POST /api/storefront/v1/wishlist` — add item
- `DELETE /api/storefront/v1/wishlist/{product_id}` — remove item

**Add to wishlist on PDP/PLP:** Heart on product cards toggles save; header badge updates without full page reload.

---

### Cart icon

| State | Display |
|-------|---------|
| Empty | Cart icon, no badge (or badge hidden) |
| Has items | Badge with item count (max display `99+`) |
| Click | Opens **mini-cart drawer** (right side LTR / left side RTL) |

**Mini-cart drawer contents:**

| Item | Detail |
|------|--------|
| Line items | Thumbnail, name, variation, qty, line total |
| Subtotal | Excluding shipping; tax note if applicable |
| Actions | **View cart** → `/cart`, **Checkout** → `/checkout` |
| Empty message | "Your cart is empty" + link to homepage or deals |
| Loading | Skeleton while fetching from API |

**API:** `GET /api/storefront/v1/cart` (summary for badge + drawer)

### Account menu

| Guest | Logged in |
|-------|-----------|
| **Login** → `/account/login` | Greeting: "Hi, {first name}" |
| **Register** → `/account/register` | **My orders** → `/account/orders` |
| | **Profile** → `/account/profile` |
| | **Addresses** → `/account/addresses` |
| | **Wishlist** → `/account/wishlist` (same as header heart icon) |
| | **Reward points** → `/account/rewards` (Phase 2) |
| | **Logout** |

Icon: user silhouette; on mobile included in drawer menu.

---

## Header — mobile

### Mobile header bar ( `< 1024px` )

```
[ ☰ ]   [ Logo ]   [ EN|AR ] [ ♥² ] [ 🔍 ] [ 🛒³ ]
```

| Control | Action |
|---------|--------|
| **Hamburger (☰)** | Opens full-height **mobile nav drawer** from start edge (left LTR / right RTL) |
| **Logo** | Centered or start-aligned — links home |
| **Language switcher** | Compact `EN \| AR` toggle — always visible in header bar |
| **Wishlist (♥)** | Links to wishlist page; badge shows saved count |
| **Search** | Opens search overlay |
| **Cart** | Opens mini-cart drawer (same as desktop) |

If the bar is too crowded on very small screens, priority order (keep visible): **Logo → Cart → Wishlist → Language → Search → Menu**. Search may collapse to icon-only before hiding wishlist or language.

### Mobile nav drawer contents

Order top → bottom:

1. **Account** — Login / Register, or user name + quick links (includes wishlist link)
2. **Language switcher** — AR / EN (duplicate of header toggle)
3. **Wishlist shortcut** — "My wishlist (n)" with count
4. **Divider**
4. **Category tree** — Expandable accordion (parent → children from API)
5. **Static links** — Deals, Our stores, Contact, Track order
6. **Divider**
7. **Contact shortcut** — Phone (click-to-call), WhatsApp link if configured

Drawer closes on link click, overlay tap, or Escape.

---

## Footer — structure

Four-column layout on desktop; stacks to single column on mobile.

```
┌──────────────┬──────────────┬──────────────┬──────────────┐
│  Shop        │  Customer    │  Company     │  Stay        │
│  (col 1)     │  service     │  (col 3)     │  connected   │
│              │  (col 2)     │              │  (col 4)     │
├──────────────┴──────────────┴──────────────┴──────────────┤
│  Footer bottom bar — copyright, payments, legal           │
└───────────────────────────────────────────────────────────┘
```

Background: dark brand color or neutral dark gray; light text. Sufficient contrast (WCAG AA).

---

## Footer — content by column

### Column 1 — Shop

**Heading (EN):** Shop  
**Heading (AR):** تسوق

| Link (EN) | Link (AR) | URL | Source |
|-----------|-----------|-----|--------|
| All categories | جميع التصنيفات | `/categories` | Static |
| Deals & offers | العروض | `/deals` | Static |
| New arrivals | وصل حديثاً | `/new-arrivals` | Phase 2 |
| Best sellers | الأكثر مبيعاً | `/bestsellers` | Phase 2 |
| *(Dynamic)* Top categories | — | `/category/{slug}` | API — top 5 categories |

### Column 2 — Customer service

**Heading (EN):** Customer service  
**Heading (AR):** خدمة العملاء

| Link (EN) | Link (AR) | URL |
|-----------|-----------|-----|
| Contact us | اتصل بنا | `/contact` |
| FAQ | الأسئلة الشائعة | `/faq` |
| Shipping & delivery | الشحن والتوصيل | `/shipping-policy` |
| Returns & refunds | الإرجاع والاسترداد | `/return-policy` |
| Track your order | تتبع طلبك | `/track-order` |
| Check repair status | حالة الصيانة | `/repair-status` (links to existing POS repair lookup) |

### Column 3 — Company

**Heading (EN):** Company  
**Heading (AR):** عن الشركة

| Link (EN) | Link (AR) | URL |
|-----------|-----------|-----|
| About Games Spot | عن جيمز سبوت | `/about` |
| Our stores | فروعنا | `/stores` |
| Careers | الوظائف | `/careers` (optional / Phase 3) |
| Privacy policy | سياسة الخصوصية | `/privacy` |
| Terms & conditions | الشروط والأحكام | `/terms` |

### Column 4 — Stay connected

**Heading (EN):** Stay connected  
**Heading (AR):** تواصل معنا

| Element | Content |
|---------|---------|
| **Short tagline** | Egypt's trusted destination for games, consoles & accessories. |
| **Tagline (AR)** | وجهتك الموثوقة للألعاب والأجهزة والإكسسوارات في مصر. |
| **Phone** | Primary business phone — click-to-call (`tel:`) — from POS business settings |
| **Email** | support@gamesspoteg.com (or from settings) |
| **WhatsApp** | Click-to-chat link (if used for support) |
| **Social icons** | Facebook, Instagram, TikTok, YouTube — URLs from settings / env |
| **Newsletter** | Email input + **Subscribe** button (Phase 2) |

**Newsletter (Phase 2):**

- Single email field; validation; success/error toast.
- Double opt-in per marketing policy.
- API: `POST /api/storefront/v1/newsletter/subscribe`

---

## Footer — bottom bar

Full-width row below the four columns.

### Left — Copyright

| Locale | Text |
|--------|------|
| EN | © {year} Games Spot. All rights reserved. |
| AR | © {year} جيمز سبوت. جميع الحقوق محفوظة. |

`{year}` = dynamic current year.

### Center — Payment methods

Display **accepted payment** icons (grayscale or brand-compliant):

| Phase 1 | Phase 2+ |
|---------|----------|
| Cash on delivery | Visa |
| *(generic card icon if online payments live)* | Mastercard |
| | Mobile wallets (Vodafone Cash, etc. as applicable) |
| | InstaPay / bank transfer icon if offered |

Icons are **informational** (not clickable). Configure which icons appear under **POS → Storefront Settings → Footer payment icons** (upload or image URL). Actual methods at checkout come from payment gateway / COD settings.

### Right — Trust & legal

| Item | Detail |
|------|--------|
| **Secure checkout** | Lock icon + "Secure checkout" / "دفع آمن" |
| **Quick legal links** | Privacy · Terms (duplicate of column 3 for convenience) |

Optional Phase 2: trust badges (SSL, verified business).

---

## Data sources

| Content | Source | Cached |
|---------|--------|--------|
| Category menu | Storefront API — categories tree | Yes — short TTL (5–15 min) |
| Cart count / mini-cart | Storefront API — cart session | No — real-time |
| Wishlist count | Storefront API — wishlist | No — real-time |
| Logo, site name, phone, email | Business settings API or env | Yes |
| Social URLs | Config / CMS | Yes |
| Announcement bar | Env / CMS (Phase 2) | Yes |
| Footer static links | Qwik i18n translation files | Build time |
| Store list (footer link target) | Storefront API — locations | Yes |
| Payment icons | Storefront settings → `payment_icons` via `GET /settings` | Yes |

---

## Multilingual & RTL

| Concern | Approach |
|---------|----------|
| **All labels** | i18n keys in Qwik (`en.json`, `ar.json`) — no hardcoded UI strings |
| **Category names** | From POS if multilingual fields exist; else single language with translation TBD |
| **RTL layout** | Mirror header (drawer from right), footer columns, mini-cart side |
| **Icons** | Cart, search, user — universal; no text in icons |
| **Phone numbers** | LTR embedded in RTL (`+20 …` with `dir="ltr"`) |

---

## Wireframes

### Desktop header

```
┌──────────────────────────────────────────────────────────────────────────┐
│ 🎉 Free delivery on orders over 1,500 EGP                    [ × ]      │
├──────────────────────────────────────────────────────────────────────────┤
│ [Logo]  Consoles ▾  Games ▾  Accessories ▾  Deals  Stores  Contact      │
│                    [ 🔍 Search games, consoles...        ]               │
│                                          [ EN|AR ] [ ♥ 2 ] [ 👤 ▾ ] [ 🛒 3 ] │
└──────────────────────────────────────────────────────────────────────────┘
```

### Desktop footer

```
┌──────────────────────────────────────────────────────────────────────────┐
│  SHOP              CUSTOMER SERVICE    COMPANY           STAY CONNECTED   │
│  All categories    Contact us        About us          Tagline...       │
│  Deals             FAQ               Our stores        📞 +20 ...         │
│  Best sellers      Shipping          Privacy           ✉ support@...    │
│  ...               Returns           Terms             [f][ig][tt][yt]   │
│                                                      [ email ] [Subscribe]│
├──────────────────────────────────────────────────────────────────────────┤
│ © 2026 Games Spot          [COD][Visa][MC]          🔒 Secure checkout  │
└──────────────────────────────────────────────────────────────────────────┘
```

### Mobile header + drawer

```
┌─────────────────────────────┐
│ ☰  [ Logo ]  EN|AR  ♥² 🔍 🛒³│
└─────────────────────────────┘

Drawer (when ☰ open):
┌─────────────────────────────┐
│ Login / Register            │
│ EN | AR                     │
│ ♥ My wishlist (2)           │
│ ─────────────────────────── │
│ ▶ Consoles                  │
│ ▶ Games                     │
│ ▶ Accessories               │
│   Deals                     │
│   Our stores                │
│   Contact                   │
│ ─────────────────────────── │
│ 📞 Call us                  │
└─────────────────────────────┘
```

---

## Implementation phases

### Phase 1 — MVP header/footer

- Logo, sticky header, mobile drawer
- Dynamic category nav from API
- Search (submit to search page; basic autocomplete optional)
- **Language switcher** in header utility cluster (desktop + mobile bar)
- **Wishlist** icon with count badge + wishlist page
- Cart icon + mini-cart drawer
- Account login/register links
- Announcement bar (single static message)
- Footer: all four columns with static links
- Footer bottom: copyright, COD + card icons, secure checkout
- Contact phone/email from business settings

### Phase 2 — Enhanced

- Search autocomplete
- Mega-menu with featured products
- Newsletter signup in footer
- Reward points in account menu
- Admin-managed announcement bar
- Best sellers / new arrivals footer links live
- Full payment method icon set

### Phase 3 — Optional

- Live chat / WhatsApp widget in footer corner
- Personalised nav (recent categories)
- A/B tested announcement messages

---

## API endpoints required

| Endpoint | Used in | Purpose |
|----------|---------|---------|
| `GET /api/storefront/v1/categories` | Header nav, footer shop links | Category tree for menu |
| `GET /api/storefront/v1/search` | Header search | Autocomplete + search page |
| `GET /api/storefront/v1/cart` | Header cart badge, mini-cart | Cart summary |
| `GET /api/storefront/v1/wishlist` | Header wishlist badge, wishlist page | Wishlist items + count |
| `POST /api/storefront/v1/wishlist` | PDP / PLP heart toggle | Add to wishlist |
| `DELETE /api/storefront/v1/wishlist/{id}` | Wishlist page, PDP | Remove from wishlist |
| `GET /api/storefront/v1/settings` | Logo, phone, email, social | Business / site settings |
| `GET /api/storefront/v1/locations` | Stores link, footer | Branch list for `/stores` |
| `POST /api/storefront/v1/newsletter/subscribe` | Footer | Newsletter (Phase 2) |

---

## Related documentation

| Document | Path |
|----------|------|
| Public storefront features | [./README.md](./README.md) |
| Store availability modal (PDP) | [./README.md#store-availability-modal](./README.md#store-availability-modal) |

---

## Document status

| Field | Value |
|-------|-------|
| **Purpose** | Header & footer content and structure for Qwik storefront |
| **Audience** | Designers, frontend (Qwik) developers, API developers |
| **Last updated** | June 2026 |
| **Maintained under** | `docs/public-site/` |
