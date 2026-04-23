# SQLite → PostgreSQL Migration (Production Checklist)

This project currently uses SQLite (`database/database.sqlite`). For 20–25 office users + customer/supplier portals (concurrent writes), PostgreSQL is the safer “tension‑free” production DB.

## 0) What you need
- A PostgreSQL database (recommended: **managed Postgres**).
- Network access from your app server to Postgres (allowlist server IP if needed).
- PHP extension on the app server: `pdo_pgsql`.
- A migration tool on *some* machine that can reach both DBs:
  - Recommended: `pgloader` (copies SQLite → Postgres quickly and reliably).

## 1) Create Postgres (managed or self-hosted)
You will receive:
- `DB_HOST`, `DB_PORT`
- `DB_DATABASE`
- `DB_USERNAME`, `DB_PASSWORD`
- (optional) SSL requirement (often `sslmode=require`)

## 2) Prepare the app (no downtime yet)
On a staging server (or a new copy of production):
1. Install PHP pgsql driver:
   - Ubuntu/Debian: `sudo apt-get update && sudo apt-get install -y php8.1-pgsql`
2. Update `.env` (example):
   - `DB_CONNECTION=pgsql`
   - `DB_HOST=...`
   - `DB_PORT=5432`
   - `DB_DATABASE=...`
   - `DB_USERNAME=...`
   - `DB_PASSWORD=...`
   - If SSL required: set `DATABASE_URL=pgsql://USER:PASSWORD@HOST:PORT/DB?sslmode=require` (Laravel will use this if present).
3. Run:
   - `php artisan config:clear`
   - `php artisan config:cache`

## 3) Create schema in Postgres (empty database)
Run migrations against Postgres:
- `php artisan migrate --force`

This creates tables + indexes as your Laravel migrations expect.

## 4) Copy data from SQLite → Postgres (pgloader, data-only)
Install pgloader on the machine doing the migration:
- Ubuntu/Debian: `sudo apt-get update && sudo apt-get install -y pgloader`

Then run `pgloader` in **data-only** mode (so it loads into the already-migrated schema).

Example (replace placeholders):
```bash
pgloader <<'LOAD'
LOAD DATABASE
     FROM sqlite:///absolute/path/to/database.sqlite
     INTO postgresql://DB_USERNAME:DB_PASSWORD@DB_HOST:DB_PORT/DB_DATABASE
WITH data only, reset sequences, prefetch rows = 10000, batch rows = 5000, workers = 4;
LOAD
```

Notes:
- `reset sequences` is important so new inserts don’t conflict with existing IDs.
- For managed Postgres, you may need `?sslmode=require` on the connection URL.

## 5) Cutover (short downtime window)
On production app server:
1. Put app in maintenance mode:
   - `php artisan down`
2. Take a final SQLite backup:
   - `sqlite3 database/database.sqlite ".backup 'storage/app/db-final.sqlite'"`
3. Run final pgloader sync (if you didn’t stop writes earlier).
4. Switch `.env` to Postgres and cache config:
   - `php artisan config:clear && php artisan config:cache`
5. Bring app back:
   - `php artisan up`

## 6) Verification checklist
- Login works (office + customer/supplier)
- Create order, create voucher, create expense
- Statements generate correctly
- Background pages load (index filters etc.)

Quick DB sanity counts (run on Postgres):
```sql
select 'users' as t, count(*) from users
union all select 'customers', count(*) from customers
union all select 'suppliers', count(*) from suppliers
union all select 'orders', count(*) from orders;
```

## 7) Rollback plan
If anything fails:
1. Switch `.env` back to SQLite (`DB_CONNECTION=sqlite`, `DB_DATABASE=...`)
2. `php artisan config:clear && php artisan config:cache`
3. Restore SQLite backup if needed

