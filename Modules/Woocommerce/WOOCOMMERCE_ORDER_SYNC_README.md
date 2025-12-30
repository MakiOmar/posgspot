# WooCommerce Order Sync Documentation

## Overview

This module enables bidirectional synchronization between WooCommerce orders and the POS (Point of Sale) system. When WooCommerce customers place orders, they are automatically imported into the POS as sales transactions.

## Sync Methods

### 1. Manual Sync (Batch Processing)
- **Trigger**: User clicks "Sync Orders" button in the admin panel
- **Command**: `php artisan pos:WooCommerceSyncOrder {business_id}`
- **Controller**: `WoocommerceController@syncOrders()`
- **Process**: Fetches all orders from WooCommerce API and syncs them in batch

### 2. Real-time Webhook Sync
WooCommerce webhooks trigger automatic synchronization for real-time updates:
- **Order Created**: `orderCreated()` - Creates new sale in POS
- **Order Updated**: `orderUpdated()` - Updates existing sale in POS
- **Order Deleted**: `orderDeleted()` - Sets sale status to draft
- **Order Restored**: `orderRestored()` - Restores sale from draft or creates new

### 3. Scheduled Sync (Cron Job)
- **Setup**: Configure cron job to run the sync command periodically
- **Recommended**: Run every 15-60 minutes for automated batch sync

## WooCommerce Order Data Fetched

### Order Information
| WooCommerce Field | POS Field | Description |
|-------------------|-----------|-------------|
| `id` | `woocommerce_order_id` | Unique WooCommerce order ID |
| `number` | `invoice_no` | Order number displayed to customer |
| `status` | `status` | Order status (pending, processing, completed, etc.) |
| `date_created` | `transaction_date` | Date when order was created |
| `date_modified` | Used for sync comparison | Last modification timestamp |
| `date_paid` | `payment[paid_on]` | Payment timestamp |
| `total` | `final_total` | Total order amount |
| `discount_total` | `discount_amount` | Total discount applied |
| `shipping_total` | `shipping_charges` | Shipping cost |
| `payment_method_title` | `payment[note]` | Payment method name |

### Line Items (Products)
| WooCommerce Field | POS Field | Description |
|-------------------|-----------|-------------|
| `line_items[].id` | `woocommerce_line_items_id` | Line item ID for tracking |
| `line_items[].product_id` | Matches `woocommerce_product_id` | Product identifier |
| `line_items[].variation_id` | Matches `woocommerce_variation_id` | Variation identifier |
| `line_items[].name` | Product name | Product display name |
| `line_items[].sku` | Product SKU | Stock keeping unit |
| `line_items[].quantity` | `quantity` | Quantity ordered |
| `line_items[].total` | `unit_price` | Line total (divided by quantity) |
| `line_items[].total_tax` | `item_tax` | Tax amount for line item |
| `line_items[].taxes` | `tax_id` | Tax rate applied |

### Custom Meta Data (Game Store Specific)
The system extracts custom meta data from line items:
| Meta Key | Stored In | Description |
|----------|-----------|-------------|
| `game_title` | `staff_note` | Title of the game |
| `type` | `staff_note` | Type of item (account, key, etc.) |
| `_account` | `staff_note` | Account username/email |
| `_password` | `staff_note` | Account password |

**Format in Staff Notes:**
```
Game Title: [Value]
Type: [Value]
Account: [Value]
Password: [Value]
----------------------
```

### Customer Information

#### For Registered Customers
Fetched from WooCommerce Customers API:
| WooCommerce Field | POS Field | Description |
|-------------------|-----------|-------------|
| `customer_id` | Used to fetch customer | Customer ID reference |
| `first_name` | `first_name` | Customer first name |
| `last_name` | `last_name` | Customer last name |
| `email` | `email` | Customer email (used for matching) |
| `billing.phone` | `mobile` | Phone number |
| `billing.address_1` | `address_line_1` | Address line 1 |
| `billing.address_2` | `address_line_2` | Address line 2 |
| `billing.city` | `city` | City |
| `billing.state` | `state` | State/Province |
| `billing.country` | `country` | Country |
| `billing.postcode` | `zip_code` | Postal code |

#### For Guest Customers
Extracted from order billing details:
| WooCommerce Field | POS Field | Description |
|-------------------|-----------|-------------|
| `billing.first_name` | `first_name` | Guest first name |
| `billing.last_name` | `last_name` | Guest last name |
| `billing.email` | `email` | Guest email |
| `billing.phone` | `mobile` | Guest phone |
| `billing.address_1` | `address_line_1` | Billing address 1 |
| `billing.address_2` | `address_line_2` | Billing address 2 |
| `billing.city` | `city` | Billing city |
| `billing.state` | `state` | Billing state |
| `billing.country` | `country` | Billing country |
| `billing.postcode` | `zip_code` | Billing postal code |

### Shipping Information
| WooCommerce Field | POS Field | Description |
|-------------------|-----------|-------------|
| `shipping.first_name` | `order_addresses[shipping_address][shipping_name]` | Recipient name |
| `shipping.company` | `order_addresses[shipping_address][company]` | Company name |
| `shipping.address_1` | `order_addresses[shipping_address][shipping_address_line_1]` | Shipping address 1 |
| `shipping.address_2` | `order_addresses[shipping_address][shipping_address_line_2]` | Shipping address 2 |
| `shipping.city` | `order_addresses[shipping_address][shipping_city]` | Shipping city |
| `shipping.state` | `order_addresses[shipping_address][shipping_state]` | Shipping state |
| `shipping.country` | `order_addresses[shipping_address][shipping_country]` | Shipping country |
| `shipping.postcode` | `order_addresses[shipping_address][shipping_zip_code]` | Shipping postal code |
| `shipping_lines[].method_title` | `shipping_details` | Shipping method (comma-separated) |

## Sync Flow

### Order Sync Process

```
┌─────────────────────────────────────────────────────────────┐
│                    WooCommerce Order Created                │
└──────────────────────────┬──────────────────────────────────┘
                           │
                           ▼
                  ┌────────────────────┐
                  │  Webhook/Manual    │
                  │  Sync Triggered    │
                  └────────┬───────────┘
                           │
                           ▼
            ┌──────────────────────────────┐
            │  Fetch Order Data from       │
            │  WooCommerce API             │
            └──────────┬───────────────────┘
                       │
                       ▼
            ┌──────────────────────────────┐
            │  Check if Order Already      │
            │  Exists (woocommerce_order_id)│
            └──────────┬───────────────────┘
                       │
           ┌───────────┴───────────┐
           │                       │
           ▼                       ▼
    ┌──────────┐          ┌──────────────┐
    │  CREATE  │          │    UPDATE    │
    │   NEW    │          │   EXISTING   │
    └────┬─────┘          └──────┬───────┘
         │                       │
         └───────────┬───────────┘
                     │
                     ▼
         ┌───────────────────────────┐
         │  1. Match/Create Customer │
         └───────────┬───────────────┘
                     │
                     ▼
         ┌───────────────────────────┐
         │  2. Match Products/       │
         │     Variations by         │
         │     woocommerce IDs       │
         └───────────┬───────────────┘
                     │
                     ▼
         ┌───────────────────────────┐
         │  3. Create/Update Sale    │
         │     Transaction           │
         └───────────┬───────────────┘
                     │
                     ▼
         ┌───────────────────────────┐
         │  4. Create Sell Lines     │
         │     (Product Lines)       │
         └───────────┬───────────────┘
                     │
                     ▼
         ┌───────────────────────────┐
         │  5. Create Payment Lines  │
         └───────────┬───────────────┘
                     │
                     ▼
         ┌───────────────────────────┐
         │  6. Update Stock Levels   │
         │     (if status = final)   │
         └───────────┬───────────────┘
                     │
                     ▼
         ┌───────────────────────────┐
         │  7. Map Purchase/Sell     │
         │     (FIFO/LIFO)           │
         └───────────┬───────────────┘
                     │
                     ▼
         ┌───────────────────────────┐
         │  8. Create Sync Log       │
         └───────────────────────────┘
```

### Status Mapping

#### WooCommerce Status → POS Sell Status
| WooCommerce Status | POS Status | Description |
|-------------------|------------|-------------|
| `pending` | `draft` | Order received, awaiting payment |
| `processing` | `final` | Payment received, processing order |
| `on-hold` | `draft` | Order on hold |
| `completed` | `final` | Order fulfilled |
| `cancelled` | `draft` | Order cancelled |
| `refunded` | `draft` | Order refunded |
| `failed` | `draft` | Payment failed |
| `shipped` | `final` | Order shipped |

#### WooCommerce Status → POS Shipping Status
Configurable in API settings (`order_statuses` and `shipping_statuses`).

## Configuration

### API Settings Required
1. **WooCommerce URL**: Your WooCommerce store URL
2. **Consumer Key**: WooCommerce REST API consumer key
3. **Consumer Secret**: WooCommerce REST API consumer secret
4. **Location ID**: POS location for synced orders
5. **Product Tax Type**: Include/Exclude tax in prices
6. **Default Selling Price Group**: Price group for order products

### Webhook Configuration
Configure these webhooks in WooCommerce:
1. **Order Created**
   - URL: `{POS_URL}/woocommerce-webhook/{business_id}/order-created`
   - Secret: Stored in `woocommerce_wh_oc_secret`

2. **Order Updated**
   - URL: `{POS_URL}/woocommerce-webhook/{business_id}/order-updated`
   - Secret: Stored in `woocommerce_wh_ou_secret`

3. **Order Deleted**
   - URL: `{POS_URL}/woocommerce-webhook/{business_id}/order-deleted`
   - Secret: Stored in `woocommerce_wh_od_secret`

4. **Order Restored**
   - URL: `{POS_URL}/woocommerce-webhook/{business_id}/order-restored`
   - Secret: Stored in `woocommerce_wh_or_secret`

## Error Handling

### Common Errors and Solutions

#### 1. Product Not Found
**Error**: `order_product_not_found`
- **Cause**: Product SKU in WooCommerce order doesn't exist in POS
- **Solution**: Sync products first before syncing orders

#### 2. Insufficient Product Quantity
**Error**: `order_insuficient_product_qty`
- **Cause**: Not enough stock to fulfill order (FIFO/LIFO accounting)
- **Solution**: Order is added to skipped orders list for manual review
- **Note**: The order can be reprocessed after purchasing more stock

#### 3. Variation Not Found
**Error**: Variation doesn't match any POS variation
- **Cause**: Variable product variations not synced properly
- **Solution**: Re-sync products to update variations

### Skipped Orders
Orders that fail to sync are added to `woocommerce_skipped_orders` in the business settings:
- Automatically retried on next sync attempt
- Removed from skip list after successful sync
- Can be manually reviewed in sync logs

## Sync Logs

All sync operations are logged in the `woocommerce_sync_logs` table:

### Log Fields
- `business_id`: Business identifier
- `sync_type`: Type of sync (`orders`, `products`, `categories`)
- `operation_type`: Operation performed (`created`, `updated`, `deleted`, `restored`)
- `data`: JSON array of synced item names/numbers
- `details`: JSON array of error details (if any)
- `created_by`: User who initiated sync
- `created_at`: Timestamp of sync

### Viewing Logs
Navigate to: **WooCommerce → Sync Logs** in the admin panel

## Stock Management

### When Order is Created/Updated to Final Status:
1. Product stock is decreased by order quantity
2. Purchase-sell mapping is created (FIFO/LIFO based on business settings)
3. Transaction payment status set to `paid`

### When Order is Deleted/Cancelled:
1. Product stock is increased (returned)
2. Purchase-sell mapping is adjusted
3. Transaction status set to `draft`

## Payment Handling

All WooCommerce orders are created with:
- **Payment Status**: `paid`
- **Payment Method**: `cash` (default)
- **Payment Note**: WooCommerce payment method title
- **Amount**: Order total
- **Paid On**: Order payment date

## Customer Matching Logic

1. **Check Email**: If order has customer email, search for existing contact
2. **Create New**: If no match found, create new customer with order details
3. **Walk-in Customer**: If guest order without email, use default walk-in customer
4. **Update Info**: Customer information is not updated on order sync (only created)

## Best Practices

1. **Sync Products First**: Always sync products before syncing orders
2. **Regular Backups**: Backup database before bulk order sync
3. **Monitor Logs**: Check sync logs regularly for errors
4. **Test Webhooks**: Use webhook testing tools to verify configuration
5. **Stock Levels**: Maintain sufficient stock to avoid sync failures
6. **Timezone**: Ensure POS timezone matches WooCommerce timezone

## Troubleshooting

### Orders Not Syncing
1. Verify API credentials in settings
2. Check sync logs for error messages
3. Ensure products are synced with correct WooCommerce IDs
4. Verify webhook secrets match configuration

### Stock Discrepancies
1. Check if accounting method (FIFO/LIFO) is configured correctly
2. Verify product stock is sufficient for order fulfillment
3. Review skipped orders for stock-related errors

### Duplicate Orders
1. Orders are matched by `woocommerce_order_id`
2. Duplicates should not occur unless order ID is missing
3. Check for manual order creation with same order number

## Database Schema

### Key Tables
- `transactions`: Stores sales (orders)
  - `woocommerce_order_id`: Links to WooCommerce order ID
  
- `transaction_sell_lines`: Stores product lines
  - `woocommerce_line_items_id`: Links to WooCommerce line item ID
  
- `contacts`: Stores customers
  - No direct WooCommerce link (matched by email)
  
- `woocommerce_sync_logs`: Stores sync history
  - Tracks all sync operations and errors

## Code References

### Main Classes
- **WoocommerceUtil**: Core sync logic (`Woocommerce/Utils/WoocommerceUtil.php`)
  - `syncOrders()`: Main order sync method
  - `createNewSaleFromOrder()`: Creates new sale from order
  - `updateSaleFromOrder()`: Updates existing sale from order
  - `formatOrderToSale()`: Converts WooCommerce order to POS sale format

- **WoocommerceWebhookController**: Webhook handlers
  - `orderCreated()`: Handle order creation webhook
  - `orderUpdated()`: Handle order update webhook
  - `orderDeleted()`: Handle order deletion webhook
  - `orderRestored()`: Handle order restoration webhook

- **WoocommerceController**: Admin interface
  - `syncOrders()`: Manual sync trigger
  - `viewSyncLog()`: Display sync logs

### Console Commands
- `WooCommerceSyncOrder`: Artisan command for batch sync
  - Usage: `php artisan pos:WooCommerceSyncOrder {business_id}`

## API Endpoints

### WooCommerce REST API Endpoints Used
- `GET /wp-json/wc/v2/orders`: Fetch all orders (paginated, 100 per page)
- `GET /wp-json/wc/v2/customers/{id}`: Fetch customer details
- `GET /wp-json/wc/v2/taxes`: Fetch tax rates

### POS Webhook Endpoints
- `POST /woocommerce-webhook/{business_id}/order-created`
- `POST /woocommerce-webhook/{business_id}/order-updated`
- `POST /woocommerce-webhook/{business_id}/order-deleted`
- `POST /woocommerce-webhook/{business_id}/order-restored`

## Force Sync Individual Orders

Sometimes orders may not sync correctly or custom meta data might be missing. You can force re-sync specific orders using the following methods:

### Method 1: API Endpoint (AJAX)

**Endpoint**: `POST /woocommerce/force-sync-order`

**Parameters**:
- `woocommerce_order_id`: The WooCommerce order ID to force sync

**Example using jQuery**:
```javascript
$.ajax({
    url: '/woocommerce/force-sync-order',
    type: 'POST',
    data: {
        woocommerce_order_id: 12345,
        _token: $('meta[name="csrf-token"]').attr('content')
    },
    success: function(response) {
        if (response.success) {
            console.log(response.msg);
            // Order synced successfully
        } else {
            console.error(response.msg);
        }
    }
});
```

**Response Format**:
```json
{
    "success": true,
    "msg": "Order #12345 synced successfully",
    "order_number": "12345"
}
```

### Method 2: Artisan Command (CLI)

Force sync a specific order from the command line:

```bash
php artisan pos:WooCommerceForceSyncOrder {business_id} {woocommerce_order_id}
```

**Example**:
```bash
php artisan pos:WooCommerceForceSyncOrder 1 12345
```

**Parameters**:
- `business_id`: Your business ID in the POS system
- `woocommerce_order_id`: The WooCommerce order ID to force sync

**Use Cases**:
1. **Missing Custom Meta**: When custom meta data shows "N/A" but exists in WooCommerce
2. **Order Update Failed**: When automatic sync fails for a specific order
3. **Manual Correction**: After manually updating order details in WooCommerce
4. **Testing**: To test order sync for specific orders

### How Force Sync Works

1. Fetches the specific order directly from WooCommerce API by order ID
2. Bypasses the `date_modified` check (syncs regardless of last sync time)
3. If order doesn't exist in POS: Creates new sale
4. If order already exists in POS: Updates existing sale with latest data
5. Re-processes all custom meta data, including game_title, account, password, type
6. Updates stock levels and purchase-sell mapping if needed
7. Creates sync log for the operation

### Troubleshooting Force Sync

**Error: "Order not found in WooCommerce"**
- Verify the WooCommerce order ID is correct
- Check if the order exists in your WooCommerce store
- Ensure API credentials are configured correctly

**Error: "Product not found"**
- The order contains products that haven't been synced to POS
- Solution: Run product sync first, then force sync the order

**Error: "Insufficient product quantity"**
- Not enough stock to fulfill the order
- Solution: Purchase more stock or adjust stock levels manually

**Error: "Session store not set on request"**
- **Fixed in latest version** - The module now automatically initializes session context
- Works seamlessly in all contexts: Web, CLI (Artisan), Webhooks, Cron jobs
- Session is automatically created with business settings when not available
- No manual configuration needed

**Error: "Attempt to read property 'enable_rp' on array"**
- **Fixed in latest version** - business_data is now passed as object to TransactionUtil
- All webhook handlers updated to use object format  
- Includes all required properties: enable_rp, pos_settings, accounting_method
- Added `ensureBusinessDataObject()` safeguard method that auto-converts arrays to objects
- Automatic fallback: missing properties are loaded from business settings

**Error: "Column 'business_id' cannot be null" in reference_counts**
- **Fixed** - `updateSaleFromOrder()` was passing null for business_id and user_id to createOrUpdatePaymentLines
- Now correctly passes $business_id and $user_id parameters
- Session initialization improved to always set business.id and user.business_id

**Error: enable_rp error in updateSellTransaction/createSellTransaction**
- **Root cause found** - TransactionUtil internally accesses business_data from $input array
- **Fixed** - Added business_data object to $input array before calling TransactionUtil methods
- Both createNewSaleFromOrder() and updateSaleFromOrder() now include business_data in input
- business_data is always passed as properly formatted object with all required properties

**Debug Logging**
- Comprehensive logging added to trace business_data flow
- Check `storage/logs/laravel.log` for detailed trace
- Session initialization now logs business.id setup
- All TransactionUtil calls logged with parameter types

## Version Information
- **Module**: WooCommerce Integration
- **API Version**: WooCommerce REST API v2
- **Required**: PHP 7.4+, Laravel Framework

---

**Last Updated**: December 2025
**Module Location**: `Modules/Woocommerce/`

