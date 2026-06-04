# GarmentsOS PRO – App Documentation (Roman Urdu)

*Last updated: 22-Apr-2026 (Asia/Karachi)*

## 1) Ye app kis cheez ke liye hai?
GarmentsOS PRO (repo name: `garmentsos-pro`) ek **garments business management web app** hai jo daily operations ko manage karta hai — customers/suppliers, orders, stock/physical quantity, shipments, invoices, payments, vouchers, reports/statements, production, utility bills, aur ledger type entries.

Tech stack:
- Backend: Laravel 10 (PHP 8.1+)
- Frontend: Blade + Vite + Tailwind + jQuery based UI helpers
- Realtime (optional): Pusher (feature flag se)
- Default DB: SQLite (`database/database.sqlite`)

## 2) Ye app kin logon ke liye hai? (Roles)
App role-based hai. Current codebase me roles ka usage controllers aur sidebar menu me hota hai:

### Back-office roles (internal staff)
- `developer`, `owner`, `admin`, `manager`, `accountant`, `store_keeper`, `guest` (app me different pages par allow list different hai)

### Portal roles (external)
- `customer`: apne orders create + apna data/statement dekh sakta hai (scoped by logged-in user)
- `supplier`: apne expenses + apni productions + apna statement dekh sakta hai (scoped by logged-in user)

**Important:** customer/supplier portal tab kaam karta hai jab `customers.user_id` / `suppliers.user_id` me us user ka `id` linked ho.

## 3) High-level modules (kya kya hai)
Neeche app ke main modules ka overview:

### A) Auth + Session control
- Login username/password se hota hai (custom flow).
- “Single active session” enforcement: agar same user dusri jagah login kare to purani session expire.
- Subscription expiry middleware: expiry ke baad **read-only mode** on ho jata hai (write actions block).

### B) Master data / Setup
- `setups` module usually dropdown options (banks, cities, worker types, supplier categories, etc.) store karta hai.

### C) Users
- Users create/manage + status + password reset.
- Theme preference (`light/dark`) aur layout preference per-route store hota hai.

### D) Customers
- Customer record + city + phone/address etc.
- Customer statement generation (general/summarized/detailed).

### E) Suppliers
- Supplier record + categories + expenses relation.
- Supplier statement generation + balance calculations.

### F) Articles / Stock / Physical Quantity
- Articles catalog (product master).
- Physical quantities / packets based tracking (stock side).

### G) Orders
- Orders create: date choose → customer choose → articles select → quantities → net amount.
- Order articles pivot table store hota hai.
- Customer portal me orders **sirf us customer** ke (self-only).

### H) Shipments + Invoices
- Orders se shipments/invoices flows (repo me controllers/views maujood).
- Invoices statement me bills side me reflect hoti hain.

### I) Payments (Customer / Supplier)
- CustomerPayment: cheque/slip/cash type records.
- SupplierPayment: voucher/program/self-cheque/ATM/etc methods.
- Payment clear records (bank accounts me clearing logic).

### J) Vouchers
- Voucher create/update: supplier vouchers + self-account vouchers.
- Methods: Cash, Cheque, Slip, Payment Program, Purchase Return, Self Cheque, ATM, Adjustment.
- Voucher payments SupplierPayment records se link hoti hain.

### K) CR / DR
- CR flow me voucher ke unpaid/uncleared cheques/slips select hote hain (return payments), phir new payments add.
- Return payments par `is_return` flags set hotay hain (SupplierPayment + related CustomerPayment).

### L) Daily Ledger
- Deposit/Use type daily ledger entries.

### M) Utility accounts + Utility bills
- Utility accounts maintain.
- Utility bills list/create + mark paid.

### N) Reports / Statements
- Statement report (customer/supplier/bank account).
- Statement types: General / Summarized / Detailed.
- Portal roles ke liye statement auto-scoped (customer/supplier apna hi).

### O) Balance Entries (Statement Adjustments)
- “Opening Balance” + “Adjustment” entries.
- Transaction direction: `plus` / `minus` (UI me Debit/Credit label).
- Ye entries statement calculations me net add/subtract hoti hain.

### P) DB Backup (SQLite-only route)
- `/backup-db` route sqlite file download karne deta hai (restricted roles).

## 4) Key flows (simple explanation)

### Order create flow
1. Date select
2. Customer select
3. Articles select + quantity set
4. Save → order + order_articles create

Customer portal me:
- customer list me sirf apna naam show hota hai
- store time par `customer_id` server-side enforce hota hai

### Statement flow (Customer/Supplier)
1. Category + Name select (portal me category/name lock)
2. Date range set
3. App opening balance calculate karti hai + transactions list (invoices/payments/adjustments)

### Balance entries (Opening Balance / Adjustment)
- Entry type `adjustment` ho to date user select kar sakta hai
- Entry type `opening_balance` ho to date auto set hoti hai: selected record ki “first transaction date” se 1 din pehle (agar transaction date mil jaye)

## 5) Database (DB) – kya hai aur kahan hai?

### Default DB
- SQLite file: `database/database.sqlite`

SQLite pros:
- Simple, single file backup/restore
- Small scale app ke liye ok

SQLite cons:
- Multiple concurrent writes me locking issues aa sakte hain (busy office usage / heavy traffic par)

### Tables (migrations se)
Migrations se ye main tables appear hote hain (summary):
- `users`, `user_sessions`, `password_reset_tokens`, `personal_access_tokens`, `failed_jobs`
- `customers`, `suppliers`, `bank_accounts`
- `articles`, `physical_quantities`
- `orders`, `order_articles`
- `shipments`, `shipment_articles`
- `invoices`, `invoice_articles`
- `customer_payments`, `supplier_payments`, `payment_clears`, `payment_programs`
- `expenses`
- `productions`
- `utility_accounts`, `utility_bills`
- `c_r_s`, `d_r_s`
- `sales_returns`
- `statement_adjustments`
- `notifications`
- `setups` (master data)

*(Exact column details aap migrations folder se dekh sakte ho.)*

### Recommended production DB
Agar aap multiple staff + heavy usage expect kar rahe ho:
- MySQL / MariaDB / Postgres move karna recommended hai.

## 6) Hosting / Deployment – kahan host karna hai aur kitna kharcha?
Pricing time ke saath change hoti rehti hai; neeche costs **approx** hain (as of Apr-2026) aur aap ke traffic/users pe depend karte hain.

### Option A: VPS (Recommended for control + value)
Best jab aap ko full control chahiye.

Typical requirements:
- 1 vCPU / 1–2GB RAM (small office) → start
- 2 vCPU / 4GB RAM (better) → recommended
- SSD storage 40–80GB

Monthly cost estimates (server only):
- DigitalOcean droplet: starting ~ **$4/mo** (small), common ~ $6–$12/mo (depending plan). (Official DO pricing pages)
- Hetzner Cloud: CPX22 after Apr-2026 adjustment around **€7.99/mo**; smaller plans cheaper. (Official Hetzner docs)
- AWS Lightsail: small bundles around **$5–$10/mo** class.

Extra monthly costs:
- Domain: ~ **$10–$20/year** (≈ $1–$2/mo average)
- Backups/storage: $1–$5/mo (provider snapshots / object storage)
- Transactional email (optional): $0–$15/mo (volume pe depend)
- Realtime (optional): Pusher ka cost plan pe depend karta hai; agar enabled hai to Pusher app keys/config chahiye.

One-time costs:
- Initial setup (agar khud karte ho): $0
- Agar DevOps/engineer se setup karwate ho: one-time service fee (varies)

### Quick budget examples (rough)
> PKR conversion exchange rate pe depend karti hai (aap jis din pay karenge).

- **Small office (internal staff, low traffic):**
  - VPS: $6–$12/mo
  - Backups: $1–$3/mo
  - Domain: ~$1–$2/mo (yearly)
  - Total: **~$8–$17/mo**

- **Medium office (better performance, more users):**
  - VPS: $12–$25/mo
  - Backups: $2–$6/mo
  - Total: **~$14–$31/mo**

### Option B: Managed Laravel hosting stack (easier, thora mehnga)
Example: Laravel Forge + VPS:
- Forge subscription + VPS cost separate.
- Benefit: deployments, SSL, queues, scheduling easy.

### Option C: Shared hosting / cPanel (cheapest, lekin limitations)
Laravel chal sakta hai agar:
- PHP 8.1+, composer access, correct document root (`public/`)
- Vite build locally karke `public/build` upload

Issues:
- Performance + debugging + cron limitations
- SQLite file permissions / backups headache

### Region / Latency (Pakistan users)
Pakistan ke liye usually:
- Singapore / India (Mumbai) / UAE regions good latency dete hain.

## 7) DB + Backup strategy (production)

### SQLite backup (simple)
- Daily/Hourly copy of `database/database.sqlite`
- Offsite storage (S3/Backblaze/Wasabi) + retention (7/30 days)
- App me `/backup-db` route sqlite download karta hai (ensure only privileged roles)

**Note:** SQLite file currently repo me present hai (`database/database.sqlite`). Public repo me ye file kabhi commit na karein.

**Production stability tips (SQLite):**
- `WAL` mode + `busy_timeout` enable karein (concurrent usage me locking issues kam hotay hain).
- `foreign_keys=ON` ensure karein (SQLite me per-connection PRAGMA hota hai).
- Regular `VACUUM`/maintenance optional (jab DB grow ho).

### MySQL/Postgres backup (recommended for scaling)
- Automated daily dumps + binlog (optional)
- Managed DB service (provider managed) use kar sakte hain (extra monthly cost)

## 8) Environment / Setup (local or server)
High-level steps:
1. `composer install`
2. `.env` configure (APP_KEY, DB settings, etc.)
3. `php artisan key:generate`
4. `php artisan migrate --force`
5. `php artisan db:seed` (agar seeders use karne hain)
6. `npm ci && npm run build` (ya build locally)
7. Storage symlink: `php artisan storage:link`
8. Web server doc root: `public/`
9. Cron for scheduler (agar use ho): `* * * * * php artisan schedule:run`

## 9) Security / Operational notes
- `.env` ko public na karein.
- `APP_DEBUG=false` production me.
- HTTPS enable (Let’s Encrypt).
- SQLite file ko public folder se bahar rakhein (default sahi hai).
- Backup route ko tightly restrict rakhein.

## 10) Next improvements (agar aap chaho)
- Customer/Supplier portal ko aur polish: dedicated dashboards, limited menus, self-only views for invoices/payments etc.
- Move DB to MySQL/Postgres for concurrency.
- Formal permissions system (policies/gates) instead of scattered role arrays.
