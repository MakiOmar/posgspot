# Customer Escalation System — Design & Workflow

This document defines the **customer escalation / complaint tracking** feature for the Games Spot POS, based on the spreadsheet `extracted_from_image.xlsx`. It maps each Excel column to database relations, UI controls, permissions, and the end-to-end operational flow.

---

## Table of Contents

1. [Overview](#overview)
2. [Source Spreadsheet](#source-spreadsheet)
3. [Column Understanding](#column-understanding)
4. [Database Design & Relations](#database-design--relations)
5. [UI: Search / Select Inputs](#ui-search--select-inputs)
6. [Permissions & Roles](#permissions--roles)
7. [Status Lifecycle](#status-lifecycle)
8. [Full Flow (Step by Step)](#full-flow-step-by-step)
9. [Screens & Routes](#screens--routes)
10. [Implementation Plan](#implementation-plan)
11. [Sample Record Mapping](#sample-record-mapping)

---

## Overview

An **escalation** is a structured record of a **customer complaint or service issue** (wrong order, rude response, delivery problem, etc.). Staff log the case, link it to the **customer** and optionally the **invoice**, assign an **observer** (supervisor) and **auditor** (quality control), and track follow-up calls until the case is closed.

The feature lives inside the existing **CRM module** (`Modules/Crm`) because it reuses customers (`contacts`), sales (`transactions`), locations (`business_locations`), and staff (`users`) — the same entities already used for follow-ups and call logs.

### Key components (planned)

| Layer | Artifact |
|-------|----------|
| Tables | `crm_escalation_sources`, `crm_escalations`, `crm_escalation_status_logs` |
| Model | `Modules\Crm\Entities\Escalation`, `EscalationSource` |
| Controller | `Modules\Crm\Http\Controllers\EscalationController` |
| Views | `Modules/Crm/Resources/views/escalation/*` |
| Permissions | `crm.escalation.*` (see below) |

---

## Source Spreadsheet

**File:** `extracted_from_image.xlsx`  
**Sheet:** `Data`  
**Columns (13):**

| # | Excel Header | Sample Value |
|---|--------------|--------------|
| 1 | employee | mohamed |
| 2 | Customer Name | aya shoaib |
| 3 | Phone Number | 1551018628 |
| 4 | Date/Time | 2026-12-04 18:00 |
| 5 | Description | wrong order |
| 6 | taken by | Instagram |
| 7 | Location | head office |
| 8 | time to call | *(empty)* |
| 9 | invoice num. | 16216 |
| 10 | Comment | رد غير لائق مع … |
| 11 | Observer | ahmed yasser |
| 12 | Observer Comment | *(empty)* |
| 13 | auditor | Shazly |

---

## Column Understanding

| Excel Column | Business Meaning | Stored As | Notes |
|--------------|------------------|-----------|-------|
| **employee** | Staff member who **logged** the escalation (not necessarily who handled the call). | `employee_id` → `users.id` | Defaults to the logged-in user on create. Searchable staff dropdown. |
| **Customer Name** | The complaining customer. | `contact_id` → `contacts.id` | Must be a customer-type contact. AJAX search by name, mobile, or contact ID. |
| **Phone Number** | Customer phone for callbacks. | `phone` (varchar) | **Auto-filled** from `contacts.mobile` when a customer is selected; editable if the complaint came from a different number. |
| **Date/Time** | When the complaint was received / logged. | `escalated_at` (datetime) | Defaults to now; user can backdate. |
| **Description** | Short summary of the issue (e.g. wrong order, late delivery). | `description` (text) | Free text or future link to `escalation_categories` lookup. |
| **taken by** | **Channel / source** where the complaint arrived — *not* a person. | `source_id` → `crm_escalation_sources.id` | Sample value `Instagram` = social channel. Managed via Settings → Escalation Sources (Instagram, Phone, WhatsApp, In-store, etc.). |
| **Location** | Branch / office related to the case. | `location_id` → `business_locations.id` | Dropdown of permitted locations for the current business. |
| **time to call** | Scheduled callback datetime. | `callback_at` (datetime, nullable) | Optional. When set, status can move to `callback_scheduled` and appear on a “callbacks due” filter. |
| **invoice num.** | Related sale invoice, if any. | `transaction_id` → `transactions.id` | AJAX search on `transactions.invoice_no` where `type = sell` and `status = final`. Display invoice # in lists; store FK internally. |
| **Comment** | Initial staff notes about the complaint. | `comment` (text, nullable) | Employee’s handling notes. |
| **Observer** | Supervisor assigned to **monitor** the case. | `observer_id` → `users.id` (nullable) | Searchable staff dropdown. Can add notes in `observer_comment`. |
| **Observer Comment** | Supervisor’s review notes. | `observer_comment` (text, nullable) | Filled during review; may be required before closing. |
| **auditor** | Quality / audit staff who **signs off** on resolution. | `auditor_id` → `users.id` (nullable) | Searchable staff dropdown. Final approval step. |

### Important clarifications

1. **`employee` ≠ `taken by`** — `employee` is a **user**; `taken by` is a **communication channel** (lookup table).
2. **Customer Name + Phone** — name comes from `contacts`; phone is denormalized for display and callback convenience.
3. **Invoice** — optional; not every escalation ties to a single invoice (general complaints, pre-sale issues).
4. **Observer vs auditor** — observer tracks day-to-day handling; auditor performs final QC before `closed`.

---

## Database Design & Relations

### ER diagram

```mermaid
erDiagram
    business ||--o{ crm_escalations : has
    business ||--o{ crm_escalation_sources : has
    users ||--o{ crm_escalations : "employee_id"
    users ||--o{ crm_escalations : "observer_id"
    users ||--o{ crm_escalations : "auditor_id"
    users ||--o{ crm_escalations : "created_by"
    contacts ||--o{ crm_escalations : "contact_id"
    business_locations ||--o{ crm_escalations : "location_id"
    transactions ||--o{ crm_escalations : "transaction_id"
    crm_escalation_sources ||--o{ crm_escalations : "source_id"
    crm_escalations ||--o{ crm_escalation_status_logs : has

    crm_escalations {
        bigint id PK
        int business_id FK
        string reference_no
        int employee_id FK
        int contact_id FK
        string phone
        datetime escalated_at
        text description
        int source_id FK
        int location_id FK
        datetime callback_at
        int transaction_id FK
        text comment
        int observer_id FK
        text observer_comment
        int auditor_id FK
        enum status
        int created_by FK
        int updated_by FK
        timestamps
        soft_deletes
    }

    crm_escalation_sources {
        int id PK
        int business_id FK
        string name
        boolean is_active
        timestamps
    }

    crm_escalation_status_logs {
        bigint id PK
        bigint escalation_id FK
        int user_id FK
        string from_status
        string to_status
        text note
        timestamps
    }
```

### Table: `crm_escalation_sources`

Lookup for **“taken by”** channels.

| Column | Type | Description |
|--------|------|-------------|
| `id` | increments | PK |
| `business_id` | unsigned int FK → `business` | Tenant scope |
| `name` | string | e.g. Instagram, Phone, WhatsApp |
| `is_active` | boolean default 1 | Hide inactive sources from forms |
| `created_by` | unsigned int FK → `users` | |
| `timestamps` | | |

**Seed defaults per business:** Instagram, Facebook, WhatsApp, Phone, Email, In-store, Website.

### Table: `crm_escalations`

Main escalation record.

| Column | Type | FK / Relation |
|--------|------|---------------|
| `id` | bigIncrements | PK |
| `business_id` | unsigned int | → `business.id` |
| `reference_no` | string unique per business | Auto: `ESC-00001` via `reference_counts` |
| `employee_id` | unsigned int | → `users.id` (employee) |
| `contact_id` | unsigned int | → `contacts.id` (customer) |
| `phone` | string(20) | Denormalized from contact |
| `escalated_at` | datetime | Date/Time column |
| `description` | text | Issue summary |
| `source_id` | unsigned int | → `crm_escalation_sources.id` |
| `location_id` | unsigned int | → `business_locations.id` |
| `callback_at` | datetime nullable | time to call |
| `transaction_id` | unsigned int nullable | → `transactions.id` |
| `comment` | text nullable | Staff comment |
| `observer_id` | unsigned int nullable | → `users.id` |
| `observer_comment` | text nullable | |
| `auditor_id` | unsigned int nullable | → `users.id` |
| `status` | enum | See [Status Lifecycle](#status-lifecycle) |
| `created_by` | unsigned int | → `users.id` |
| `updated_by` | unsigned int nullable | → `users.id` |
| `timestamps` | | |
| `deleted_at` | soft delete | |

**Indexes:** `business_id`, `contact_id`, `transaction_id`, `status`, `escalated_at`, `callback_at`, `employee_id`, `observer_id`, `auditor_id`.

### Table: `crm_escalation_status_logs`

Audit trail for status changes (complements Spatie `activity_log`).

| Column | Type | Description |
|--------|------|-------------|
| `escalation_id` | FK | Parent escalation |
| `user_id` | FK → `users` | Who changed status |
| `from_status` | string nullable | Previous status |
| `to_status` | string | New status |
| `note` | text nullable | Reason for change |

### Eloquent relations (`Escalation` model)

```php
// belongsTo
employee()    → User::class, 'employee_id'
contact()     → Contact::class
location()    → BusinessLocation::class
source()      → EscalationSource::class
transaction() → Transaction::class
observer()    → User::class, 'observer_id'
auditor()     → User::class, 'auditor_id'
createdBy()   → User::class, 'created_by'

// hasMany
statusLogs()  → EscalationStatusLog::class
```

---

## UI: Search / Select Inputs

Fields that map to **existing database entities** use **Select2 with AJAX search** on create/edit forms (same pattern as CRM follow-ups and POS customer picker).

| Form Field | UI Control | Data Source | Endpoint / Pattern |
|------------|------------|-------------|-------------------|
| Employee | Select2 AJAX | `users` | New: `GET /crm/escalations/search-users?q=` — active users for `business_id`, search `first_name`, `last_name`, `username` |
| Customer Name | Select2 AJAX | `contacts` | Reuse: `GET /contacts/customers?q=` — existing `ContactController@getCustomers` |
| Phone Number | Text input | Auto from contact | On customer `select2:select`, set `#phone` from `data.mobile`; still editable |
| Date/Time | DateTime picker | — | `moment` + daterangepicker (project standard) |
| Description | Textarea | — | Required |
| Taken by (source) | Select2 | `crm_escalation_sources` | Static list loaded in controller; admin CRUD under settings |
| Location | Select2 | `business_locations` | Preloaded dropdown filtered by `permitted_locations()` |
| Time to call | DateTime picker | — | Optional |
| Invoice num. | Select2 AJAX | `transactions` | New: `GET /crm/escalations/search-invoices?q=` — `type=sell`, `status=final`, search `invoice_no`; optionally filter by selected `contact_id` |
| Comment | Textarea | — | |
| Observer | Select2 AJAX | `users` | Same as Employee search |
| Observer Comment | Textarea | — | Shown when `observer_id` set or user has observe permission |
| Auditor | Select2 AJAX | `users` | Same as Employee search |

### List page filters (index)

| Filter | Control |
|--------|---------|
| Customer | Select2 AJAX customers |
| Employee | Select2 users |
| Observer | Select2 users |
| Auditor | Select2 users |
| Location | Select2 locations |
| Source | Select2 sources |
| Status | Select2 statuses |
| Date range | escalated_at range |
| Callback due | toggle: `callback_at <= now()` and status not closed |

### Index DataTable columns

Reference #, Date/Time, Customer, Phone, Description, Source, Location, Invoice #, Employee, Observer, Auditor, Status, Callback, Actions (view / edit / change status).

---

## Permissions & Roles

Registered via migration (same pattern as `crm_schedules` permissions):

| Permission | Who | Capability |
|------------|-----|------------|
| `crm.escalation.view_all` | Manager, Admin | See all escalations for the business |
| `crm.escalation.view_own` | Employee | See escalations where `employee_id = me` OR `observer_id = me` OR `auditor_id = me` |
| `crm.escalation.create` | Front-line staff | Create escalations |
| `crm.escalation.update` | Employee, Observer | Edit fields they own; observer can edit `observer_comment` |
| `crm.escalation.delete` | Admin | Soft-delete |
| `crm.escalation.assign_observer` | Supervisor | Set/change `observer_id` |
| `crm.escalation.assign_auditor` | Admin / QC lead | Set/change `auditor_id` |
| `crm.escalation.close` | Auditor, Admin | Move to `closed` |
| `crm.escalation.manage_sources` | Admin | CRUD escalation sources |

**Suggested role mapping**

| Role | Permissions |
|------|-------------|
| Employee | `view_own`, `create`, `update` (own records) |
| Observer / Supervisor | `view_all`, `update`, `assign_observer` |
| Auditor | `view_all`, `update`, `close` |
| Admin | All |

---

## Status Lifecycle

```
open → in_review → callback_scheduled → resolved → closed
                  ↘ cancelled
```

| Status | Meaning | Typical trigger |
|--------|---------|-----------------|
| `open` | Newly logged, not yet reviewed | On create |
| `in_review` | Observer is reviewing | Observer assigned or opens case |
| `callback_scheduled` | Follow-up call planned | `callback_at` is set |
| `resolved` | Issue handled; pending audit | Employee/observer marks resolved |
| `closed` | Auditor approved; case archived | Auditor closes |
| `cancelled` | Invalid / duplicate entry | Admin cancels |

Status changes write to `crm_escalation_status_logs` and Spatie `activity_log`.

---

## Full Flow (Step by Step)

### Phase 1 — Intake (Employee)

1. Staff receives a complaint (Instagram DM, phone call, in-store, etc.).
2. User opens **CRM → Escalations → Add**.
3. System pre-fills **Employee** with the logged-in user (editable if logging on behalf of someone).
4. User searches and selects **Customer** → **Phone** auto-fills from `contacts.mobile`.
5. User sets **Date/Time** (defaults to now).
6. User enters **Description** (e.g. “wrong order”).
7. User selects **Taken by** source (e.g. Instagram).
8. User selects **Location** (e.g. Head Office).
9. If related to a sale, user searches **Invoice num.** and links the transaction.
10. User enters initial **Comment**.
11. Optionally assigns **Observer** and sets **Time to call**.
12. User saves → status = `open`, reference # generated (`ESC-00001`), toast success.

### Phase 2 — Supervision (Observer)

13. Observer sees the case in **Escalations** list (filter: assigned to me / all).
14. Observer opens the record, reviews customer, invoice, and description.
15. Observer updates status to `in_review`.
16. Observer adds **Observer Comment** (handling instructions, outcome of investigation).
17. If a callback is needed, observer or employee sets **Time to call** → status `callback_scheduled`.
18. After the callback / fix, observer or employee sets status to `resolved`.

### Phase 3 — Audit (Auditor)

19. Auditor receives cases in `resolved` status (filter: pending audit).
20. Auditor reviews full thread: comment, observer comment, linked invoice.
21. Auditor adds final notes if needed.
22. Auditor sets status to `closed` → case locked for editing except by admin.

### Phase 4 — Reporting & follow-up

23. Managers use filters: by location, source, date range, open vs closed.
24. **Callbacks due** widget/list shows escalations where `callback_at <= now()` and status ∉ (`closed`, `cancelled`).
25. Contact profile tab (optional) shows escalation history for that customer.
26. All status changes appear in activity log for compliance.

### Edge cases

| Scenario | Handling |
|----------|----------|
| Customer not in system | Quick-add customer modal (reuse contact create) then select in form |
| No invoice | Leave `transaction_id` null |
| Duplicate escalation | Mark as `cancelled` with note; link to original in comment |
| Wrong source | Admin adds new source under Settings; inactive old ones |
| Permission denied | 403 on controller; hide menu item if no view permission |

---

## Screens & Routes

| Route | Method | Action |
|-------|--------|--------|
| `/crm/escalations` | GET | Index (DataTable) |
| `/crm/escalations/create` | GET | Create form |
| `/crm/escalations` | POST | Store |
| `/crm/escalations/{id}` | GET | Show |
| `/crm/escalations/{id}/edit` | GET | Edit form |
| `/crm/escalations/{id}` | PUT | Update |
| `/crm/escalations/{id}` | DELETE | Soft delete |
| `/crm/escalations/{id}/status` | POST | Change status + log |
| `/crm/escalations/search-users` | GET | AJAX user search |
| `/crm/escalations/search-invoices` | GET | AJAX invoice search |
| `/crm/escalation-sources` | resource | Admin CRUD for “taken by” channels |

**Menu:** CRM submenu → **Escalations** (icon: `fa-exclamation-triangle`), gated by `crm.escalation.view_all` or `crm.escalation.view_own`.

**Translations:** `Modules/Crm/Resources/lang/en/lang.php` keys under `escalation_*`.

---

## Implementation Plan

| Step | Task |
|------|------|
| 1 | Migrations: `crm_escalation_sources`, `crm_escalations`, `crm_escalation_status_logs` |
| 2 | Seed default sources; permissions migration |
| 3 | Models + relations + `EscalationUtil` (reference number, status transitions) |
| 4 | `EscalationController` + `EscalationSourceController` |
| 5 | Blade views: index, create, edit, show, partials |
| 6 | JS: DataTable, Select2 AJAX hooks, SweetAlert2 delete confirm, toasts |
| 7 | Routes in `Modules/Crm/Routes/web.php` |
| 8 | CRM dashboard widget: open escalations + callbacks due |
| 9 | Optional: contact view tab for customer escalation history |
| 10 | Manual test checklist (see below) |

### Test checklist

- [ ] Create escalation with customer search + phone auto-fill
- [ ] Link invoice via AJAX search
- [ ] Observer can comment and change status
- [ ] Auditor can close resolved case
- [ ] `view_own` user cannot see unrelated escalations
- [ ] Location filter respects `permitted_locations()`
- [ ] Callback due filter works
- [ ] Soft delete + activity log entries created

---

## Sample Record Mapping

Spreadsheet row → database row:

| Excel | DB Value |
|-------|----------|
| employee = mohamed | `employee_id` = User where name matches “mohamed” |
| Customer Name = aya shoaib | `contact_id` = Contact where `name` = “aya shoaib” |
| Phone Number = 1551018628 | `phone` = `1551018628` |
| Date/Time = 2026-12-04 18:00 | `escalated_at` = `2026-12-04 18:00:00` |
| Description = wrong order | `description` = `wrong order` |
| taken by = Instagram | `source_id` = EscalationSource `Instagram` |
| Location = head office | `location_id` = BusinessLocation `head office` |
| time to call = empty | `callback_at` = `NULL` |
| invoice num. = 16216 | `transaction_id` = Transaction where `invoice_no` = `16216` |
| Comment = رد غير لائق … | `comment` = *(Arabic text)* |
| Observer = ahmed yasser | `observer_id` = User “ahmed yasser” |
| Observer Comment = empty | `observer_comment` = `NULL` |
| auditor = Shazly | `auditor_id` = User “Shazly” |
| *(implicit)* | `status` = `open`, `reference_no` = `ESC-00001` |

---

## Related existing code (reuse)

| Need | Existing artifact |
|------|-------------------|
| Customer AJAX search | `ContactController@getCustomers` — `/contacts/customers` |
| Invoice listing pattern | `ScheduleController@getInvoicesForFollowUp` |
| CRM module structure | `Modules/Crm` schedules / call logs |
| Reference numbers | `reference_counts` + `TransactionUtil::generateReferenceNumber` pattern |
| Activity logging | Spatie `activity_log` |
| UI patterns | Select2, DataTables, SweetAlert2, toastr — `Modules/Crm/Resources/assets/js/crm.js` |

---

*Document version: 1.0 — based on `extracted_from_image.xlsx` (June 2026)*
