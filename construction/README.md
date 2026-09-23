# Construction workspace

Entry: `/?business=construction`. The existing RAY owner login is mandatory for all pages, form submissions and exports. The old standalone login/setup is disabled. Standalone entry links route back through RAY.

The live Construction source was imported without replacing its MySQL database or seeding new records. `config.php` remains server-only and ignored by Git. It must be copied from the existing deployment when provisioning another server; never commit credentials or SQL backups.

Authorization resolves the configured RAY owner's email to exactly one active Construction user with a company-scoped OWNER role. Missing or ambiguous mappings fail closed. Existing company and project permission checks remain in place. Team records do not create additional RAY login access.

Before retiring the original hostname, verify all pages, record counts and backups. Keep the Construction database. Removing the legacy hostname must not remove the migrated directory, RAY storage, or the WordPress website.

Rollback: retain the original files and database until cutover is verified. Re-enable the original hostname and restore only its own application files if needed. Never restore a database over newer records without a separate review.
