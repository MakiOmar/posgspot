# Cash Register Refund Process - Workflow & Lifecycle

## Table of Contents
1. [Overview](#overview)
2. [Refund Types](#refund-types)
3. [Complete Refund Workflow](#complete-refund-workflow)
4. [Cash Register Integration](#cash-register-integration)
5. [Database Impact](#database-impact)
6. [Payment Processing](#payment-processing)
7. [Inventory Management](#inventory-management)
8. [Business Logic](#business-logic)
9. [Error Handling](#error-handling)
10. [Security & Permissions](#security--permissions)

---

## Overview

The refund process in this POS system is a comprehensive workflow that handles customer returns, affects cash register balances, manages inventory, and maintains accurate financial records. The system supports both **full refunds** and **partial refunds** with detailed tracking.

### Key Components:
- **SellReturnController**: Main refund processing controller
- **TransactionUtil**: Core refund business logic
- **CashRegisterUtil**: Cash register impact handling
- **ProductUtil**: Inventory management
- **Transaction Model**: Main refund transaction record

---

## Refund Types

### 1. **Sell Return (Customer Refund)**
- **Purpose**: Customer returns purchased items
- **Transaction Type**: `sell_return`
- **Parent Transaction**: Links to original `sell` transaction
- **Impact**: Reduces cash register balance, increases inventory

### 2. **Purchase Return (Supplier Refund)**
- **Purpose**: Return items to suppliers
- **Transaction Type**: `purchase_return`
- **Parent Transaction**: Links to original `purchase` transaction
- **Impact**: Reduces cash register balance, decreases inventory

### 3. **Transaction Status Change Refund**
- **Purpose**: When a sale transaction is changed from `final` to `draft`
- **Trigger**: Transaction status modification
- **Impact**: Automatic refund of all payments to register

---

## Complete Refund Workflow

### **Phase 1: Refund Initiation**

#### 1.1 **User Access & Permissions**
```php
// Check user permissions
if (!auth()->user()->can('access_sell_return') && !auth()->user()->can('access_own_sell_return')) {
    abort(403, 'Unauthorized action.');
}
```

#### 1.2 **Business Validation**
```php
// Check business subscription
if (!$this->moduleUtil->isSubscribed($business_id)) {
    return $this->moduleUtil->expiredResponse(action([SellReturnController::class, 'index']));
}
```

#### 1.3 **Original Transaction Validation**
```php
// Find original sell transaction
$sell = Transaction::where('business_id', $business_id)
    ->where('type', 'sell')
    ->findOrFail($input['transaction_id']);
```

### **Phase 2: Refund Transaction Creation**

#### 2.1 **Create Refund Transaction**
```php
// TransactionUtil::addSellReturn()
$sell_return_data = [
    'business_id' => $business_id,
    'location_id' => $sell->location_id,
    'contact_id' => $sell->contact_id,
    'customer_group_id' => $sell->customer_group_id,
    'type' => 'sell_return',
    'status' => 'final',
    'created_by' => $user_id,
    'return_parent_id' => $sell->id,  // Links to original sale
    'transaction_date' => Carbon::now(),
    'final_total' => $calculated_total,
];
$sell_return = Transaction::create($sell_return_data);
```

#### 2.2 **Generate Reference Number**
```php
// Generate unique refund reference
$ref_count = $this->setAndGetReferenceCount('sell_return', $business_id);
$sell_return_data['invoice_no'] = $this->generateReferenceNumber('sell_return', $ref_count, $business_id);
```

### **Phase 3: Product Line Processing**

#### 3.1 **Update Original Sell Lines**
```php
foreach ($sell->sell_lines as $sell_line) {
    if (array_key_exists($sell_line->id, $returns)) {
        $quantity = $returns[$sell_line->id] * $multiplier;
        $quantity_before = $sell_line->quantity_returned;
        
        // Update quantity returned
        $sell_line->quantity_returned = $quantity;
        $sell_line->save();
    }
}
```

#### 3.2 **Inventory Management**
```php
// Update product quantities
$this->productUtil->updateProductQuantity(
    $sell_return->location_id, 
    $sell_line->product_id, 
    $sell_line->variation_id, 
    $quantity, 
    $quantity_before, 
    null, 
    false
);
```

### **Phase 4: Purchase Line Integration**

#### 4.1 **Update Purchase Line Quantities**
```php
// Update quantity sold in corresponding purchase lines
$this->updateQuantitySoldFromSellLine($sell_line, $quantity, $quantity_before, false);
```

#### 4.2 **Purchase Line Logic**
```php
public function updateQuantitySoldFromSellLine($sell_line, $new_quantity, $old_quantity) {
    $qty_difference = $new_quantity - $old_quantity;
    
    if ($qty_difference != 0) {
        $sell_line_purchase_lines = TransactionSellLinesPurchaseLines::where('sell_line_id', $sell_line->id)->get();
        
        foreach ($sell_line_purchase_lines as $tslpl) {
            if ($qty_difference > 0) {
                // Increase quantity returned
                $purchase_line->quantity_sold -= $qty_difference;
                $tslpl->qty_returned += $qty_difference;
            } else {
                // Decrease quantity returned
                $purchase_line->quantity_sold += abs($qty_difference);
                $tslpl->qty_returned -= abs($qty_difference);
            }
        }
    }
}
```

---

## Cash Register Integration

### **Automatic Refund Processing**

#### 1. **Refund Detection**
```php
// CashRegisterUtil::refundSell()
public function refundSell($transaction) {
    $user_id = auth()->user()->id;
    $register = CashRegister::where('user_id', $user_id)
                            ->where('status', 'open')
                            ->first();
}
```

#### 2. **Payment Method Analysis**
```php
// Get all payments for the original transaction
$total_payment = CashRegisterTransaction::where('transaction_id', $transaction->id)
    ->select(
        DB::raw("SUM(IF(pay_method='cash', IF(type='credit', amount, -1 * amount), 0)) as total_cash"),
        DB::raw("SUM(IF(pay_method='card', IF(type='credit', amount, -1 * amount), 0)) as total_card"),
        // ... other payment methods
    )->first();
```

#### 3. **Create Refund Transactions**
```php
$refunds = [
    'cash' => $total_payment->total_cash,
    'card' => $total_payment->total_card,
    'cheque' => $total_payment->total_cheque,
    // ... other payment methods
];

$refund_formatted = [];
foreach ($refunds as $key => $val) {
    if ($val > 0) {
        $refund_formatted[] = new CashRegisterTransaction([
            'amount' => $val,
            'pay_method' => $key,
            'type' => 'debit',  // Money going out
            'transaction_type' => 'refund',
            'transaction_id' => $transaction->id,
        ]);
    }
}

$register->cash_register_transactions()->saveMany($refund_formatted);
```

### **Status Change Refunds**

#### When Transaction Status Changes
```php
// CashRegisterUtil::updateSellPayments()
if ($status_before == 'final' && $transaction->status == 'draft') {
    $this->refundSell($transaction);  // Automatic refund
} elseif ($status_before == 'draft' && $transaction->status == 'final') {
    $this->addSellPayments($transaction, $payments);  // Add payments back
}
```

---

## Database Impact

### **Tables Affected:**

#### 1. **transactions Table**
```sql
-- New refund transaction record
INSERT INTO transactions (
    business_id, location_id, type, status, contact_id,
    return_parent_id, final_total, created_by, transaction_date
) VALUES (
    ?, ?, 'sell_return', 'final', ?, ?, ?, ?, NOW()
);
```

#### 2. **transaction_sell_lines Table**
```sql
-- Update quantity_returned in original sell lines
UPDATE transaction_sell_lines 
SET quantity_returned = quantity_returned + ? 
WHERE id = ?;
```

#### 3. **cash_register_transactions Table**
```sql
-- Create refund entries for each payment method
INSERT INTO cash_register_transactions (
    cash_register_id, amount, pay_method, type, 
    transaction_type, transaction_id
) VALUES (
    ?, ?, 'cash', 'debit', 'refund', ?
);
```

#### 4. **variation_location_details Table**
```sql
-- Increase product quantity in location
UPDATE variation_location_details 
SET qty_available = qty_available + ? 
WHERE variation_id = ? AND location_id = ?;
```

#### 5. **purchase_lines Table**
```sql
-- Decrease quantity_sold in purchase lines
UPDATE purchase_lines 
SET quantity_sold = quantity_sold - ? 
WHERE id = ?;
```

---

## Payment Processing

### **Refund Payment Methods**

#### 1. **Cash Refunds**
- **Type**: `debit` transaction in cash register
- **Impact**: Reduces physical cash in drawer
- **Tracking**: Recorded in `cash_register_transactions`

#### 2. **Card Refunds**
- **Type**: `debit` transaction in cash register
- **Impact**: Reduces card payment totals
- **Note**: May require external card processor integration

#### 3. **Store Credit**
- **Type**: Custom payment method
- **Impact**: Increases customer account balance
- **Tracking**: Managed through contact balance system

### **Payment Status Updates**
```php
// Update payment status for refund transaction
$this->updatePaymentStatus($sell_return->id, $sell_return->final_total);
```

---

## Inventory Management

### **Quantity Updates**

#### 1. **Product Quantity Increase**
```php
// Increase available quantity
$this->productUtil->updateProductQuantity(
    $location_id, 
    $product_id, 
    $variation_id, 
    $return_quantity,  // Positive value
    $old_return_quantity
);
```

#### 2. **Purchase Line Quantity Adjustment**
```php
// Decrease quantity sold in purchase lines
$this->updateQuantitySoldFromSellLine(
    $sell_line, 
    $new_return_quantity, 
    $old_return_quantity
);
```

### **Inventory Tracking**
- **FIFO/LIFO**: Maintains proper inventory costing
- **Serial Numbers**: Tracks returned serialized items
- **Batch Tracking**: Handles batch-based returns
- **Expiry Dates**: Manages returned perishable goods

---

## Business Logic

### **Refund Calculations**

#### 1. **Total Calculation**
```php
$return_total = 0;
foreach ($product_lines as $product_line) {
    $return_total += $product_line['unit_price_inc_tax'] * $product_line['quantity'];
}

// Apply discount if any
if ($discount_type == 'percentage') {
    $discount_amount = $return_total * ($discount_amount / 100);
} else {
    $discount_amount = $discount_amount;
}

$final_total = $return_total - $discount_amount + $tax_amount;
```

#### 2. **Reward Points Adjustment**
```php
if ($business->enable_rp == 1 && !empty($sell->rp_earned)) {
    $diff = $sell->final_total - $sell_return->final_total;
    $new_reward_point = $this->calculateRewardPoints($business_id, $diff);
    $this->updateCustomerRewardPoints($sell->contact_id, $new_reward_point, $sell->rp_earned);
}
```

### **Validation Rules**
- **Maximum Return**: Cannot exceed original sale quantity
- **Time Limits**: May have return time restrictions
- **Condition Checks**: Returned items condition validation
- **Authorization**: Manager approval for high-value returns

---

## Error Handling

### **Exception Management**

#### 1. **Database Rollback**
```php
try {
    DB::beginTransaction();
    
    // Refund processing logic
    
    DB::commit();
    $output = ['success' => 1, 'msg' => 'Refund processed successfully'];
} catch (\Exception $e) {
    DB::rollBack();
    
    if (get_class($e) == \App\Exceptions\PurchaseSellMismatch::class) {
        $msg = $e->getMessage();
    } else {
        \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());
        $msg = __('messages.something_went_wrong');
    }
    
    $output = ['success' => 0, 'msg' => $msg];
}
```

#### 2. **Purchase-Sell Mismatch**
```php
// Exception for inventory tracking issues
class PurchaseSellMismatch extends \Exception {
    // Handles cases where returned quantity exceeds available purchase quantity
}
```

### **Validation Errors**
- **Insufficient Inventory**: Cannot return more than sold
- **Invalid Transaction**: Original sale not found
- **Permission Denied**: User lacks refund permissions
- **Business Rules**: Violates return policies

---

## Security & Permissions

### **Access Control**
```php
// Permission checks
if (!auth()->user()->can('access_sell_return') && !auth()->user()->can('access_own_sell_return')) {
    abort(403, 'Unauthorized action.');
}
```

### **Ownership Validation**
```php
// For users who can only access their own returns
if (!auth()->user()->can('access_sell_return') && auth()->user()->can('access_own_sell_return')) {
    $query->where('created_by', request()->session()->get('user.id'));
}
```

### **Business Isolation**
```php
// Ensure refund belongs to current business
$query = Transaction::where('business_id', $business_id)
    ->where('type', 'sell_return');
```

---

## Refund Lifecycle Summary

### **Complete Flow:**
```
1. User initiates refund
   ↓
2. Validate permissions & business rules
   ↓
3. Find original sale transaction
   ↓
4. Create refund transaction record
   ↓
5. Update sell line quantities returned
   ↓
6. Update inventory quantities
   ↓
7. Update purchase line quantities
   ↓
8. Process cash register refunds
   ↓
9. Update payment status
   ↓
10. Adjust reward points (if applicable)
    ↓
11. Generate receipt/confirmation
    ↓
12. Log activity & complete transaction
```

### **Key Features:**
- **Atomic Operations**: All changes in database transaction
- **Audit Trail**: Complete activity logging
- **Inventory Integrity**: Proper quantity tracking
- **Financial Accuracy**: Cash register balance maintenance
- **Customer Experience**: Receipt generation and confirmation
- **Business Intelligence**: Detailed reporting and analytics

This comprehensive refund system ensures accurate financial tracking, proper inventory management, and maintains data integrity throughout the entire return process.

---

## How to Create a Refund from Dashboard

### **Method 1: From Sales List (Recommended)**

#### **Step 1: Navigate to Sales**
1. **Login** to your POS dashboard
2. **Click on "Sales"** in the main navigation menu
3. **Go to "All Sales"** page (`/sells`)

#### **Step 2: Find the Sale Transaction**
1. **Use filters** to find the specific sale:
   - **Location**: Select business location
   - **Customer**: Filter by customer name
   - **Date Range**: Select the date range
   - **User**: Filter by sales representative (if you have permissions)

2. **Search** for the invoice number or customer

#### **Step 3: Access Refund Options**
1. **Click on the sale transaction** row
2. **Look for action buttons** in the last column:
   - **"Return"** button
   - **"Add Return"** button
3. **Click "Add Return"** button

#### **Step 4: Create Refund**
1. **Redirected to refund form** (`/sell-return/add/{id}`)
2. **Fill in refund details**:
   - **Return quantities** for each product
   - **Discount** (if applicable)
   - **Additional notes** (optional)

#### **Step 5: Save Refund**
1. **Review the refund total**
2. **Click "Save"** button
3. **Refund is processed** and affects cash register

---

### **Method 2: Direct Refund Access**

#### **Step 1: Navigate to Sell Returns**
1. **Go to "Sell Return"** section in navigation
2. **Access URL**: `/sell-return`
3. **View existing refunds** list

#### **Step 2: Create New Refund**
1. **Click "Add" button** (if available)
2. **Search for invoice** to return:
   - **Enter invoice number**
   - **Select customer**
   - **Choose date range**

#### **Step 3: Validate Invoice**
1. **System validates** the invoice exists
2. **Checks permissions** for the sale
3. **Confirms** customer and business location

---

### **Method 3: From Individual Sale View**

#### **Step 1: Open Sale Details**
1. **Click on any sale** from the sales list
2. **View sale details** page
3. **Look for "Return" action** button

#### **Step 2: Initiate Return**
1. **Click "Return" button**
2. **System redirects** to refund form
3. **Pre-fills** customer and sale information

---

### **Refund Form Interface**

#### **Form Sections:**

1. **Parent Sale Information**
   ```
   - Invoice Number: [Auto-filled]
   - Date: [Auto-filled]
   - Customer: [Auto-filled]
   - Location: [Auto-filled]
   ```

2. **Product Return Table**
   ```
   | Product Name | Unit Price | Sell Qty | Return Qty | Subtotal |
   |--------------|------------|----------|------------|----------|
   | Product A    | $10.00     | 5        | [input]    | [calc]   |
   | Product B    | $20.00     | 2        | [input]    | [calc]   |
   ```

3. **Discount Options**
   ```
   - Discount Type: [None/Fixed/Percentage]
   - Discount Amount: [Input field]
   ```

4. **Totals Display**
   ```
   - Total Return Discount: (-) $0.00
   - Total Return Tax: (+) $0.00
   - Return Total: $0.00
   ```

---

### **Refund Processing Details**

#### **What Happens When You Create a Refund:**

1. **Transaction Creation**
   - Creates new `sell_return` transaction
   - Links to original sale via `return_parent_id`
   - Generates unique refund reference number

2. **Inventory Updates**
   - **Increases** product quantities in location
   - **Updates** sell line `quantity_returned`
   - **Adjusts** purchase line quantities

3. **Cash Register Impact**
   - **Automatically processes** refund payments
   - **Reduces** cash register balance
   - **Creates** debit transactions for each payment method

4. **Customer Account**
   - **Updates** customer balance (if applicable)
   - **Adjusts** reward points (if enabled)
   - **Records** refund in customer history

---

### **Required Permissions**

#### **User Permissions Needed:**
```php
// Check these permissions in user roles
- 'access_sell_return'        // Full refund access
- 'access_own_sell_return'    // Own sales refunds only
- 'sell.view'                 // View sales
- 'sell.payments'             // Payment management
```

---

### **Refund Validation Rules**

#### **System Validations:**
1. **Quantity Limits**
   - Cannot return more than original sale quantity
   - Respects decimal/whole number product settings

2. **Business Rules**
   - Must have valid business location
   - Customer must exist and be active
   - Sale transaction must be in 'final' status

3. **Permission Checks**
   - User must have refund permissions
   - Business subscription must be active
   - Location access must be granted

---

### **Quick Access URLs**

#### **Direct Navigation:**
```
- All Sales: /sells
- Sell Returns: /sell-return
- Create Refund: /sell-return/add/{sale_id}
- Validate Invoice: /validate-invoice-to-return/{invoice_no}
```

#### **Example Workflow:**
```
1. Go to: /sells
2. Find sale with ID: 123
3. Click "Add Return"
4. Redirected to: /sell-return/add/123
5. Fill form and save
6. Refund processed automatically
```

---

### **Pro Tips**

1. **Use Filters**: Narrow down sales list for faster searching
2. **Invoice Search**: Use invoice number for quick access
3. **Batch Returns**: Process multiple items in one refund
4. **Receipt Generation**: System automatically generates refund receipts
5. **Audit Trail**: All refunds are logged with user and timestamp

This comprehensive process ensures accurate refund processing while maintaining proper inventory tracking and cash register balance management.
