## WooCommerce Combo (Compo) Product Sync – End‑to‑End Guide

This document explains how **combo/compo products** in the POS are synced to WooCommerce, how the custom plugin participates, and how to manage and troubleshoot the process.

---

## 1. Architecture overview

- **POS**:
  - Stores combo products as `products.type = 'combo'`.
  - Each combo has a single “DUMMY” variation whose `combo_variations` field defines the components:
    - `variation_id` – POS variation ID of the component item
    - `quantity` – quantity of that variation per combo
    - `unit_id` – unit used in the calculation
  - The WooCommerce sync code lives in `Modules\Woocommerce\Utils\WoocommerceUtil`.

- **WooCommerce plugin**:
  - File: `woocommerce-pos-combo-plugin.php` (to be installed on the Woo site).
  - Registers a **custom product type**: `pos_combo`.
  - Extends the WooCommerce REST product schema so `type: "pos_combo"` is accepted.
  - Persists combo structure in post meta: `_pos_combo_items`.

---

## 2. Which combo products are eligible for sync?

On the POS side, the product sync query now includes combo products:

- `business_id` matches the current business.
- `type` is one of: `single`, `variable`, `combo`.
- `woocommerce_disable_sync = 0`.
- Optional: passes `ForLocation` filter when a WooCommerce location is configured.
- For **“new only”** sync: `woocommerce_product_id` is `null`.

Additional combo‑specific requirements (checked per product during sync):

- The combo’s “DUMMY” variation has a **non‑empty `combo_variations` array**.
- For every `combo_variation` entry:
  - The referenced variation exists.
  - Its parent product has a **non‑null `woocommerce_product_id`**.
  - If that product is variable, the variation may also have `woocommerce_variation_id`.

If any component fails these checks, the combo is **skipped** and a log entry is written (see logging below).

---

## 3. Data sent from POS to WooCommerce for combos

When the sync loop encounters a combo product:

- **Type mapping**
  - `single` → `simple`
  - `variable` → `variable`
  - `combo` → `pos_combo` (the custom type registered by the plugin)

- **Standard fields (same as for simple products)**
  - `name`
  - `sku`
  - `categories` (derived from POS category/subcategory and their `woocommerce_cat_id`)
  - `description` / `short_description` (according to WooCommerce API settings)
  - `images` (product image or existing `woocommerce_media_id`)
  - `regular_price` (from the combo’s variation sell price, optionally via price groups)
  - `tax_class` and `weight` if enabled in API settings

- **Stock behaviour**
  - For combos, WooCommerce stock is **not** used:
    - `manage_stock` is set to `false`.
    - `in_stock` is set to `true` (availability is enforced by POS when orders sync back).

- **Combo structure (components)**
  - POS reads the combo’s `combo_variations` and resolves each component variation to its WooCommerce IDs.
  - It builds an array:
    - `[{ "woocommerce_product_id", "woocommerce_variation_id" (optional), "quantity" }, ...]`
  - This array is sent via the WooCommerce REST `meta_data` field:

    ```json
    {
      "key": "_pos_combo_items",
      "value": [
        { "woocommerce_product_id": 123, "woocommerce_variation_id": 456, "quantity": 2 },
        { "woocommerce_product_id": 789, "woocommerce_variation_id": null, "quantity": 1 }
      ]
    }
    ```

  - If any component is missing the necessary WooCommerce IDs, the combo is **skipped** and not included in the current batch.

---

## 4. What the WooCommerce plugin does

The plugin (`woocommerce-pos-combo-plugin.php`) performs three main tasks:

- **Registers the `pos_combo` product type**
  - Uses `product_type_selector` and `woocommerce_product_class` filters to expose a “POS Combo” type in WooCommerce and to map it internally to `WC_Product_Simple`.

- **Extends the REST API schema**
  - Uses `woocommerce_rest_product_schema` to append `pos_combo` to the allowed `type` values.
  - This allows the POS to send `type: "pos_combo"` in `products/batch` requests.

- **Persists the combo structure**
  - Hooks into `woocommerce_rest_insert_product`.
  - Reads `meta_data` from the REST request.
  - Locates the entry with `key = "_pos_combo_items"`.
  - Normalizes the value:
    - If it’s a JSON string, tries to `json_decode` it; if decode fails, logs a warning.
    - If it’s already an array, uses it as‑is.
  - Saves the normalized value to post meta:
    - `update_post_meta( <product_id>, '_pos_combo_items', $normalized_value )`
  - Logs an info entry in WooCommerce logs when `_pos_combo_items` is updated.

At this stage, WooCommerce treats the combo as a normal purchasable product with a fixed price, while the **structure of its components** is available in `_pos_combo_items` for use by future features (custom frontend display, stock logic, or enhanced order sync back to POS).

---

## 5. Logging and tracking

### 5.1 POS application logs (Laravel)

In `WoocommerceUtil`:

- When a combo is **skipped** because components are not fully synced:
  - Logs an `info` entry with:
    - business id
    - POS product id, name, SKU
    - Reason: missing component WooCommerce IDs

- When a combo is **created** in WooCommerce:
  - Logs an `info` entry:
    - `WooCommerce combo product created`
    - business id, POS product id, name, SKU
    - `woocommerce_product_id`

- When a combo is **created using an existing resource id** from WooCommerce:
  - Logs an `info` entry:
    - `WooCommerce combo product created using existing resource id`
    - Same context fields as above.

- When a combo is **updated**:
  - Logs an `info` entry:
    - `WooCommerce combo product updated`
    - Same context fields.

The existing `WoocommerceSyncLog` entity still records overall product sync activity (created/updated SKUs under the “all_products” key); this now includes combos alongside singles and variables.

### 5.2 WooCommerce logs

In the plugin:

- Uses `wc_get_logger()` (when available) to:
  - Log a **warning** if `_pos_combo_items` arrives as invalid JSON.
  - Log an **info** message whenever `_pos_combo_items` meta is updated from a REST sync.

These logs are visible in **WooCommerce → Status → Logs** (filter by the plugin’s source, e.g. `pos-combo`).

---

## 6. Recommended sync workflow

To keep combo sync reliable and predictable:

- **Step 1: Sync single and variable products**
  - Run “Sync new products” or “Sync all products” until:
    - All relevant components have `woocommerce_product_id`.
    - Variable products also have `woocommerce_variation_id` for their variations.

- **Step 2: Sync combo products**
  - Run the same sync again; this time, combos will be included when:
    - All components in their `combo_variations` resolve to valid WooCommerce IDs.
  - Monitor the POS logs for:
    - “combo product created/updated” entries.
    - “combo product skipped” entries that indicate missing components.

- **Step 3: Verify in WooCommerce**
  - Confirm that:
    - The product type is `POS Combo` (`pos_combo`).
    - Price and basic fields (name, SKU, image, categories) look correct.
    - The `_pos_combo_items` meta exists for the product and contains the expected mapping.

---

## 7. Management & troubleshooting checklist

- **Combo not appearing in WooCommerce**
  - Check POS logs for “combo product skipped” entries.
  - Verify each component product/variation has a valid WooCommerce ID.

- **Combo appears but has no structure**
  - In WooCommerce, inspect post meta for `_pos_combo_items`.
  - Check WooCommerce logs for warnings about invalid JSON.
  - Confirm that POS is sending `meta_data` with a `_pos_combo_items` entry.

- **Inconsistent availability/stock**
  - Remember that WooCommerce does **not** manage stock for combos; POS remains the source of truth.
  - Ensure the order sync from WooCommerce back to POS correctly maps combo lines to the POS combo product and its child components (this part depends on your order‑sync implementation).

By following this process and using the logs on both sides, you can reliably manage and audit the full lifecycle of combo/compo product sync between the POS and WooCommerce.

