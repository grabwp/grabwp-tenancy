# Project Roadmap

## Overview

Roadmap tracks the free GrabWP Tenancy plugin in this repository. Dates are not commitments unless attached to a release issue or milestone.

## Current Status (v1.0.9)

| Area | Status | Verified | Notes |
| --- | --- | --- | --- |
| Shared MySQL tenancy | Complete | v1.0.0 | Tenant table prefix set during early bootstrap. |
| Domain routing | Complete | v1.0.0 | Domains map through `tenants.php`. |
| Path routing | Complete | v1.0.7 | `/site/{tenant_id}` rules and query fallback working. |
| Tenant uploads isolation | Complete | v1.0.9 | Moved to `wp-content/grabwp-tenancy/` with legacy auto-migration. |
| Tenant CRUD admin | Complete | v1.0.0 | Create, edit, delete, list, settings, status pages. |
| Base tenant clone | Complete | v1.0.8 | 6-step shared-MySQL clone with AJAX polling. |
| Setup automation | Complete | v1.0.6 | MU-plugin, loader, .htaccess, protection file installers. |
| Symlink safety | Complete | v1.0.9 | Clone and filesystem ops handle symlinks safely. |
| Automated tests | Not started | — | No test suite present; framework selection pending M2. |
| File modularization | Partial | — | Large files documented; refactoring deferred to M3. |

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

Status: Complete (2026-04-20).

- Initialize `docs/`.
- Add overview, codebase summary, standards, architecture, roadmap, deployment, design, and changelog docs.
- Link docs from README.
- Completed by documentation initialization session.

### Milestone 2: Release Safety & Testing Foundation

Status: Planned (target: Q3 2026). Owners: TBD.

**Goals:**
- Establish test framework adoption
- Reduce regression risk before releases
- Document repeatable release process

**Tasks:**
- [ ] Add PHP syntax validation (GitHub Actions or release script)
- [ ] Create WordPress manual QA checklist: activation, routing, CRUD, clone, deactivation
- [ ] Evaluate test framework (WP-CLI smoke tests vs PHPUnit vs local integration fixtures)
- [ ] Adopt chosen framework with initial test suite (core bootstrap, tenant creation, clone)
- [ ] Document release steps: testing → version bump → changelog → WordPress.org submission

**Decision Required:** Which test framework minimizes setup complexity while covering critical paths?

### Milestone 3: Code Maintainability & Modularization

Status: Planned (target: Q3–Q4 2026). Owners: TBD. Coordination: Pro team required.

**Goals:**
- Reduce large file complexity (1000+ LOC classes)
- Preserve Pro extension compatibility
- Improve code review velocity

**Tasks:**
- [ ] Extract admin form handlers from `GrabWP_Tenancy_Admin` (1,076 LOC) into separate `Form_Handler` class
- [ ] Extract status-page component rendering from `status.php` (856 LOC) into PHP partials or render helpers
- [ ] Consolidate tenant ID validation logic (bootstrap vs path manager — check for duplication)
- [ ] Review `load-helper.php` (854 LOC) for bootstrap complexity — may need tenant detection refactor
- [ ] Audit all Pro extension points (`class_exists()` checks, action hooks) to ensure refactors remain compatible

**Coordination:** Pro team must review refactors to ensure override functions and hooks still work.

### Milestone 4: Extension API Documentation

Status: Planned (target: Q4 2026). Owners: TBD. Coordination: Pro team required.

**Goals:**
- Enable third-party extensions
- Document Pro internals for future plugins
- Create onboarding guide for extension developers

**Tasks:**
- [ ] Catalog all 20+ base hooks (action & filter) with signatures, return types, examples
- [ ] Document Pro override function contracts (e.g., `GrabWP_Tenancy_Pro_Clone`)
- [ ] Create extension developer guide: hook lifecycle, data model, best practices
- [ ] Add code examples for tenant lifecycle hooks (create, update, delete, clone)
- [ ] Document Pro extension points (where Pro can inject custom logic without base changes)
- [ ] Link API docs from grabwp.com and repository

**Coordination:** Pro team must review and confirm documented contracts match implementation.

## Success Metrics

| Metric | Target | Milestone |
| --- | --- | --- |
| New contributor onboarding time | < 2 hours to understand bootstrap | M1 ✓, M3, M4 |
| Release regression risk | Zero PHP syntax errors; 100% QA checklist coverage | M2 |
| Code review speed | Average PR review <= 1 day (blocked on file size) | M3 |
| Large file count | <= 3 files > 500 LOC (down from 6) | M3 |
| Extension adoption | >= 2 third-party plugins using documented hooks | M4 |

## Unresolved Questions

- **Test Framework:** WP-CLI smoke tests (lightweight, CLI), PHPUnit (standard), or local fixtures (custom, fast)?
- **Pro Coordination:** Should base repo document Pro internals, or should Pro have separate architecture docs?
- **Release Automation:** Manual changelog sync or automated release script linking base & public repos?
