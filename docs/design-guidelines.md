# Design Guidelines

## Overview

The admin UI is a WordPress admin interface. Preserve WordPress conventions first. The product should feel native to `wp-admin`, not like a separate application embedded inside it.

## UI Principles

- Use WordPress admin page patterns, tables, notices, buttons, and form controls.
- Keep tenant management actions explicit and reversible where possible.
- Treat destructive actions as high-friction actions.
- Keep status/fix actions clear: what is broken, why it matters, and what the button changes.
- Avoid visual styles that conflict with WordPress core admin.

## Current UI Surfaces

| Surface | File |
| --- | --- |
| Tenant list | `admin/views/tenants.php`, `admin/class-grabwp-tenancy-list-table.php` |
| Create tenant | `admin/views/tenant-create.php` |
| Edit tenant | `admin/views/tenant-edit.php` |
| Settings | `admin/views/settings.php` |
| Status | `admin/views/status.php` |
| Clone tenant | `admin/views/tenant-clone.php` |
| Admin styles | `admin/css/grabwp-admin.css` |
| Admin interactions | `admin/js/grabwp-admin.js` |

## Interaction Standards

- Forms must include nonce fields.
- Redirect after successful or failed POST to prevent resubmission.
- Use WordPress notices for status feedback.
- For delete actions, require tenant ID confirmation.
- For clone actions, show progress step by step because the operation can be long-running.
- Limit dynamic domain inputs to the server-side maximum of 10 domains.

## Copy Standards

- Write short, direct labels.
- Avoid marketing copy in admin screens.
- For setup errors, state the missing component and the required fix.
- For destructive actions, state the consequence.

## Accessibility

- Use semantic form labels.
- Keep button text descriptive.
- Preserve keyboard focus behavior in WordPress admin.
- Do not rely on icon-only actions unless there is an accessible label/title.
- Admin notices should use WordPress notice classes.

## Visual Constraints

- Reuse the existing WordPress color vocabulary and admin layout spacing.
- Keep custom CSS scoped to GrabWP admin pages.
- Avoid large visual rewrites while changing backend behavior.
- Test at narrow admin widths because WordPress sidebars reduce content space.

## Unresolved Questions

- Should the status page be split into reusable PHP partials before larger UI changes?
