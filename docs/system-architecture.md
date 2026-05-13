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

Base clone is a 6-step AJAX polling orchestrator for shared-MySQL tenants (30-minute job TTL). Pro provides a 7-step variant with dedicated database and isolated wp-content support.

### Clone Process Flow

```
1. Init Job (AJAX POST)
   └─ Validate source/target tenants
   └─ Create temp staging directory
   └─ Store job state in transient (30-min TTL)

2-6. Step Loop (AJAX POST with job_id)
   ├─ Step 2: Export source database
   │  ├─ SELECT in 500-row chunks
   │  ├─ Wrap INSERTs in 100-row transaction batches
   │  └─ Save database.sql + metadata.json
   │
   ├─ Step 3: Import database with prefix replacement
   │  ├─ Read database.sql
   │  ├─ Replace source prefix with target prefix
   │  └─ Stream INSERT statements to target tables
   │
   ├─ Step 4: Copy uploads directory
   │  ├─ Source: {source_tenant_id}/uploads/
   │  ├─ Dest: {target_tenant_id}/uploads/
   │  └─ Safe ops: skip symlinks (GrabWP_Tenancy_Clone_Fs_Helper)
   │
   ├─ Step 5: Fix URLs & cleanup
   │  ├─ Replace siteurl/home options (old domain → new domain)
   │  ├─ If mainsite source: remove GrabWP plugins from target options
   │  └─ Update target tenant metadata
   │
   └─ Step 6: Cleanup
      └─ Remove temp staging directory

Job State Machine:
  Transient storage: 'grabwp_clone_job_{job_id}'
  Fields: source_tenant_id, target_tenant_id, step, total, data, error
  TTL: 1800 seconds (30 minutes)

On Failure:
  └─ Cleanup temp directory automatically
  └─ Return error with context
```

**Diff from Pro:** Pro's 7-step clone adds symlink extension sync (step 6.5) and supports isolated MySQL/SQLite, with per-tenant wp-config.php.

If Pro is active, base clone admin routes do not register.

## Security Boundaries

- Admin operations require `manage_options`.
- Forms and AJAX endpoints use WordPress nonces.
- Tenant data files are protected by generated `.htaccess` and `index.php`.
- Directory deletion avoids following symlinks.
- Plugin/theme management can be hidden and file edits/mods disabled on tenant sites.

## Pro Extension Architecture (High-Level)

Pro enhances base tenancy with:

- **Dedicated Databases:** Each tenant gets isolated MySQL database or SQLite
- **Full wp-content Separation:** Per-tenant plugins, themes, uploads (vs shared in base)
- **Advanced Clone:** 7-step clone with symlink extension sync
- **Backup/Restore:** 8-step restore with real-time progress UI
- **Cross-DB Migration:** Move tenants between shared MySQL, dedicated MySQL, SQLite
- **Per-Tenant wp-config.php:** New tenants inherit master defaults
- **Extension Sync:** Sync plugins/themes between symlink and copy installs

Base plugin checks `class_exists( 'GrabWP_Tenancy_Pro_...' )` to defer to Pro when active.

Pro maintains file ownership over its own classes and hooks (`includes/pro/`, `admin/pro/`) and is loaded via `grabwp_tenancy_init` action hook.

## Unresolved Questions

- Should this repo document Pro internals or should those live in the Pro plugin repo only?
