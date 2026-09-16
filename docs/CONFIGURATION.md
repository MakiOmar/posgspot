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

## Storefront AI support chat

| Variable | Purpose |
|----------|---------|
| `STOREFRONT_SUPPORT_CHAT` | Enable chat API + `settings.support_chat.enabled` (also needs `OPENAI_API_KEY`) |
| `STOREFRONT_SUPPORT_CHAT_MODEL` | OpenAI chat model (default `gpt-4o-mini`) |
| `STOREFRONT_SUPPORT_CHAT_RATE_LIMIT` | Per-IP requests/minute for `/support/*` (default `30`) |
| `STOREFRONT_SUPPORT_ESCALATION_EMPLOYEE_ID` | CRM `employee_id` for AI-created escalations |
| `STOREFRONT_SUPPORT_ESCALATION_LOCATION_ID` | CRM `location_id` |
| `STOREFRONT_SUPPORT_ESCALATION_CREATED_BY` | CRM `created_by` user id |
| `STOREFRONT_SUPPORT_ESCALATION_SOURCE_NAME` | Escalation source name (default `Storefront AI Chat`; auto-created when possible) |

Config: `config/storefront.php` → `support_chat.*`, `config/openai.php`. Scenarios: [`docs/public-site/AI_SUPPORT_SCENARIOS.html`](public-site/AI_SUPPORT_SCENARIOS.html).

Guests send `X-Support-Guest-Token` (UUID). Escalation to CRM requires a signed-in storefront customer.
