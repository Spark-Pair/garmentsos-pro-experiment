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
- Store application-created backups outside `public/` and require developer/admin authorization for downloads.
- Verify backup checksums before trusting a backup file.

## 9) Release Packaging
- Follow `docs/RELEASE_PACKAGING.md` before preparing any client package.
- Client packages must not include `.git/`, real `.env` files, GitHub credentials, private keys, developer databases, DB sidecar files, dumps, backups, logs, tests, or dev-only files.
- Update packages must never overwrite a client database, client `.env`, uploads, or backups.
- A local PHP/Laravel app on a client PC cannot be made 100% secret. Use practical controls: private repository, reviewed release package, generated client environment, no credentials in the app, signed updates in a future phase, and optional obfuscation later.

## 10) Licensing Foundation
- Licensing is documented in `docs/LICENSING.md`.
- Keep `LICENSE_ENFORCEMENT_ENABLED=false` until a client is explicitly ready for activation and rollout.
- Licensing is installation/server-based. LAN browser PCs are not separately licensed.
- Store hashed installation fingerprints only; never persist raw machine identifiers.
- Store only `license_key_hash`; do not store raw license keys after activation.
- Verify signed license payloads with the public key before trusting or persisting license fields.
