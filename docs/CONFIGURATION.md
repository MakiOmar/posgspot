# Configuration

Operator-facing environment and config notes for this fork. Prefer **`.env.example`** as the key inventory; this file explains non-obvious behavior.

## Storefront social login (Google + Facebook)

Uses **Laravel Socialite**. Credentials are **env-only** (never Storefront Settings JSON / POS admin).

| Variable | Purpose |
|----------|---------|
| `GOOGLE_CLIENT_ID` / `GOOGLE_CLIENT_SECRET` | Web OAuth client (redirect flow) |
| `GOOGLE_REDIRECT_URI` | Defaults to `{APP_URL}/api/storefront/v1/auth/social/google/callback` |
| `FACEBOOK_CLIENT_ID` / `FACEBOOK_CLIENT_SECRET` | Meta app credentials |
| `FACEBOOK_REDIRECT_URI` | Defaults to `{APP_URL}/api/storefront/v1/auth/social/facebook/callback` |
| `STOREFRONT_SOCIAL_GOOGLE` / `STOREFRONT_SOCIAL_FACEBOOK` | Optional force enable/disable (default: enabled when id+secret set) |
| `GOOGLE_ANDROID_CLIENT_ID` / `GOOGLE_IOS_CLIENT_ID` | Extra audiences accepted for mobile Google `id_token` verification |

Config: `config/services.php` (`google` / `facebook`), `config/storefront.php` → `social_login.*`.

Public clients read flags only via `GET /api/storefront/v1/settings` → `social_login.google_enabled` / `facebook_enabled`.

### Mobile (Expo)

Public client IDs in `storefront-mobile/.env` (never secrets):

- `EXPO_PUBLIC_GOOGLE_ANDROID_CLIENT_ID`
- `EXPO_PUBLIC_GOOGLE_IOS_CLIENT_ID`
- `EXPO_PUBLIC_GOOGLE_WEB_CLIENT_ID` (fallback)
- `EXPO_PUBLIC_FACEBOOK_APP_ID`

Scheme: `gamesspot` (see `app.json`). Register the AuthSession redirect URI in Google Cloud / Meta consoles.

API contract: [`docs/public-site/API.md`](public-site/API.md) (Auth → social).
