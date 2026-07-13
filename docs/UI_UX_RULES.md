# UI/UX Rules

## Layout

- Preserve existing app UI.
- Do not change `main-child` classes.
- Do not make `main-child` scrollable.
- Header/search-header should not scroll.
- Inner content areas should scroll.
- Use existing app components, classes, tables, modals, and context menus.

## Feedback

- Normal CRUD success/error uses toast only.
- Top-right notifications are only for real persistent/system notifications.
- Do not duplicate normal flash messages as top-right notifications.
- Do not use browser `alert()` for new work.

## Update Available

- Update available uses the app modal.
- Do not reintroduce a top warning box.
- Update Now, Details, Later/Close must keep working.
- Mandatory update behavior and Windows launcher handoff must remain intact.

## Pending UX Cleanup

Some older JS still uses browser `alert()`. Cleanup is pending and should be handled separately from branch stabilization.
