# Vendored Geidea React Native SDK

Private Geidea package (not on public npm).

| File | Version |
|------|---------|
| `geidea-payment-sdk-react-native-0.0.12.tgz` | `@geidea/payment-sdk-react-native@0.0.12` |

Installed via `package.json`:

```json
"@geidea/payment-sdk-react-native": "file:vendor/geidea/geidea-payment-sdk-react-native-0.0.12.tgz"
```

After changing the tarball: `npm install` then rebuild the Dev Client (`npx expo prebuild` / `expo run:android`). Expo Go cannot load this native module.

Server fulfilment stays on `POST /api/storefront/v1/payments/geidea/webhook` (`callbackUrl` on Create Session).
