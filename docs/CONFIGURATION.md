# Configuration

Operator-facing environment and config notes for this fork. Prefer **`.env.example`** as the key inventory; this file explains non-obvious behavior.

**Deploy the public Qwik storefront** (Node Express SSR, env, CORS, reverse proxy): [`docs/public-site/DEPLOY.md`](public-site/DEPLOY.md).

**Parallel VPS staging** (Docker Compose on `thespotmanagment.io`): [`docs/public-site/STAGING_VPS.md`](public-site/STAGING_VPS.md).

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

## Storefront Custom Bundle

Physical-only “build a console package” flow (web `/[lang]/custom-bundle`; Expo can reuse the same API later).

| Variable | Purpose |
|----------|---------|
| `STOREFRONT_CUSTOM_BUNDLE` | Enable API + `settings.custom_bundle.enabled` (default `false`) |
| `STOREFRONT_CUSTOM_BUNDLE_MIN_ITEMS` | Min selected lines before checkout (default `2`) |
| `STOREFRONT_CUSTOM_BUNDLE_MAX_ITEMS` | Max selected lines (default `15`) |

Config: `config/storefront.php` → `custom_bundle.*`. Contract: [`docs/public-site/API.md`](public-site/API.md) (Custom Bundle).

## Storefront Sell to us (trade-in)

Logged-in customers submit trade-in requests (digital account / disc / device). Optional invoice verify against their orders. Staff get a POS list plus email.

| Variable | Purpose |
|----------|---------|
| `STOREFRONT_SELL_TO_US` | Enable API + `settings.sell_to_us.enabled` (default `false`) |
| `STOREFRONT_SELL_TO_US_MAX_PHOTOS` | Max photos per device request (default `6`) |
| `STOREFRONT_SELL_TO_US_MAX_PHOTO_KB` | Max size per photo in KB (default `4096`) |

Notify inbox: **Storefront Settings → Contact → Sell to us notify email** (`settings.sell_to_us.notify_email`; falls back to contact form inbox).

Config: `config/storefront.php` → `sell_to_us.*`. Contract: [`docs/public-site/API.md`](public-site/API.md) (Sell to us).

## Storefront Community CMS

Tournaments, events, and news posts managed in POS (**Community posts**). Public API lists/shows published posts per `X-Content-Locale` (strict — no fallback). Qwik routes: `/tournaments`, `/events`, `/gaming-news`.

| Variable | Purpose |
|----------|---------|
| `STOREFRONT_COMMUNITY` | Enable `GET /community/posts` API (default `false`) |

Config: `config/storefront.php` → `community.enabled`.

## Storefront Track order

Public guest lookup by invoice + phone/email (`POST /track-order`). Qwik page `/[lang]/track-order` also lists signed-in account orders.

No env flag — always available (throttled). Phone matching uses the same national-digit normalize as repair lookup.

## Storefront Request a product

Guest or signed-in customers submit product requests; staff get POS list + email.

| Variable | Purpose |
|----------|---------|
| `STOREFRONT_REQUEST_PRODUCT` | Enable `GET /request-product/meta` + `POST /request-product/requests` (default `false`) |

Notify inbox: **Storefront Settings → Request a product notify email** (`settings.request_product.notify_email`; falls back to contact form inbox).

Config: `config/storefront.php` → `request_product.enabled`.

## Accounts catalog sync (per-offer products)

Accounts pushes one hidden POS product per game offer / card category through `POST /api/accounts/catalog/upsert/{business_id}`. Those products are filed under an existing POS category and brand looked up by slug:

| Variable | Purpose |
|----------|---------|
| `ACCOUNTS_CATALOG_CATEGORY_SLUG` | Category for synced game offers (default `digital-games`; created only if the slug does not exist) |
| `ACCOUNTS_CATALOG_CARD_CATEGORY_SLUG` | Category for synced gift cards (default: same as the game category) |
| `ACCOUNTS_CATALOG_BRAND_SLUG` | Brand for all synced products (default `games-spot`; a missing brand is logged and leaves the product's brand unchanged) |

Config: `config/services.php` → `accounts.catalog_*`. Changes apply on the next sync of each product (`php artisan pos:sync-catalog` in Accounts); run `php artisan config:clear` after editing `.env`.

## POS backups

**POS → Backup** (`/backup`, administrator usernames only) lists archives, runs **Backup now**, and sets the automatic schedule. Schedule settings live in the `system` table (`backup_auto_*`), not `.env`:

| Setting | Default | Options |
|---------|---------|---------|
| Enabled | on | on / off |
| Interval | hourly | 30 min, 1 / 2 / 6 / 12 h, daily 01:30, weekly Sunday 01:30 |
| Scope | database + uploads + `.env` | or database only |


**Retention:** backups are never deleted automatically — only via Delete on the page. `backup:clean` is a no-op (`App\Backup\Cleanup\KeepAllBackups`). The page shows total backup size and free disk space; hourly full backups grow by roughly 24 archives/day, so watch disk usage or switch to database-only / a longer interval.

The scheduler runs `php artisan backup:auto` (backup with the page's scope) on the chosen interval in every environment except `demo`. It needs the Laravel scheduler cron on the server (shown on the page):

```
* * * * * php /path/to/artisan schedule:run >> /dev/null 2>&1
```

| Variable | Purpose |
|----------|---------|
| `BACKUP_DISK` | Filesystem disk for archives (default `backups` → `storage/app/backups`). `local` is **redirected** to `backups`, because the `local` disk is `public/uploads` (web-accessible). |
| `BACKUP_ARCHIVE_PASSWORD` | Optional AES-256 zip password |

MySQL dumps use `--single-transaction` (`config/database.php` → `mysql.dump`) so backups don't lock tables; all tables are InnoDB. Only failure notifications are mailed (`config/backup.php`). If older archives exist in `public/uploads/UltimatePOS/`, move them to `storage/app/backups/UltimatePOS/` and delete the public copies.

## Upload images to WebP

When PHP GD has `imagewebp()`, raster uploads (JPEG/PNG/GIF/BMP) are converted to WebP after store. SVG, ICO, animated GIF, and existing WebP are left unchanged. Applies to POS `Util::uploadFile(..., 'image')` (products, brands, business logo, storefront Appearance uploads, etc.), the storefront media library, customer avatars, and sell-to-us photos.

| Variable | Purpose |
|----------|---------|
| `UPLOAD_WEBP_CONVERT` | Convert on upload (default `true`) |
| `UPLOAD_WEBP_QUALITY` | GD WebP quality 1-100 (default `82`) |

Config: `config/images.php`.

**Bulk existing assets** (storefront settings/library + POS products/categories/brands/gallery):

```
php artisan storefront:convert-images-to-webp
php artisan storefront:convert-images-to-webp 1 --dry-run --verbose-details
php artisan storefront:convert-images-to-webp --only=catalog
php artisan storefront:convert-images-to-webp --only=storefront --keep-originals
```

`--only=all` (default) converts storefront Appearance/library **and** catalog (`products.image`, category image/shelf images, brand logos, `media` gallery files). Use `--only=catalog` or `--only=storefront` to limit scope.

External image URLs in homepage sections (promo tiles, hero slides, etc. — e.g. WordPress `/wp-content/uploads/…`) are downloaded, converted to WebP, saved under `uploads/storefront_library/{business_id}/`, and the settings `url` is replaced with a local `image` path.

### Storefront footer reseed

```
php artisan storefront:reset-footer
php artisan storefront:reset-footer --business_id=1 --force
```

Replaces saved footer menus (Storefront Settings → Footer) with the defaults (Shop / My Account / Help / Company). Only the `footer` key is rewritten; other storefront settings are kept. The previous footer is printed as JSON first, so it can be re-entered manually. `--business_id` defaults to `STOREFRONT_BUSINESS_ID`.
