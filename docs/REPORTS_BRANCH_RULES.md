# Reports Branch Rules

These reports use the global branch switcher context:

- `reports_statement`
- `reports_article`
- `reports_pending_payments`
- `reports_physical_quantity`

## Rules

- Use selected branch IDs from global branch context.
- Do not depend on old in-form branch selectors.
- Single branch report uses selected branch data and selected branch branding.
- Multi/all reports use selected branches only, Main Branch branding, and selected branch labels.
- Legacy `NULL branch_id` records are included only when Main Branch is selected.
- AJAX endpoints must use the same branch context.

## Statement Report

The statement 500 regression was fixed by passing `$includeNullBranchRecords` into the Customer statement adjustment closure.

Verify:

- Main only statement includes legacy null records.
- Play Zone only statement excludes legacy null records.
- Main + Play Zone includes null records because Main is selected.
