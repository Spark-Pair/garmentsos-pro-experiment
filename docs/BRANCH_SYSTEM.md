# Branch System

## Completed Behavior

- Main Branch is the base/default branch.
- Main Branch defaults all modules enabled.
- Developer can disable any module, including Main Branch modules.
- No modules are locked.
- Branch Module Settings show configured and detected modules.
- Record Filtering is a real checkbox saved in metadata.
- Global route-aware branch switcher renders from `resources/views/app.blade.php`.
- Hidden branch preference form is outside page forms.
- Individual pages should not manually include branch selectors.

## Selector Rules

The global selector shows when:

- Route resolves to a module key.
- Module is enabled.
- Branch switching is enabled.
- User has more than one usable branch.

The selector hides when:

- Route resolves `null`.
- Module is disabled.
- Branch switching is disabled.
- Only one usable branch exists.

## Forms

- Branch switch buttons must be `type="button"`.
- Branch switching must not submit create/edit/report forms.
- Hidden branch preference form must remain outside page forms.
