# Changelog

All noteworthy changes to this module should be documented here.

Release notes on GitHub should be derived from the current `Unreleased` section.
After a release, move the entries from `Unreleased` into a versioned section and start a new empty `Unreleased` section.

## Unreleased

### Added

- Added per-term and bulk administrator actions to synchronize automatic Wikipedia language links from Wikidata.
- Added structured privacy notices for Wikimedia, FactGrid, and lobid-gnd together with documentation of server-side data transfers and local caching.

### Changed

- Display Wikipedia language links in a compact striped table with one shared header row.

### Fixed

- Normalize Wikidata identifiers, derive Wikipedia URLs from sitelink titles when necessary, and report identifiers for which no Wikipedia sitelinks were found.
- Added a Wikimedia-compatible user agent and exposed concrete external-service errors for Wikipedia synchronization and profile summaries.
- Fixed saving manually maintained Wikipedia link lists for normalized terms and replaced misleading empty URL placeholders with an explicit no-links status.

## 2.2.6.5 - 2026-06-27

### Fixed

- Removed the remaining automatic repair update for existing seeded normalization rules from the module boot path, preventing lock wait timeouts when legacy rules have no normalized-term reference.

## 2.2.6.4 - 2026-06-27

### Added

- Added multilingual Wikipedia link lists with manual overrides on normalized terms and individual normalization entries.
- Added language-sensitive Wikipedia introductory paragraphs with a 30-day cache and English fallback.
- Added manager-only external-data status information and stale-cache fallback during temporary service failures.
- Added checksum-based import of bundled GenWiki occupation links and display matching links on occupation profile pages.
- Added a linked, collision-free top-20 word cloud of visible normalized occupations to the landing page.
- Added an admin action to re-run automatic normalization for a selected family tree while preserving reviewed and manually changed entries.
- Added trigger counters for built-in normalization rules in the admin settings.
- Added a list/chart switch to the occupation profile place card.
- Added a list/timeline switch to the occupation profile period card.
- Added a coordinate-based map view for linked `_LOC` places on occupation profile pages.
- Added a period histogram for occupation profile pages.

### Fixed

- Avoid rewriting seeded normalization terms during every module boot, preventing lock wait timeouts on ordinary page requests.
- Read XLSX shared and inline strings without relying on inherited XML namespace prefixes.
- Updated the inheritance-analysis explanation now that the graphic exists.
- Gave the inheritance-flow labels more horizontal space and optional two-line wrapping.
- Open the place card with the density chart and the period card with the timeline by default.
- Clarify hierarchy subcategory summaries by naming the current hierarchy level or category.
- Translate the social-status inheritance button in the module context.

## 2.2.6.3 - 2026-06-25

### Added

- Added curated German labels and descriptions for HISCO major, minor, and unit groups with checksum-based import.
- Added a HISCO hierarchy tile to the occupation landing page.
- Added a HISCO hierarchy browser with major groups, minor groups, unit groups, occupations, counts, and matching visible individuals in the active family tree.
- Added a dedicated frequency-analysis tile for top-10 occupation and social-status charts.
- Added a graphical top-10 and tabular inheritance analysis for occupation and social-status entries between parents and children, with selectable normalized, OhdAB, and HISCO analysis levels.

### Changed

- Changed OhdAB links to use the FactGrid item of the imported OhdAB concept.
- Prefer cached FactGrid labels for OhdAB hierarchy labels where a linked FactGrid hierarchy item can be resolved.
- Show English labels for OhdAB top-level hierarchy categories when the user interface is not German.
- Show imported HISCO descriptions as hover text in the HISCO hierarchy browser.
- Split the landing-page top-10 chart into separate OhdAB A and B charts.
- Simplified the settings rule active checkbox and replaced browser-localized file input text for the OhdAB import.

### Fixed

- Count HISCO hierarchy entries when the HISCO code is stored on the normalized term instead of the copied entry row.
- Count HISCO settings statistics from normalized term HISCO codes when copied entry rows do not carry their own code.
- Detect OhdAB top-level category labels more robustly for English settings statistics.
- Prefix OhdAB hierarchy labels with their A/B code and avoid overly broad English fallback labels for A subcategories.
- Use localized normalized term labels in the landing-page top-10 occupation charts.
- Use cached FactGrid concept labels as fallback labels in the landing-page top-10 occupation charts.
- Avoid duplicated OhdAB codes in hierarchy output and occupation-label tooltips.
- Show consistent cross-navigation buttons between the occupation list, OhdAB hierarchy, and HISCO hierarchy views.
- Style cross-navigation buttons as a recognizable navigation bar.
- Reduce the label size in the inheritance-flow chart for better readability.

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
