# WooCommerce Product Sync (Including Combo Products)

This document describes how POS products are synced to WooCommerce, and how **combo products** (sometimes referred to as “compo” products) are handled.

---

## Summary: Which Products Are Synced?

| POS product type | Synced to WooCommerce? | WooCommerce product type |
|------------------|------------------------|---------------------------|
| **Single**       | Yes                    | Simple                    |
| **Variable**      | Yes                    | Variable (with variations)|
| **Combo**         | **No**                 | —                         |

**Combo products are not synced to WooCommerce.** The sync process only includes products whose `type` is `single` or `variable`. Combo products (bundles of other products) are excluded by design.

---

## Why Combo Products Are Not Synced

- In the POS, a **combo** is a product made of multiple sub-products (variations) with quantities. Stock is derived from the component products.
- WooCommerce does not have a direct equivalent for this bundle model in the same way. Mapping would require custom logic (e.g. grouped products or custom product types).
- The current implementation keeps sync simple and reliable by syncing only **single** and **variable** products.

If you need combo-like behaviour on WooCommerce, you can:

- Create a **simple** or **variable** product in the POS that represents the combo (e.g. same name/price) and sync that, or  
- Rely on WooCommerce plugins (e.g. product bundles) and maintain those products manually.

---

## Products That *Are* Synced

### 1. Filters applied

A product is considered for sync only if **all** of the following hold:

- `business_id` matches the business being synced.
- `type` is `single` or `variable` (combo is excluded).
- `woocommerce_disable_sync` is `0` (sync not disabled on the product).
- If WooCommerce API settings specify a **location**, the product must be available at that location (`ForLocation` scope).
- For “new only” sync: `woocommerce_product_id` is `null`.

### 2. Incremental behaviour

- A product that already has a `woocommerce_product_id` is **skipped** if its last update (product `updated_at` or last stock update at the selected location) is **before** the last sync time. This avoids unnecessary API calls when nothing changed.

### 3. Sync order (per run)

1. **Categories** – Parent and child product categories are synced (create/update) so WooCommerce category IDs exist.
2. **Variation attributes** – POS variation attributes are synced so variable products can use them.
3. **Products** – Products are built as create or update payloads and sent in batches (e.g. up to 99 per batch) to the WooCommerce REST API `products/batch` endpoint.
4. **Variable product variations** – For each variable product that was created/updated, its POS variations are synced to WooCommerce as product variations (create/update via `products/{id}/variations/batch`).

### 4. Data mapped for products

- **Type**: POS `single` → WooCommerce `simple`; POS `variable` → WooCommerce `variable`.
- **SKU**, **name**, **categories** (from POS category/subcategory and their `woocommerce_cat_id`).
- **Price** (from first variation for single; from selling price group if configured), **tax** (incl/excl from settings).
- **Stock**: optional; if enabled, quantity is taken from the selected business location.
- **Image**: product image URL or existing WooCommerce media ID.
- **Description**: long/short/both depending on WooCommerce API settings.
- **Weight** (if configured in API settings).

Which fields are sent on **create** vs **update** is controlled by WooCommerce API settings (`product_fields_for_create`, `product_fields_for_update`, etc.).

### 5. Variable products

- Variation attributes are sent on the parent product; each variation is sent with attributes (e.g. size, colour), SKU, price, stock, and optional image.
- Variation templates must have `woocommerce_attr_id` set so POS attributes match WooCommerce attribute IDs.

---

## How to Run the Sync

### From the dashboard

1. Open **WooCommerce** (sidebar) and ensure API settings are configured for the business.
2. Use the **Sync Products** actions:
   - **Sync new products** – only products that do not yet have a `woocommerce_product_id`.
   - **Sync all products** – new and existing; only products changed since last sync are actually sent (see “Incremental behaviour” above).
3. Sync runs in pages (e.g. 100 products per request); the UI may call the endpoint repeatedly until done.

Required permission: `woocommerce.sync_products` (and WooCommerce module enabled for the business).

### From the command line

Full sync for one business (no pagination; all eligible products in one go):

```bash
php artisan pos:WoocommerceSyncProducts {business_id}
```

Example:

```bash
php artisan pos:WoocommerceSyncProducts 1
```

This uses the same `syncProducts()` logic (categories → variation attributes → products → variable variations). Memory and execution limits are raised for the command.

---

## Per-product “Disable WooCommerce sync”

- Each product has a **Disable Woocommerce Sync** option (`woocommerce_disable_sync`).
- If this is enabled (`woocommerce_disable_sync = 1`), the product is **never** included in sync, regardless of type (single/variable/combo). So even single/variable products can be excluded individually.

---

## Sync logs

- Sync actions (e.g. categories created/updated, products created/updated) are recorded in the WooCommerce sync logs (e.g. `WoocommerceSyncLog`). Use them to verify what was synced and to troubleshoot.

---

## Summary

- **Combo (compo) products are not synced** to WooCommerce; only **single** and **variable** products are.
- Sync is filtered by business, type, `woocommerce_disable_sync`, and optional location, and is incremental where possible.
- Sync order: categories → variation attributes → products (batch) → variable variations.
- You can run sync from the **WooCommerce** UI (Sync Products) or via **`php artisan pos:WoocommerceSyncProducts {business_id}`**.
