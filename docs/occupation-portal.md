# Occupation Profile Pages

The module provides a profile page for normalized occupations.
The page combines tree-specific module data with external authority data where identifiers are available.

## Page Scope and Visibility

An occupation profile page is specific to the active family tree.
It presents information about one normalized occupation concept and the visible occupation entries in that tree.

The current URL form uses the imported OhdAB concept id as an internal concept reference:

```text
/tree/{tree}/occupation-standardizer?view=occupation&source=ohdab&concept_id=<id>
```

The `concept_id` is the module-owned reference to the normalized occupation concept, comparable to an XREF in GEDCOM.

Links to this page are rendered from:

- normalized occupation badges in the original occupation list
- normalized occupation badges in the people tab of the OhdAB hierarchy view
- normalized occupation labels where a module-owned concept id is available

The page is visible to visitors, members, managers, and administrators, subject to the ordinary webtrees module and privacy checks.
All tree-derived person and occupation data must still pass the ordinary webtrees visibility checks:

- show a person only when the current user may see that person
- show an occupation entry only when the current user may see that occupation fact
- do not leak hidden names, private facts, or restricted relationships through lists

## Current Content

The page can show:

- normalized occupation details
- external identifiers and source data
- OhdAB hierarchy
- HISCO hierarchy
- visible individuals in the active tree who exercised this occupation
- original occupation variants mapped to this normalized occupation
- places
- time span statistics
- source references
- multilingual Wikipedia links and a language-appropriate introductory paragraph

The persons card is permission-filtered for the current user.

## External Sources

The currently supported external identifier families are:

- OhdAB
- HISCO
- FactGrid
- Wikidata
- GND / DNB

The implementation fetches Wikidata, FactGrid, and GND entity data when a normalized occupation concept or one of its visible occupation entries contains the corresponding external identifier.
The raw JSON response is stored in the local cache and the page displays a small source-data table with the source label, description, and source link.

All Wikipedia sitelinks returned by Wikidata are exposed as a language-and-link
list. Administrators can maintain a complete overriding list on normalized
terms, and managers can override it on individual normalization entries. An
entry-level override has priority over a term-level override; otherwise the
Wikidata list is used. A deliberately empty manually maintained list suppresses
the automatic links. The most recently discovered automatic list is also stored
with its non-managed state so that an administrator or manager can use it as the
starting point for manual maintenance.

Administrators can explicitly synchronize the automatic Wikipedia lists from
the module settings, either for one normalized term or for all terms. The
synchronization requires a Wikidata identifier and never overwrites a manually
maintained list. Its result message reports checked and updated terms, missing
Wikidata identifiers, terms without Wikipedia sitelinks, protected manual
lists, and external-service errors.

For the introductory paragraph, the module first looks for the primary user
interface language and then for English. If neither link exists, no Wikipedia
introduction is shown. The selected article is queried through the public
MediaWiki Action API using a plain-text introductory extract. Only the first
non-empty paragraph is displayed, together with its Wikipedia source link and
the applicable CC BY-SA 4.0 license notice.

GND identifiers are also linked to the GND Explorer relation view.
This is exposed as an external link, not embedded into the page.

HISCO hierarchy details are resolved locally from the bundled HISCO catalog tables.
OhdAB hierarchy details are resolved locally from the imported tailored OhdAB extract.
Where an imported OhdAB concept links to a FactGrid item, hierarchy labels may be resolved from the official FactGrid hierarchy items and cached with the same external authority cache.
If FactGrid cannot be reached or no matching hierarchy item is found, the module falls back to the imported OhdAB label.

## Cache Strategy

External authority data must not be fetched repeatedly during ordinary page rendering.
The module therefore uses a local JSON file cache below:

```text
data/cache/hh_occupation_standardizer/<source>-<hash>.json
```

The initial TTL for authority data is 24 hours, matching the cache strategy used
in `hh-historic-events`. Wikipedia introductory extracts are valid for 30 days.
If the cache directory is not writable, the module continues to work without storing the response.

The cache key is based on the full request URL.
This keeps source-specific query parameters part of the cache identity.

Visitors may trigger cache misses.
The external authority databases are public sources, and their public data may be displayed and cached by the module.
If an external source is queried or cached, the page must show a source reference for the displayed information.

When an expired cache entry cannot be refreshed because the external service is
temporarily unavailable, the module continues to display the last valid cached
data. Ordinary visitors do not see technical cache information. Managers and
administrators can open a collapsed status section showing the source, the last
successful retrieval, and whether the data is current, stale, partly
unavailable, or unavailable.

This status applies only to online services such as Wikidata, FactGrid, GND, and
Wikipedia. Locally imported HISCO, OhdAB, and GenWiki data are outside this
online-cache status.
