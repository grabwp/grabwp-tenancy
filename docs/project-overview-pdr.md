# Project Overview PDR

## Overview

GrabWP Tenancy is a WordPress plugin that turns one WordPress installation into a lightweight multi-tenant platform without WordPress Multisite. Tenants share the same WordPress codebase, themes, plugins, and MySQL database, while tenant data is separated by table prefix and uploads directory.

The free plugin provides shared-MySQL tenancy, domain routing, path routing, tenant CRUD, tenant cloning, setup automation, and tenant admin restrictions. Pro extension points exist throughout the bootstrap, admin, path, and clone layers.

## Product Goals

- Host many tenant sites from one WordPress installation.
- Avoid WordPress Multisite network admin complexity.
- Keep free-tier tenancy simple: shared MySQL, shared code, isolated uploads.
- Support custom-domain tenants and no-DNS path tenants.
- Provide one-click setup checks for early loader, MU-plugin, rewrite rules, and data directory protection.
- Preserve a clean extension surface for GrabWP Tenancy Pro.

## Users

| User | Need |
| --- | --- |
| Freelancer | Manage multiple client sites from one WordPress install. |
| Agency | Lower hosting overhead while retaining tenant-level data separation. |
| SaaS builder | Prototype WordPress-powered SaaS with tenant creation and cloning. |
| Site admin | Create, edit, delete, and clone tenants from WordPress admin. |

## Functional Requirements

- Detect tenants before normal WordPress runtime through `load.php`.
- Resolve tenant context by CLI constant, domain mapping, path routing, or query fallback.
- Set tenant-specific `$table_prefix`, `WP_HOME`, `WP_SITEURL`, cookie paths, and uploads constants.
- Store tenant mappings in `GRABWP_TENANCY_BASE_DIR/tenants.php`.
- Create tenant upload directories under the configured tenancy base directory.
- Expose main-site admin pages for tenant list, creation, editing, settings, clone, and status.
- Support path tenants through `/site/{tenant_id}` and `nodomain.local` placeholder mappings.
- Clone source tenant or mainsite data into an existing target tenant through AJAX steps.
- Hide plugin/theme management on tenant dashboards based on settings.
- Install and remove MU-plugin, `wp-config.php` loader, `.htaccess` rewrites, and data-directory protection.

## Non-Functional Requirements

- WordPress 5.0 or newer.
- PHP 7.4 or newer.
- Minimal tenant request overhead.
- Early bootstrap must avoid WordPress APIs where WordPress may not be loaded yet.
- Tenant IDs must be six lowercase alphanumeric characters and reject reserved/problematic values.
- Admin mutations must use capability checks, nonces, sanitization, and redirect-after-post.
- Data directory PHP files should be blocked from direct web execution where Apache `.htaccess` applies.

## Constraints

- Free plugin uses shared MySQL only.
- Tenant code isolation is not provided in the free plugin; themes and plugins are shared.
- Tenant mappings are PHP files, not database records, because bootstrap needs early access.
- Path routing depends on root rewrite rules for pretty `/site/{id}` URLs.
- WordPress cron currently uses main-site context.

## Acceptance Criteria

- A tenant mapped to a custom domain routes to the tenant table prefix and upload path.
- A tenant with `nodomain.local` can route through `/site/{tenant_id}`.
- Tenant admin access URL validates token and domain hash before login handoff.
- Tenant creation writes mappings and creates uploads directory.
- Tenant deletion removes mapping, tenant directory, and tenant-prefixed DB tables.
- Clone flow completes six steps or cleans temporary files on failure.
- Status page can identify and fix missing setup components.

## References

- `README.md`
- `grabwp-tenancy.php`
- `load.php`
- `load-helper.php`
- `includes/class-grabwp-tenancy-admin.php`
- `includes/class-grabwp-tenancy-installer.php`
- `includes/backup/class-grabwp-tenancy-clone.php`

## Unresolved Questions

- Should future docs describe Pro-only dedicated database and full `wp-content` isolation in this repository or keep them on grabwp.com?
