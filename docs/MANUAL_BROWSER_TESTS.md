# Manual Browser Tests

Run these before commit/release.

## 1. Branch Selector

- Open `payment-programs`.
- Open `reports/article`.
- Open `reports/statement`.
- Open `reports/pending-payments`.
- Open `reports/physical-quantity`.
- Open `physical-quantities`.
- Open vouchers with vouchers global/disabled.
- Confirm selector shows/hides according to module settings.
- Confirm branch switching does not submit page forms.

## 2. Isolation

Test Main vs Play Zone for:

- articles
- orders
- invoices
- payment_programs
- bank_accounts
- daily_ledger
- utility_bills
- bilties
- cr
- dr

## 3. Vouchers

- Vouchers global: all vouchers show.
- Vouchers branch-wise: selected branch vouchers show only.

## 4. Dropdowns

- Vouchers global + `customer_payments` branch-wise: all payments show.
- Vouchers branch-wise + `customer_payments` branch-wise: selected payments only.
- Orders branch-wise + customers global: global customers show.
- Orders branch-wise + customers branch-wise: selected customers only.

## 5. Reports

- Statement Main only.
- Statement Play Zone only.
- Statement Main + Play Zone.
- Article report.
- Pending payments.
- Physical quantity.

## 6. Serial

- Order toast, index, show, and print show the same stored number.
- Invoice toast, index, show, and print show the same stored number.
- Voucher toast, index, show, and print show the same stored number.
- Old base format is preserved.

## 7. UX

- CRUD uses toast only.
- Update available appears as app modal.
- Developer pages scroll correctly.
