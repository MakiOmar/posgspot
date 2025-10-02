# Add Payment Functionality - POS & Sales System

## 📋 Overview

The "Add Payment" functionality allows users to add additional payments to existing transactions that are not fully paid. This feature is essential for handling partial payments, split payments, and completing transactions after the initial sale.

## 🎯 Key Features

- **Partial Payment Processing** - Add payments to partially paid transactions
- **Multiple Payment Methods** - Support for cash, card, cheque, bank transfer, and custom payments
- **Split Payments** - Divide single transaction across multiple payment methods
- **Real-time Status Updates** - Automatic payment status calculation
- **Cash Register Integration** - Automatic drawer balance updates
- **Customer Balance Management** - Advance payment support
- **Audit Trail** - Complete payment history tracking
- **Permission-Based Access** - Secure user access control

---

## 🚀 Getting Started

### Prerequisites

- **User Permissions**: `sell.payments`, `purchase.payments`, or `edit_sell_payment`
- **Active Business**: Business must be active and subscribed
- **Valid Transaction**: Transaction must exist and be accessible
- **Payment Status**: Transaction should not be fully paid

### Access Points

The "Add Payment" option appears in the **Actions Menu** for transactions when:
- Payment status is NOT 'paid' (partial, due, overdue)
- User has appropriate payment permissions
- Transaction exists and is accessible to the user

---

## 📱 User Interface

### Modal Form Structure

```
┌─────────────────────────────────────────────────────┐
│                    Add Payment                      │
├─────────────────────────────────────────────────────┤
│ Customer: John Doe [Auto-filled]                   │
│ Invoice: INV-001 [Auto-filled]                     │
│ Amount Due: $300.00 [Calculated]                   │
│                                                     │
│ ┌─────────────────┬─────────────────┬─────────────┐ │
│ │ Amount          │ Payment Method  │ Date        │ │
│ │ [$150.00] *     │ [Cash ▼] *      │ [Today]     │ │
│ └─────────────────┴─────────────────┴─────────────┘ │
│                                                     │
│ Payment Account: [Bank Account ▼] (Optional)       │
│                                                     │
│ Payment Note:                                       │
│ ┌─────────────────────────────────────────────────┐ │
│ │ Additional payment for invoice INV-001          │ │
│ └─────────────────────────────────────────────────┘ │
│                                                     │
│ Attach Document: [Choose File]                     │
│                                                     │
│                    [Save] [Close]                   │
└─────────────────────────────────────────────────────┘
```

### Form Fields

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| **Amount** | Number | Yes | Payment amount (cannot exceed amount due) |
| **Payment Method** | Dropdown | Yes | Cash, Card, Cheque, Bank Transfer, Custom, Advance |
| **Payment Date** | Date | Yes | Date of payment (defaults to current date) |
| **Payment Account** | Dropdown | No | Bank account for non-cash payments |
| **Payment Note** | Textarea | No | Additional notes about the payment |
| **Document** | File | No | Receipt or supporting document |

---

## 💳 Payment Methods

### Standard Payment Methods

#### 1. Cash 💵
- **Description**: Physical cash payment
- **Cash Register**: Updates drawer balance
- **Denominations**: Supports cash denomination tracking
- **Validation**: Amount must match denomination total (if enabled)

#### 2. Card 💳
- **Description**: Credit/Debit card payment
- **Required Fields**: Card number, holder name, transaction number
- **Account**: Links to bank account
- **Processing**: External payment gateway integration

#### 3. Cheque 📝
- **Description**: Cheque payment
- **Required Fields**: Cheque number, bank account number
- **Account**: Links to bank account
- **Processing**: Manual verification required

#### 4. Bank Transfer 🏦
- **Description**: Direct bank transfer
- **Required Fields**: Bank account number
- **Account**: Links to bank account
- **Processing**: External bank integration

#### 5. Other 📋
- **Description**: Miscellaneous payment methods
- **Usage**: Custom business-specific payments
- **Account**: Links to specified account
- **Processing**: Manual verification

### Custom Payment Methods

#### Custom Payment 1-7
- **Description**: Configurable payment methods
- **Setup**: Defined in business settings
- **Usage**: Business-specific payment types
- **Validation**: Custom transaction numbers

### Advance Payment
- **Description**: Payment from customer's advance balance
- **Validation**: Cannot exceed customer balance
- **Processing**: Reduces customer advance balance
- **Usage**: For customers with pre-paid accounts

---

## 🔧 Technical Implementation

### Routes

```php
// Main route for adding payments
Route::get('/payments/add_payment/{transaction_id}', 
    [TransactionPaymentController::class, 'addPayment']);

// Route for processing payments
Route::post('/payments', 
    [TransactionPaymentController::class, 'store']);
```

### Controller Methods

#### `TransactionPaymentController@addPayment($transaction_id)`
```php
public function addPayment($transaction_id)
{
    // Check user permissions
    // Get transaction details
    // Calculate amount due
    // Prepare payment form data
    // Return payment modal view
}
```

#### `TransactionPaymentController@store(Request $request)`
```php
public function store(Request $request)
{
    // Validate payment data
    // Create TransactionPayment record
    // Update transaction payment status
    // Process cash register (if applicable)
    // Send notifications
    // Return success response
}
```

### Database Schema

#### Transaction Payments Table
```sql
CREATE TABLE transaction_payments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    transaction_id INT NOT NULL,
    business_id INT NOT NULL,
    is_return TINYINT DEFAULT 0,
    amount DECIMAL(15,4) NOT NULL,
    method VARCHAR(191) NOT NULL,
    transaction_no VARCHAR(191) NULL,
    card_transaction_number VARCHAR(191) NULL,
    card_number VARCHAR(191) NULL,
    card_type VARCHAR(191) NULL,
    card_holder_name VARCHAR(191) NULL,
    cheque_number VARCHAR(191) NULL,
    bank_account_number VARCHAR(191) NULL,
    paid_on DATETIME NOT NULL,
    created_by INT NOT NULL,
    payment_for INT NULL,
    payment_ref_no VARCHAR(191) NULL,
    account_id INT NULL,
    document VARCHAR(191) NULL,
    note TEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);
```

---

## 📊 Business Logic

### Payment Status Calculation

```php
// Automatic payment status updates
$total_paid = TransactionPayment::where('transaction_id', $transaction_id)
    ->sum('amount');

if ($total_paid >= $transaction->final_total) {
    $payment_status = 'paid';
} elseif ($total_paid > 0) {
    $payment_status = 'partial';
} else {
    $payment_status = 'due';
}
```

### Cash Register Integration

```php
// For POS transactions
if (!$is_direct_sale && $transaction->type == 'sell') {
    $cashRegisterUtil->addSellPayments($transaction, $payments);
}
```

### Customer Balance Management

```php
// For advance payments
if ($method == 'advance' && $amount > $contact_balance) {
    throw new AdvanceBalanceNotAvailable();
}
```

---

## 🎯 Use Cases & Examples

### 1. Partial Payment Scenario

**Scenario**: Customer wants to pay in installments

```
Original Invoice: $1,000.00
Previous Payments: $400.00
Amount Due: $600.00

Add Payment:
- Amount: $300.00
- Method: Cash
- Note: "Second installment payment"

Result:
- Total Paid: $700.00
- Payment Status: Partial
- Amount Due: $300.00
```

### 2. Complete Payment Scenario

**Scenario**: Customer pays remaining balance

```
Original Invoice: $1,000.00
Previous Payments: $700.00
Amount Due: $300.00

Add Payment:
- Amount: $300.00
- Method: Card
- Note: "Final payment - invoice complete"

Result:
- Total Paid: $1,000.00
- Payment Status: Paid
- Amount Due: $0.00
```

### 3. Split Payment Scenario

**Scenario**: Customer wants to pay with multiple methods

```
Original Invoice: $500.00
Previous Payments: $200.00
Amount Due: $300.00

Add Payment 1:
- Amount: $150.00
- Method: Cash

Add Payment 2:
- Amount: $150.00
- Method: Card

Result:
- Total Paid: $500.00
- Payment Status: Paid
- Multiple payment methods recorded
```

### 4. Advance Payment Scenario

**Scenario**: Customer uses pre-paid balance

```
Customer Advance Balance: $500.00
Invoice Amount: $300.00

Add Payment:
- Amount: $300.00
- Method: Advance

Result:
- Total Paid: $300.00
- Payment Status: Paid
- Customer Balance: $200.00
```

---

## 🔐 Security & Permissions

### Required Permissions

```php
// User permission checks
'sell.payments'           // Add payments to sales
'purchase.payments'       // Add payments to purchases
'edit_sell_payment'       // Edit sales payments
'delete_sell_payment'     // Delete sales payments
'all_expense.access'      // Expense payments
'view_own_expense'        // Own expense payments
'hms.add_booking_payment' // Hotel management payments
```

### Business Rules

- **Transaction Access**: User must have access to the transaction
- **Amount Validation**: Payment cannot exceed amount due
- **Advance Balance**: Advance payments cannot exceed customer balance
- **Business Status**: Business must be active and subscribed
- **Location Access**: User must have access to transaction location

### Validation Rules

```php
// Payment validation
'amount' => 'required|numeric|min:0.01',
'method' => 'required|string|max:191',
'paid_on' => 'required|date',
'note' => 'nullable|string|max:1000',
'document' => 'nullable|file|max:10240'
```

---

## 🔄 Integration Points

### 1. Cash Register System

- **Cash Payments**: Automatically update drawer balance
- **Refunds**: Decrease drawer balance
- **Denominations**: Track cash denominations
- **Session Management**: Link payments to register sessions

### 2. Customer Management

- **Balance Updates**: Reduce customer advance balance
- **Payment History**: Maintain complete payment records
- **Credit Limits**: Respect customer credit limits
- **Contact Information**: Use customer details for receipts

### 3. Accounting System

- **Account Integration**: Debit/credit appropriate accounts
- **Transaction References**: Generate unique payment references
- **Audit Trail**: Maintain complete payment audit trail
- **Reporting**: Include payments in financial reports

### 4. Notification System

- **Payment Confirmations**: Send receipts to customers
- **Due Alerts**: Notify about overdue payments
- **Status Updates**: Update payment status notifications
- **Admin Notifications**: Alert administrators of large payments

---

## 📈 Advanced Features

### Cash Denominations

For cash payments, the system supports denomination tracking:

```
Denomination Tracking:
┌─────────────┬────────┬─────────┬─────────────┐
│ Denomination│ Count  │ Total   │ Subtotal    │
├─────────────┼────────┼─────────┼─────────────┤
│ $100.00     │   5    │   $500  │   $500.00   │
│ $50.00      │   2    │   $100  │   $100.00   │
│ $20.00      │   3    │    $60  │    $60.00   │
│ $10.00      │   4    │    $40  │    $40.00   │
├─────────────┼────────┼─────────┼─────────────┤
│ Total       │  14    │   $700  │   $700.00   │
└─────────────┴────────┴─────────┴─────────────┘
```

### Payment Accounts

Link payments to specific bank accounts:

- **Cash Account**: For cash payments
- **Bank Accounts**: For card, cheque, transfer payments
- **Custom Accounts**: For business-specific payment methods
- **Multi-Currency**: Support for different currencies

### Document Attachments

Support for payment-related documents:

- **Receipt Images**: Upload payment receipts
- **Bank Statements**: Attach bank transfer confirmations
- **Cheque Images**: Store cheque images
- **Custom Documents**: Any payment-related documents

---

## 🚨 Error Handling

### Common Error Scenarios

#### 1. Insufficient Permissions
```
Error: "Unauthorized action"
Solution: Check user permissions for payment operations
```

#### 2. Amount Exceeds Due
```
Error: "Payment amount cannot exceed amount due"
Solution: Verify payment amount against remaining balance
```

#### 3. Insufficient Advance Balance
```
Error: "Required advance balance not available"
Solution: Check customer advance balance before payment
```

#### 4. Invalid Payment Method
```
Error: "Invalid payment method selected"
Solution: Verify payment method configuration
```

### Error Recovery

- **Database Rollback**: Automatic rollback on errors
- **User Notifications**: Clear error messages to users
- **Logging**: Comprehensive error logging
- **Retry Mechanisms**: Allow users to retry failed payments

---

## 📊 Reporting & Analytics

### Payment Reports

#### 1. Payment Summary
- Total payments by method
- Payment trends over time
- Outstanding balances
- Payment completion rates

#### 2. Customer Payment History
- Individual customer payment records
- Payment method preferences
- Average payment amounts
- Payment frequency analysis

#### 3. Cash Register Reports
- Cash vs non-cash payments
- Daily payment summaries
- Payment method distribution
- Register balance reconciliation

### Export Options

- **PDF Reports**: Printable payment summaries
- **Excel Export**: Detailed payment data
- **CSV Export**: Raw payment information
- **API Integration**: Real-time payment data

---

## 🔧 Configuration

### Business Settings

#### Payment Methods Configuration
```php
// Enable/disable payment methods
'cash' => true,
'card' => true,
'cheque' => true,
'bank_transfer' => true,
'other' => true,
'custom_pay_1' => 'Mobile Payment',
'custom_pay_2' => 'Gift Card',
'custom_pay_3' => 'Store Credit'
```

#### Cash Denomination Settings
```php
// Cash denomination configuration
'cash_denominations' => '100,50,20,10,5,1',
'enable_cash_denomination_on' => 'all_screens',
'cash_denomination_strict_check' => true
```

#### Account Integration
```php
// Default payment accounts
'default_cash_account' => 1,
'default_card_account' => 2,
'default_cheque_account' => 3,
'default_bank_transfer_account' => 4
```

---

## 🚀 Best Practices

### 1. Payment Processing

- **Verify Amounts**: Always verify payment amounts before processing
- **Check Permissions**: Ensure users have appropriate permissions
- **Validate Methods**: Confirm payment method is configured
- **Document Payments**: Attach receipts when possible

### 2. Cash Management

- **Count Denominations**: Use denomination tracking for accuracy
- **Regular Reconciliation**: Reconcile cash register regularly
- **Secure Storage**: Keep cash secure and organized
- **Document Transactions**: Maintain clear payment records

### 3. Customer Relations

- **Clear Communication**: Explain payment options to customers
- **Flexible Options**: Offer multiple payment methods
- **Receipt Generation**: Provide clear payment receipts
- **Follow-up**: Follow up on partial payments

### 4. Security

- **User Access**: Limit payment access to authorized users
- **Audit Trail**: Maintain complete payment audit trail
- **Data Protection**: Secure payment information
- **Regular Backups**: Backup payment data regularly

---

## 🆘 Troubleshooting

### Common Issues

#### 1. Payment Not Appearing
**Symptoms**: Payment added but not reflected in totals
**Solutions**:
- Check payment status calculation
- Verify cash register integration
- Review database transaction logs
- Check user permissions

#### 2. Cash Register Imbalance
**Symptoms**: Cash register totals don't match
**Solutions**:
- Reconcile denomination counts
- Check payment method mappings
- Verify register session status
- Review payment processing logs

#### 3. Permission Errors
**Symptoms**: Users cannot add payments
**Solutions**:
- Check user role permissions
- Verify business subscription status
- Review location access rights
- Check transaction ownership

#### 4. Payment Method Issues
**Symptoms**: Payment method not available
**Solutions**:
- Check business payment method configuration
- Verify account integration settings
- Review payment method permissions
- Check system configuration

### Support Resources

- **Documentation**: Refer to system documentation
- **User Manual**: Check user manual for procedures
- **Admin Panel**: Review admin configuration
- **Technical Support**: Contact technical support for issues

---

## 📚 Additional Resources

### Related Documentation

- [Cash Register System](./CASH_REGISTER_README.md)
- [Transaction Management](./TRANSACTION_MANAGEMENT_README.md)
- [Customer Management](./CUSTOMER_MANAGEMENT_README.md)
- [Payment Gateway Integration](./PAYMENT_GATEWAY_README.md)

### API Reference

- [Payment API Endpoints](./API_PAYMENT_REFERENCE.md)
- [Transaction API](./API_TRANSACTION_REFERENCE.md)
- [Customer API](./API_CUSTOMER_REFERENCE.md)

### Video Tutorials

- [Adding Payments to Transactions](./tutorials/add-payment-tutorial.md)
- [Cash Register Reconciliation](./tutorials/cash-register-tutorial.md)
- [Payment Method Configuration](./tutorials/payment-methods-tutorial.md)

---

## 📞 Support

For technical support or questions about the Add Payment functionality:

- **Email**: support@yourcompany.com
- **Phone**: +1-800-XXX-XXXX
- **Documentation**: [Online Documentation](https://docs.yourcompany.com)
- **Community**: [User Community Forum](https://community.yourcompany.com)

---

*Last Updated: [Current Date]*
*Version: 1.0*
*Author: Development Team*
