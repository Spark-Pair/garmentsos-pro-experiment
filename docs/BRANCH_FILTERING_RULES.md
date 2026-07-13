# Branch Filtering Rules

## shouldFilterRecords

`ModuleBranchService::shouldFilterRecords($moduleKey)` must require:

- Module is enabled.
- `record_filtering_enabled` metadata is true.
- A usable selected branch exists.
- Module/table has `branch_id` support.
- Module is considered filtering-ready/wired.

## applyScope

`applyScope($query, $moduleKey)` rules:

- If `shouldFilterRecords()` is false, return the query unchanged.
- If Main Branch is selected, include Main branch records and legacy `NULL branch_id` records.
- If non-main branch is selected, include selected branch records only.
- Do not include `NULL branch_id` records for non-main-only selection.

## applyRelatedScope

Related dropdown filtering happens only when both are true:

- Current working module is record-filtered.
- Related module is record-filtered.

Required examples:

- `vouchers` global + `customer_payments` branch-wise: show all eligible payments.
- `vouchers` branch-wise + `customer_payments` branch-wise: show selected branch payments only.
- `orders` branch-wise + `customers` global: show global customers.
- `orders` branch-wise + `customers` branch-wise: show selected branch customers.
