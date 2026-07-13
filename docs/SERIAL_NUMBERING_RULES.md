# Serial Numbering Rules

## Permanent Rules

- Existing old records are not renamed.
- Old base number format is preserved.
- New branch-aware records only prepend `branchPrefix-docIdentity-baseNumber`.
- Toast, index, show, edit, and print must all display the same stored number.
- Duplicate checks use the final stored number.

## BranchSerialService

`BranchSerialService` strips known branch/doc prefixes before calculating the next base number. This keeps the old base format stable.

Branch/doc prefix is applied only when:

- Module is record-filtered.
- Branch serial prefix is supported.
- A branch is available.

## Modules To Verify

- orders
- invoices
- vouchers
- productions/tickets
- customer_payments
- supplier_payments
- payment_programs
- bilties
- cargos
- shipments
- cr
- dr
- bank_accounts if serial generated
- daily_ledger if generated
- utility_bills if generated
