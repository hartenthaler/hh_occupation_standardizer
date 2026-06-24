# Changelog

All noteworthy changes to this module should be documented here.

Release notes on GitHub should be derived from the current `Unreleased` section.
After a release, move the entries from `Unreleased` into a versioned section and start a new empty `Unreleased` section.

## Unreleased

### Added

- Added a HISCO hierarchy tile to the occupation landing page.
- Added a HISCO hierarchy browser with major groups, minor groups, unit groups, occupations, counts, and matching visible individuals in the active family tree.

### Changed

- Changed OhdAB links to use the FactGrid item of the imported OhdAB concept.
- Show English labels for OhdAB top-level hierarchy categories when the user interface is not German.
- Show imported HISCO descriptions as hover text in the HISCO hierarchy browser.
- Split the landing-page top-10 chart into separate OhdAB A and B charts.
- Simplified the settings rule active checkbox and replaced browser-localized file input text for the OhdAB import.

### Fixed

- Count HISCO hierarchy entries when the HISCO code is stored on the normalized term instead of the copied entry row.
- Count HISCO settings statistics from normalized term HISCO codes when copied entry rows do not carry their own code.
- Detect OhdAB top-level category labels more robustly for English settings statistics.
- Prefix OhdAB hierarchy labels with their A/B code and avoid overly broad English fallback labels for A subcategories.

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

- Several translations.
