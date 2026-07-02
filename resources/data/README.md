# Storefront static data

Project-owned JSON used by Laravel storefront APIs and the Qwik `storefront-qwik` app.

## Phone countries (`countries-codes-and-flags.json`)

- **Path:** `resources/data/countries-codes-and-flags.json`
- **Used by:** `PhoneValidationService`, `GET /api/storefront/v1/phone-countries`
- **Fields per row:** `name_en`, `name_ar`, `dial_code`, `flag`, `country_code`, `validation_pattern`
- **Validation:** full international number (e.g. `+201012345678`) is matched against `validation_pattern` for the selected dial code. Empty pattern = accept any number for that dial code.

## Geo subdivisions (`geo/countries-states.json`)

- **Path:** `resources/data/geo/countries-states.json`
- **Used by:** `GET /api/storefront/v1/geo/states/{countryCode}`
- **Country list:** derived from unique `country_code` values in the phone countries file via `GeoDataService::getCountries()`.

When adding or editing validation patterns, update only the file under `resources/data/` — the Qwik client loads patterns from the API at runtime.
