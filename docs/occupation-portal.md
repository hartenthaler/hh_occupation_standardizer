# Occupation Portal

Issue #38 describes a future portal page for each normalized occupation.
The portal should combine internal module data with external authority data.

## Milestones

The issue is split into smaller steps:

- #39: design page data model and navigation
- #40: add cached external occupation authority service
- #41: build the first portal page from internal module data
- #42: enrich the portal with Wikidata, FactGrid, GND, Wikipedia, and GenWiki

## Cache Strategy

External authority data must not be fetched repeatedly during ordinary page rendering.
The module therefore uses a local JSON file cache below:

```text
data/cache/hh_occupation_standardizer/<source>-<hash>.json
```

The initial TTL is 24 hours, matching the cache strategy used in `hh-historic-events`.
If the cache directory is not writable, the module should continue to work without storing the response.

The cache key is based on the full request URL. This keeps source-specific query parameters part of the cache identity.

Visitors may trigger cache misses. The external authority databases are public sources, and their public data may be displayed and cached by the module.
If an external source is queried or cached, the portal page must show a source reference for the displayed information.

## Page Scope and Visibility

The first occupation portal page is tree-specific.
It presents information about one normalized occupation in the active tree.

The first URL form uses the imported OhdAB concept id as an internal concept reference:

```text
/tree/{tree}/occupation-standardizer?view=occupation&source=ohdab&concept_id=<id>
```

The `concept_id` is the module-owned reference to the normalized occupation concept, comparable to an XREF in GEDCOM.

The first links to this page are rendered from:

- normalized occupation badges in the original occupation list
- normalized occupation badges in the people tab of the OhdAB hierarchy view

The page is visible to visitors and editors.
All tree-derived person and occupation data must still pass the ordinary webtrees visibility checks:

- show a person only when the current user may see that person
- show an occupation entry only when the current user may see that occupation fact
- do not leak hidden names, private facts, or restricted relationships through counts or lists

The portal should include a card with the list of people in the active tree who exercised this occupation.
This list is permission-filtered for the current user.

## External Sources

The planned external sources are:

- Wikidata
- FactGrid
- GND / DNB or lobid
- Wikipedia
- GenWiki

The second implementation fetches Wikidata and FactGrid entity data when a
normalized occupation concept contains the corresponding external identifier.
The raw JSON response is stored in the local cache and the portal displays a
small source-data table with the source label, description, source link, and
the Wikidata-derived Wikipedia link where available.

Full semantic interpretation of external ontology data is intentionally left to
later pull requests.

## Open Decisions

The following decisions still need explicit agreement:

- Whether Wikipedia summaries should be displayed or only linked.
- Which source has priority when labels or descriptions disagree.
- How GenWiki links should be discovered or maintained.
