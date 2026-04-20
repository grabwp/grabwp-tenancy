# System Architecture

## Overview

GrabWP Tenancy uses an early bootstrap loader to detect tenant context before normal WordPress execution. The detected tenant changes WordPress constants, upload paths, and database table prefix. The normal plugin runtime then loads either tenant-only behavior or main-site administration.

## Components

| Component | Files | Responsibility |
| --- | --- | --- |
| Early loader | `load.php`, `load-helper.php` | Detect tenant before WordPress bootstrap completes. |
| Main plugin | `grabwp-tenancy.php` | Load dependencies, choose tenant-only or main-site mode. |
| Path manager | `includes/class-grabwp-tenancy-path-manager.php` | Resolve data, tenant, upload, token, and mapping paths. |
| Admin controller | `includes/class-grabwp-tenancy-admin.php` | Tenant CRUD, settings, admin menu, page rendering. |
| Installer | `includes/class-grabwp-tenancy-installer.php` | Activation, deactivation, MU-plugin, loader, `.htaccess`, protection files. |
| Loader service | `includes/class-grabwp-tenancy-loader.php` | Tenant directories, table cleanup, admin token handling, path-route fixes. |
| Tenant model | `includes/class-grabwp-tenancy-tenant.php` | Tenant ID/domain data and admin access token helpers. |
| Clone services | `includes/backup/*` | Shared-MySQL export, import, uploads copy, URL replacement. |
| Admin UI | `admin/*` | WordPress list table, forms, status, clone UI, JS/CSS. |

## Bootstrap Sequence

```text
wp-config.php
  -> require wp-content/plugins/grabwp-tenancy/load.php
    -> load-helper.php
      -> detect tenant
      -> define tenant constants
      -> set table prefix
      -> set uploads path
WordPress loads plugins
  -> grabwp-tenancy.php
    -> tenant-only runtime OR main-site admin runtime
```

## Tenant Detection Order

1. Existing `GRABWP_TENANCY_TENANT_ID` constant, mainly for CLI.
2. Domain mapping from `tenants.php`.
3. Path route `/site/{tenant_id}`.
4. Query fallback `?site={tenant_id}`.
5. No match: main site context.

Domain mapping wins over path/query so a custom-domain tenant cannot be overridden by a conflicting path segment.

## Data Layout

Default tenant data path:

```text
wp-content/grabwp-tenancy/
  tenants.php
  settings.php
  tokens.php
  {tenant_id}/
    uploads/
  tmp/
```

Legacy paths are detected before default path:

- `wp-content/grabwp/`
- `wp-content/uploads/grabwp-tenancy/`

## Database Isolation

The free plugin uses shared MySQL with per-tenant table prefixes:

```text
main site:   wp_posts, wp_options, ...
tenant abc: abc123_posts, abc123_options, ...
tenant def: def456_posts, def456_options, ...
```

`GRABWP_TENANCY_ORIGINAL_PREFIX` stores the original prefix and `GRABWP_TENANCY_TABLE_PREFIX` stores the tenant prefix for reference.

## Routing

### Domain Routing

Custom domain request:

```text
tenant.example.com
  -> host matches tenants.php mapping
  -> tenant ID constant defined
  -> WP_HOME/WP_SITEURL use tenant domain
```

### Path Routing

Shared-domain request:

```text
example.com/site/abc123/wp-admin/
  -> .htaccess rewrites to /wp-admin/?site=abc123
  -> bootstrap verifies abc123 exists in tenants.php
  -> WP_HOME/WP_SITEURL use example.com/site/abc123
  -> cookie paths use /site/abc123/
```

The installer places GrabWP rewrite rules before the WordPress block to avoid WordPress catch-all rules intercepting tenant paths.

## Tenant Admin Access

- `GrabWP_Tenancy_Tenant::get_global_admin_token()` stores a 24-hour token in `tokens.php`.
- Tenant admin URLs include `grabwp_token` and `grabwp_hash`.
- Hash is based on normalized domain, tenant ID, and `AUTH_SALT`.
- Loader validates token/hash and logs in the lowest admin user for tenant admin access.

## Clone Architecture

The base clone feature targets shared MySQL:

```text
AJAX init
  -> transient job state
AJAX step loop
  -> validate
  -> export source DB
  -> import target DB with prefix replacement
  -> copy uploads
  -> replace URLs/update options
  -> cleanup
```

If Pro is active, base clone admin hooks do not register.

## Security Boundaries

- Admin operations require `manage_options`.
- Forms and AJAX endpoints use WordPress nonces.
- Tenant data files are protected by generated `.htaccess` and `index.php`.
- Directory deletion avoids following symlinks.
- Plugin/theme management can be hidden and file edits/mods disabled on tenant sites.

## Unresolved Questions

- Should future architecture docs include Pro architecture diagrams in this repo or keep Pro internals separate?
