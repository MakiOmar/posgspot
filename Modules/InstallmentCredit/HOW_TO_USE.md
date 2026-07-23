# How to use Installment Credit

This guide is for **admins and cashiers**. It explains how to track money that **installment companies** (Value, Maylo, Tru, Aman, Forsa, Seven, Sohoula, …) owe Games Spot after a customer buys with BNPL.

> This is **not** customer installment plans. The customer is finished at the till; the **company** pays Games Spot later.

For technical install steps, see [README.md](README.md).

---

## Quick overview

```text
Customer buys at POS
        │
        ▼
Cashier chooses company payment (e.g. "Value")
        │
        ▼
Sale = paid for the customer
Company receivable = pending (money not in cash drawer yet)
        │
        ▼
Company pays you (bank / settlement)
        │
        ▼
Admin records settlement → cash/bank account credited
```

**Menu:** sidebar → **Installment Credit** (same labels as in the app)

| Menu item | Who uses it | Purpose |
|-----------|-------------|---------|
| Pending Receivables | Admin / accounts | List open debts; **Add Pending Receivable**; settle |
| Installment Companies | Admin | Define BNPL partners + POS payment link |
| Settlements | Admin | History of money received from companies |
| Reports | Admin | By branch × company, aging, CSV export |
| Import Open Balances | Admin | Bulk import via **.xlsx sample** / CSV |
| Dashboard | Admin | Pending / overdue totals |

---

## One-time setup

### 1. Permissions

**User Management → Roles** — give the right roles:

| Permission | Meaning |
|------------|---------|
| View installment receivables | Dashboard + Pending Receivables |
| Manage installment companies | Installment Companies CRUD |
| Settle installment receivables | Record settlements |
| View installment reports | Reports + CSV |
| Import installment open balances | Import Open Balances |

Admin roles usually get these from migration. Superadmin always has access.

### 2. Installment companies

1. Open **Installment Credit → Installment Companies**.
2. Defaults (Value, Maylo, Tru, Aman, Forsa, Seven, Sohoula) are created on install. Use **Seed default companies** if the list is empty.
3. Each company is linked to a **Custom Payment** slot that was **free** (not InstaPay / Vodafone Cash / Wallet / etc.).
4. If a company wrongly sits on a used slot, click **Fix payment slot conflicts**.

Check **Business Settings → Custom Labels → Labels for custom payments**:

- Keep existing: Custom Payment 1–3 = InstaPay, Vodafone Cash, Wallet (example).
- Installment names appear on **other** numbers (e.g. 5 = Forsa, 8 = Value).

### 3. Enable methods per branch

For **each business location**:

1. Edit the location.
2. Under payment accounts / payment methods, **enable** the custom payments used by installment companies.
3. Cashiers will then see those names on the POS payment screen.

Without this step, the company method will not appear at the till.

### 4. Settlement bank account (recommended)

On each company (edit under **Installment Companies**), set **Default deposit account** to the bank/cash account where that company usually pays. That account is **pre-selected** as Payment Account on Record Settlement; you can still change it there.

---

## Daily use — cashiers (POS)

1. Complete the sale as usual.
2. On payment, choose the **installment company** name (e.g. **Value**), not cash/card.
3. You may split: part cash + part company (one receivable per company portion).
4. Finish the sale.

**What happens:**

- Invoice shows as **paid** (customer owes nothing).
- Cash drawer / bank is **not** increased yet.
- A pending row appears under **Installment Credit → Pending Receivables**.

**Do not** use InstaPay / Vodafone Cash / Wallet for BNPL company sales — those are different payment methods.

---

## Daily use — accounts / admin

### See what companies owe

1. **Installment Credit → Pending Receivables**.
2. Filter by company, branch, or aging if needed.
3. **Outstanding** = amount still unpaid by the company (Excel “Total”).

Or use **Installment Credit → Dashboard** for totals and overdue counts.

### Add a pending receivable manually

When there was no POS BNPL payment (or you need to enter an old balance one-by-one):

1. Open **Installment Credit → Pending Receivables**.
2. Click **Add Pending Receivable**.
3. Choose **company**, **branch**, optional **invoice no**, dates, and **due amount**.
4. Save — the row appears in the pending list and can be settled like any other.

You need settle or import permission to add manually.

### When the company pays you

1. Open **Installment Credit → Pending Receivables**.
2. Tick one or more rows from the **same company**.
3. Click **Record Settlement**.
4. Fill in:

| Field | Meaning |
|-------|---------|
| Settlement date | When you received the money |
| Payment account | Bank/cash to credit (defaults from company Default Deposit Account) |
| Business location | Auto-filled when all selected invoices share the same branch; otherwise choose manually |
| Booked amount | Full claim (usually = outstanding) — used for outstanding, reports, and account credit |
| Actual received | Optional reference only (not used in reports or cashbook posting) |
| External ref | Transfer / statement reference (optional) |

5. Save.

**What happens:**

- Receivable marked **settled** (or reduced if partial booked amount).
- Chosen **account** is credited with **booked amount**.
- Actual received is stored for display only; no BNPL fee expense is posted from any difference.

Review past settlements under **Installment Credit → Settlements**.

### Reports (replaces Excel Summery / Trans.)

Open **Installment Credit → Reports**.

| Report | Excel equivalent |
|--------|------------------|
| Pending by Branch × Company | Summery |
| Aging by Company (0–30 … 120+) | Trans. aging |
| Export Pending CSV | Sheet1 open rows |

---

## Importing receivables

### IDs only import (preferred for POS invoices)

Use when the sale **already exists** in POS and was paid with an installment company method (Payment mode on the invoice).

1. **Installment Credit → Import Open Balances**.
2. Under **IDs only import**, download the Excel/CSV sample.
3. Columns:

| Column | Example | Notes |
|--------|---------|--------|
| invoice_no | 12345 | Required; must match a POS sell invoice |
| actual_received | 9500 | Optional; reference only (applied when the invoice has a single BNPL payment) |

4. Upload the file. Company, due amount, branch, and dates are taken from the invoice’s installment payment line(s).
5. Check **Pending Receivables**.

Sales with no installment-company payment are skipped (use a POS payment correction, or the open-balances import below).

### Import Open Balances (manual columns)

Use this once to move **open** rows from `Credit Anlyses.xlsx` (where Total ≠ 0), or any bulk list that is **not** tied to a live BNPL payment on the invoice.

1. Same page → **Import Open Balances (manual columns)**.
2. Download **Download Excel (.xlsx) sample** (recommended) or the CSV template.
3. Keep the header row; fill data rows:

| Column | Example | Notes |
|--------|---------|--------|
| invoice_date | 2026-05-01 | |
| due_date | 2026-05-21 | Optional; defaults to invoice + company settlement days |
| invoice_no | 12345 | POS invoice if known |
| branch | Nasr City | Must match (or alias) a business location |
| company_code | value | `value`, `maylo`, `tru`, `aman`, `forsa`, `seven`, `sohoula` |
| due_amount | 10000 | Outstanding / booked claim amount |
| actual_received | 9500 | Optional; reference only (aliases: `amount_received`) |
| notes | From Excel | Optional |

4. Upload `.xlsx`, `.xls`, or `.csv`.
5. Check **Pending Receivables** and the imported total.

For a single row, prefer **Add Pending Receivable** on the pending list instead of import.

Settled Excel history can stay in the archive file; only open balances need importing.

---

## Common questions

**Why didn’t cash increase when I sold with Value?**  
By design. Cash increases when you **settle**, not at sale time.

**POS doesn’t show Value / Maylo?**  
Enable that custom payment on the **business location**, and confirm the company is **Active** under **Installment Companies** with a payment method key.

**Sale used InstaPay by mistake for BNPL?**  
That is a normal payment (cashbook immediately). It will **not** create an installment receivable. Void/correct the payment and re-enter with the company method if needed.

**Can one invoice use two companies?**  
Yes — two payment lines → two receivables for the same invoice number.

**Where is “Companies”?**  
In the sidebar it is labeled **Installment Companies** (under **Installment Credit**).

**Menu Installment Credit missing?**  
See [README.md](README.md) install section (`installmentcredit_version` + permissions + cache clear).

---

## Checklist after go-live

- [ ] **Installment Companies** list correct; payment slots not overlapping InstaPay / wallets  
- [ ] Custom labels show company names on free slots  
- [ ] Each branch has those methods enabled  
- [ ] Staff trained: company method on POS → settle later under Pending Receivables  
- [ ] Open Excel balances imported via Import Open Balances (if any)  
- [ ] First real settlement tested (bank balance credited with **booked amount**)  
- [ ] Excel no longer used for day-to-day pending tracking  
