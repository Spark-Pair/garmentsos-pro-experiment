# Auto Created Records Rules

Auto-created records must not become wrongly global.

## Required Inheritance

- Payment program from order inherits order branch.
- Invoice from shipment inherits shipment branch.
- Invoice from order inherits order/current invoice branch.
- Voucher-created customer/supplier payments inherit voucher/current branch appropriately.
- Utility bill mark-paid related record branch must be correct.
- Daily ledger related records branch must be correct.
- Attendance salary generation branch must be correct.
- Fabric issue/return branch must be correct.
- CR/DR/Bilty/Cargo/Shipment related records must keep branch context where applicable.

## Implementation Rule

Use `ModuleBranchService::branchIdForCreate()` or `assignBranchOnCreate()` unless a source record branch should explicitly be inherited.
