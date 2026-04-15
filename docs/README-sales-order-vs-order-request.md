# Sales order vs. order request

This application stores both **sales orders** and **CRM order requests** as the same underlying record type (`transactions.type = sales_order`). They differ by **who creates them**, **which screens you use**, and the flag **`crm_is_order_request`** (order requests are stored with this flag set to `1`).

---

## What is a sales order?

A **sales order** is a structured **commitment to sell** before (or instead of) ringing everything through as an immediate cash-and-carry invoice. In this POS, it is a transaction in **ordered** or **partial** status (and eventually **completed** when fully fulfilled) with lines, customer, location, totals, and optional **shipping** tracking.

**Typical business meaning**

- You record **what** the customer agreed to buy, **quantities**, and **pricing** early.
- You can **fulfil in stages** (partial deliveries or partial invoicing) while the system tracks **quantity remaining** on the order.
- Staff can later **pull that order into the POS screen**, load its lines, and complete a sale so stock and payments align with what was promised on the order.

**Why it matters in this POS**

- Separates **“customer ordered these items”** from **“we finalized payment and stock movement for this visit”**, which reduces errors for phone orders, pre-orders, B2B batches, and multi-step fulfilment.
- The register flow can **attach a checkout to an existing sales order** (open orders for that customer and location are offered in POS; lines can be brought into the cart from `/get-sales-orders` / `/get-sales-order-lines`). When the sale is saved, linked sales order status is updated.
- The **Sales order** menu gives operations a single place to monitor order status, shipping, and remaining quantities across customers and locations.

---

## What is an order request?

An **order request** is still stored as a **sales order** row, but it is created through the **CRM customer portal** and marked with **`crm_is_order_request = 1`**. Think of it as the customer’s **self-service “I want to order this”** submission rather than something a cashier typed in at the shop.

**Typical business meaning**

- The buyer enters products and quantities **outside** the shop (often B2B reordering).
- The business may still need to **confirm stock, prices, credit, or delivery** before treating it as a firm operational order—your internal process can treat the queue as “inbound requests” first.
- Once accepted and processed, it follows the **same fulfilment mechanics** as any other sales order (statuses, remaining quantities, POS linkage), so you do not maintain a second parallel order system.

**Why it differs from a staff-only sales order in practice**

- **Provenance:** created by the logged-in **contact user**, scoped to their **contact** in the portal.
- **Reference:** can use CRM settings (for example **order request prefix** / `crm_order_request` reference type) so request numbers are recognizable in lists and on documents.
- **Visibility:** staff can isolate these in **CRM → Order request** without clutter from every counter-created sales order.

---

## Why use sales orders and order requests in this POS system?

| Need | How the system helps |
|------|----------------------|
| **Avoid mixing “quote intent” with a paid ticket** | A sales order holds agreed lines and value **before** the final POS sale, with clear statuses. |
| **Fulfil over time** | Partial status and **quantity remaining** support split picking, partial dispatch, or multiple register visits against one order. |
| **POS checkout tied to the promise** | Staff select the customer’s open sales order in POS and load lines so the **invoice matches the original order** and stock logic stays consistent. |
| **Shipping and back-office filters** | The main **Sales order** screen supports **shipping status** and the same filters you use for operational follow-up. |
| **B2B / wholesale self-service** | **Order requests** let trusted customers submit demand **24/7** from the portal; your team reviews them on **CRM → Order request** or alongside all orders on **Sales order**. |
| **One fulfilment pipeline** | Order requests are **not** a duplicate document type—they reuse **sales order** behaviour, so training and reporting stay simpler. |

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
