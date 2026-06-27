# Installation-Based Licensing

## Current Phase
Phase 2A adds the licensing, audit, and backup/restore foundation only. Enforcement is disabled by default:

```env
LICENSE_ENFORCEMENT_ENABLED=false
INSTALLATION_MODE=local_lan
```

When enforcement is disabled, licensing middleware must not block, redirect, set read-only mode, or alter normal app behavior.

## Installation Model
GarmentsOS PRO licensing is app/server-installation based.

- Single PC mode: the local app installation is licensed.
- LAN mode: the office server PC installation is licensed. Browser PCs on the LAN are not separately licensed.
- Future cloud mode: the cloud/VPS deployment is licensed using the persistent installation UUID plus cloud-friendly identity such as app URL/domain where available.

The app stores a persistent installation UUID under the local license storage area. It should survive app updates and backups. The fingerprint is a hash derived from the installation UUID plus sanitized server/deployment signals. Raw hardware identifiers are not persisted.

## Core Rules
- No license or unactivated installation: block app when enforcement is enabled.
- Tampered license, invalid signature, copied installation, or installation fingerprint mismatch: block app when enforcement is enabled.
- Valid license with expired subscription: read-only mode by default.
- Valid license with active subscription: full access.
- Offline grace valid: continue according to the last valid signed license.
- Offline grace expired: block invalid/tampered/no-license cases; use read-only for subscription expiry by default.

## Current Rollout State
- `LICENSE_ENFORCEMENT_ENABLED=false` by default.
- `ensureLicense` is registered as a middleware alias for future use but is not broadly wired into production routes.
- Enable enforcement only after staging/client-copy testing and explicit approval.
- The current foundation is installation/server based, not every browser or office PC based.

## Tables
- `app_installations`: persistent installation UUID, mode, masked/sanitized fingerprint hash, status, and sanitized metadata.
- `licenses`: local license/subscription record for the installation.
- `license_checks`: sanitized check history for activation, startup, online/offline checks, tampering, and grace results.
- `audit_logs`: future audit trail for app opened, login/logout, license events, backup/restore, updater, settings, and important CRUD actions.
- `backup_logs`: future backup/restore/update safety history.

These are additive tables only. They do not alter business tables.

## Services
- `InstallationIdentityService`: gets or creates the persistent installation UUID without overwriting an existing identity.
- `InstallationFingerprintService`: returns hash/preview only and never persists raw machine details.
- `SignedLicenseFileService`: verifies signed local license cache with a public key; it does not sign locally.
- `LicenseActivationClient`: boundary for future online activation.
- `OfflineActivationService`: generates an offline request code without internet.
- `LicenseService`: calculates status; enforcement remains disabled unless explicitly enabled.
- `AuditLogService`: sanitizes sensitive context before storing audit logs.
- `BackupService` and `RestoreService`: skeleton foundation for future safe backup/restore workflows.

## Middleware
`ensureLicense` is registered for future use. Future target order:

```text
auth, activeSession, ensureLicense, subscriptionExpiry, readonly, dbTransaction
```

Phase 2A does not add it to the main production route group.

## Offline Activation
Offline activation exports a request code containing the installation UUID and fingerprint hash. A license server/tool signs a license response with a private key. The client app imports and verifies that signed response using `LICENSE_PUBLIC_KEY`.

Private signing keys must never be shipped to client PCs.

## Online Activation And Refresh
Online activation sends the raw license key only for the activation request. The app stores only `license_key_hash` from the signed server response.

Activation and refresh requests include:
- installation UUID
- installation fingerprint hash
- installation mode
- app name/version

They must not include raw hardware identifiers, `.env`, `APP_KEY`, DB credentials, tokens, or private keys.

Refresh sends the server license id, license key hash, installation UUID, fingerprint hash, and current signed payload hash. The server returns a new signed payload.

## Module Licensing
- License disallow wins over local developer settings.
- Local disable blocks when the license allows or does not restrict the module.
- Missing/default settings preserve current staged behavior.
- LAN/browser client PCs are not individual licensed devices.

## Signed Payload Verification
The app verifies signed canonical JSON before trusting payload fields. The expected payload contains:
- server license id
- license key hash
- client/business names
- installation UUID and fingerprint hash
- installation mode
- license/subscription statuses
- subscription/license expiry dates
- offline grace/cache deadline
- allowed modules/features/future brand ids
- update channel
- issued timestamp
- signature version
- payload hash

Invalid signature, tampered payload, installation mismatch, fingerprint mismatch, expired cache, or suspicious dates map to blocked/tampered-style status for future enforcement.

## Reactivation
Reactivation request codes can be generated for legitimate server changes. The app does not self-approve reactivation. Approval must come from an online license response or imported signed payload.

## Data Safety
- Do not overwrite client databases, `.env`, uploads, logs, or backups.
- Back up before future update, migration, or restore operations.
- Restore must require confirmation and create an emergency backup first in a future implementation phase.
- Backups must not be public.

## Secret And Log Safety
- Do not show raw machine details, raw license keys, private keys, credentials, tokens, or `.env` values in UI/logs.
- License private signing keys belong only on a future license server/signing tool.
- Client PCs may contain a public verification key only.
- Audit/license logs must stay sanitized.

## Local PHP Limitation
A local PHP app on a client PC cannot be protected perfectly from a determined technical user. Licensing is practical protection against ordinary copy-paste use. Stronger protection can be added later with signed updates, obfuscation, compiled runtime, or cloud hosting.
