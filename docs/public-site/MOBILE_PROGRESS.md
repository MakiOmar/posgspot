# Mobile app implementation progress

**Canonical tracker** for Games Spot iOS/Android (`storefront-mobile/` + Storefront API mobile gaps).  
**Architecture:** [`MOBILE.md`](./MOBILE.md) · **Web progress:** [`STOREFRONT_PROGRESS.md`](./STOREFRONT_PROGRESS.md) · **API:** [`API.md`](./API.md)

| | |
|---|---|
| **Last updated** | 2026-07-23 |
| **Phase** | Phase 4 — React Native (Expo Dev Client) |
| **Overall** | Docs + API gaps + Expo app scaffold with full screen map shipped |

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
| M2 commerce (Fawry wrapper, coupons, RP, wishlist, reviews) | Done |
| M3 extras (digital, stores, repair, content) | Done |
| M4 store release (EAS config + listing checklist) | Partial — replace EAS/project IDs before submit |

---

## Changelog

| Date | Change |
|------|--------|
| 2026-07-23 | Phase 4: docs, `storefront-mobile/` Expo app, device API + push jobs, deep links, shop parity screens, EAS checklist. |
