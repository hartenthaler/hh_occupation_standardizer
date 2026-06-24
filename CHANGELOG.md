# Changelog

All noteworthy changes to this module should be documented here.

Release notes on GitHub should be derived from the current `Unreleased` section.
After a release, move the entries from `Unreleased` into a versioned section and start a new empty `Unreleased` section.

## Unreleased

### Added

- Added a HISCO hierarchy tile to the occupation landing page.
- Added a HISCO hierarchy browser with major groups, minor groups, unit groups, occupations, counts, and matching visible individuals in the active family tree.

## 2.2.6.2 - 2026-06-24

### Added

- Added bundled HISCO catalog import into module-owned database tables.
- Added local HISCO lookup for normalized occupation entries with HISCO identifiers.
- Added HISCO hierarchy details to occupation labels and occupation profile pages.
- Added occupation profile pages for normalized occupations with visible individuals, external identifiers, source data, OhdAB hierarchy, HISCO hierarchy, places, periods, original occupation variants, and sources.
- Added cached external authority data for occupation profile pages.
- Added a top-10 chart for the most common visible normalized occupations on the occupation landing page.
- Added German translations for module-specific normalization labels and status values.

### Changed

- Updated the occupation landing page to serve as the primary entry point for occupation facts, hierarchy views, and summary information.
- Improved occupation labels so they prefer gender-specific or neutral German/English forms where available.
- Improved the handling of module-specific gettext strings so translation tools can extract them reliably.
- Documented release-note handling so future releases list substantial functional changes instead of only technical implementation details.

### Fixed

- Fixed missing translation of `Qualification`.
- Fixed missing translation of normalization status values such as `recognized`.
