# Games Spot Mobile App

**Progress tracker:** [`MOBILE_PROGRESS.md`](./MOBILE_PROGRESS.md)  
**Web storefront:** [`README.md`](./README.md) · **API:** [`API.md`](./API.md)

React Native (Expo Dev Client) iOS/Android client for the same Storefront API as Qwik.

## Stack

| Layer | Choice |
|-------|--------|
| App | React Native + TypeScript, Expo Router, Expo Development Builds |
| Auth | Laravel **Sanctum** bearer tokens on `Contact` (same as web) — **not** Passport |
| Payments | Fawry: [`@fawry_pay/rn-fawry-pay-sdk`](https://github.com/FawryPay/ReactNative-Fawrypay-Anonymous-sample). Geidea: hosted HPP in WebView now (`src/lib/geidea.ts`); native `payWithGeidea` when `@geidea/payment-sdk-react-native` is installed. |
| API | `/api/storefront/v1/*` + `Authorization: Bearer` + `X-Content-Locale` |
| Push | FCM HTTP v1 via Laravel jobs; device tokens on `storefront_device_tokens` |

**Expo Go is not supported** for native payment SDKs. Use `npx expo prebuild` + Dev Client / EAS Build. Geidea hosted checkout still runs in the existing WebView without the vendor tarball.

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
| Online pay | `/checkout/payment` | checkout `payment` block; Fawry RN SDK or Geidea hosted WebView |
| Auth / account | `/login`, `/register`, `/(tabs)/account/*`, `/account/security`, `/account/payments`, `/account/invoice` | auth, Login & Security, Payments & Payouts, in-app invoice |
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

## Online payments (mobile)

Checkout sends `payment_method` as the active provider slug from `GET /settings` (`fawry` or `geidea`).

### Fawry

1. `POST /checkout` with `payment_method: fawry` → signed `payment` / session from Laravel.
2. Map server fields into `startPayment` from `@fawry_pay/rn-fawry-pay-sdk`.
3. **Never** embed `merchantSecretCode` / storefront security key in the app binary.
4. Listen for SDK success/fail; Laravel **webhook** remains source of truth for `payment_status`.
5. Optional `POST /payments/fawry/return` or `POST /payments/fawry/session` for recovery.

### Geidea

1. `POST /checkout` with `payment_method: geidea` → client-safe session (`session_id`, `sdk_url`, `region`, `environment`). No keys or signatures.
2. `startGeideaPayment` tries native `payWithGeidea` when `@geidea/payment-sdk-react-native` is linked; otherwise loads HPP HTML in `react-native-webview`.
3. WebView `postMessage` (`completed` / `canceled` / `failed`) only drives navigation. Fulfilment is `POST /payments/geidea/webhook`.
4. `POST /payments/geidea/return` is optional recovery; Laravel re-fetches remote `detailedStatus`.
5. Gradle: `expo-build-properties` `minSdkVersion: 24` plus no-op-until-installed `plugins/withGeideaSdk.js`. Do not replace Expo `android/build.gradle`. Skip vendor cleartext `network_security_config.xml`.

Details: [`README-GEIDEA-PAYMENTS.md`](./README-GEIDEA-PAYMENTS.md).

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
| `https://{STOREFRONT_URL}/en/reset-password` (App Link) | In-app reset (6-digit code) |
| `gamesspot://product/{slug}` | PDP (custom scheme fallback) |
| `gamesspot://order/{id}` | Order detail |
| `gamesspot://reset-password` | In-app password reset |

Configure associated domains / intent filters in `app.json` / EAS. Forgot-password email uses a 6-digit code so reset stays in the app; the web App Link is a backup.

## Local development

```bash
cd storefront-mobile
cp .env.example .env
npm install
npx expo prebuild
npx expo run:android   # or run:ios
```

Staging API must allow the device (CORS not required for native fetch).
