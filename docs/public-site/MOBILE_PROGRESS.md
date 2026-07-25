# Mobile app implementation progress

**Canonical tracker** for Games Spot iOS/Android (`storefront-mobile/` + Storefront API mobile gaps).  
**Architecture:** [`MOBILE.md`](./MOBILE.md) · **Web progress:** [`STOREFRONT_PROGRESS.md`](./STOREFRONT_PROGRESS.md) · **API:** [`API.md`](./API.md)

| | |
|---|---|
| **Last updated** | 2026-07-25 |
| **Phase** | Phase 4 — React Native (Expo Dev Client) |
| **Overall** | Checkout always sends fulfillment location_id |

**Status legend:** Done · Partial · Not started

---

## Summary

| Area | Status |
|------|--------|
| Docs (`MOBILE.md`, progress, README Phase 4) | Done |
| Expo scaffold (Router, i18n, API client, SecureStore) | Done |
| Device tokens + FCM push jobs | Done |
| Universal / App Links + Fawry RN wiring | Done (install `@fawry_pay/rn-fawry-pay-sdk` + prebuild for native pay) |
| M1 shop core (browse → COD checkout → orders) | Done |
| Home section UI (hero, promos, shelves, rails) | Done |
| Route map (`/products` index, stack headers, FlatList flex) | Done |
| PDP id fallback (null catalog slugs) + storefront chrome header | Done |
| Wishlist bottom tab + guest/auth wishlist context | Done |
| Product card actions (wishlist · add to cart · options · availability) | Done |
| Wave 1 — PDP gallery/variations/qty/related/reviews | Done |
| Wave 1 — PLP sort / in-stock / pagination | Done |
| Wave 1 — Cart validate + coupons | Done |
| Wave 1 — Checkout rates / pickup / digital / points / totals | Done |
| Wave 2 — Profile, rewards, reorder/invoice, forgot/reset password | Done |
| Wave 3 — About/FAQ/legal content; games platform; gift cards; stores/repair | Done |
| Header nav drawer (menu + categories + language) | Done |
| Searchable country/state/district selects | Done |
| Arabic RTL layout (header, forms, screens) | Done |
| Star ratings on cards + PDP | Done |
| Coupon picker (available codes) | Done |
| Reward points validate + max redeem | Done |
| Global maintenance gate | Done |
| expo-image cached remotes | Done |
| Home shelf concurrency + virtualized rails | Done |
| Search pagination via `/products?q=` | Done |
| Toast feedback (success/error vs Alert) | Done |
| Checkout sends required `location_id` | Done |
| M2 commerce (Fawry wrapper, coupons, RP, wishlist, reviews) | Done |
| M3 extras (digital, stores, repair, content) | Done |
| M4 store release (EAS config + listing checklist) | Partial — replace EAS/project IDs before submit |

---

## Changelog

| Date | Change |
|------|--------|
| 2026-07-23 | Phase 4: docs, `storefront-mobile/` Expo app, device API + push jobs, deep links, shop parity screens, EAS checklist. |
| 2026-07-24 | Android white-screen fixes (splash gate, babel/reanimated, emulator SwiftShader); home section renderers + real prices/images; polish tabs. |
| 2026-07-24 | Fix dead `/products` CTA route (add catalog index); root Stack headers; FlatList flex; safer href mapping + not-found. |
| 2026-07-24 | PDP open by id when slug null; StorefrontHeader chrome; wishlist tab in footer. |
| 2026-07-24 | Product cards: wishlist heart, add-to-cart / view options / check availability (Qwik parity). |
| 2026-07-24 | Wave 1 parity: PDP gallery/variations/qty/related/reviews; PLP toolbar; cart validate/coupons; checkout shipping/pickup/points. |
| 2026-07-24 | Wave 2: account profile, reward points, order reorder+invoice, forgot/reset password. |
| 2026-07-25 | Splash: nested opposite arcs around logo via Modal (no leak after dismiss). |
| 2026-07-25 | Homepage: all section types (promo/trust/shelves); category banners with overlay kicker/title/FG/CTA. |
| 2026-07-25 | Nav drawer (menu/categories/lang); searchable geo selects; Arabic RTL; account nav links removed. |
| 2026-07-25 | PDP: fixed faded bottom bar with qty beside add-to-cart / availability. |
| 2026-07-25 | PDP: floating share + wishlist chips on gallery (RTL-aware end side). |
| 2026-07-25 | Stars on cards/PDP; coupon picker; validated reward-points redeem. |
| 2026-07-25 | Global maintenance gate; expo-image; home fetch pool + FlatList rails; search pagination. |
| 2026-07-25 | Toast host + imperative API; success/error Alert.alert replaced across shop flows. |
| 2026-07-25 | Checkout: always load selling location and send required `location_id` (not only for pickup). |
