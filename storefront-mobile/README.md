# Games Spot mobile (Expo Dev Client)

React Native storefront client for iOS/Android. Consumes Laravel `/api/storefront/v1`.

See [`docs/public-site/MOBILE.md`](../docs/public-site/MOBILE.md).

## Requirements

- Node 20+
- Expo Dev Client / `npx expo prebuild` (Expo Go **not** supported once Fawry SDK is linked)
- iOS 16.6+, Android API 21+

## Setup

```bash
cp .env.example .env
# set EXPO_PUBLIC_API_BASE to your Laravel origin
npm install
npx expo start
```

### Fawry native SDK (production payments)

```bash
npm install @fawry_pay/rn-fawry-pay-sdk react-native-nitro-modules
npx expo prebuild
npx expo run:android
# macOS: npx expo run:ios
```

Follow Fawry’s Android Maven + iOS Podfile notes in their [sample README](https://github.com/FawryPay/ReactNative-Fawrypay-Anonymous-sample).

**Never** put the merchant security key in the app — Laravel signs checkout sessions.

### Push

Configure `STOREFRONT_FCM_PROJECT_ID` + credentials on Laravel. The app registers device tokens via `POST /account/devices` after login.

### EAS

```bash
npx eas-cli login
npx eas build --profile development --platform android
npx eas build --profile production --platform all
npx eas submit --platform all
```

Update `app.json` → `extra.eas.projectId` and store listing metadata before submit.

## Store listing checklist

- [ ] App name / subtitle EN + AR screenshots
- [ ] Privacy policy URL (`STOREFRONT_URL` legal pages)
- [ ] Support URL / contact email
- [ ] Age rating / payments disclosure
- [ ] TestFlight + Play internal track smoke (COD + Fawry staging)
