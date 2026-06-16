# Client-Specific Customization

GarmentsOS PRO should remain one global codebase while supporting client-specific installs.

## Strategy

Use these layers in order:

1. Client profile/config for identity and install metadata
2. Labels for wording
3. Feature flags for access control
4. Workflow config for process differences
5. Module manifest for package ownership and pruning
6. Client overlays only as a last resort

## Global Codebase

Global fixes and shared improvements should live in the main app. Avoid copying whole modules for one client.

Good example:

- Add Invoice Quick Print globally.
- Client A receives it with Article labeled Design.
- Client B receives it with default Article wording.
- Client A can keep Shipments disabled.

## Labels

Use labels when only text changes:

- Article -> Design
- Supplier -> Vendor
- Customer -> Party
- Invoice -> Bill
- Shipment -> Delivery

Labels should not change table names, route names, model names, or business logic.

## Feature Flags

Use feature flags to hide and block access.

Example:

Client A has Shipments disabled. Sidebar should hide Shipments, and shipment routes should be blocked once route guards are applied.

Feature flags do not remove code from packages by themselves.

## Module Manifest

Module manifests will later define code ownership:

- routes
- controllers
- models
- views
- JavaScript
- migrations
- public assets
- dependencies
- reports and print/export hooks

The release builder can use this manifest to prune modules from client packages.

## Dependency Rules

Modules may depend on each other:

- invoices may depend on orders or shipments
- stock depends on articles, invoices, shipments, sales returns, and physical quantities
- reports read many modules
- payments depend on customers, suppliers, bank accounts, employees, and vouchers

Disabling or pruning a module requires dependency checks. A disabled module must not leave dashboard cards, routes, JS calls, or reports pointing at missing code.

## Overlays

Use overlays only for true client-specific code that cannot be represented by labels, features, workflows, or module config.

Overlay rules:

- overlays must be explicit
- no unrelated client code in another client package
- overlays must be tested against the base module
- overlays must not hide global security fixes

## Example

Client A:

- `APP_CLIENT=client_a`
- Article label: Design
- Shipments feature disabled
- shipment module pruned later

Client B:

- `APP_CLIENT=client_b`
- default Article label
- Shipments enabled

Global change:

- Invoice Quick Print added to invoice module

Expected result:

- Client A gets Invoice Quick Print, sees Design wording, and still has no Shipments access.
- Client B gets Invoice Quick Print with default wording and Shipments enabled.
