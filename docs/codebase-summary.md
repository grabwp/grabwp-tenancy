# Codebase Summary

**Version:** 1.0.9 | **Last Updated:** 2026-05-14

## Overview

GrabWP Tenancy is a multi-tenant WordPress plugin (7,467 LOC) that enables hosting multiple client sites on a single WordPress installation with shared MySQL and table prefix isolation. The implementation is PHP with small admin JavaScript/CSS assets and WordPress.org metadata files.

**Codebase size (May 2026 scan, excluding translations & README):**

| Area | Files | Lines | Purpose |
| --- | ---: | ---: | --- |
| `includes/` | 14 | 4,716 | Core services: tenant model, admin, loader, installer, path manager, clone orchestration |
| `admin/` | 9 | 2,751 | Admin UI: list table, CRUD forms, status page, clone interface |
| Root PHP | 3 | 1,381 | Plugin entry, early loader, load helper (bootstrap) |
| **Total scanned** | **26** | **8,848** | |

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
2. `load.php` (25 LOC) guards against double loading and includes `load-helper.php`.
3. `load-helper.php` (854 LOC) detects tenant context via CLI constant → domain mapping → path route → query string.
4. If a tenant is found, constants (`GRABWP_TENANCY_TENANT_ID`, `GRABWP_TENANCY_TABLE_PREFIX`) and table prefix are set before WordPress bootstrap.
5. `grabwp-tenancy.php` (502 LOC) runs the normal plugin entrypoint, initializing tenant-only hooks (if tenancy detected) or full main-site admin.

## Admin Flow

- Main-site admins manage tenants from `Tenancy` menu pages.
- Admin form submissions run on `admin_init`, check `manage_options`, verify nonce, sanitize input, and redirect.
- Tenant mappings are saved to `tenants.php` as a PHP array.
- Settings are saved to `settings.php` under the tenancy base directory.
- Status actions use AJAX to install/fix setup components.

## Clone Architecture

Base clone is shared-MySQL only (6-step AJAX polling, 30-minute job TTL):

| Step | Class | Purpose |
| --- | --- | --- |
| 1 | `GrabWP_Tenancy_Clone` | Validate source/target tenants, create temp staging directory |
| 2 | `GrabWP_Tenancy_Clone_Db_Exporter` | Export source database (500-row chunks, 100-row TX batches) to `database.sql` + `metadata.json` |
| 3 | `GrabWP_Tenancy_Clone_Db_Importer` | Import into target tenant with prefix replacement, streaming SQL |
| 4 | `GrabWP_Tenancy_Clone_Fs_Helper` | Copy uploads directory (symlink-aware safe ops) |
| 5 | `GrabWP_Tenancy_Clone_URL_Replacer` | Fix siteurl/home options, strip GrabWP plugins if mainsite source |
| 6 | `GrabWP_Tenancy_Clone` | Cleanup temp staging directory |

If Pro is active, base clone admin routes do not register (Pro provides enhanced 7-step clone with isolated database/wp-content support).

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

## Testing & Validation

**Current State:** No automated test suite is present.

**Validation approach:**
- PHP syntax check: `find . -name '*.php' | xargs php -l`
- Manual QA on WordPress installs (activation, routing, CRUD, clone, deactivation)
- Release checklist in `deployment-guide.md`

**Test Framework Decision (Unresolved):** Milestone 2 will evaluate and adopt one of:
- WP-CLI smoke tests (lightweight, CLI-first)
- PHPUnit with WordPress test suite (standard, but heavier)
- Local integration fixtures (custom, lightweight)

## Large Files

Several files exceed the 200-line preference (grouped by domain complexity):

| File | Lines | Reason | Refactor Target |
| --- | ---: | --- | --- |
| `includes/class-grabwp-tenancy-admin.php` | 1,076 | Tenant CRUD, settings, admin menu, form processing combined | Milestone 3: Split form handlers |
| `admin/views/status.php` | 856 | Setup checks, fix actions, diagnostic UI | Milestone 3: Extract into PHP partials/render helpers |
| `load-helper.php` | 854 | Early bootstrap, tenant detection (CLI/domain/path/query), path setup | Core bootstrap — necessary complexity |
| `includes/class-grabwp-tenancy-installer.php` | 660 | Activation, deactivation, MU-plugin setup, .htaccess, protection files | Acceptable: focused single responsibility |
| `admin/class-grabwp-tenancy-list-table.php` | 550 | WordPress list table for tenants (standard pattern) | Acceptable: native WordPress pattern |
| `grabwp-tenancy.php` | 502 | Main plugin entry, singleton pattern, mode branching (tenant-only vs admin) | Acceptable: plugin entry requirement |

Note: Refactoring is deferred to Milestone 3 to preserve Pro extension compatibility (Pro overrides hooks in these classes).

## Key Classes & Hooks

**Core Classes:**
- `GrabWP_Tenancy` — Singleton entry point, mode routing
- `GrabWP_Tenancy_Loader` — WordPress integration, admin token auth, path-based routing fixes
- `GrabWP_Tenancy_Tenant` — Tenant model, token security, 6-char alphanumeric ID generation
- `GrabWP_Tenancy_Admin` — Tenant CRUD, admin menu, form processing (1,076 LOC)
- `GrabWP_Tenancy_Settings` — Tenant capability restrictions (file mods, plugin/theme visibility)
- `GrabWP_Tenancy_Path_Manager` — Centralized path resolution with legacy migration
- `GrabWP_Tenancy_Installer` — Plugin activation, MU-plugin setup, loader, .htaccess, protection
- `GrabWP_Tenancy_Clone` — 6-step AJAX clone orchestrator
- `GrabWP_Tenancy_Clone_Db_Exporter` — Chunks SELECT (500 rows), TX batches (100)
- `GrabWP_Tenancy_Clone_Db_Importer` — Streaming SQL import with prefix replacement
- `GrabWP_Tenancy_Clone_Fs_Helper` — Safe filesystem ops, symlink-aware
- `GrabWP_Tenancy_Clone_URL_Replacer` — URL rewriting in cloned content

**Extension Hooks (20+):**
`grabwp_tenancy_init`, `grabwp_tenancy_init_tenant_only`, `grabwp_tenancy_loader_init`, `grabwp_tenancy_admin_init`, `grabwp_tenancy_admin_menu`, `grabwp_tenancy_before_create_tenant`, `grabwp_tenancy_after_create_tenant`, `grabwp_tenancy_after_update_tenant`, `grabwp_tenancy_before_delete_tenant`, `grabwp_tenancy_after_delete_tenant`, `grabwp_tenancy_tenant_row_actions`, and more in Pro.

## Unresolved Questions

- Should repo documentation include Pro-specific architecture or keep it separate?
