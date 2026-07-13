# Release Safety Checklist

Do not release/tag until this checklist passes.

- Run `git status`.
- Review unrelated-looking `public/images` and `public/favicon.ico` changes.
- Run `php artisan migrate:status`.
- Confirm untracked migrations are included, especially `2026_07_11_000006`.
- Run `php -l` on changed PHP files.
- Run `php artisan view:cache`.
- Run `php artisan view:clear`.
- Run `php artisan route:list --json`.
- Run `node --check` on changed JS files.
- Run `git diff --check`.
- Run browser smoke tests from [Manual Browser Tests](MANUAL_BROWSER_TESTS.md).
- Check `storage/logs/laravel.log` for critical new errors.
- Confirm no `.env` or secrets are tracked.
- Confirm no already-run migration was rewritten.
- Confirm no old document numbers were renamed automatically.
- Do not release/tag until manual smoke passes.
