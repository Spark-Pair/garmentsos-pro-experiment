# Security Guide

This file outlines practical steps to keep the application secure in production and to reduce risk when the source code is opened on a client PC.

## 1) Server‑Side Is The Source Of Truth
- All permissions and restrictions **must** be enforced in controllers/middleware (never only in JS).
- Read‑only mode is enforced by `SubscriptionExpiry` + `ReadOnlyMode` middleware.
- UI hiding is only a convenience; the backend still blocks write actions.

## 2) Protect Secrets
- Keep `.env` **out of version control**. Only share `.env.example`.
- Never expose keys in Blade or JS unless absolutely required.
- For public keys (e.g., Pusher), consider rotating periodically.

## 3) Disable Debug In Production
- `APP_DEBUG=false`
- `APP_ENV=production`
- Restrict error output to logs only.

## 4) Harden Authentication
- Enforce strong passwords and rate‑limit login attempts.
- Keep sessions short and regenerate on login.
- Ensure `SESSION_SECURE_COOKIE=true` in HTTPS environments.

## 5) Lock Down File Access
- Storage paths should **not** be publicly browsable.
- Use signed/temporary URLs for file access when needed.

## 6) Source Code On Client PC
If the client insists on keeping code locally:
- Never store real production secrets on that machine.
- Provide a **limited** `.env` with dummy keys for testing.
- Use role‑based access to restrict actions in the UI **and** on server.
- Build assets before delivery (`npm run build`) and ship only `/public` if possible.
- Prefer hosting on your own server so users do not need source code locally.

## 7) Audit Checklist
- Check write routes require `auth` + `activeSession` + `subscriptionExpiry` + `readonly`.
- Validate all user input server‑side.
- Verify file uploads are scanned and size‑limited.
- Ensure CSRF protection is enabled for all forms.

## 8) Backups
- Enable automated DB backups.
- Store backups in encrypted storage with restricted access.
