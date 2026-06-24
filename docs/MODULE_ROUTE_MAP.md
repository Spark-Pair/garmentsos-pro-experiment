# Module Route Map

This document tracks reviewed module route enforcement for GarmentsOS PRO.

## Current Enforced Modules

- `articles`: Article resource routes, image update, and rate action.
- `customers`: Customer resource routes only.
- `suppliers`: Supplier resource routes and the direct supplier category update action.

Missing local module settings preserve current behavior because module defaults remain enabled. When licensing is enabled and a license declares allowed modules, license restrictions take precedence over local developer settings.

## Current Sidebar Coverage

- `articles`, `customers`, and `suppliers` are hidden from both mobile and desktop sidebar menus when their effective module state is disabled.
- Sidebar hiding is only a usability layer. Direct URL access is blocked by `moduleEnabled:*` middleware for the reviewed routes above.

## Delayed Shared Routes

Shared helper, dropdown, report, statement, and session-layout routes are intentionally not module-blocked yet. Examples include statement helpers, report helper routes, category/data helpers, and cross-module AJAX routes.

These routes can be used by more than one workflow, so they must be reviewed and tested separately before enforcement.

## Delayed High-Risk Modules

Order, invoice, payment, voucher, shipment, stock/fabric, production, ledger, statement, report, and finance routes are intentionally delayed. They affect calculations, balances, reports, and operational workflows, so enforcement must expand module-by-module only after focused route-map review and tests.

## Safety Rules

- Do not wrap the whole application in module middleware.
- Do not block developer/admin tooling through business module toggles.
- Do not add migrations for route-map expansion.
- Do not alter business calculations, balances, stock, payments, invoices, orders, reports, or statements while expanding module visibility.
