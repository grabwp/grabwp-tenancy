# GrabWP Tenancy (Base) — Plugin API Reference

> For shared code standards, architecture, deployment: see `~/grabwp/docs/`

## Scope

Foundation multi-tenant WordPress system. Shared MySQL + table prefix isolation.
**Version:** 1.0.7 | **Requires:** WordPress 5.0+, PHP 7.4+

## Dependency Chain

```
Base (this) ← Pro ← WaaS ← Sepay
```

Pro, WaaS, and Sepay extend this plugin via hooks. Base works standalone.

## Constants (defined in load-helper.php, pre-WordPress)

| Constant | Type | Description |
|----------|------|-------------|
| `GRABWP_TENANCY_VERSION` | string | Plugin version |
| `GRABWP_TENANCY_IS_TENANT` | bool | Whether current request is a tenant |
| `GRABWP_TENANCY_TENANT_ID` | string | 6-char tenant ID (e.g., `a3x7b2`) |
| `GRABWP_TENANCY_ROUTING_METHOD` | string | `domain`, `path`, or `query` |
| `GRABWP_TENANCY_BASE_DIR` | string | Data directory path |

## Actions

| Hook | When | Typical Use |
|------|------|-------------|
| `grabwp_tenancy_init` | After main singleton init | Pro plugin hooks in |
| `grabwp_tenancy_init_tenant_only` | Tenant context detected | Apply tenant restrictions, load tenant-only features |
| `grabwp_tenancy_init_main_site_full` | Main site context | Load admin features, CRUD UI |

## Key Functions (Pre-WordPress / load-helper.php)

| Function | Returns | Purpose |
|----------|---------|---------|
| `grabwp_tenancy_identify_tenant()` | string | Checks domain → path → query; returns tenant ID |
| `grabwp_tenancy_get_tenant_id_from_domain($domain)` | string | Domain lookup in tenants.php |
| `grabwp_tenancy_extract_tenant_id_from_path($uri)` | string\|false | Parse `/site/{id}` from URI |
| `grabwp_tenancy_extract_tenant_id_from_query($qs)` | string\|false | Parse `?site={id}` |
| `grabwp_tenancy_validate_tenant_id($id)` | bool | Validates `/^[a-z][a-z0-9]{5}$/` |
| `grabwp_tenancy_validate_domain($domain)` | bool | Validates domain format |

## Data Files (in GRABWP_TENANCY_BASE_DIR)

| File | Format | Purpose |
|------|--------|---------|
| `tenants.php` | PHP array `['domain' => 'id']` | Domain→tenant mapping |
| `settings.php` | PHP `$grabwp_tenancy_settings` var | Global tenant settings |
| `tokens.php` | PHP `$admin_token` var | Admin access token (24h, single-use) |

## Admin Interface

- **Menu:** Dashboard → GrabWP Tenancy
- **Pages:** Tenant list, Create, Edit, Settings, Status (3-tab diagnostic)
- **Capabilities:** `manage_options` required
- **Tenant ID format:** 6-char alphanumeric, first char alphabetic

## Early Loading Constraints

`load-helper.php` runs **before WordPress**:
- No WordPress functions (`wp_*`, `do_action`, etc.)
- No database access via WordPress
- Only file operations, string functions, PHP stdlib
- Pro helper loads first if present (allows overrides)
