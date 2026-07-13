# Installment Credit Module

First-party UltimatePOS module that replaces the Excel **Credit Analyses** workbook: installment companies owe Games Spot after BNPL sales.

## Install

1. Module is enabled in `modules_statuses.json` (`InstallmentCredit: true`).
2. Run migrations: `php artisan module:migrate InstallmentCredit --force`
3. Or visit `/installment-credit/install` as superadmin (sets `installmentcredit_version` + seeds companies).

Already applied on this environment if `system.installmentcredit_version` = `1.0`.

## Setup for cashiers

1. **Installment Credit → Companies** — defaults: Value, Maylo, Tru, Aman, Forsa, Seven, Sohoula (each mapped to `custom_pay_1`…`7`).
2. **Business Settings → Custom Labels** — set Custom Payment 1–7 labels to the company names.
3. **Business Location → Payment accounts** — enable those custom payment methods for POS.
4. Grant role permissions: `installment.view`, `companies`, `settle`, `reports`, `import`.

## Flows

| Action | Result |
|--------|--------|
| POS sale paid with mapped company method | Sale marked paid; **no** cashbook credit; pending receivable created |
| Settle selected pending rows | Credits deposit account for **actual received**; posts **BNPL Fees** expense for fee |
| Delete/void BNPL payment | Pending receivable cancelled |
| Import CSV | Open balances only (Excel pending rows) |

## Menu

`Installment Credit` → Dashboard, Pending, Companies, Settlements, Reports, Import.

## Key paths

- Module: `Modules/InstallmentCredit/`
- Skip cashbook on BNPL sale: `app/Listeners/AddAccountTransaction.php`
- Domain docs: `d:\DevMaestro\Work\gamessspot\CREDIT_ANALYSES_README.md`
