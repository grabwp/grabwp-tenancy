# GrabWP Tenancy Product Analysis

## What Is This Product?
- **WordPress multi-tenant platform plugin** — turns single WordPress install into SaaS hosting engine
- Alternative to WordPress Multisite: avoids network admin overhead, enables table-prefix isolation
- Free tier focuses on shared-MySQL model; Pro adds dedicated databases per tenant

## Target Users
- **Freelancers** — manage multiple client sites from one WordPress install
- **Agencies** — lower hosting overhead with tenant-level data separation
- **SaaS builders** — prototype WordPress-powered SaaS with tenant creation/cloning
- **Site admins** — full tenant CRUD (create, read, update, delete) from WordPress admin UI

## Key Features (Free Tier)
- Shared MySQL + table prefix isolation (`{tenant_id}_` prefix per site)
- Isolated uploads per tenant (`wp-content/grabwp-tenancy/{tenant_id}/uploads/`)
- Domain routing (custom domains) OR path-based routing (`yoursite.com/site/{tenant_id}`)
- Tenant cloning (duplicate any tenant including DB + files)
- Admin interface (full tenant management UI)
- Security controls (hide plugin/theme management, disable file edits per tenant)
- One-click setup automation (MU-plugin and wp-config.php installer)

## Unique Selling Points (USPs)
- **No WordPress Multisite complexity** — cleaner, simpler data model
- **Flexible routing** — zero DNS changes needed for path-based tenants
- **Lightweight** — loads early, minimal request overhead
- **Extensible** — hooks throughout for Pro plugin to extend (dedicated DBs, full wp-content isolation, backup/restore)

## Pricing Model
- **Free** — shared MySQL, basic CRUD, cloning
- **Pro** — from $9.99/month (all Pro features included in all plans); 20% early-bird discount code: `EARLYBIRDPRO`
- Pro adds: per-tenant dedicated MySQL/SQLite, full wp-content isolation, AJAX backup/restore, cross-database migration, extension sync, custom data locations

## Technology Stack
| Layer | Technology |
|-------|-----------|
| **Runtime** | PHP 7.4+; early-bootstrap loader (before WordPress init) |
| **Database** | Shared MySQL with table prefix isolation |
| **Storage** | Filesystem (uploads) + PHP file config (tenants.php, settings.php) |
| **Admin UI** | WordPress list table, standard admin pages, AJAX for setup/clone |
| **Routing** | Apache rewrite rules (`/site/{tenant_id}`) + domain mapping |
| **Cloning** | Database export/import + file sync over AJAX (6-step workflow) |
| **Distribution** | WordPress.org plugin + GitHub source |

## Version & Requirements
- **Current version:** 1.0.9
- **WordPress:** 5.0+ (tested to 6.9)
- **PHP:** 7.4+
- **License:** GPLv2 or later

## Unresolved Questions
- Test framework strategy (WP-CLI, PHPUnit, or integration fixtures)?
- Should Pro documentation stay on grabwp.com or merge into this repo?
