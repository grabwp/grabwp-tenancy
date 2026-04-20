# Project Roadmap

## Overview

Roadmap tracks the free GrabWP Tenancy plugin in this repository. Dates are not commitments unless attached to a release issue or milestone.

## Current Status

| Area | Status | Notes |
| --- | --- | --- |
| Shared MySQL tenancy | Complete | Tenant table prefix set during early bootstrap. |
| Domain routing | Complete | Domains map through `tenants.php`. |
| Path routing | Complete | `/site/{tenant_id}` rules and query fallback exist. |
| Tenant uploads isolation | Complete | Uploads stored under tenant data directory. |
| Tenant CRUD admin | Complete | Create, edit, delete, list, settings, status pages. |
| Base tenant clone | Complete | Six-step shared-MySQL clone flow. |
| Setup automation | Complete | MU-plugin, loader, rewrite, protection fixes. |
| Automated tests | Not started | No test suite detected. |
| File modularization | Partial | Some large legacy files remain. |

## Near-Term Priorities

| Priority | Work | Rationale |
| --- | --- | --- |
| High | Add smoke test harness for PHP syntax and core bootstrap functions. | Reduce release regression risk. |
| High | Add manual QA checklist for tenant creation, routing, clone, and status fixes. | Current validation is mostly manual. |
| Medium | Split admin form handling from `GrabWP_Tenancy_Admin`. | Reduce risk in 1000+ line controller. |
| Medium | Split status view into partials or render helpers. | Improve maintainability. |
| Medium | Document public hooks and Pro extension points. | Help extension development. |
| Low | Add architecture diagrams to docs. | Improve onboarding. |

## Suggested Milestones

### Milestone 1: Documentation Baseline

Status: Complete.

- Initialize `docs/`.
- Add overview, codebase summary, standards, architecture, roadmap, deployment, design, and changelog docs.
- Link docs from README.

### Milestone 2: Release Safety

Status: Planned.

- Add PHP syntax check script.
- Add WordPress manual QA checklist.
- Add minimal tests for pure validation helpers where feasible.
- Document release steps for WordPress.org.

### Milestone 3: Maintainability

Status: Planned.

- Extract admin form handlers.
- Extract status-page component rendering.
- Review repeated tenant ID validation logic.
- Preserve Pro extension compatibility.

### Milestone 4: Extension Documentation

Status: Planned.

- Catalog hooks and filters.
- Document Pro override function contracts.
- Add examples for tenant lifecycle hooks.

## Success Metrics

- New contributors can explain bootstrap flow from docs alone.
- Release checklist covers activation, routing, CRUD, clone, and deactivation.
- No PHP syntax errors before release.
- Large-file changes become smaller and easier to review over time.

## Unresolved Questions

- Which test approach should be adopted first: WP-CLI smoke tests, PHPUnit with WordPress test suite, or a local integration fixture?
