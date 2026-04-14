# Sales order vs. order request

This application stores both **sales orders** and **CRM order requests** as the same underlying record type (`transactions.type = sales_order`). They differ by **who creates them**, **which screens you use**, and the flag **`crm_is_order_request`** (order requests are stored with this flag set to `1`).

---

## Routes and who uses them

| Route (path) | Typical user | Purpose |
|--------------|--------------|---------|
| `/sales-order` | Staff (back office) | Main **sales order** list and operations: all sales orders for the business, filters including **shipping status**, **Add sales order**, and (when the CRM module is installed) a **visual badge** on lines that originated as an order request. |
| `/crm/order-request` | Staff (CRM area) | **Order requests only**: list is limited to rows where `crm_is_order_request = 1` (customer-submitted / portal requests). Good for triaging inbound requests without mixing in every staff-created sales order. |
| `/contact/order-request` | Logged-in **customer** (CRM contact portal) | Customer **creates** order requests and sees **only their own** requests for their linked contact. |

---

## When to use **Sales order** (`/sales-order`)

Use the **sales order** screen when you need day-to-day **internal** sales order management:

- Viewing **all** sales orders (staff-created and, if CRM is enabled, customer requests appear in the same list; requests are marked in the invoice column when CRM is installed).
- Filtering by **shipping status** alongside location, customer, date, and order status.
- Creating a sales order from the back office (**Add sales order**).

**Permissions:** users need sales order permissions such as `so.view_own`, `so.view_all`, and/or `so.create` (see `SalesOrderController`).

---

## When to use **Order request**

### Staff: `/crm/order-request`

Use this when you want a **dedicated queue of customer-submitted requests** only:

- The list calls the same sell listing API but passes `crm_is_order_request`, so only flagged transactions appear.
- Useful for **sales or operations** who only care about portal/B2B submissions.

**Permissions:** the listing is gated similarly to sales orders (`so.view_own` / `so.view_all` on the CRM list view).

### Customer: `/contact/order-request`

Use this for **customers with a CRM portal login** who should **place or track their own** order requests:

- New requests are stored as `sales_order` with `crm_is_order_request = 1` and CRM-specific reference numbering (see CRM settings such as `order_request_prefix`).
- The customer index only shows requests for **their** contact and **their** user.

---

## Quick decision guide

- **Internal team managing any sales order (including shipping and staff-created orders)** → `/sales-order`
- **Internal team reviewing only portal/customer-submitted requests** → `/crm/order-request`
- **Customer placing a request through the portal** → `/contact/order-request`

---

## Technical note

Order requests are not a separate database “order type”; they are **sales orders** with `crm_is_order_request = 1`. Fulfilment and downstream flows (for example converting to a sale) follow the same sales order behaviour; choose the screen above based on **workflow and audience**, not on a different transaction type.
