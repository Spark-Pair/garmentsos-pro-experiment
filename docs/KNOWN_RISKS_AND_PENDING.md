# Known Risks and Pending Work

## Must Review Before Commit

- `public/images` and `public/favicon.ico` changes look unrelated to branch-system work.
- `2026_07_11_000006_complete_branch_module_registry_and_dispatch_scope.php` appears untracked while migration status says it ran.
- Confirm no `.env` or secrets are tracked.

## Pending Manual Verification

- Full browser smoke tests.
- Developer Branch Details spacing and scroll.
- Branch selector single/multi behavior after Back/Refresh.
- Report totals/branding across Main, Play Zone, and multi-branch.
- Serial display consistency across toast/index/show/print.

## Pending Cleanup

- Some older JS still uses browser `alert()`.
- Browser-only UX paths should be converted to existing toast/modal patterns in a separate pass.

## Release Blocker

Do not release before manual smoke tests pass.
