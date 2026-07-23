# Games Spot Mobile App

**Progress tracker:** [`MOBILE_PROGRESS.md`](./MOBILE_PROGRESS.md)  
**Web storefront:** [`README.md`](./README.md) · **API:** [`API.md`](./API.md)

React Native (Expo Dev Client) iOS/Android client for the same Storefront API as Qwik.

## Stack

| Layer | Choice |
|-------|--------|
| App | React Native + TypeScript, Expo Router, Expo Development Builds |
| Auth | Laravel **Sanctum** bearer tokens on `Contact` (same as web) — **not** Passport |
| Payments | Official [`@fawry_pay/rn-fawry-pay-sdk`](https://github.com/FawryPay/ReactNative-Fawrypay-Anonymous-sample) (iOS + Android) |
| API | `/api/storefront/v1/*` + `Authorization: Bearer` + `X-Content-Locale` |
| Push | FCM HTTP v1 via Laravel jobs; device tokens on `storefront_device_tokens` |

**Expo Go is not supported** — Fawry uses Nitro native modules. Use `npx expo prebuild` + Dev Client / EAS Build.

## Repo

```
storefront-mobile/     # Expo app
docs/public-site/MOBILE.md
docs/public-site/MOBILE_PROGRESS.md
```

Env: `EXPO_PUBLIC_API_BASE` (Laravel origin, no trailing slash). Feature flags from `GET /settings`.

## Screen map

| Area | Routes (Expo Router) | Primary API |
|------|----------------------|-------------|
| Home | `/(tabs)/index` | `GET /homepage`, products |
| Shop / search | `/(tabs)/shop`, `/search` | `GET /products`, `/search` |
| Category / brand | `/category/[slug]`, `/brands`, `/brands/[slug]` | categories, brands |
| PDP | `/products/[slug]` | product detail, availability, reviews |
| Cart / checkout | `/(tabs)/cart`, `/checkout` | cart validate, checkout |
| Fawry pay | `/checkout/payment` | checkout payment block + RN SDK |
| Auth / account | `/login`, `/register`, `/(tabs)/account/*` | auth, account |
| Wishlist | `/wishlist` | wishlist |
| Games / cards | `/games`, `/games/[id]`, `/gift-cards` | digital catalog |
| Stores / contact / repair | `/stores`, `/contact`, `/repair-status` | locations, contact, repair |
| Content | `/about`, `/faq`, `/legal/[slug]` | static + settings |

## Client patterns (parity with Qwik)

- Envelope: `{ success, data, meta }` — see `storefront-mobile/src/lib/api.ts`
- Cart keys: `gs-cart-guest-v1` / `gs-cart-user-{id}-v1` (AsyncStorage)
- Auth: SecureStore `gs-auth-v1`; clear on 401
- Header `X-Storefront-Client: mobile` on all requests
- Digital line meta (`kind`, `line_key`, `price`) identical to web checkout

## Fawry (mobile)

1. `POST /checkout` with `payment_method: fawry` → signed `payment` / session from Laravel.
2. Map server fields into `startPayment` from `@fawry_pay/rn-fawry-pay-sdk`.
3. **Never** embed `merchantSecretCode` / storefront security key in the app binary.
4. Listen for SDK success/fail; Laravel **webhook** remains source of truth for `payment_status`.
5. Optional `POST /payments/fawry/return` or `POST /payments/fawry/session` for recovery.

## Push devices

| Method | Path | Auth |
|--------|------|------|
| POST | `/account/devices` | Sanctum — body `{ platform: ios\|android, token, locale? }` |
| DELETE | `/account/devices/{token}` | Sanctum |

Jobs fire on storefront order **shipped** (and optionally paid) to registered tokens.

## Deep links

| Link | Opens |
|------|--------|
| `https://{STOREFRONT_URL}/en/products/{slug}` (Universal / App Link) | PDP |
| `https://{STOREFRONT_URL}/en/account/orders/{id}` | Order detail |
| `gamesspot://product/{slug}` | PDP (custom scheme fallback) |
| `gamesspot://order/{id}` | Order detail |

Configure associated domains / intent filters in `app.json` / EAS. Password reset stays on web `STOREFRONT_URL` for v1.

## Local development

```bash
cd storefront-mobile
cp .env.example .env
npm install
npx expo prebuild
npx expo run:android   # or run:ios
```

Staging API must allow the device (CORS not required for native fetch).
