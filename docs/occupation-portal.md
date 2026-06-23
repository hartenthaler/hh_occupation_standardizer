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

## External Sources

The planned external sources are:

- Wikidata
- FactGrid
- GND / DNB or lobid
- Wikipedia
- GenWiki

The first implementation adds only the reusable cache and HTTP foundation.
Source-specific interpretation and display rules are intentionally left to later pull requests.

## Open Decisions

The following decisions still need explicit agreement:

- Whether visitors may trigger cache refreshes, or only managers.
- Whether Wikipedia summaries should be displayed or only linked.
- Which source has priority when labels or descriptions disagree.
- How GenWiki links should be discovered or maintained.
