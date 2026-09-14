# Geidea Payment Gateway — Integration Instructions

**Status:** planning / instructions only. Nothing in this document is implemented yet.
**Scope:** add **Geidea** as an online payment provider for the Qwik storefront (`storefront-qwik/`) and the Expo mobile app (`storefront-mobile/`), alongside the existing FawryPay provider.

**Related repo docs:** [`API.md`](./API.md) · [`STOREFRONT_PROGRESS.md`](./STOREFRONT_PROGRESS.md) · [`MOBILE.md`](./MOBILE.md) · [`MOBILE_PROGRESS.md`](./MOBILE_PROGRESS.md)
**Vendor docs:** [Overview](https://docs.geidea.net/docs/overview) · [API Reference](https://docs.geidea.net/reference/welcome-to-geideas-api-reference-documentation) · [Doc index (`llms.txt`)](https://docs.geidea.net/llms.txt)

> Any Geidea documentation page can be read as markdown by appending `.md` to its URL.

---

## 1. What we already have (do not rebuild it)

Online payments in this repo are already **pluggable**. Geidea should be a **second driver**, not a parallel checkout flow.

| Existing piece | Path | Reuse as-is |
|---|---|---|
| Driver contract | `app/Services/Storefront/Payment/PaymentGatewayInterface.php` | Yes — implement it for Geidea |
| Driver registry | `config/storefront-payments.php` (`drivers`, `labels`) | Yes — add a `geidea` row |
| Active-provider resolution | `app/Services/Storefront/Payment/PaymentGatewayManager.php` | Yes — reads `settings.gateway.provider` |
| Paid/pending recorder | `app/Services/Storefront/Payment/StorefrontPaymentRecorder.php` | Yes |
| Provider-agnostic routes | `routes/storefront.php` → `POST /payments/{provider}/{webhook,return,session}` | Yes |
| Return + session controller | `app/Http/Controllers/Api/Storefront/PaymentReturnController.php` | Mostly — see §5.4 |
| Reference implementation | `app/Services/Storefront/Payment/FawryPaymentGateway.php` | Read it first as the template |
| Encrypted settings secrets | `StorefrontSettingService` (`secretPaths()`, `Crypt::encryptString`) | Yes |
| Admin settings UI | `resources/views/storefront/settings.blade.php` (Payment gateway section) | Extend |

**Consequence:** the bulk of this work is one new driver class plus one new client-side launcher per app. Checkout, cart, coupons, rewards, shipping, and order creation stay untouched.

---

## 2. Prerequisites (business + account setup)

Do these before writing anything, per [Pre-requisites](https://docs.geidea.net/docs/pre-requisites).

| Step | Detail |
|---|---|
| Get a test (sandbox) account | Request from the Geidea enablement team; see [Overview](https://docs.geidea.net/docs/overview) → "Create a test account" |
| Collect **two** credential pairs | A **Merchant Public Key** + **API Password** for **sandbox** and another for **production**. Both come from the enablement email or the merchant portal under **Payment Gateway → Gateway Settings**. Test vs live is decided by which pair you use — see §3.2 |
| Confirm the region | Egypt / KSA / UAE each have **different** API hosts and HPP script hosts (§3.1) |
| Confirm the currency | Games Spot sells in **EGP**. Multi-currency is off by default and must be enabled by Geidea support |
| Confirm enabled payment methods | Cards are default; Meeza QR, bank installments, BNPL, and wallets are each **enabled per merchant** by Geidea operations (§7) |
| Get portal branding done | Merchant name + logo + colors are configured in the portal, not in code — [Branding and Customization](https://docs.geidea.net/docs/branding) (**Management → Stores → default store**) |
| Support channel | `geideapay.support@geidea.net` |

**Hard security rule from the vendor docs:** the API Password is a secret. It must live **only** in Laravel (encrypted in storefront settings), never in the Qwik bundle, never in the mobile binary, never in an env var exposed with a `PUBLIC_`/`EXPO_PUBLIC_` prefix. Both the [Pre-requisites](https://docs.geidea.net/docs/pre-requisites) and [HPP Checkout](https://docs.geidea.net/docs/geidea-checkout-v2) pages state this explicitly.

---

## 3. Environments: region hosts and live vs test mode

Geidea has **two independent axes**. Conflating them is the single easiest way to get this integration wrong, because it does not work the way Fawry does.

| Axis | Controls | Values |
|---|---|---|
| **Region / environment** | Which **host** you talk to | Egypt, KSA, UAE (§3.1) |
| **Mode** | Whether the transaction is **test or live** | Sandbox or Production — selected purely by **which credential pair you send** (§3.2) |

### 3.1 Region hosts (must be configurable, not hardcoded)

Sourced from [HPP Checkout](https://docs.geidea.net/docs/geidea-checkout-v2) and [Express Checkout](https://docs.geidea.net/docs/express-checkout-wallets).

| Region | API base | HPP script host |
|---|---|---|
| Egypt | `https://api.merchant.geidea.net` | `https://www.merchant.geidea.net/hpp/geideaCheckout.min.js` |
| KSA | `https://api.ksamerchant.geidea.net` | `https://www.ksamerchant.geidea.net/hpp/geideaCheckout.min.js` |
| UAE | `https://api.geidea.ae` | `https://payments.geidea.ae/hpp/geideaCheckout.min.js` |

Operations we will call, relative to the region API base:

| Operation | Method + path | Reference |
|---|---|---|
| Create Session | `POST /payment-intent/api/v2/direct/session` | [Create Session](https://docs.geidea.net/reference/create-session-v2-1) |
| Fetch order by Geidea order id | `GET /pgw/api/v1/direct/order/{orderId}` | [Fetch Transaction or Order Details](https://docs.geidea.net/docs/fetch-1) |
| Fetch order by our reference | `GET /pgw/api/v1/direct/order?MerchantReferenceId={id}` | [Fetch by Merchant Reference](https://docs.geidea.net/docs/fetch-transaction-or-order-details-by-merchant-reference) |
| Refund (full or partial) | `POST /pgw/api/v2/direct/refund` | [Refund](https://docs.geidea.net/docs/refund-2) |
| Cancel order (pre-Pay only) | `POST /pgw/api/v1/direct/cancel` | [Cancel Order](https://docs.geidea.net/docs/cancel-order-1) |
| Void (authorized, uncaptured) | See API reference | [Void](https://docs.geidea.net/docs/void-1) · [Void Payment](https://docs.geidea.net/reference/void-payment-1) |

All of these use **HTTP Basic auth**: Merchant Public Key as username, API Password as password.

### 3.2 Live vs test (sandbox) mode

**There is no separate sandbox host and no test-mode flag in the API.** Mode is decided entirely by the credentials. The WooCommerce plugin FAQ states it plainly: *"production and sandbox mode is controlled by the API keys you use"* ([WooCommerce plugin](https://docs.geidea.net/docs/woocommerce)). Consistent with that, every official plugin's **Environment** dropdown lists only `EGY-PROD`, `KSA-PROD`, `UAE-PROD` — those are **regions**, not stages.

The canonical settings shape to copy is Geidea's own PrestaShop module ([PrestaShop](https://docs.geidea.net/docs/prestashop)), which stores **both credential pairs at once** plus a mode switch:

| Field | Purpose |
|---|---|
| **Mode** | `sandbox` or `live`. PrestaShop's default is **sandbox** — ours should default to sandbox too |
| Sandbox Merchant Public Key | Test credentials |
| Sandbox Merchant API Password | Test credentials |
| Production Merchant Public Key | Live credentials |
| Production Merchant API Password | Live credentials |
| Environment | Region (host selection) |

**Do not copy the Fawry `staging` semantics.** `gateway.fawry.staging` flips to a *different host* (`atfawry.fawrystaging.com` instead of `www.atfawry.com`). Geidea's mode flag must **not** touch the host — it selects which key pair to sign and authenticate with. A developer working from `FawryPaymentGateway.php` as a template will get this wrong by default, so it is worth a comment at the point of use.

Practical consequences for our implementation:

- Store **four** credential fields, not two. Both API passwords are secrets and both go through `Crypt` and `secretPaths()`.
- Keep both pairs populated in production so an operator can flip to sandbox to reproduce a problem, then flip back, **without re-entering keys and without a deploy**.
- The mode switch is an admin setting in `resources/views/storefront/settings.blade.php`, not an env var — the rest of the gateway config already lives in storefront settings per business.
- Make the active mode **visible in the admin UI and in the checkout logs**. A test-mode order that silently looks real is worse than a failed payment.
- **Use `isTest` as a tripwire.** Order responses from Fetch Order carry an `isTest` boolean ([Fetch Transaction or Order Details](https://docs.geidea.net/docs/fetch-1)). In `applyPaymentResult()`, if `isTest` disagrees with our configured mode, refuse to mark the order paid and log loudly. That single check catches "we shipped with sandbox keys" and "someone pointed test traffic at live keys" — both of which are otherwise invisible until reconciliation.
- **Test cards only work under sandbox credentials.** The card numbers and the expiry-date-driven outcomes in §10 will simply be declined by a live account.
- Tie mode to the deployment: staging/local default to sandbox, production to live. Since the value is per-business settings rather than env, guard it — a staging database restored from production will arrive carrying `live`.

---

## 4. Choose the integration mode

[Overview](https://docs.geidea.net/docs/overview) splits Geidea into pre-built and API solutions.

| Mode | Use for us? | Why |
|---|---|---|
| **HPP Checkout** (hosted payment page) | **Yes — this is the target** | No PCI-DSS scope for us, 3DS 2.0 handled by Geidea, all payment methods light up without extra dev work. Matches how Fawry is already wired. [Geidea HPP Checkout](https://docs.geidea.net/docs/geidea-checkout-v2) |
| **Direct API** (Initiate Authentication → Authenticate Payer → Pay) | No | Requires us to be **PCI DSS compliant** because card data touches our systems. [Direct API Integration](https://docs.geidea.net/docs/pg-direct-api) |
| **Pay by Link — APIs** | Optional later | Useful for POS staff collecting remotely (phone/WhatsApp orders), not for storefront checkout. [Pay by Link (Egypt & UAE)](https://docs.geidea.net/docs/payment-links) · [Pay by Link - APIs](https://docs.geidea.net/docs/pay-by-link-apis) |
| **Pay by Link — Portal** | No dev work | Staff-operated, no integration. [Portal (Egypt)](https://docs.geidea.net/docs/pay-by-link-portal) |

**Decision to record before coding:** HPP Checkout, `paymentOperation: Pay` (immediate capture), Egypt region. Pre-authorization (`PreAuthorize` + Capture) is available if the business later wants to hold funds until a console/game is confirmed in stock — see [Overview → Features](https://docs.geidea.net/docs/overview) and [Capture Transaction](https://docs.geidea.net/reference/capture-transaction-1).

---

## 5. Backend work (Laravel)

### 5.1 Register the driver and its settings

1. Add `geidea` to `drivers` and `labels` in `config/storefront-payments.php`.
2. Extend the `gateway` defaults in `StorefrontSettingService` with a `geidea` block. Required keys: `mode` (`sandbox` default / `live`), **sandbox** public key + API password, **production** public key + API password, region (`egypt`/`ksa`/`uae`), and HPP display mode (§6.3). See §3.2 for why the credentials are doubled and why `mode` must not switch hosts.
3. Add **both** API passwords to `secretPaths()` so they are `Crypt`-encrypted at rest, exactly like `gateway.fawry.security_key`.
4. Add the fields to the **Payment gateway** section of `resources/views/storefront/settings.blade.php`, and show the callback URL there (the Fawry section already does this) so an operator can paste it into the Geidea portal. Show the active mode prominently, and make the mode switch a deliberate control rather than a stray checkbox.
5. Public `GET /settings` must expose **only** the provider slug / enabled flag — never keys. Follow the existing pattern in `SettingsApiService`.

> Module on/off toggles belong in `config/features.php` per `.cursor/rules/feature-modules.mdc`; **merchant credentials** belong in storefront settings, which is where Fawry's already are. Keep that split.

### 5.2 Map our driver contract onto Geidea's flow

| `PaymentGatewayInterface` method | What it must do for Geidea |
|---|---|
| `name()` | Return `geidea` |
| `buildChargeSession()` | Resolve the credential pair for the active mode (§3.2), make the server-to-server **Create Session** call, then return a **client-safe** payload: `session.id`, the region HPP script URL, display mode, locale, and the return URL. **Never** include the public key + API password pair, and never include the signature material |
| `verifyWebhookPayload()` | Recompute and compare the callback `signature` (§5.3) |
| `verifyReturnPayload()` | Do **not** trust browser-supplied status. Treat the browser callback as a *hint* and re-verify server-side via Fetch Order (§5.4) |
| `fetchStatus()` | Call Fetch by Merchant Reference with our `storefront_order_id`, or Fetch by `orderId` when we stored it |
| `applyPaymentResult()` | Validate amount + currency against the POS transaction, cross-check `isTest` against the configured mode (§3.2), then map status (§5.5) and call `StorefrontPaymentRecorder` |
| `webhookResponse()` | Return `200` on accepted, non-`2xx` on invalid signature so Geidea retries. Do not copy Fawry's `300`/`202` numeric convention |

### 5.3 Signatures — three different recipes, do not share one helper blindly

Every signature is: concatenate the listed values in order → **SHA-256 keyed with the Merchant API Password** (HMAC, as shown in the vendor PHP sample) → **base64-encode**.

| Operation | Concatenation order | Reference |
|---|---|---|
| Create Session | Merchant Public Key, amount (**formatted to exactly 2 decimals**), currency, `merchantReferenceId`, timestamp | [HPP Checkout → Signature](https://docs.geidea.net/docs/geidea-checkout-v2) |
| Callback / webhook validation | Merchant Public Key, order amount, order currency, `orderId`, status, `merchantReferenceId`, timestamp | [Webhook/Callback Notifications](https://docs.geidea.net/docs/sample-callback-responses) |
| Refund | timestamp, Merchant Public Key, refund amount, `orderId` | [Refund](https://docs.geidea.net/docs/refund-2) |

Notes that will bite if ignored:

- The amount must be rendered with two decimals before hashing; the vendor sample normalizes it explicitly.
- The `timestamp` used in the hash must be the **same string** sent in the request body.
- The callback recipe's field order is **not** the same as the session recipe's. Keep them as separate private methods.
- Confirm the exact `status` value used in the callback hash against a real sandbox callback (the payload carries both `status` and `detailedStatus`). Log the first sandbox callbacks verbatim while validating.

### 5.4 Callbacks, return URLs, and who is authoritative

Two different URLs, two different jobs — both come from the Create Session request:

| Field | Points at | Purpose |
|---|---|---|
| `callbackUrl` | `{APP_URL}/api/storefront/v1/payments/geidea/webhook` | Server-to-server truth. **Must be HTTPS with a valid certificate** |
| `returnUrl` | `{STOREFRONT_URL}/{lang}/checkout/payment/return/?order={storefront_order_id}` | Where the shopper lands afterwards (already built by `PaymentReturnController::buildReturnUrl()`) |

Behaviour we must design around, from [Webhook/Callback Notifications](https://docs.geidea.net/docs/sample-callback-responses):

- **No callback is sent while an order is `InProgress`.** Abandoned attempts, 3DS cancellations mid-flow, and authentication failures produce **no** separate callback. Do not build UI that waits for one.
- Closing the HPP **does** produce a callback with detailed message `Transaction Cancelled By User`.
- A shopper may retry many times on one order. The eventual success callback **consolidates every attempt** under the same `orderId`. Our webhook handler must therefore be **idempotent** and must not regress a paid order.
- Only treat a payment as good when **all four** hold: `responseCode` `000`, `responseMessage` `Success`, `detailedResponseCode` `000`, `detailedResponseMessage` `The operation was successful` — **and** the amount matches our order. The vendor doc calls this out as a warning.

Changes needed in `PaymentReturnController`:

- Its docblock and response keys are Fawry-shaped (`fawry_ref_number`). Add provider-neutral keys (for example a generic reference/order id) and keep the Fawry keys as aliases so the existing Qwik return page and mobile screen keep working.
- `PaymentResult` (`app/Services/Storefront/Payment/PaymentResult.php`) has a `fawryRefNumber` property. Generalize or alias it the same way.
- For Geidea, `confirm()` should accept the browser-reported `orderId` and then **re-fetch from Geidea** before recording anything. The browser payload is unsigned in HPP mode, so it cannot be the basis for marking an order paid.

### 5.5 Status mapping

Read `order.detailedStatus` / `order.status` from Fetch Order or the callback, per [Fetch Transaction or Order Details](https://docs.geidea.net/docs/fetch-1).

| Geidea | Our `payment_status` | Action |
|---|---|---|
| `Paid` / `Captured` (+ the four success codes) | `paid` | `markPaid()`; existing digital-fulfilment and push jobs then fire on the paid path |
| `Authorized` (only if we adopt `PreAuthorize`) | pending | Store meta; capture later |
| `InProgress` / `Initiated` | pending | Store meta only; never fulfil |
| `Cancelled` (`responseCode` `010`) | leave due | Surface a cancel message |
| `Failed` / `OrderFailed` / `Rejected` | leave due | Show retry; a new session is required |
| `Refunded` / `PartiallyRefunded` | out of scope for v1 | Reconcile in POS manually |

Code groups worth handling explicitly: `000` success, `010` cancelled, `100` general, `110` HPP integration error, `300` 3DS failure, `700` pay failure. Full tables: [API Response Codes and Messages](https://docs.geidea.net/docs/api-response-codes-and-messages).

### 5.6 Session lifetime and idempotency

- **A session expires 15 minutes after creation** ([HPP Checkout](https://docs.geidea.net/docs/geidea-checkout-v2)). Our existing `POST /payments/{provider}/session` endpoint already exists to mint a fresh session for a pending order — wire Geidea into it so a shopper returning to an unpaid order gets a new session instead of an expired one.
- `merchantReferenceId` should be our `storefront_order_id`, so callbacks and Fetch-by-reference line up with the POS transaction.
- **Verify with Geidea before building:** the Create Session API reference describes `merchantReferenceId` as "must be a valid UUID", while the HPP guide's own examples use non-UUID strings. Our `storefront_order_id` is not a UUID. Either confirm free-form strings are accepted in the Egypt environment, or store a generated UUID per order and keep our id in `metadata.custom`.

### 5.7 Checkout plumbing

In `app/Services/Storefront/CheckoutService.php`:

- `normalizePaymentMethod()` currently collapses `card` / `fawry` / `online` to `fawry`. Make it resolve to **the configured active provider** instead of a hardcoded slug.
- `assertFawryEnabled()` should become a provider-generic guard that asks the driver whether it is configured.
- `appendPaymentSession()` similarly hardcodes `driver('fawry')` — switch it to the active driver.

Keep the API contract stable: `POST /checkout` continues to accept `payment_method: card`, and continues to return a `payment` block. Only the block's inner shape differs per provider.

### 5.8 Tests

Mirror the existing suites (`tests/Feature/Storefront/FawryPaymentTest.php`, `tests/Unit/.../FawryPaymentGatewayTest.php`):

- Signature generation for all three recipes against fixed known inputs.
- Webhook: valid signature → paid; tampered signature → rejected; amount mismatch → rejected; **replayed success callback → still one payment, no duplicate fulfilment**.
- Cancelled callback leaves the order unpaid.
- Session creation for an already-paid order short-circuits (the controller already does this).
- **Mode resolution:** `sandbox` mode signs with the sandbox key pair, `live` with the production pair, and **neither changes the host**. Also assert the mode default is `sandbox` when unset.
- **`isTest` mismatch** (live mode, test-flagged order) does not mark the order paid.
- Mock all Geidea HTTP calls (`Http::fake`) — no live calls in the suite.

---

## 6. Storefront work (Qwik — `storefront-qwik/`)

### 6.1 Where it goes

| File | Change |
|---|---|
| `src/routes/[lang]/checkout/index.tsx` | Method picker label comes from settings; do not hardcode "Fawry" |
| `src/routes/[lang]/checkout/payment/index.tsx` | Launch Geidea when the active provider is Geidea |
| `src/routes/[lang]/checkout/payment/return/index.tsx` | Handle Geidea's result object; keep server confirmation |
| `src/lib/geidea-checkout.ts` **(new)** | Script loader + launcher, modelled on `src/lib/fawry-pay.ts` |
| `src/lib/types.ts` | Add the Geidea session type |
| `src/i18n/en.json`, `src/i18n/ar.json` | New strings (EN + AR, both required) |
| `src/lib/security/csp.ts` | Allow the Geidea hosts (§6.4) |

### 6.2 Client flow

Per [HPP Checkout → Steps 2 and 3](https://docs.geidea.net/docs/geidea-checkout-v2):

1. Load `geideaCheckout.min.js` from the region host. **The vendor explicitly requires no `defer` and no `async`** — the global must exist before initialization runs. Load it from the payment route only, never from the root layout, per `.cursor/rules/qwik-minimal-chunks.mdc`.
2. Construct the checkout object with three handlers: `onSuccess`, `onError`, `onCancel`. All three are mandatory.
3. Start the payment with the `session.id` obtained from our Laravel `payment` block.
4. Each handler receives one object: `responseCode`, `responseMessage`, `detailedResponseCode`, `detailedResponseMessage`, `orderId`, `reference`. `orderId` is Geidea's order GUID; `reference` is a support-lookup GUID that exists even when no order was created.
5. On success **do not** show "paid" straight from the browser. Post `orderId` to `POST /payments/geidea/return`, let Laravel verify against Geidea, and render from that response — the pattern the current return page already follows.

### 6.3 Display mode

Three modes, same library and session id:

| Mode | Behaviour | Note |
|---|---|---|
| **Popup / modal** | Overlay iframe on our page | Vendor-recommended default; the session `appearance.uiMode` value is `modal` |
| **Drop-in** | Renders into a `div` we supply | Session `appearance.uiMode` is `dropin`; the target element must exist in the DOM before starting |
| **Redirection** | Opens the Geidea-hosted page | Session `appearance.uiMode` is `redirection` |

Recommendation: **popup** on web (keeps the shopper on our checkout, no CSP `form-action` gymnastics), **redirection** for the mobile fallback in §7.2. Make the mode a setting so it can be changed without a deploy.

### 6.4 CSP

`src/lib/security/csp.ts` currently allows only the Fawry hosts. Add the Geidea script/frame hosts for the configured region to `script-src`, `frame-src`, and — if redirection mode is ever used — `form-action`. Keep `PUBLIC_CSP_REPORT_ONLY=true` while validating so a missing host surfaces as a report rather than a broken checkout.

### 6.5 UX rules that already apply

- Wrap every async action in `withPendingFeedback` per `.cursor/rules/storefront-pending-feedback.mdc`; disable the pay button while a session is being created.
- Checkout, payment, and return pages stay `noindex, nofollow` per `.cursor/rules/storefront-seo.mdc`.
- Use SweetAlert2 / toast for outcomes, not `alert()` (the vendor samples use `alert()` purely for brevity).
- Pass `language` as `en` or `ar` on the session so the HPP matches the active locale; RTL must be verified.
- The Fawry flow has a "pay later at a Fawry outlet with this reference" state. **Geidea HPP has no equivalent** — its flows are terminal at the HPP. Remove that copy from the Geidea path rather than leaving a dead reference field. (If offline collection is wanted, that is Meeza QR or Pay by Link, §7.)

---

## 7. Mobile work (Expo — `storefront-mobile/`)

> Before touching this app, read the versioned Expo docs as required by `storefront-mobile/AGENTS.md`.

Geidea ships native SDKs for [Android](https://docs.geidea.net/docs/android-sdk), [iOS](https://docs.geidea.net/docs/ios-sdk), [React Native](https://docs.geidea.net/docs/react-native-1), and [Flutter](https://docs.geidea.net/docs/flutter-1). Overview: [Mobile SDKs](https://docs.geidea.net/docs/e-commerce-sdks).

Both options below keep the same non-negotiable rule already documented in [`MOBILE.md`](./MOBILE.md): **the session is created by Laravel; no merchant secret ever ships in the app binary.**

### 7.1 Option A — native React Native SDK (best UX)

From [React Native](https://docs.geidea.net/docs/react-native-1):

| Item | Requirement |
|---|---|
| Package | `@geidea/payment-sdk-react-native`, delivered as a **`.tgz` archive from Geidea** — it is not on public npm, so the tarball must be vendored in the repo or hosted in a private registry |
| Entry point | `payWithGeidea({ sessionId, merchantId, language, environment, region, primaryColor, secondaryColor, merchantLogo })` |
| Mode + region | The SDK mirrors the same two axes as the server: `region` (`egypt`, …) and `environment` (the vendor sample passes `prod`). **Confirm the sandbox value with Geidea** — it is not documented. Derive both from our storefront settings and return them alongside the session id, so the app never hardcodes a stage |
| Result | A status of `completed` or `canceled`, plus a result payload |
| Android toolchain | minSdk **24**, compile/target SDK **35**, NDK **27.1.12297006**, Kotlin **1.9.24**, AGP **8.7.3** |
| Android Gradle edits | A `flatDir` repository pointing at the package's `android/libs`, plus Compose BOM, Material 3, `navigation-compose`, `navigation-runtime-ktx`, `androidx.activity`, and `kotlinx-collections-immutable` dependencies |
| Android manifest | A `network_security_config.xml` referenced from `<application>` (the vendor sample uses it for local dev hosts only — do **not** ship cleartext exemptions to production) |
| Assets | Merchant logo added to Android `res/drawable` and to the iOS asset catalog; referenced by name via `merchantLogo` |
| iOS | `pod install` after adding the package |
| Apple Pay | `merchantId` in the call is the **Apple Pay** merchant id — see [Apple Pay](https://docs.geidea.net/docs/apple-pay) |

**Expo-specific constraints (the vendor docs assume bare React Native):**

- The app uses `expo prebuild`, so hand edits to `android/build.gradle` and `android/app/build.gradle` are destroyed on the next `--clean`. Encapsulate all of the above in a **local Expo config plugin** (or commit the native directories deliberately). Do not document manual gradle edits as the process.
- **Expo Go cannot work** — this is a native module. Dev Client / EAS Build only, which is already the case for Fawry.
- Verify the SDK's Kotlin/AGP/compileSdk pins against what the installed Expo SDK's `expo-build-properties` produces. A version clash here is the most likely source of a red build, and it should be resolved with `expo-build-properties` rather than by editing generated files.
- Version `0.0.1` appears in the vendor install snippet. Confirm the current version and its Expo/React Native compatibility with Geidea before committing to Option A.

### 7.2 Option B — hosted page in a web view (zero native work, good fallback)

1. Laravel creates the session exactly as for web.
2. Open the HPP in **redirection mode** via an auth-session browser (`expo-web-browser`), or a `WebView`.
3. Set the session `returnUrl` to an app deep link. Geidea's own samples show a custom scheme return URL (`geidea://paymentsdk/return`), so `gamesspot://` links — already registered for this app — are an accepted shape. Confirm the scheme is whitelisted for the merchant account.
4. On return, call `POST /payments/geidea/return` (or `fetchStatus` via our API) and render from Laravel's answer.

This is the pragmatic first ship: it reuses the entire web integration and adds no native surface. Option A can replace it later without any backend change.

### 7.3 Mobile files to touch

| File | Change |
|---|---|
| `src/lib/geidea.ts` **(new)** | Launcher, modelled on `src/lib/fawry.ts` (which already does a guarded dynamic `require` so the app still bundles when the native module is absent — reuse that trick) |
| `app/checkout/payment.tsx` | Branch on the active provider |
| `app/checkout/index.tsx` | Method label from settings |
| `src/lib/types.ts`, `src/lib/i18n.ts` | Session type + EN/AR strings |

The webhook stays the source of truth on mobile too; the SDK/browser result only drives what the shopper sees.

---

## 8. Optional capabilities (enable per business decision, later)

Each of these is a **Create Session parameter or a portal switch**, not a new integration — which is the main argument for HPP over Direct API.

| Capability | How it turns on | Reference |
|---|---|---|
| Apple / Google / Samsung Pay inside the HPP | On by default where supported; suppress with `hideWallets` | [Express Checkout (Wallets)](https://docs.geidea.net/docs/express-checkout-wallets) |
| Standalone wallet button on our own page | `expressCheckouts` array in the session + a different SDK class + `.mount()` into a container | [Express Checkout (Wallets)](https://docs.geidea.net/docs/express-checkout-wallets) · [Google Pay](https://docs.geidea.net/docs/google-pay) |
| Save card / card-on-file | `cardOnFile: true` on the session; a `tokenId` comes back on the callback; later sessions send `tokenId` | [Tokenization](https://docs.geidea.net/docs/tokenization) · [Tokenization in Mobile Apps](https://docs.geidea.net/docs/tokenization-in-mobile-apps) · [Standalone Save Card](https://docs.geidea.net/docs/save-card) |
| Meeza QR wallet (Egypt) | Merchant registration with Meeza + National ID and Commercial Register configured by Geidea ops | [Meeza QR Wallet Payment](https://docs.geidea.net/docs/meeza-qr-payment-method) · [Meeza QR APIs](https://docs.geidea.net/docs/meeza-qr-apis) |
| Bank installments (Egypt) | Enabled per merchant + plans chosen with the account manager; BIN-driven, shown inside the HPP | [Installments (Egypt)](https://docs.geidea.net/docs/installments) |
| BNPL (Egypt) | ValU, Souhoola | [BNPL](https://docs.geidea.net/docs/buy-now-pay-later-bnpl) · [ValU](https://docs.geidea.net/docs/valu) · [Souhoola](https://docs.geidea.net/docs/souhoola) |
| BNPL (UAE/KSA only) | Tamara needs full customer + `order.items` detail; Tabby needs `returnUrl`, phone, and items | [Tamara](https://docs.geidea.net/docs/tamara) · [Tabby](https://docs.geidea.net/docs/tabby-uae-ksa) |
| Recurring / subscriptions | Auto-debit via stored token, or recurring payment links | [Recurring Payments](https://docs.geidea.net/docs/recurring) · [Subscriptions](https://docs.geidea.net/docs/subscriptions) |

**Data-quality note:** if BNPL or installments are ever enabled, the session must carry the richer `customer` and `order.items` objects (id, name, description, categories, count, price, sku, plus billing/shipping address). Our checkout already has all of it. Send it from day one so enabling a method later is a settings change rather than a code change.

---

## 9. Post-payment operations

| Operation | Constraint | Reference |
|---|---|---|
| **Refund** (full or partial, repeatable) | Only for **paid / captured / settled** transactions. Geidea blocks cumulative over-refunds. Needs its own signature and `orderId` | [Refund](https://docs.geidea.net/docs/refund-2) |
| **Void** | Only for **pre-authorized, uncaptured** transactions. Authorizations auto-void if not captured within 28 days | [Void](https://docs.geidea.net/docs/void-1) |
| **Cancel order** | Only valid **before** the Pay step. After Pay it returns "Order is already completed" | [Cancel Order](https://docs.geidea.net/docs/cancel-order-1) |
| **Reconciliation / status polling** | Fetch by `orderId` or by `merchantReferenceId`; also useful as a cron safety net for orders stuck pending | [Fetch](https://docs.geidea.net/docs/fetch-1) · [Fetch by reference](https://docs.geidea.net/docs/fetch-transaction-or-order-details-by-merchant-reference) · [Fetch all / search](https://docs.geidea.net/docs/fetch-all-transactions-or-search-transactions) |

For v1, refunds can stay **portal-operated** (the merchant portal has Refund / Partial Refund buttons on paid transactions). Returns/cancel are already deferred in [`STOREFRONT_PROGRESS.md`](./STOREFRONT_PROGRESS.md), so an API-driven refund should land with that work rather than ahead of it. Store the Geidea `orderId` on our transaction payment meta now, otherwise refunds later have nothing to reference.

---

## 10. Testing

| What | How |
|---|---|
| Mode | All of the below requires **sandbox credentials** (§3.2). A live account declines test cards |
| Vendor test page | `https://www.merchant.geidea.net/testpage` — sanity-check credentials before touching our code ([Test Cards](https://docs.geidea.net/docs/test-cards)) |
| Test cards | Mastercard / Visa / MADA / Amex numbers for 3DS challenge, 3DS frictionless, and not-enrolled cases ([Test Cards](https://docs.geidea.net/docs/test-cards)) |
| Forcing outcomes | The **expiry date** drives the result: `01/39` success, `05/39` declined-contact-bank, `04/27` expired card, `08/28` provider timeout, `05/37` unknown provider error |
| CVV outcomes | `100` match, `101` not processed, `102` no match (Amex uses `1000` / `1010` / `1020`) |
| Response codes | [API Response Codes and Messages](https://docs.geidea.net/docs/api-response-codes-and-messages) |
| Webhook delivery | Point `callbackUrl` at a request-bin first, capture real payloads, then build signature verification against those fixtures |
| Glossary / FAQ | [Payments glossary](https://docs.geidea.net/docs/payments-glossary) · [Troubleshooting & FAQs](https://docs.geidea.net/docs/troubleshooting-faqs) |

Scenarios to run end-to-end on both storefront and mobile: success, 3DS challenge success, declined card, shopper closes the HPP, session left to expire past 15 minutes then retried, duplicate/replayed webhook, and a digital-goods order (credentials must only be allocated after `paid`).

Mode-specific scenarios, easy to forget and expensive to miss:

- Flip mode sandbox → live → sandbox in admin and confirm the very next session uses the newly selected key pair (no cached client, no cached config).
- With mode set to `live` but sandbox keys saved, confirm the `isTest` tripwire (§3.2) blocks the order from being marked paid instead of silently accepting it.
- One **real, small live transaction** before launch, then refund it from the merchant portal — this is the only way to prove production credentials, the production callback URL, and settlement are all actually wired.

---

## 11. Open items to confirm before implementation

1. **Region and legal entity** — Egypt confirmed? It fixes both hosts and the available payment methods.
2. **`merchantReferenceId` format** — UUID required, or is our `storefront_order_id` acceptable? (§5.6)
3. **Callback signature `status` field** — which of `status` / `detailedStatus` participates in the hash. Validate against a captured sandbox callback.
4. **Coexistence with Fawry** — one active provider at a time (today's model), or both offered side by side at checkout? Offering both means the settings shape and the checkout method picker change from "the provider" to "a list of providers".
5. **`paymentOperation`** — `Pay` (capture now) vs `PreAuthorize` + Capture for stock-risky items.
6. **Methods to enable at launch** — cards only, or cards + Meeza QR + installments? This drives what session fields are mandatory.
7. **Mobile path** — ship Option B (web view) first, or wait for Option A (native SDK)? Confirm the SDK's current version and Expo compatibility with Geidea either way.
8. **Refund path** — portal-only for v1, confirmed?
9. **Sandbox account shape** — are the test and production credentials two separate merchant accounts (so both key pairs can be stored side by side, as PrestaShop assumes), or one account that Geidea toggles? This decides whether the "flip to sandbox without re-entering keys" workflow in §3.2 is actually possible.
10. **Mobile SDK `environment` enum** — the documented sample only shows `prod`. Get the sandbox value before building Option A.
11. **Callback URL per mode** — one URL for both modes (our webhook can serve both, since the route is provider-scoped not mode-scoped), or does Geidea want a separate registration per account? Confirm when registering.

---

## 12. Go-live checklist

- [ ] Production Merchant Public Key + API Password stored **encrypted** in storefront settings; nothing in git, nothing in a client bundle.
- [ ] **Mode set to `live`** on production, and sandbox keys still saved (so support can reproduce issues later without hunting for credentials).
- [ ] **Staging/local still default to `sandbox`** — verified after any database copy from production, which carries the `live` value with it.
- [ ] `isTest` tripwire active: a sandbox-flagged order can never be marked paid while in live mode.
- [ ] Correct region host configured for the production account.
- [ ] `callbackUrl` registered in the Geidea portal, HTTPS with a valid certificate, reachable from outside.
- [ ] Webhook verified idempotent under replay and under the consolidated multi-attempt callback.
- [ ] The four-part success check (`responseCode`, `responseMessage`, `detailedResponseCode`, `detailedResponseMessage`) plus an amount + currency match before any order is marked paid.
- [ ] Geidea `orderId` persisted on our transaction for later refunds and support.
- [ ] CSP updated and verified enforcing (not report-only) on production.
- [ ] Branding (name, logo, colors) configured in the Geidea portal for both HPP and the mobile SDK.
- [ ] EN + AR verified on the HPP, including RTL.
- [ ] One real live transaction completed and refunded from the portal.
- [ ] Feature tests green; no live network calls in the suite.
- [ ] Fawry path still works — this is additive.
- [ ] Docs updated in the same change: [`API.md`](./API.md) (new provider slug, session block shape, webhook URL, admin settings), [`STOREFRONT_PROGRESS.md`](./STOREFRONT_PROGRESS.md), [`MOBILE.md`](./MOBILE.md) + [`MOBILE_PROGRESS.md`](./MOBILE_PROGRESS.md) — per `.cursor/rules/documentation-updates.mdc` and the progress-tracker rules.

---

## 13. Vendor reference index

| Topic | Link |
|---|---|
| Product overview | https://docs.geidea.net/docs/overview |
| Credentials / security | https://docs.geidea.net/docs/pre-requisites |
| HPP Checkout (primary guide) | https://docs.geidea.net/docs/geidea-checkout-v2 |
| Create Session (API) | https://docs.geidea.net/reference/create-session-v2-1 |
| Webhooks / callbacks | https://docs.geidea.net/docs/sample-callback-responses |
| Express Checkout / wallets | https://docs.geidea.net/docs/express-checkout-wallets |
| Mobile SDKs | https://docs.geidea.net/docs/e-commerce-sdks |
| React Native SDK | https://docs.geidea.net/docs/react-native-1 |
| Tokenization | https://docs.geidea.net/docs/tokenization |
| Transaction & order management | https://docs.geidea.net/docs/overview-1 |
| Refund / Void / Cancel | https://docs.geidea.net/docs/refund-2 · https://docs.geidea.net/docs/void-1 · https://docs.geidea.net/docs/cancel-order-1 |
| Test vs live mode (settings model) | https://docs.geidea.net/docs/prestashop (Mode + dual credential fields) · https://docs.geidea.net/docs/woocommerce (FAQ: mode is decided by the API keys) |
| Test cards | https://docs.geidea.net/docs/test-cards |
| Response codes | https://docs.geidea.net/docs/api-response-codes-and-messages |
| Troubleshooting / FAQ | https://docs.geidea.net/docs/troubleshooting-faqs |
| Full doc index | https://docs.geidea.net/llms.txt |
