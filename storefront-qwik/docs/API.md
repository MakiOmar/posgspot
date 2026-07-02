# Storefront API (Qwik client)

Base URL: `{PUBLIC_API_BASE}/api/storefront/v1`

All responses use `{ "success": true, "data": …, "meta": {} }` or `{ "success": false, "message": "…", "errors": {} }`.

## Phone & geo

| Method | Path | Description |
|--------|------|-------------|
| GET | `/phone-countries` | Dial codes, flags, and `validation_pattern` per country |
| GET | `/geo/countries` | `{ code, name }[]` for billing country selector |
| GET | `/geo/states/{countryCode}` | `{ code, name }[]` for state/province selector |

## Add customer (landing page)

| Method | Path | Body |
|--------|------|------|
| POST | `/customers/add` | `first_name`, `last_name`, `email`, `birth_date` (Y-m-d), `country` (ISO-2), `state`, `mobile` (full `+…` number), optional `dial_code` |

Creates a POS `Contact` (`type=customer`) by mobile. Rejects duplicate **email** or **mobile** (mobile match includes numbers stored without dial code, e.g. `010…` vs `+2010…`). Phone is validated server-side using `resources/data/countries-codes-and-flags.json`.

## Auth (phone validation)

`POST /auth/register` accepts optional `dial_code` and validates `mobile` when a pattern exists for that dial code.

`PUT /account/profile` accepts optional `dial_code` and validates `mobile` when provided.

`POST /contact` accepts optional `dial_code` and validates `phone` when a pattern exists.

See also: [resources/data/README.md](../../resources/data/README.md)
