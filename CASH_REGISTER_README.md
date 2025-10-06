# Cash Register System Documentation

## Table of Contents
1. [Overview](#overview)
2. [Core Concepts](#core-concepts)
3. [Database Structure](#database-structure)
4. [System Architecture](#system-architecture)
5. [Workflow & Lifecycle](#workflow--lifecycle)
6. [Transaction Types](#transaction-types)
7. [Payment Methods](#payment-methods)
8. [API Endpoints](#api-endpoints)
9. [Key Features](#key-features)
10. [Business Logic](#business-logic)
11. [Security & Permissions](#security--permissions)
12. [Troubleshooting](#troubleshooting)

---

## Overview

The Cash Register System is a comprehensive POS (Point of Sale) module that manages cash flow, transactions, and register operations for retail businesses. It provides real-time tracking of sales, expenses, refunds, and cash denominations within a specific business location and user session.

### Key Components
- **CashRegister Model**: Main register entity
- **CashRegisterTransaction Model**: Individual transaction records
- **CashRegisterController**: Handles register operations
- **CashRegisterUtil**: Core business logic and calculations
- **Register Details Modal**: Real-time register summary

---

## Core Concepts

### 1. **Register Session**
A register session represents a specific period when a cashier operates a cash register. Each session has:
- **Unique ID**: Database primary key
- **User**: Cashier who opened the register
- **Business Location**: Physical location of the register
- **Status**: `open` or `close`
- **Time Range**: From opening to closing time

### 2. **Cash In Hand**
The initial amount of cash placed in the register drawer when opening a session. This serves as the starting point for all cash calculations.

### 3. **Transaction Types**
- **Sell**: Customer purchases (increases register balance)
- **Expense**: Business expenses (decreases register balance)
- **Refund**: Customer refunds (decreases register balance)
- **Initial**: Opening cash amount (sets initial balance)

### 4. **Payment Methods**
- **Cash**: Physical currency transactions
- **Card**: Credit/debit card payments
- **Cheque**: Check payments
- **Bank Transfer**: Electronic transfers
- **Custom Payments**: Up to 7 customizable payment methods
- **Other**: Miscellaneous payment types

---

## Database Structure

### 1. **cash_registers Table**
```sql
CREATE TABLE cash_registers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    business_id INT NOT NULL,
    user_id INT NOT NULL,
    location_id INT NULL,
    status ENUM('open', 'close') DEFAULT 'open',
    closed_at DATETIME NULL,
    closing_amount DECIMAL(22,4) DEFAULT 0,
    total_card_slips INT DEFAULT 0,
    total_cheques INT DEFAULT 0,
    closing_note TEXT NULL,
    denominations JSON NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (business_id) REFERENCES business(id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (location_id) REFERENCES business_locations(id)
);
```

**Key Fields:**
- `status`: Current state of the register
- `denominations`: JSON array storing cash denomination counts
- `closing_amount`: Final cash amount when register is closed
- `closing_note`: Optional notes when closing the register

### 2. **cash_register_transactions Table**
```sql
CREATE TABLE cash_register_transactions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    cash_register_id INT NOT NULL,
    amount DECIMAL(22,4) DEFAULT 0,
    pay_method ENUM('cash','card','cheque','bank_transfer','custom_pay_1','custom_pay_2','custom_pay_3','custom_pay_4','custom_pay_5','custom_pay_6','custom_pay_7','other','advance'),
    type ENUM('debit', 'credit'),
    transaction_type ENUM('initial', 'sell', 'transfer', 'refund', 'expense'),
    transaction_id INT NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (cash_register_id) REFERENCES cash_registers(id)
);
```

**Key Fields:**
- `pay_method`: Payment method used
- `type`: Whether money is added (`credit`) or removed (`debit`)
- `transaction_type`: Type of business transaction
- `transaction_id`: Links to main transaction record

---

## System Architecture

### 1. **Models**
```php
// CashRegister Model
class CashRegister extends Model {
    protected $casts = [
        'denominations' => 'array',  // JSON to array conversion
    ];
    
    public function cash_register_transactions() {
        return $this->hasMany(CashRegisterTransaction::class);
    }
}

// CashRegisterTransaction Model
class CashRegisterTransaction extends Model {
    protected $guarded = ['id'];
}
```

### 2. **Controller Structure**
```php
class CashRegisterController extends Controller {
    // Main methods:
    // - create(): Show register opening form
    // - store(): Open new register
    // - getRegisterDetails(): Show current register details
    // - getCloseRegister(): Show closing form
    // - postCloseRegister(): Close register
}
```

### 3. **Utility Class**
```php
class CashRegisterUtil extends Util {
    // Key methods:
    // - countOpenedRegister(): Check if user has open register
    // - addSellPayments(): Record sale payments
    // - refundSell(): Handle refunds
    // - getRegisterDetails(): Get comprehensive register data
    // - getRegisterTransactionDetails(): Get transaction summaries
}
```

---

## Workflow & Lifecycle

### 1. **Register Opening Process**

#### Step 1: Check Existing Register
```php
if ($this->cashRegisterUtil->countOpenedRegister() != 0) {
    // Redirect to POS screen (register already open)
    return redirect()->action([SellPosController::class, 'create']);
}
```

#### Step 2: Create Register Record
```php
$register = CashRegister::create([
    'business_id' => $business_id,
    'user_id' => $user_id,
    'status' => 'open',
    'location_id' => $request->input('location_id'),
    'created_at' => Carbon::now()->format('Y-m-d H:i:00'),
]);
```

#### Step 3: Record Initial Cash (if provided)
```php
if (!empty($initial_amount)) {
    $register->cash_register_transactions()->create([
        'amount' => $initial_amount,
        'pay_method' => 'cash',
        'type' => 'credit',
        'transaction_type' => 'initial',
    ]);
}
```

### 2. **Transaction Recording**

#### Sale Transaction
```php
public function addSellPayments($transaction, $payments) {
    $register = CashRegister::where('user_id', $user_id)
                           ->where('status', 'open')
                           ->first();
    
    foreach ($payments as $payment) {
        $payment_amount = ($payment['is_return'] == 1) 
            ? (-1 * $this->num_uf($payment['amount'])) 
            : $this->num_uf($payment['amount']);
            
        $register->cash_register_transactions()->create([
            'amount' => $payment_amount,
            'pay_method' => $payment['method'],
            'type' => 'credit',  // Sales add money
            'transaction_type' => 'sell',
            'transaction_id' => $transaction->id,
        ]);
    }
}
```

#### Refund Transaction
```php
public function refundSell($transaction) {
    // Create debit transactions to remove money from register
    foreach ($refund_formatted as $refund) {
        $register->cash_register_transactions()->save($refund);
    }
}
```

### 3. **Register Closing Process**

#### Step 1: Calculate Totals
- Sum all cash transactions
- Calculate expected vs actual cash
- Count card slips and cheques

#### Step 2: Record Denominations
```php
$input['denominations'] = !empty(request()->input('denominations')) 
    ? json_encode(request()->input('denominations')) 
    : null;
```

#### Step 3: Close Register
```php
CashRegister::where('user_id', $user_id)
           ->where('status', 'open')
           ->update([
               'status' => 'close',
               'closed_at' => Carbon::now(),
               'closing_amount' => $closing_amount,
               'total_card_slips' => $total_card_slips,
               'total_cheques' => $total_cheques,
               'closing_note' => $closing_note,
               'denominations' => $denominations,
           ]);
```

---

## Transaction Types

### 1. **Initial Transaction**
- **Purpose**: Record opening cash amount
- **Type**: `credit`
- **Transaction Type**: `initial`
- **Amount**: Cash placed in drawer at opening

### 2. **Sell Transaction**
- **Purpose**: Record customer purchases
- **Type**: `credit` (money coming in)
- **Transaction Type**: `sell`
- **Payment Methods**: All supported payment types

### 3. **Expense Transaction**
- **Purpose**: Record business expenses
- **Type**: `debit` (money going out)
- **Transaction Type**: `expense`
- **Examples**: Petty cash, supplies, etc.

### 4. **Refund Transaction**
- **Purpose**: Record customer refunds
- **Type**: `debit` (money going out)
- **Transaction Type**: `refund`
- **Note**: Reverses previous sale transactions

### 5. **Transfer Transaction**
- **Purpose**: Record money transfers between registers
- **Type**: `debit` or `credit`
- **Transaction Type**: `transfer`
- **Note**: Used for multi-register operations

---

## Payment Methods

### Standard Payment Methods
1. **Cash** (`cash`)
   - Physical currency transactions
   - Affects cash drawer balance
   - Requires denomination counting

2. **Card** (`card`)
   - Credit/debit card payments
   - Tracked separately from cash
   - Counted as card slips

3. **Cheque** (`cheque`)
   - Check payments
   - Requires separate tracking
   - Counted as cheques

4. **Bank Transfer** (`bank_transfer`)
   - Electronic bank transfers
   - No physical cash involved

5. **Other** (`other`)
   - Miscellaneous payment types
   - Catch-all category

### Custom Payment Methods
- **Custom Pay 1-7** (`custom_pay_1` to `custom_pay_7`)
- Configurable payment types
- Can be named by business (e.g., "Gift Card", "Store Credit")

### Advance Payments
- **Advance** (`advance`)
- Customer advance payments
- Pre-payments for future purchases

---

## API Endpoints

### 1. **Register Management**
```
GET  /cash-register                    # List registers
GET  /cash-register/create             # Show opening form
POST /cash-register                    # Open new register
GET  /cash-register/{id}               # Show register details
GET  /cash-register/close-register/{id?}  # Show closing form
POST /cash-register/close-register     # Close register
```

### 2. **Register Details**
```
GET  /cash-register/register-details   # Show current register details (modal)
```

### 3. **Permission Requirements**
- `view_cash_register`: View register details
- `close_cash_register`: Close register
- `access_cash_register`: General access

---

## Key Features

### 1. **Real-time Register Details**
- **Payment Summary**: Breakdown by payment method
- **Cash Denominations**: Detailed cash counting
- **Product Sales**: Individual product sales summary
- **Sales by Brand**: Brand-wise sales analysis
- **Types of Service**: Service-based sales (if enabled)

### 2. **Cash Denomination Tracking**
```json
{
    "100": 5,    // 5 x $100 bills
    "50": 10,    // 10 x $50 bills
    "20": 15,    // 15 x $20 bills
    "10": 20,    // 20 x $10 bills
    "5": 25,     // 25 x $5 bills
    "1": 100     // 100 x $1 bills
}
```

### 3. **Comprehensive Reporting**
- **Total Sales**: All sales transactions
- **Total Expenses**: All expense transactions
- **Total Refunds**: All refund transactions
- **Cash Flow**: Net cash movement
- **Payment Method Breakdown**: Detailed payment analysis

### 4. **Print Functionality**
- **Register Summary**: Complete register report
- **Cash Count Sheet**: Denomination breakdown
- **Sales Report**: Product and brand summaries

---

## Business Logic

### 1. **Cash Flow Calculations**

#### Expected Cash Calculation
```php
$expected_cash = $cash_in_hand + $total_cash_sales - $total_cash_refunds - $total_cash_expenses;
```

#### Cash Variance
```php
$cash_variance = $actual_cash - $expected_cash;
```

### 2. **Transaction Aggregation**
```php
// Total sales by payment method
DB::raw("SUM(IF(pay_method='cash', IF(transaction_type='sell', amount, 0), 0)) as total_cash")

// Total refunds by payment method
DB::raw("SUM(IF(transaction_type='refund', IF(pay_method='cash', amount, 0), 0)) as total_cash_refund")

// Net cash movement
$net_cash = $cash_in_hand + $total_cash - $total_cash_refund;
```

### 3. **Product Sales Analysis**
```php
// Individual product sales
SELECT p.name, v.name as variation_name, 
       SUM(TSL.quantity) as total_quantity,
       SUM(TSL.unit_price_inc_tax * TSL.quantity) as total_amount
FROM transactions t
JOIN transaction_sell_lines TSL ON t.id = TSL.transaction_id
JOIN variations v ON TSL.variation_id = v.id
JOIN products p ON v.product_id = p.id
WHERE t.created_by = ? AND t.created_at BETWEEN ? AND ?
GROUP BY v.id
```

### 4. **Brand-wise Sales**
```php
// Sales grouped by brand
SELECT B.name as brand_name,
       SUM(TSL.quantity) as total_quantity,
       SUM(TSL.unit_price_inc_tax * TSL.quantity) as total_amount
FROM transactions t
JOIN transaction_sell_lines TSL ON t.id = TSL.transaction_id
JOIN products P ON TSL.product_id = P.id
LEFT JOIN brands B ON P.brand_id = B.id
WHERE t.created_by = ? AND t.created_at BETWEEN ? AND ?
GROUP BY B.id
```

---

## Security & Permissions

### 1. **Access Control**
```php
// Check permissions before operations
if (!auth()->user()->can('view_cash_register')) {
    abort(403, 'Unauthorized action.');
}
```

### 2. **User Isolation**
- Each user can only have **one open register** at a time
- Register data is isolated by `user_id`
- Business location restrictions apply

### 3. **Data Validation**
```php
// Validate closing amounts
$input['closing_amount'] = $this->cashRegisterUtil->num_uf($input['closing_amount']);

// Sanitize denominations
$input['denominations'] = json_encode(request()->input('denominations'));
```

### 4. **Demo Mode Protection**
```php
// Disable operations in demo mode
if (config('app.env') == 'demo') {
    $output = ['success' => 0, 'msg' => 'Feature disabled in demo!!'];
    return redirect()->back()->with('status', $output);
}
```

---

## Troubleshooting

### 1. **Common Issues**

#### Issue: "Register already open" error
**Cause**: User already has an open register
**Solution**: Close existing register before opening new one

#### Issue: Cash variance discrepancies
**Causes**:
- Incorrect denomination counting
- Missing transactions
- Manual cash adjustments

**Solution**:
- Re-count cash denominations
- Verify all transactions are recorded
- Check for manual cash additions/removals

#### Issue: Register details not loading
**Causes**:
- No open register for user
- Permission issues
- Database connection problems

**Solution**:
- Verify user has open register
- Check user permissions
- Verify database connectivity

### 2. **Data Recovery**

#### Recovering Lost Transactions
```sql
-- Find transactions for a specific register session
SELECT * FROM cash_register_transactions 
WHERE cash_register_id = ? 
ORDER BY created_at;
```

#### Recalculating Totals
```sql
-- Recalculate register totals
SELECT 
    SUM(IF(transaction_type='initial', amount, 0)) as cash_in_hand,
    SUM(IF(transaction_type='sell', amount, 0)) as total_sales,
    SUM(IF(transaction_type='expense', amount, 0)) as total_expenses,
    SUM(IF(transaction_type='refund', amount, 0)) as total_refunds
FROM cash_register_transactions 
WHERE cash_register_id = ?;
```

### 3. **Performance Optimization**

#### Indexing Recommendations
```sql
-- Add indexes for better performance
CREATE INDEX idx_cash_register_user_status ON cash_registers(user_id, status);
CREATE INDEX idx_cash_register_transactions_register ON cash_register_transactions(cash_register_id);
CREATE INDEX idx_cash_register_transactions_type ON cash_register_transactions(transaction_type);
```

#### Query Optimization
- Use `LIMIT` for large datasets
- Implement pagination for register lists
- Cache frequently accessed data

---

## Integration Points

### 1. **POS System Integration**
- **SellPosController**: Main POS interface
- **Transaction Creation**: Automatic register transaction recording
- **Payment Processing**: Real-time payment tracking

### 2. **Reporting System Integration**
- **Register Reports**: Daily/monthly register summaries
- **Cash Flow Reports**: Cash movement analysis
- **Sales Reports**: Product and brand performance

### 3. **Business Location Integration**
- **Location-specific Registers**: Each location can have multiple registers
- **Location-based Reporting**: Separate reports per location
- **Location Access Control**: Restrict register access by location

---

## Best Practices

### 1. **Register Management**
- Always count cash denominations accurately
- Record all cash additions/removals
- Close register at end of shift
- Keep detailed closing notes

### 2. **Data Integrity**
- Verify transaction amounts before recording
- Double-check payment method assignments
- Regular backup of register data
- Monitor for unusual cash variances

### 3. **Security**
- Limit register access to authorized users
- Regular password updates
- Monitor for suspicious activities
- Implement audit trails

### 4. **Performance**
- Regular database maintenance
- Optimize queries for large datasets
- Implement caching where appropriate
- Monitor system performance

---

## Future Enhancements

### 1. **Planned Features**
- Multi-register transfers
- Advanced cash forecasting
- Integration with accounting systems
- Mobile register access
- Real-time register monitoring

### 2. **API Improvements**
- RESTful API endpoints
- Webhook notifications
- Third-party integrations
- Mobile app support

### 3. **Reporting Enhancements**
- Advanced analytics
- Custom report builder
- Export to multiple formats
- Scheduled report generation

---

## Cash Register Details Modal - Totals Section Calculations

### Overview
The register details modal displays several key totals that provide a comprehensive view of register performance. Each total is calculated differently and serves a specific business purpose.

### 1. **Total Sales (First occurrence)**
```php
// From: $register_details->total_sale
DB::raw("SUM(IF(transaction_type='sell', amount, IF(transaction_type='refund', -1 * amount, 0))) as total_sale")
```

**Calculation:**
- **Sums all `sell` transactions** (positive amounts)
- **Subtracts all `refund` transactions** (negative amounts)
- **Result:** Net sales amount after refunds
- **Source:** `cash_register_transactions` table

**Example:**
- Sales: $1,000
- Refunds: $50
- **Total Sales: $950**

### 2. **Total Refund**
```php
// From: $register_details->total_refund
DB::raw("SUM(IF(transaction_type='refund', amount, 0)) as total_refund")
```

**Calculation:**
- **Sums all `refund` transactions** (absolute values)
- **Shows breakdown by payment method** (cash, card, cheque, etc.)
- **Source:** `cash_register_transactions` table

**Example:**
- Cash refunds: $30
- Card refunds: $20
- **Total Refund: $50**

### 3. **Total Payment**
```php
// From: Line 225 in payment_details.blade.php
{{ $register_details->cash_in_hand + $register_details->total_cash - $register_details->total_cash_refund }}
```

**Calculation:**
- **Cash in Hand** (initial amount) + **Cash Sales** - **Cash Refunds**
- **Result:** Total cash that should be in the register drawer
- **Purpose:** Cash reconciliation and drawer count verification

**Example:**
- Initial cash: $100
- Cash sales: $800
- Cash refunds: $30
- **Total Payment: $870**

### 4. **Credit Sales**
```php
// From: Line 233 in payment_details.blade.php
{{ $details['transaction_details']->total_sales - $register_details->total_sale }}
```

**Calculation:**
- **Transaction Total Sales** - **Register Total Sales**
- **Result:** Sales paid by non-cash methods (credit sales)
- **Purpose:** Identify non-cash revenue

**Example:**
- Transaction total: $2,000
- Register total (cash only): $950
- **Credit Sales: $1,050**

### 5. **Total Sales (Second occurrence)**
```php
// From: $details['transaction_details']->total_sales
DB::raw('SUM(final_total) as total_sales')
```

**Calculation:**
- **Sums `final_total` from all completed sell transactions**
- **Includes ALL payment methods** (cash + non-cash)
- **Result:** Gross sales before any deductions
- **Source:** `transactions` table

**Example:**
- All sales transactions: $2,000
- **Total Sales: $2,000**

### 6. **Total Expense**
```php
// From: $register_details->total_expense
DB::raw("SUM(IF(transaction_type='expense', IF(transaction_type='refund', -1 * amount, amount), 0)) as total_expense")
```

**Calculation:**
- **Sums all `expense` transactions** (positive amounts)
- **Subtracts any expense refunds** (negative amounts)
- **Result:** Net expenses for the register session
- **Source:** `cash_register_transactions` table

**Example:**
- Expenses: $200
- Expense refunds: $20
- **Total Expense: $180**

### **Calculation Logic Summary**

#### **Data Sources:**
1. **`$register_details`** - From `cash_register_transactions` table (cash-focused)
2. **`$details['transaction_details']`** - From `transactions` table (all sales)

#### **Key Differences:**

| Field | Source Table | Scope | Includes |
|-------|-------------|-------|----------|
| **Total Sales (1st)** | `cash_register_transactions` | Register only | Cash sales - cash refunds |
| **Total Sales (2nd)** | `transactions` | All sales | All payment methods |
| **Credit Sales** | Calculated | Difference | Non-cash sales only |

#### **Calculation Flow:**
```
1. Register Sales = Cash Register Transactions (sell - refund)
2. Transaction Sales = All Transactions (final_total)
3. Credit Sales = Transaction Sales - Register Sales
4. Total Payment = Initial Cash + Cash Sales - Cash Refunds
5. Total Refund = All Refund Transactions
6. Total Expense = Expense Transactions - Expense Refunds
```

#### **Business Logic:**
- **Register totals** focus on **cash flow** and **physical money**
- **Transaction totals** include **all sales** regardless of payment method
- **Credit sales** represent sales not involving physical cash
- **Expenses** are tracked separately from sales transactions

This dual calculation system allows the business to track both **cash flow** (what's physically in the register) and **total business** (all sales including credit transactions).

---

This documentation provides a comprehensive overview of the cash register system. For specific implementation details, refer to the source code in the respective files mentioned throughout this document.
