# Installment Credit Module

First-party UltimatePOS module that replaces the Excel **Credit Analyses** workbook: installment companies owe Games Spot after BNPL sales.

## Install (after git pull)

1. Confirm `modules_statuses.json` has `"InstallmentCredit": true`.
2. Run:

```bash
php artisan module:migrate InstallmentCredit --force
```

Migration `…_mark_installment_credit_installed` sets `installmentcredit_version` in the `system` table, seeds default companies, and grants Admin role permissions. **Without that row the sidebar menu will not appear.**

3. Clear caches:

```bash
php artisan optimize:clear
```

4. Log out/in (or hard refresh). Sidebar: **Installment Credit**.

### If the menu is still missing

- Open as **superadmin**: `/installment-credit/install` then submit Install.
- Or check DB: `SELECT * FROM system WHERE \`key\` = 'installmentcredit_version';`
- Role must have `installment.view` (or be superadmin). Re-save the role under User Management → Roles if needed.

## Setup for cashiers

1. **Installment Credit → Companies** — companies use **free** `custom_pay_*` slots only. Existing labels (InstaPay, Vodafone Cash, Wallet, …) are never overwritten.
2. If companies were wrongly mapped onto 1–3, run migrate (remap migration) or click **Fix payment slot conflicts** on the companies page.
3. **Business Location → Payment accounts** — enable the installment custom payment methods (e.g. Custom Payment 5–11) for POS.
4. Grant role permissions as needed.

## Flows

| Action | Result |
|--------|--------|
| POS sale paid with mapped company method | Sale paid; **no** cashbook credit; pending receivable created |
| Settle pending rows | Credits deposit account for actual received; BNPL fee expense |
| Delete/void BNPL payment | Pending receivable cancelled |
| Import CSV | Open balances only |

## Menu

`Installment Credit` → Dashboard, Pending, Companies, Settlements, Reports, Import.

## Key paths

- Module: `Modules/InstallmentCredit/`
- Skip cashbook on BNPL sale: `app/Listeners/AddAccountTransaction.php`
