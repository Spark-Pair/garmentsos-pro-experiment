# GarmentsOS Pro – User Guide (Quick)

This is a practical guide to help new users and accounting teams understand daily workflows quickly.

## 1) Core Navigation
- Use the sidebar to switch modules (Customers, Suppliers, Orders, Payments, Vouchers, Reports).
- The top action bar on most pages includes:
  - Search
  - Layout toggle (grid/table)
  - Reset sort
  - Print where applicable

## 2) Search & Filters
- Each listing page supports search and filters. Enter a keyword and results update accordingly.
- Use date range filters where available to limit results.

## 3) Forms & Modals
- Add/Edit actions open modals (or forms) with required fields clearly labeled.
- Select inputs support quick typing and arrow navigation.
- Validation errors appear below inputs in red.

## 4) Menu Modal Shortcuts
- Open the menu modal with `Ctrl + Space`.
- From search input, press `Enter` to focus the first card.
- Use arrow keys to move between cards.
- Press `Enter` to open sub‑menu items.
- Use arrow keys inside the sub‑menu.
- Press `Esc`:
  - closes the sub‑menu if open
  - otherwise closes the modal

## 5) Home Shortcut
- `Shift + Space` navigates to Home.

## 6) Accounting Use‑Cases
- Customer/Supplier balances are visible in Payments and Programs.
- Vouchers reflect both cash/bank flows and program payments.
- Reports consolidate balances, daily ledgers, and statement summaries.
- For accountants:
  - Use **Reports > Statement** for customer/supplier/bank statements.
  - Use **Daily Ledger** to review day‑wise cash/bank movement.
  - Use **Vouchers** to reconcile cash/bank with program transactions.

## 7) Read‑Only Mode
- If the subscription expires, the app enters read‑only mode.
- You can view all data, but cannot add/update/delete/mark paid.
- A warning banner appears at the top of the app.

## 8) Permissions & Roles
- Permission mapping lives in:
  - `docs/PERMISSIONS.md`
  - `docs/ROLE_MATRIX.md`
- To change access:
  - Update the role mapping in code or admin data.
  - Verify the UI visibility using Blade `@can`/`@if`.
  - Always keep server‑side checks in controllers/middleware.

## 9) If Something Looks Wrong
- Refresh with the reload button.
- Ensure filters are cleared.
- Confirm your role has access (see `docs/PERMISSIONS.md`).
