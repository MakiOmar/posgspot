# Configuration

Operator-facing environment and config notes for this fork. Prefer **`.env.example`** as the key inventory; this file explains non-obvious behavior.

**Deploy the public Qwik storefront** (Node Express SSR, env, CORS, reverse proxy): [`docs/public-site/DEPLOY.md`](public-site/DEPLOY.md).

**Production VPS** (Docker Compose behind Cloudflare on `gamesspoteg.com`): [`docs/public-site/STAGING_VPS.md`](public-site/STAGING_VPS.md).

## Trusted proxies (`TRUSTED_PROXIES`)

`config/trustedproxy.php` reads `TRUSTED_PROXIES`, a comma-separated list of IPs/CIDRs whose `X-Forwarded-*` headers are trusted. Behind Cloudflare and Traefik every hop must be listed (Docker private ranges plus Cloudflare's published ranges). Otherwise `$request->ip()` returns a Cloudflare edge address, and the per-IP storefront rate limiters throttle all visitors together. Empty keeps the legacy behaviour of trusting only the immediate caller. Accounts reads the same key.

## Config caching and `env()`

Production web containers run `php artisan optimize --except=views` at boot (see [STAGING_VPS](public-site/STAGING_VPS.md)). With a cached config, `env()` returns `null` outside `config/*.php`, so application code must read `config(...)`. Keys moved into config for this: `services.woo.{base,auth_key,webhook_secret}` (`WOO_BASE`, `AUTH_KEY`, `WOOCOMMERCE_WEBHOOK_SECRET`), `services.google_maps.api_key` (`GOOGLE_MAP_API_KEY`), `services.google.{android_client_id,ios_client_id}`. Mail driver checks use `config('mail.default')`.

## Storefront API caching

- Public catalog endpoints send `Cache-Control: public`, an ETag that includes the content locale, and `Vary: X-Content-Locale, Accept-Language, Origin`. Conditional requests get `304`.
- `storefront.categories.*` (per business and locale) is forgotten whenever a category or category translation is saved, deleted or restored, and after bulk translation or WebP rewrites.
- Accounts proxy calls (platform listings, featured games, card categories) are cached for 30s, and only successful responses are cached, so an Accounts outage is never pinned.
- Storefront settings and `System` properties are memoized per HTTP request (not in console, so queue workers always read fresh values).
- The storefront's own SSR cache (stale-while-revalidate, 15s failure backoff) is described in [API.md](public-site/API.md).

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
| `ACCOUNTS_CATALOG_RATE_LIMIT_PER_MINUTE` | Requests per minute per API user on `POST /api/accounts/catalog/upsert/{business_id}` (default `300`). Replaces the general 60/min `api` limit on that route so an Accounts `pos:sync-catalog` backfill is not cut off at 60 games |

Config: `config/services.php` → `accounts.catalog_*`. Changes apply on the next sync of each product (`php artisan pos:sync-catalog` in Accounts); run `php artisan config:clear` after editing `.env`.

#### Sales orders and POS sells with Accounts products

Synced Accounts products stay `not_for_selling`, so they never appear on the storefront. Product search on the **sales order** form returns them, and so does the **POS / direct sell** screen while `ACCOUNTS_POS_SELL` is on (never `-OFFLINE` offers). On save, POS rejects offline offers, rejects new Accounts lines on plain sells when `ACCOUNTS_POS_SELL` is off, and rejects removing / reducing a line — or deleting a sale — whose account was already allocated (use a sell return instead).

- Lines with **Fetch account now** ticked are allocated from Accounts when the sale is saved (even unpaid). On **sales orders** the rest follow once fully paid; on **POS / direct sells** unticked lines are never fetched (edit the sale and tick the line to fetch later). POS sells are allocated only once **final** (not drafts, quotations or suspended sales). Credentials go to the staff note (and the line note when the storefront "expose credentials" setting is on); sales-order credentials are copied onto the converted invoice, whose copied lines are never allocated again.
- After a POS checkout the cashier sees the allocated credentials in a pop-up (not printed on the receipt).
- One ledger row per unit in `storefront_digital_fulfillments` with `storefront_order_id = so-{sales order id}` or `pos-{sell id}`; Accounts dedupes on that id + line key, and the Accounts order is stamped `pos_order_id` (Send to POS skips it).
- Failures are noted on the staff note and retried by `php artisan accounts:fulfill-sales-orders` (scheduled every 5 minutes, up to 5 attempts).
- **Undo in Accounts:** when a manager undoes an Accounts order that belongs to an `so-` / `pos-` row, Accounts queues `POST /api/accounts/orders/undone/{business_id}` (Passport; body `storefront_order_id`, `storefront_line_key`, optional `accounts_order_id`). POS deletes that ledger row, puts the credentials in the staff note back to `N/A`, strips them from the line notes (including invoices converted from the sales order) and adds an "Account undone" staff-note line. The line is then unfetched: it is unlocked for edit / delete and is **not** refetched automatically; staff tick **Fetch account now** and save to get a new account (a sales order that becomes paid again also fetches it). Missing, already-reset or stale (`accounts_order_id` mismatch) rows answer `200 {"reset": false}`; storefront `sf-` rows are rejected (422).

| Variable | Purpose |
|----------|---------|
| `ACCOUNTS_SALES_ORDER_FULFILLMENT` | `true` (default) / `false` — turn allocation (sales orders and POS sells) on or off |
| `ACCOUNTS_POS_SELL` | `true` (default) / `false` — also sell synced Accounts products from the POS / direct sell screen (storefront unaffected) |
| `ACCOUNTS_SALES_ORDER_STORE_PROFILE_ID` | Accounts store profile credited for these sales orders and POS sells; empty = storefront `digital.accounts_store_profile_id` (default 17) |
| `ACCOUNTS_LEGACY_POS_SKUS` | Comma list of pre-sync Accounts placeholder SKUs counted as account lines by the **Contents** filter (default empty — only list SKUs that are truly Accounts placeholders, never physical products) |
| `ACCOUNTS_LEGACY_PRODUCT_IDS` | Comma list of old manually created game product ids counted as account lines |

**Contents filter:** `transactions.content_kind` (`accounts_only` / `mixed` / `pos_only`) is refreshed whenever sell lines are written and shown as a filter + badge on the sells and sales-order lists. Backfill after deploy or after changing the legacy lists: `php artisan sales:classify-content` (a `--recent` run is scheduled nightly).

**Catalog diagnostics:** `GET /api/storefront/v1/digital/games` adds a `debug` object (Accounts host, request URL, item counts, and a reason such as games with no synced POS product) only when `APP_DEBUG=true`. Keep `APP_DEBUG=false` in production so internal hosts are never exposed; the storefront shows its debug panel only when the API sends this object.

#### Storefront digital fulfilment retries

Paid storefront digital lines are allocated from Accounts on the paid hook. Each ledger row is claimed (`processing`) before the Accounts call, so the payment webhook, the return URL and the scheduler cannot allocate the same line twice. A row stuck in `processing` for 10 minutes is retried.

- `php artisan storefront:fulfill-digital --recent` runs every 5 minutes. It retries `pending` / `failed` rows from the last 7 days, up to 5 failed attempts. It also emails the customer when a retry allocates a line.
- When Accounts answers **202 pending** (a Next Load supplier order is still in progress), the row stays `pending` with the message in `last_error`. This does not count as an attempt. Accounts reuses the same supplier order on the next retry, so the card is never bought twice. See the Accounts repo `docs/NEXTLOAD.md`.
- Staff retry for one order, ignoring the attempts cap: `php artisan storefront:fulfill-digital --transaction={id}`.
- A success response without credentials (no code, or no account email/password) marks the row `failed` instead of `allocated`.

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
| `DB_DUMP_SKIP_SSL` | `true` makes `mysqldump` connect without TLS. Needed when the client (e.g. Debian's MariaDB `default-mysql-client`, as in the staging Docker image) rejects the MySQL server's self-signed certificate. Default `false`. |
| `BACKUP_GOOGLE_DRIVE_ENABLED` | `true` copies each new archive to Google Drive after a successful backup. Only active when the three `GOOGLE_DRIVE_*` credentials below are also set. Default `false`. |
| `BACKUP_GOOGLE_DRIVE_KEEP_DAYS` | Days of archives kept **on Drive** (older Drive copies are deleted; the newest is always kept). Local archives are unaffected. Default `14`. |
| `GOOGLE_DRIVE_CLIENT_ID` / `GOOGLE_DRIVE_CLIENT_SECRET` / `GOOGLE_DRIVE_REFRESH_TOKEN` | OAuth client + refresh token for the Google account that owns the backups. |
| `GOOGLE_DRIVE_FOLDER` | Folder **path** in My Drive (e.g. `Spot Backups/POS`), created if missing. Use a different folder per app. |
| `GOOGLE_DRIVE_TEAM_DRIVE_ID` | Optional Shared Drive ID. |

**Google Drive copy:** Drive is not a Spatie destination — a Drive failure never fails or retries the local backup. `backup:auto` uploads the newest archive and prunes old Drive copies; the result shows as **Google Drive copy** under **Status**. Verify credentials with `php artisan backup:drive-check`. Setup (Google Cloud project, refresh token, troubleshooting): [integrations/GOOGLE_DRIVE_BACKUPS.md](integrations/GOOGLE_DRIVE_BACKUPS.md).

Backups need `mysqldump` on the server (`default-mysql-client` in the staging POS image). A failed run shows its reason under **Status** on the Backup page.

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
