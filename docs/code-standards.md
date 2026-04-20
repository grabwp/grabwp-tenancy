# Code Standards

## Overview

Follow WordPress plugin conventions and the existing GrabWP naming patterns. Prefer small, direct changes that preserve the early-bootstrap constraints.

## Naming

| Item | Pattern |
| --- | --- |
| Functions | `grabwp_tenancy_` prefix. |
| Classes | `GrabWP_Tenancy_` prefix. |
| Constants | `GRABWP_TENANCY_` prefix. |
| Hooks | `grabwp_tenancy_*` prefix. |
| PHP files | Existing WordPress class-file style, for example `class-grabwp-tenancy-admin.php`. |
| Documentation files | Kebab-case evergreen names in `docs/`. |

## PHP Style

- Guard direct access with `if ( ! defined( 'ABSPATH' ) ) { exit; }`.
- Use WordPress APIs where WordPress is loaded.
- In early bootstrap code, avoid WordPress APIs unless the function is guaranteed available.
- Sanitize request data before use.
- Unslash WordPress input data before sanitizing.
- Escape output in admin templates.
- Use nonces for admin forms and AJAX.
- Use capability checks before admin mutations.
- Keep Pro compatibility by preserving existing hooks and `function_exists()`/`class_exists()` extension checks.

## File Size Guidance

Several existing files exceed the 200-line preference because they group legacy WordPress admin or bootstrap behavior:

| File | Lines | Note |
| --- | ---: | --- |
| `includes/class-grabwp-tenancy-admin.php` | 1076 | Candidate for future form-handler/service split. |
| `admin/views/status.php` | 856 | Candidate for status-card partials. |
| `load-helper.php` | 854 | Early bootstrap file; split only with care because load order matters. |
| `includes/class-grabwp-tenancy-installer.php` | 660 | Candidate for setup component services. |
| `admin/class-grabwp-tenancy-list-table.php` | 550 | WordPress list-table implementation. |
| `grabwp-tenancy.php` | 502 | Main plugin runtime and tenant/main-site branching. |

Do not modularize as drive-by work. Split only when changing behavior in the file and when the boundary reduces risk.

## Security Standards

- Treat tenant ID as untrusted input until validated.
- Tenant ID format: exactly six lowercase alphanumeric characters.
- Keep reserved tenant ID list checks in sync between bootstrap and path manager fallback.
- Reject duplicate real domains across tenants.
- Skip `nodomain.local` in domain uniqueness checks because it is the path-only placeholder.
- Avoid following symlinks when deleting tenant directories.
- Never expose `tokens.php`, `settings.php`, or `tenants.php` through public web access.
- Do not commit environment files, credentials, API keys, DB dumps, or generated tenant data.

## Documentation Standards

- Keep evergreen docs in `docs/`.
- Start each markdown file with one H1.
- Sacrifice grammar for concision in reports.
- Put unresolved questions at the end when any exist.
- Update `docs/project-roadmap.md` and `docs/project-changelog.md` after significant implementation.

## Validation

For PHP changes, run at minimum:

```bash
find . -path './.git' -prune -o -name '*.php' -print | xargs -n1 php -l
```

For WordPress behavior changes, also validate manually in a WordPress install because this repo does not currently include automated tests.

## Unresolved Questions

- Should this repository adopt PHPCS/WordPress Coding Standards as a checked dependency?
