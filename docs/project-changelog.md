# Project Changelog

## Overview

This changelog tracks both repository documentation updates and code releases. Public plugin release notes remain in `README.md` and `readme.txt` (synced from here).

## 2026-05-14

### Updated

- `codebase-summary.md` — Accurate LOC metrics (8,848 total), clone architecture table, large file refactor roadmap, test framework decision options, key classes and hooks inventory.
- `system-architecture.md` — Expanded clone architecture with 6-step flow diagram, Pro architecture high-level summary, step-by-step process breakdown with chunk/batch sizes.

### Notes

- No code changes.
- Focus: Documentation accuracy for v1.0.9 state.

## 2026-05-11

### Updated

- `codebase-summary.md` — Updated LOC metrics (2026-05-11 scan), expanded test suite notes.
- `code-standards.md` — Verified large file LOC counts and refactoring rationale.
- `project-roadmap.md` — Clarified milestone status, documented decision gaps.
- `deployment-guide.md` — Added CI/CD absence note, reviewed release checklist.
- Other docs — Minor accuracy pass: verified hook lists, architecture flows, UI surfaces.

### Notes

- No production code changed.
- Documentation focus: accuracy verification against codebase, gap identification.

## 2026-04-20

### Added

- Initialized project documentation in `docs/`.
- Added project overview PDR.
- Added codebase summary.
- Added code standards.
- Added system architecture.
- Added project roadmap.
- Added deployment guide.
- Added design guidelines.

### Notes

- Documentation was generated from the current repository scan and README.
- No production code changed.

## Release Notes Reference

Latest releases (synced from `README.md` and `readme.txt`):

### v1.0.9 (2026-05)
- **Fix:** Tenant data directory moved to `wp-content/grabwp-tenancy/` (outside `uploads/`) — prevents direct web access; legacy paths (`wp-content/grabwp/`, `wp-content/uploads/grabwp-tenancy/`) auto-detected
- **Fix:** Clone and filesystem ops now safely handle symlinks via `GrabWP_Tenancy_Clone_Fs_Helper`
- **Enhance:** Admin bar Plugin/Theme nodes hidden on tenant sites when corresponding settings enabled
- **Enhance:** Tenant create page — auto-suggested domain, Clear button, fluid layout

### v1.0.8 (2026-04)
- **New:** Tenant cloning (6-step AJAX) — duplicate any tenant or mainsite to new tenant with DB copy + file sync
- **New:** `GRABWP_MAINSITE_ID` constant (`__mainsite__`) for using mainsite as clone source
- **Enhance:** Mainsite domain detection supports localhost and LAN domains with no TLD
- **Fix:** Plugin asset URL resolution with symlinked plugin directory
- **Quality:** Normalized line endings to LF

### v1.0.7
- Path-based routing, Status page UI, installer refactor, nonce security

## Unresolved Questions

- Should repository changelog and public release notes be auto-synced via release script?
