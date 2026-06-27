# Module Route Map

This document tracks reviewed module route enforcement for GarmentsOS PRO.

## Current Enforced Modules

- `articles`: Article resource routes, image update, and rate action.
- `customers`: Customer resource routes only.
- `suppliers`: Supplier resource routes and the direct supplier category update action.
- `reports`: Reviewed pure report routes only: statement, statement record details, pending payments, article report, and physical quantity report.
- `rates`: Direct rate management resource routes only.

Missing local module settings preserve current behavior because module defaults remain enabled. When licensing is enabled and a license declares allowed modules, license restrictions take precedence over local developer settings.

## Current Sidebar Coverage

- `articles`, `customers`, `suppliers`, `reports`, and the desktop Rates user-menu link are hidden from their reviewed sidebar locations when their effective module state is disabled.
- Reports sidebar coverage includes the desktop Reports group and the customer/supplier portal Statement links.
- Sidebar hiding is only a usability layer. Direct URL access is blocked by `moduleEnabled:*` middleware for the reviewed routes above.

## Delayed Shared Routes

Shared helper, dropdown, report, statement, and session-layout routes are intentionally not module-blocked yet. Examples include `reports.statement.get-names`, statement type setters, physical quantity report type setters, category/data helpers, and cross-module AJAX routes.

Rates enforcement intentionally does not guard `setups`, the article workflow `add-rate` action, shared helper routes, session setters, or saved-rate usage in article/order/invoice/report calculations. Disabling `rates` blocks direct rate management pages/actions only.

These routes can be used by more than one workflow, so they must be reviewed and tested separately before enforcement.

## Delayed High-Risk Modules

Order, invoice, payment, voucher, shipment, stock/fabric, production, ledger, statement-adjustment, report helper, and finance routes are intentionally delayed. They affect calculations, balances, reports, and operational workflows, so enforcement must expand module-by-module only after focused route-map review and tests.

## Safety Rules

- Do not wrap the whole application in module middleware.
- Do not block developer/admin tooling through business module toggles.
- Do not add migrations for route-map expansion.
- Do not alter business calculations, balances, stock, payments, invoices, orders, reports, or statements while expanding module visibility.
