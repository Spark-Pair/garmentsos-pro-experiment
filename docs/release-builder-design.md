# GarmentsOS PRO Release Builder Design

## Purpose

The release builder will create versioned, client-specific production ZIP
artifacts on a trusted developer or CI machine. These artifacts will later be
consumed by a separately designed updater or installer.

The builder must package application code and prebuilt dependencies without
including client-owned runtime data, developer tooling, credentials, or source
control metadata.

This document defines the security and packaging contract only. Executable
builder, client-packaging, manifest-generation, updater, and installer scripts
will be implemented in later approved phases.

## Client PC Boundary

Client PCs must not require or receive:

- GitHub access or repository credentials
- A `.git` directory or Git history
- GitHub tokens, package registry tokens, or developer secrets
- Composer update or dependency-resolution access
- npm, Node.js, `node_modules`, or an npm build process
- Source build tools, tests, developer notes, or CI configuration

Composer dependency installation and npm asset compilation must happen only on
the trusted developer or CI machine. The release ZIP must already contain the
production `vendor` tree and compiled `public/build` assets.

## Artifact Output

The future builder should produce:

```text
release-artifacts\
  {client-id}\
    {version}\
      garmentsos-pro-{client-id}-{version}.zip
      garmentsos-pro-{client-id}-{version}.release.json
      garmentsos-pro-{client-id}-{version}.sha256
```

Artifacts must never be written into production data, backup, upload, log, or
runtime directories.

## ZIP Structure

The ZIP root represents one immutable Laravel application release:

```text
app\
bootstrap\
  app.php
  cache\
config\
database\
  migrations\
public\
  build\
  images\
  js\
  .htaccess
  index.php
  favicon.ico
  manifest.json
  service-worker.js
  offline.html
  robots.txt
  jquery.js
  calibri-regular.ttf
resources\
  views\
routes\
storage\
  framework\
    cache\
      data\
    sessions\
    views\
  logs\
vendor\
artisan
composer.json
composer.lock
release-info.json
```

Writable directory skeletons may be included only when required for Laravel
startup. They must contain no logs, sessions, caches, compiled views, uploads,
backups, databases, keys, or other runtime data.

## Include Rules

The builder must stage files from an explicit allowlist. It must not copy the
repository root and then attempt to remove unwanted content.

Required application inputs:

- `app/**`
- `bootstrap/app.php`
- production package-discovery files generated in staging under
  `bootstrap/cache`
- `config/**`
- `database/migrations/**`
- `resources/views/**`
- `routes/**`
- `public/build/**`
- `public/js/**`
- required public images, fonts, PWA files, entrypoints, and web-server files
- production-only `vendor/**`
- `artisan`
- `composer.json`
- `composer.lock`

The final ZIP must include:

- `public/build/manifest.json`
- every asset referenced by the Vite manifest
- `vendor/autoload.php`
- all production Composer dependencies
- all migrations required by the release
- an embedded `release-info.json`

`public/build` and `vendor` are intentionally ignored by Git, so the builder
must verify and include them explicitly.

The existing development `vendor` directory must not be copied directly into a
release. The future builder must create a production dependency tree using the
locked dependencies on the trusted build machine, with development packages
excluded.

The builder must also clear copied bootstrap cache contents before the
production Composer operation. `bootstrap/cache/packages.php` and
`bootstrap/cache/services.php` must be generated from the staged production
dependencies rather than copied from a development cache.

## Exclude Rules

The following content is prohibited:

- `.git`, `.github`, Git metadata, and CI credentials
- `.env`, `.env.*`, `auth.json`, private keys, tokens, and secrets
- SQLite databases, WAL/SHM files, dumps, exports, and corrupt copies
- `storage/app/**`
- logs, sessions, file caches, and compiled views
- backups, uploads, and other client-owned runtime data
- `public/storage`, `public/uploads`, and `public/hot`
- `node_modules`
- tests, test configuration, and test caches
- docs and internal security/deployment notes
- source build scripts and release-builder scripts
- npm package manifests and Vite/PostCSS development configuration
- factories and seeders unless explicitly approved by a later release rule
- obsolete `.bat` and `.vbs` launch scripts
- temporary files, editor configuration, and developer scratch files
- unrelated client branding, overlays, configuration, or identity files

An exclusion must win if a path matches both an include and an exclude rule.

## Release Manifest

The detached release manifest should use a versioned JSON schema:

```json
{
  "schema_version": 1,
  "app": {
    "name": "GarmentsOS PRO",
    "version": "1.2.3"
  },
  "target": {
    "client_id": "default",
    "client_name": "GarmentsOS PRO",
    "channel": "stable"
  },
  "build": {
    "built_at": "2026-06-12T12:00:00Z",
    "source_commit": "full-git-commit-hash"
  },
  "artifact": {
    "filename": "garmentsos-pro-default-1.2.3.zip",
    "sha256": "lowercase-sha256",
    "size_bytes": 0,
    "included_files_count": 0
  },
  "database": {
    "migrations_included": true,
    "migration_count": 0,
    "migrations": []
  }
}
```

Required metadata:

- application name and version
- target client ID and display name
- update channel
- UTC build date
- full source commit
- artifact filename, size, and SHA-256
- included file count
- migration presence, count, and inventory

## Embedded And Detached Metadata

The ZIP contains `release-info.json` with application, client, channel, build,
source commit, and migration metadata.

The detached `{artifact}.release.json` contains the final ZIP filename, size,
file count, and SHA-256 in addition to the embedded metadata.

The ZIP must not attempt to contain its own final SHA-256. Adding or changing an
embedded hash would change the ZIP and invalidate that hash. The final checksum
therefore belongs in the detached manifest and `.sha256` file.

Future signing should sign the detached manifest or a canonical digest of the
manifest and artifact. Signing is not part of this phase.

## Build Flow

The future build process should:

1. Require explicit client ID, client display name, version, and channel.
2. Validate identifiers and reject unsafe output paths.
3. Require a clean Git working tree unless an explicit controlled override is
   approved.
4. Record the full source commit.
5. Validate Composer metadata and run the application test suite.
6. Run `npm ci` and `npm run build` on the developer or CI machine.
7. Verify `public/build/manifest.json` and every referenced asset.
8. Create a clean staging directory outside all runtime and production data.
9. Copy only paths allowed by `scripts/release-rules.json`.
10. Install locked production Composer dependencies in staging with
    development packages excluded.
11. Apply only the approved overlay for the requested client.
12. Create empty Laravel writable-directory skeletons where required.
13. Generate the embedded release information and migration inventory.
14. Scan staging for prohibited paths, file patterns, and secret indicators.
15. Create the ZIP using stable relative paths and deterministic ordering where
    practical.
16. Reopen the ZIP and validate its complete inventory.
17. Calculate the final SHA-256 and artifact size.
18. Generate the detached release manifest and checksum file.
19. Remove the temporary staging directory.

The source tree and all production data must remain unchanged.

## Validation And Security Checks

The builder must stop on any failed required check:

- missing `artisan`, `composer.json`, or `composer.lock`
- missing `vendor/autoload.php`
- development Composer packages in the staged `vendor`
- missing `public/build/manifest.json`
- missing asset referenced by the Vite manifest
- missing routes, views, application code, or migrations
- `.env`, `.git`, SQLite, WAL, SHM, backup, upload, or log content present
- `public/hot`, `public/storage`, or `public/uploads` present
- unsafe archive paths, absolute paths, `..` traversal, or reparse points
- a filename collision with an existing artifact
- manifest target client differing from the requested client
- unapproved client-specific overlay or cross-client branding
- checksum mismatch after ZIP creation

Secret scanning is defense in depth. The allowlist and prohibited-path checks
remain the primary security boundary.

## Client-Specific Packages

One private source repository may produce releases for multiple clients.

Client packaging must:

- require an explicit target client
- begin from the common allowlisted application release
- apply only a declared, approved overlay for that client
- prevent one client's assets or configuration from entering another package
- record target client identity in embedded and detached metadata
- never include a real client `.env`, database, uploads, logs, or backups
- preserve default/global application behavior unless an approved client
  configuration explicitly changes it

Client overlays need their own future structure and validation rules. No
overlay discovery or business behavior is implemented by this design.

## Non-Negotiable Rules

- Never blindly ZIP the project directory.
- Never build on a client production PC.
- Never ship GitHub credentials, `.git`, `.env`, or developer secrets.
- Never ship a client database, uploads, logs, sessions, caches, or backups.
- Never run Composer update or npm build on a client PC.
- Never reuse an artifact filename for different contents.
- Never publish an artifact without verifying its inventory and SHA-256.
- Never install an artifact for a different client or channel.
- Never overwrite the currently active release during staging.
- Never treat `.gitignore` as a release security control.

## Risks

- Secret or production-data leakage: critical
- Cross-client data or branding leakage: critical
- Shipping development dependencies: high
- Missing public JavaScript, Vite assets, vendor files, or migrations: high
- Manifest/checksum mismatch: high
- Unsafe archive paths or links: high
- Version reuse or artifact overwrite: medium
- Unsigned detached manifests: medium until signing is introduced

## Later Implementation

After this design and `scripts/release-rules.json` are reviewed, later phases
may implement:

- `scripts/generate-release-manifest.php`
- release inventory validation
- `scripts/build-release.ps1`
- `scripts/package-client.ps1`
- detached manifest signing

No executable builder or package creation is included in this phase.
