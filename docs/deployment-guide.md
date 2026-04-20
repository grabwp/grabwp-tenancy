# Deployment Guide

## Overview

GrabWP Tenancy is deployed as a WordPress plugin. Runtime setup requires both normal plugin activation and early loader installation so tenant routing can run before WordPress selects database tables and paths.

## Requirements

- WordPress 5.0 or newer.
- PHP 7.4 or newer.
- Writable `wp-content/` during setup.
- Writable `wp-config.php` if using one-click loader installation.
- Writable root `.htaccess` for path routing on Apache.
- MySQL privileges to create/drop tenant-prefixed tables for clone/delete workflows.

## Plugin Installation

### WordPress.org

1. Install `GrabWP Tenancy` from Plugins > Add New.
2. Activate the plugin.
3. Open Tenancy > Status.
4. Use status actions to install/fix setup components.

### Manual

1. Copy this repository to `wp-content/plugins/grabwp-tenancy/`.
2. Activate the plugin in WordPress admin.
3. Install the loader in `wp-config.php`:

```php
( $_grabwpl = __DIR__ . "/wp-content/plugins/grabwp-tenancy/load.php" ) && file_exists( $_grabwpl ) && require_once $_grabwpl;
```

4. Confirm status checks pass.

## Setup Components

| Component | Purpose |
| --- | --- |
| `wp-config.php` loader | Runs tenant detection before WordPress completes bootstrap. |
| MU-plugin | Ensures base plugin and Pro plugin load early in normal plugin lifecycle. |
| Root `.htaccess` block | Routes `/site/{tenant_id}` paths to WordPress with tenant context. |
| Data directory `.htaccess` | Blocks direct PHP access and directory listing. |
| Data directory `index.php` | Prevents directory browsing fallback. |
| `tenants.php` | Stores tenant-to-domain mappings. |

## Data Directory

Default:

```text
wp-content/grabwp-tenancy/
```

Legacy installs may continue using:

```text
wp-content/grabwp/
wp-content/uploads/grabwp-tenancy/
```

Do not delete legacy data paths unless migration is complete and verified.

## Creating Tenants

1. Open Tenancy > Add New.
2. Enter up to 10 domains, or leave empty to create a path-only tenant.
3. Path-only tenants receive `nodomain.local` in mappings.
4. Access path-only tenant at `/site/{tenant_id}`.
5. Use generated tenant admin access URL when needed.

## Deactivation

Plugin deactivation removes:

- Root `.htaccess` path routing block.
- `wp-config.php` loader block when managed by markers.
- Generated MU-plugin file when it belongs to GrabWP Tenancy.

Tenant data and tenant mappings are preserved.

## Release Checklist

Before publishing a new plugin build:

```bash
find . -path './.git' -prune -o -name '*.php' -print | xargs -n1 php -l
```

Also verify:

- Plugin activates.
- Status page reports expected setup state.
- Domain tenant routes correctly.
- Path tenant routes correctly.
- Tenant admin login token works.
- Create/edit/delete tenant works.
- Clone from tenant to tenant works.
- Clone from mainsite to tenant works.
- Deactivation removes managed setup hooks without deleting tenant data.

## Unresolved Questions

- No automated packaging script or WordPress.org deployment script is present.
