# Licensing

GarmentsOS PRO licensing is server/installation based. LAN browser PCs do not consume separate licenses.

## Defaults

```env
LICENSE_ENFORCEMENT_ENABLED=false
INSTALLATION_MODE=local_lan
```

When disabled, licensing middleware is a no-op and existing behavior is preserved.

## Activation

Supported flows:

- online activation when `LICENSE_SERVER_URL` is configured
- offline signed license import
- reactivation request code for legitimate server/hardware changes

The app stores only `license_key_hash`, signed payload/cache, installation UUID, and hashed fingerprint. It does not store raw license keys or raw machine identifiers.

## Enforcement

The `ensureLicense` middleware is wired after `auth` and `activeSession` and before subscription/read-only/database transaction middleware. It changes behavior only when `LICENSE_ENFORCEMENT_ENABLED=true`.

When enabled:

- no license/unactivated: blocked
- valid active license: full app
- subscription expired: read-only mode
- tampered payload/fingerprint mismatch/copied installation: blocked
- offline grace: allowed until grace expires

## Modules And Features

Precedence:

1. License disallow wins.
2. Local disable blocks reviewed modules.
3. Missing settings preserve current behavior.

Reviewed route-enforced modules currently include `articles`, `customers`, `suppliers`, `reports`, and `rates`. Feature flags affect only routes/actions that explicitly use them.

## Secrets

The private signing key stays only on the license server. Client installs may contain only public verification keys. Audit/license logs must not contain raw license keys, raw hardware identifiers, `.env` values, private keys, tokens, or credentials.

## Local PHP Limitation

A local PHP app on a client PC cannot be protected perfectly from a determined technical user. Licensing is practical protection against ordinary copy-paste use. Stronger protection may be added later with signed updates, compiled runtime, or cloud hosting.
