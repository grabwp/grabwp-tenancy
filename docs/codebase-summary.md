# Codebase Summary

## Overview

This repository contains the GrabWP Tenancy WordPress plugin. The implementation is PHP with small admin JavaScript/CSS assets and WordPress.org metadata files.

Approximate scanned size, excluding translation catalogs:

| Area | Files | Lines |
| --- | ---: | ---: |
| `includes/` | 14 | 4716 |
| `admin/` | 9 | 2751 |
| Root PHP | 3 | 1381 |
| README/readme | 2 | 445 |
| Total scanned | 28 | 9293 |

## Top-Level Files

| Path | Purpose |
| --- | --- |
| `grabwp-tenancy.php` | Main plugin header, singleton, dependency loading, main-site vs tenant initialization. |
| `load.php` | Early loader included from `wp-config.php`; loads `load-helper.php`. |
| `load-helper.php` | Early tenant detection, routing constants, upload path setup, table prefix setup. |
| `readme.txt` | WordPress.org plugin readme. |
| `README.md` | GitHub/user-facing overview. |

## Directories

| Path | Purpose |
| --- | --- |
| `admin/` | Admin list table, page templates, CSS, and JavaScript. |
| `includes/` | Runtime services for admin, settings, loader, installer, paths, logging, tenant model. |
| `includes/backup/` | Base tenant clone workflow and helpers. |
| `languages/` | Translation files and POT source. |
| `docs/` | Project documentation initialized by `ck:docs init`. |

## Runtime Flow

1. `wp-config.php` loads `wp-content/plugins/grabwp-tenancy/load.php`.
2. `load.php` guards double loading, includes `load-helper.php`, then calls `grabwp_tenancy_early_init()`.
3. `load-helper.php` detects tenant context by CLI constant, domain mapping, path route, then query string.
4. If a tenant is found, constants and table prefix are set before WordPress continues.
5. The normal plugin entrypoint `grabwp-tenancy.php` initializes either tenant-only hooks or full main-site admin.

## Admin Flow

- Main-site admins manage tenants from `Tenancy` menu pages.
- Admin form submissions run on `admin_init`, check `manage_options`, verify nonce, sanitize input, and redirect.
- Tenant mappings are saved to `tenants.php` as a PHP array.
- Settings are saved to `settings.php` under the tenancy base directory.
- Status actions use AJAX to install/fix setup components.

## Clone Flow

Base clone is shared-MySQL only and runs via AJAX polling:

1. Validate source and target tenants, create temp directory.
2. Export source database.
3. Import database with prefix replacement.
4. Copy uploads.
5. Replace URLs and update tenant options.
6. Clean temp directory.

## Extension Points

The codebase exposes action/filter hooks and checks for Pro-specific override functions/classes. Examples include:

- `grabwp_tenancy_init`
- `grabwp_tenancy_init_tenant_only`
- `grabwp_tenancy_loader_init`
- `grabwp_tenancy_admin_init`
- `grabwp_tenancy_admin_menu`
- `grabwp_tenancy_before_create_tenant`
- `grabwp_tenancy_after_create_tenant`
- `grabwp_tenancy_after_update_tenant`
- `grabwp_tenancy_before_delete_tenant`
- `grabwp_tenancy_after_delete_tenant`
- `grabwp_tenancy_tenant_row_actions`

## Testing

No automated test suite is currently present. Testing strategy remains unresolved (see `project-roadmap.md`).

Current validation approach:
- PHP syntax check: `find . -path './.git' -prune -o -name '*.php' -print | xargs -n1 php -l`
- Manual QA on WordPress installs (activation, routing, CRUD, clone, deactivation).
- No PHPUnit, WP-CLI smoke tests, or integration fixtures are in place.

## Large Files

Several files exceed the 200-line preference (grouped by domain complexity):

| File | Lines | Purpose |
| --- | ---: | --- |
| `includes/class-grabwp-tenancy-admin.php` | 1076 | Tenant CRUD, settings, admin menu, form processing |
| `admin/views/status.php` | 856 | Setup status page, fix actions, component checks |
| `load-helper.php` | 854 | Early bootstrap, tenant detection, path setup |
| `includes/class-grabwp-tenancy-installer.php` | 660 | Activation, deactivation, setup components |
| `admin/class-grabwp-tenancy-list-table.php` | 550 | WordPress list table for tenants |
| `grabwp-tenancy.php` | 502 | Main plugin runtime, mode branching |

These remain unsplit pending larger refactoring efforts (see `project-roadmap.md` Milestone 3).

## Unresolved Questions

- Which test framework should be adopted: WP-CLI smoke tests, PHPUnit with WordPress test suite, or local integration fixtures?
