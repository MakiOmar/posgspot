# Zoho CPaaS transactional mail (Laravel)

How POS and Accounts send transactional mail through **Zoho CPaaS** (formerly ZeptoMail).

**Canonical setup:** the **Zoho CPaaS plugin for Laravel** panel in the console (Agent → SMTP/API → Laravel). That panel is account-specific and matches what Zoho ships today.

Also useful: [Laravel integration (help center)](https://www.zoho.com/cpaas/help/laravel-integration.html), [API authentication](https://www.zoho.com/cpaas/help/api/api-authentication.html), [Multiple data centers](https://www.zoho.com/cpaas/help/api/multiple-data-centers.html).

> The in-product plugin uses package `zohomail/laravel-zeptomail` and mailer `zeptomail`. Some help-center pages still mention `laravel-cpaas` / `MAIL_MAILER=cpaas`. Prefer the **plugin panel** values for this project.

**Scope:** transactional mail only (OTP, password reset, order confirmations, staff alerts, invoices, device-repair notices). CPaaS does **not** support bulk or promotional mail. Newsletters stay on Mailchimp / MailerLite (`app/Services/Storefront/Newsletter/` in POS).

**This account (from the plugin panel):** hosted domain `zoho.com`, Agent `agent_1`, verified sending domain `gamesspoteg.com`.

**Source of truth:** this file in the POS repo. Accounts keeps a short mirror at `gamessspot/docs/ZOHO_CPAAS_MAIL.md`.

---

## How it fits

| App | Path | Transport |
|-----|------|-----------|
| **POS** | System-wide `.env`, and businesses with **Use system email configurations** | CPaaS **API** (`MAIL_MAILER=zeptomail`) |
| **POS** | Business Settings → Email (own host/user/pass) | CPaaS **SMTP** or any SMTP |
| **Accounts** | Single app `.env` only (no per-store mailer) | CPaaS **API** (`MAIL_MAILER=zeptomail`) |

POS storefront/notifications call `StorefrontMailService` / `NotificationUtil::configureEmail()`. Those keep API transports (`zeptomail`, `mailgun`) when the business opts into superadmin settings.

---

## 1. Pre-requisites (Zoho console)

1. **Verify the sending domain** in CPaaS (SPF / DKIM) — `gamesspoteg.com`.
2. Create or pick an **Agent** (here: `agent_1`).
3. Open **Agent → SMTP/API** and use the **Laravel** plugin steps (or copy the Send API key from the API tab).

The plugin’s `.env` example pastes the token **with** the `Zoho-enczapikey ` prefix into `ZEPTOMAIL_TOKEN`. Copy exactly what the panel shows.

`MAIL_FROM_ADDRESS` must be an address on the **verified** domain (e.g. `noreply@gamesspoteg.com`) or Zoho rejects the send.

---

## 2. Install (plugin step 1)

```bash
composer require zohomail/laravel-zeptomail:dev-main
```

Already required in both POS and Accounts.

---

## 3. Register the mailer (plugin step 2)

In `config/mail.php`, under `mailers`:

```php
'zeptomail' => [
    'transport' => 'zeptomail',
],
```

App code reads credentials via `config/services.php`:

```php
'zeptomail' => [
    'host' => env('ZEPTOMAIL_HOST', 'zoho.com'),
    'token' => env('ZEPTOMAIL_TOKEN'),
],
```

---

## 4. Environment (plugin step 3)

```env
ZEPTOMAIL_HOST=zoho.com
ZEPTOMAIL_TOKEN="Zoho-enczapikey YOUR_SEND_API_KEY"
MAIL_MAILER=zeptomail
MAIL_FROM_ADDRESS=noreply@gamesspoteg.com
MAIL_FROM_NAME="${APP_NAME}"
```

Do not commit the real API key. Comment out unused `MAILGUN_*` when ZeptoMail is the system mailer.

### Data center → `ZEPTOMAIL_HOST`

| Region | `ZEPTOMAIL_HOST` | API base (reference) |
|--------|------------------|----------------------|
| United States | `zoho.com` | `https://cpaas.zoho.com/` |
| Europe | `zoho.eu` | `https://cpaas.zoho.eu/` |
| Australia | `zoho.com.au` | `https://cpaas.zoho.com.au/` |
| India | `zoho.in` | `https://cpaas.zoho.in/` |
| Canada | `zohocloud.ca` | `https://cpaas.zohocloud.ca/` |
| Japan | `zoho.jp` | `https://cpaas.zoho.jp/` |
| UAE | `zoho.ae` | `https://cpaas.zoho.ae/` |
| Singapore | `zoho.sg` | `https://cpaas.zoho.sg/` |
| United Kingdom | `zoho.uk` | `https://cpaas.zoho.uk/` |
| Saudi Arabia | `zoho.sa` | `https://cpaas.zoho.sa/` |

After changing env on a config-cached deploy:

```bash
php artisan optimize:clear
```

---

## 5. Smoke test

```bash
php artisan tinker
```

```php
Mail::raw('This is a test email', function ($message) {
    $message->to('you@example.com')->subject('Testing Laravel ZeptoMail');
});
```

**POS:** `php artisan storefront:send-verification you@example.com`  
**POS UI:** Storefront Settings → Contact → Send test email  
**Accounts:** trigger a device-service status notification to a known inbox, or use tinker above.

---

## 6. Operator deploy (staging / production)

`configure-production-env.sh` copies live `pos.env` / `accounts.env` and preserves mail keys. Set on the VPS (or Hostinger live-env copies):

```env
MAIL_MAILER=zeptomail
ZEPTOMAIL_HOST=zoho.com
ZEPTOMAIL_TOKEN="Zoho-enczapikey …"
MAIL_FROM_ADDRESS=noreply@gamesspoteg.com
MAIL_FROM_NAME=Games Spot
```

Use the same token for both apps (same Agent). Recreate containers after editing env, then smoke-test each app. For POS, enable **Use system email configurations** on the business that should send via CPaaS.

Staging notes: [docs/public-site/STAGING_VPS.md](../public-site/STAGING_VPS.md).

---

## 7. Per-business SMTP (POS only, optional)

If a business should **not** use system mail, enter CPaaS SMTP from **Agent → SMTP/API → SMTP** in Business Settings. Do **not** enable **Use system email configurations**.

---

## What not to do

- Do not send newsletters or marketing blasts through CPaaS.
- Do not use an unverified From address.
- Do not use a Send API key from a different data center than `ZEPTOMAIL_HOST`.
- Do not commit real `ZEPTOMAIL_TOKEN` values.
- Do not follow the older `laravel-cpaas` / `MAIL_MAILER=cpaas` help-center snippet when the console plugin shows `laravel-zeptomail` / `zeptomail`.

---

## Checklist

- [x] Domain verified in CPaaS (`gamesspoteg.com`)
- [x] Package `zohomail/laravel-zeptomail:dev-main` in POS + Accounts
- [x] `zeptomail` mailer in `config/mail.php` (both apps)
- [x] `services.zeptomail` in `config/services.php` (both apps)
- [x] POS: `IsMailConfigured` / `configureEmail` treat `zeptomail` like Mailgun
- [x] POS + Accounts unit tests for mail config
- [x] `.env.example` documents ZeptoMail (both apps)
- [ ] Operator: set `ZEPTOMAIL_*` on live `pos.env` + `accounts.env`
- [ ] Smoke test POS (tinker / `storefront:send-verification`)
- [ ] Smoke test Accounts (tinker / device-service mail)
- [ ] POS businesses that should use system mail have **Use system email configurations** enabled
- [ ] Config cache cleared / containers recreated after env change

---

## Applied in this repo set

| | POS (`posgamesspoteg`) | Accounts (`gamessspot`) |
|---|---|---|
| Package | `zohomail/laravel-zeptomail` | same |
| Mailer | `MAIL_MAILER=zeptomail` | same |
| Config | `config/mail.php` + `services.zeptomail` | same |
| Runtime helpers | `Util::IsMailConfigured`, `NotificationUtil::configureEmail`, `StorefrontMailService` | default Laravel mailer only |
| Main sends | Storefront OTP/reset, order/staff alerts, POS notifications | Device repair `DeviceServiceNotification` |
| Tests | `tests/Unit/MailgunMailConfigTest.php` | `tests/Unit/ZeptomailMailConfigTest.php` |
| Doc mirror | this file | `docs/ZOHO_CPAAS_MAIL.md` |
| Suggested From | `noreply@gamesspoteg.com` | same domain; distinct `MAIL_FROM_NAME` OK |
