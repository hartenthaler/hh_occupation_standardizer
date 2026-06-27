# Database Schema

This module stores normalization data in its own tables. It does not change
GEDCOM data while collecting, normalizing, reviewing, or editing occupation
entries.

Table names are shown without a webtrees database prefix. In a configured
installation, webtrees may add its configured table prefix.

## `occupation_standardizer_metadata`

Stores small module-internal settings.

| Column | Type | Meaning |
| --- | --- | --- |
| `setting_name` | `string(64)` primary key | Name of the module setting. |
| `setting_value` | `text` | Stored value. |

Current use:

| Setting pattern | Meaning |
| --- | --- |
| `tree_occu_<tree_id>` | Fingerprint of all current `INDI:OCCU` facts in one tree. If this fingerprint has not changed, synchronization can skip rebuilding normalization rows. |
| `treeLanguage-<tree_id>` | Default language in which occupation facts are described in one tree. This is maintained in the module settings and used when new normalization rows are created. |
| `hisco_catalog_hash` | SHA-1 fingerprint of the bundled HISCO CSV catalog. If this changes, the bundled catalog is imported again. |
| `genwiki_occupation_hash` | SHA-1 fingerprint of the bundled GenWiki occupation-link workbook. If this changes, the links are imported again. |

Changing the site-managed normalization mapping table clears these fingerprints
so the next manager visit can resynchronize affected occupation rows.

## `occupation_standardizer_entries`

Stores one normalization row for each detected part of a GEDCOM `INDI:OCCU`
fact. If one original occupation fact is split into multiple parts, each part
gets its own row.

The original GEDCOM fact remains authoritative. These rows are module-owned
working data for display, review, correction, and later export.

### Identity and source context

| Column | Type | Meaning |
| --- | --- | --- |
| `id` | auto-increment integer | Internal row id. |
| `entry_key` | `char(40)` unique | Stable SHA-1 key built from tree id, individual xref, fact id, and part index. |
| `tree_id` | integer | webtrees tree id (`gedcom.gedcom_id`). |
| `individual_xref` | `string(32)` | XREF of the individual record containing the `OCCU` fact. |
| `fact_id` | `string(128)` | webtrees fact id for the `OCCU` fact. |
| `part_index` | integer | Zero-based index of the normalized part within the split original fact. |
| `original_fact_text` | `text` | Complete original `OCCU` value. |
| `original_part_text` | `text` | Original text of the split part represented by this row. |

### Copied GEDCOM substructures

These values are copied from the original GEDCOM fact for context. They are not
written back to GEDCOM by the module.

| Column | Type | Meaning |
| --- | --- | --- |
| `date` | `string(255)` nullable | GEDCOM `DATE` below the `OCCU` fact. |
| `place` | `text` nullable | GEDCOM `PLAC` below the `OCCU` fact. |
| `location_xref` | `string(32)` nullable | GEDCOM `_LOC` xref below `PLAC`, if the place links to a shared place record. |
| `location_hierarchy` | `text` nullable | Resolved shared-place hierarchy at the event date, if available. |
| `employer` | `text` nullable | GEDCOM `AGNC` below the `OCCU` fact. |
| `type` | `text` nullable | GEDCOM `TYPE` below the `OCCU` fact. |
| `note` | `text` nullable | Inline GEDCOM `NOTE` below the `OCCU` fact. |
| `source_xrefs` | `text` nullable | Semicolon-separated source XREFs from `SOUR` links. |
| `source_names` | `text` nullable | Semicolon-separated source names shown to the user. |

### Normalization fields

| Column | Type | Meaning |
| --- | --- | --- |
| `language` | `string(35)` nullable | Language tag of the original occupation text, e.g. `de` or `de-DE`. Initial value comes from the module's per-tree occupation language setting; managers can override it per row. |
| `social_status` | `string(255)` nullable | Social status such as `Bürger` or `Witwe`. |
| `occupation_normalized` | `string(255)` nullable | Normalized fallback label. The displayed badge prefers the language- and gender-specific forms when present. |
| `occupation_de_male` | `string(255)` nullable | German masculine form copied from the normalized term. |
| `occupation_de_female` | `string(255)` nullable | German feminine form copied from the normalized term. |
| `occupation_de_neutral` | `string(255)` nullable | German neutral form copied from the normalized term. This is preferred for sex `X` or `U`. |
| `occupation_en_male` | `string(255)` nullable | English masculine form copied from the normalized term. |
| `occupation_en_female` | `string(255)` nullable | English feminine form copied from the normalized term. |
| `occupation_en_neutral` | `string(255)` nullable | English neutral form copied from the normalized term. This is preferred for sex `X` or `U`. |
| `office` | `string(255)` nullable | Office or honorary office, separated from occupation. |
| `qualification` | `string(255)` nullable | Qualification or craft grade such as `Meister`, `Geselle`, or `Lehrling`. |
| `code_hisco` | `string(64)` nullable | HISCO code. |
| `code_gnd` | `string(64)` nullable | GND identifier. |
| `code_ohdab` | `string(64)` nullable | OhdAB identifier. |
| `code_factgrid` | `string(64)` nullable | FactGrid item id, e.g. `Q699480`. |
| `code_wikidata` | `string(64)` nullable | Wikidata item id, e.g. `Q28640`. |
| `wikipedia_links` | `text` nullable | JSON list of Wikipedia language codes and article URLs; stores either the latest automatic list or a manual override. |
| `wikipedia_links_managed` | boolean | Whether the stored list fully overrides automatically discovered Wikidata sitelinks. |
| `norm_concept_id` | integer nullable | Linked imported norm concept, currently used for the tailored German OhdAB special database. |
| `status` | `string(32)` | Normalization status, currently `recognized`, `unclear`, or `ignored`. These values are stored as stable internal keys and translated for display. |
| `reviewed` | boolean | Explicit reviewer decision. Saving a manual edit does not automatically set this flag. |
| `manually_changed` | boolean | Internal flag that protects unfinished manual edits from being overwritten by later automatic synchronization. |
| `is_active` | boolean | Whether the row still corresponds to a currently existing `OCCU` part. Deleted or changed GEDCOM facts leave inactive rows instead of immediately deleting review history. |
| `rule_numbers` | `string(255)` | Rule ids that produced the current automatic value, e.g. `M2-R050`. |

### Timestamps

| Column | Type | Meaning |
| --- | --- | --- |
| `created_at` | timestamp | Creation time of the row. |
| `updated_at` | timestamp nullable | Last update time. |
| `last_seen_at` | timestamp nullable | Last synchronization time when the corresponding `OCCU` part was still present. |

### Indexes

| Index | Columns | Purpose |
| --- | --- | --- |
| unique | `entry_key` | Prevent duplicate rows for one normalized part. |
| `idx_occ_std_indi` | `tree_id`, `individual_xref` | Find rows for one person in one tree. |
| `idx_occ_std_fact` | `tree_id`, `fact_id` | Find rows for one fact in one tree. |
| `idx_occ_std_status` | `tree_id`, `status` | Filter by normalization status. |
| `idx_occ_std_active` | `tree_id`, `is_active` | Filter current and inactive rows. |

## `occupation_standardizer_terms`

Stores normalized occupation terms. A term is the shared normalized target for
one or more original spelling variants. Language-specific and gendered labels
as well as external identifiers belong here, not on the individual mapping rule.

| Column | Type | Meaning |
| --- | --- | --- |
| `id` | auto-increment integer | Internal normalized term id. |
| `language` | `string(35)` nullable | Language tag of the normalized occupational terminology, e.g. `de` or `en`. This will later select the matching normalization ontology. |
| `normalized_key` | `string(255)` unique | Derived internal key built from `language` and the language-specific masculine form, e.g. `de:Arzt` or `en:Minister`. |
| `occupation_de_male` | `string(255)` nullable | German masculine form. |
| `occupation_de_female` | `string(255)` nullable | German feminine form. |
| `occupation_de_neutral` | `string(255)` nullable | German neutral form. |
| `occupation_en_male` | `string(255)` nullable | English masculine form. |
| `occupation_en_female` | `string(255)` nullable | English feminine form. |
| `occupation_en_neutral` | `string(255)` nullable | English neutral form. |
| `code_hisco` | `string(64)` nullable | HISCO code for the normalized term. |
| `code_gnd` | `string(64)` nullable | GND identifier for the normalized term. |
| `code_ohdab` | `string(64)` nullable | OhdAB identifier for the normalized term. |
| `code_factgrid` | `string(64)` nullable | FactGrid item id for the normalized term. |
| `code_wikidata` | `string(64)` nullable | Wikidata item id for the normalized term. |
| `wikipedia_links` | `text` nullable | JSON list of Wikipedia language codes and article URLs; stores either the latest automatic list or a manual override. |
| `wikipedia_links_managed` | boolean | Whether the stored list fully overrides automatically discovered Wikidata sitelinks. |
| `created_at` | timestamp | Creation time of the term. |
| `updated_at` | timestamp nullable | Last update time. |

### Indexes

| Index | Columns | Purpose |
| --- | --- | --- |
| unique | `normalized_key` | Ensure one shared target term for each combination of language and language-specific masculine form. |

The logical key of a normalized term is the combination of `language` and the
masculine form in that language. This is important because the same label can
refer to different occupational concepts in different languages. For example,
`Minister` in German and `Minister` in English may later be linked to different
normalization ontologies.

### External Identifier URLs

The module keeps URL patterns for external identifiers in
`ExternalIdentifierService`. The code value is stored in the database; the URL
is generated for display.

| Identifier | URL pattern |
| --- | --- |
| FactGrid | `https://database.factgrid.de/wiki/Item:<code>` |
| GND | `https://d-nb.info/gnd/<code>` |
| HISCO | `https://druid.datalegend.net/HistoryOfWork/HISCO-latest/browser?resource=https%3A%2F%2Fiisg.amsterdam%2Fresource%2Fhisco%2Fcode%2Fhisco%2F<code>` |
| OhdAB | Use the FactGrid item id for the imported OhdAB concept: `https://database.factgrid.de/wiki/Item:<factgrid_id>`. FactGrid may contain both the OhdAB concept item and a more general historical occupation item; the OhdAB concept item is the relevant target for OhdAB links. |

## Bundled HISCO Catalog Tables

The module ships normalized HISCO CSV files in `resources/data/hisco`.
They are imported into module-owned tables on first use and re-imported when
their bundled CSV fingerprint changes.

Source:

```bibtex
@data{JA9B8O_2016,
author = {Van Leeuwen},
publisher = {IISH Data Collection},
title = {{Files from HISCO database}},
UNF = {UNF:6:P/x7e56FlwNkplEB7kJWiQ==},
year = {2016},
version = {V2},
doi = {10622/JA9B8O},
url = {https://hdl.handle.net/10622/JA9B8O}
}
```

The cited source provides the HISCO data as a downloaded source table. For this
module, that table was normalized into separate CSV files in
`resources/data/hisco` (in this case with assistance from Claude). During this
preparation step the single source table was split into major groups, minor
groups, unit groups, and occupation rows, and the corresponding module-owned
database schema was derived from that structure.

### `occupation_standardizer_hisco_major_groups`

| Column | Type | Meaning |
| --- | --- | --- |
| `major_id` | unsigned tiny integer primary key | HISCO major group id. |
| `label_en` | `string(255)` | Original English major group label. |
| `label_de` | `string(255)` nullable | Optional German major group label. |
| `description_en` | `text` | Original English description. |
| `description_de` | `text` nullable | Optional German major group description. |
| `updated_at` | timestamp nullable | Last import/update time. |

### `occupation_standardizer_hisco_minor_groups`

| Column | Type | Meaning |
| --- | --- | --- |
| `minor_id` | unsigned tiny integer primary key | HISCO minor group id. |
| `major_id` | unsigned tiny integer | Parent major group id. |
| `label_en` | `string(255)` | Original English minor group label. |
| `label_de` | `string(255)` nullable | Optional German minor group label. |
| `description_en` | `text` | Original English description. |
| `description_de` | `text` nullable | Optional German minor group description. |
| `updated_at` | timestamp nullable | Last import/update time. |

### `occupation_standardizer_hisco_unit_groups`

| Column | Type | Meaning |
| --- | --- | --- |
| `unit_id` | unsigned small integer primary key | HISCO unit group id. |
| `minor_id` | unsigned tiny integer | Parent minor group id. |
| `label_en` | `string(255)` | Original English unit group label. |
| `label_de` | `string(255)` nullable | Optional German unit group label. |
| `description_en` | `text` | Original English description. |
| `description_de` | `text` nullable | Optional German unit group description. |
| `updated_at` | timestamp nullable | Last import/update time. |

### `occupation_standardizer_hisco_occupations`

| Column | Type | Meaning |
| --- | --- | --- |
| `hisco_id` | unsigned medium integer primary key | Five-digit HISCO occupation code without punctuation. |
| `unit_id` | unsigned small integer | Parent unit group id. |
| `micro_suffix` | unsigned tiny integer | Last two digits of the HISCO occupation code. |
| `hisco_pretty` | `string(10)` | Book notation such as `9-41.60`. |
| `label_en` | `string(255)` | Original English occupation label. |
| `description_en` | `text` | Original English occupation description. |
| `updated_at` | timestamp nullable | Last import/update time. |

## Bundled GenWiki Occupation Links

The workbook `resources/data/GenWiki/Berufe_GenWiki.xlsx` contains links to
occupation descriptions in the German-language GenWiki. Columns `Beruf` and
`Link` are imported on first use and whenever the workbook checksum changes.
The embedded hyperlinks in the first workbook column are intentionally not
required by the importer.

### `occupation_standardizer_genwiki_occupations`

| Column | Type | Meaning |
| --- | --- | --- |
| `occupation_text` | `string(255)` primary key | German occupation name used for matching a normalized occupation. |
| `genwiki_url` | `text` | Link to the corresponding GenWiki occupation description. |

## `occupation_standardizer_rules`

Stores site-wide normalization mapping rules maintained by administrators in
the module settings. These rules map a language-specific original text to one
normalized term in `occupation_standardizer_terms`. They are applied after
built-in local rules and before the fallback rule. They are intended for local
knowledge such as language-specific variants and spelling conventions.

The first seeded German examples are:

- `Ärztin` / `de` -> `Arzt`
- `Beck` / `de` -> `Bäcker`
- `Kieffer` / `de` -> `Küfer`
- `Orgelbauerin` / `de` -> `Orgelbauer`
- `Schuster` / `de` -> `Schuhmacher`

The language is part of both the mapping rule key and the normalized term key.
This matters when the same text can mean different things in different
languages. For example, `Minister` / `de` can describe a government office,
while `Minister` / `en` can describe a clergyman.

| Column | Type | Meaning |
| --- | --- | --- |
| `id` | auto-increment integer | Internal rule id. |
| `language` | `string(35)` | Language tag of the original text. A rule with `de` also matches a tree language such as `de-DE`. |
| `original_text` | `string(255)` | Original occupation text to match. Matching is case-insensitive. |
| `normalized_term_id` | integer nullable | Linked normalized term in `occupation_standardizer_terms`. |
| `social_status` | `string(255)` nullable | Social status to set. |
| `qualification` | `string(255)` nullable | Qualification to set. |
| `enabled` | boolean | Whether the rule is active. |
| `created_at` | timestamp | Creation time of the rule. |
| `updated_at` | timestamp nullable | Last update time. |

### Indexes

| Index | Columns | Purpose |
| --- | --- | --- |
| `idx_occ_std_rule_text` unique | `language`, `original_text` | Ensure one active mapping definition per language and original text. |

## Imported Norm Data Tables

The M4 prototype imports a tailored German OhdAB Excel file uploaded in the
module settings. The uploaded file is used only as temporary input and is
deleted after the import. The imported norm data is stored in the module-owned
database tables below.

The import separates source metadata, original spelling variants, normalized
concepts, and hierarchy nodes. This keeps the redundant OhdAB category labels
out of the occupation entries table.

### `occupation_standardizer_norm_sources`

Stores one imported norm source.

| Column | Type | Meaning |
| --- | --- | --- |
| `id` | auto-increment integer | Internal source id. |
| `source_key` | `string(64)` unique | Stable source key, currently `ohdab_special_de`. |
| `label` | `string(255)` | Human-readable source label. |
| `language` | `string(35)` | Source language, currently `de`. |
| `file_name` | `string(255)` nullable | Imported file name. |
| `file_hash` | `char(40)` nullable | SHA-1 hash used to skip unchanged imports. |
| `row_count` | integer | Number of imported usable rows. |
| `imported_at` | timestamp nullable | Last successful import time. |
| `created_at` | timestamp | Creation time. |
| `updated_at` | timestamp nullable | Last update time. |

### `occupation_standardizer_norm_concepts`

Stores normalized occupation concepts from the imported source.

| Column | Type | Meaning |
| --- | --- | --- |
| `id` | auto-increment integer | Internal concept id. |
| `source_id` | integer | Imported norm source. |
| `language` | `string(35)` | Concept language. |
| `preferred_label` | `string(255)` | Preferred source label. |
| `occupation_de_male` | `string(255)` nullable | German masculine form derived from the preferred label where possible. |
| `occupation_de_female` | `string(255)` nullable | German feminine form derived from the preferred label where possible. |
| `occupation_de_neutral` | `string(255)` nullable | German neutral form from the preferred label. |
| `ohdab_full_id` | `string(64)` | Full OhdAB id from the import. |
| `ohdab_class` | `string(8)` nullable | OhdAB top-level class, e.g. `A` or `B`. |
| `ohdab_group` | `string(32)` nullable | OhdAB group code. |
| `ohdab_individual` | `string(32)` nullable | OhdAB individual code from the import. |
| `factgrid_id` | `string(64)` nullable | FactGrid item id, e.g. `Q699480`. |
| `requirement_level` | `string(32)` nullable | Requirement level from the import. |
| `requirement_label` | `string(255)` nullable | Requirement label from the import. |
| `created_at` | timestamp | Creation time. |
| `updated_at` | timestamp nullable | Last update time. |

### `occupation_standardizer_norm_variants`

Maps imported original spellings to normalized concepts.

| Column | Type | Meaning |
| --- | --- | --- |
| `id` | auto-increment integer | Internal variant id. |
| `source_id` | integer | Imported norm source. |
| `concept_id` | integer | Linked normalized concept. |
| `language` | `string(35)` | Variant language. |
| `original_text` | `string(255)` | Original spelling from the import. |
| `original_key` | `string(255)` | Case-insensitive normalized match key. |
| `created_at` | timestamp | Creation time. |
| `updated_at` | timestamp nullable | Last update time. |

### `occupation_standardizer_norm_hierarchy_nodes`

Stores each imported OhdAB hierarchy node once.

| Column | Type | Meaning |
| --- | --- | --- |
| `id` | auto-increment integer | Internal hierarchy node id. |
| `source_id` | integer | Imported norm source. |
| `language` | `string(35)` | Hierarchy language. |
| `level` | integer | Hierarchy depth. |
| `code` | `string(64)` | OhdAB hierarchy code for this level. |
| `label` | `text` | Hierarchy label. |
| `parent_id` | integer nullable | Parent hierarchy node. |
| `created_at` | timestamp | Creation time. |
| `updated_at` | timestamp nullable | Last update time. |

### `occupation_standardizer_norm_concept_hierarchy`

Links imported concepts to their OhdAB hierarchy path.

| Column | Type | Meaning |
| --- | --- | --- |
| `id` | auto-increment integer | Internal link id. |
| `concept_id` | integer | Linked normalized concept. |
| `node_id` | integer | Linked hierarchy node. |
| `position` | integer | Position in the concept's hierarchy path. |

## Synchronization Notes

When a manager opens the occupation list, the module compares the current tree
fingerprint with the value in `occupation_standardizer_metadata`. If it changed,
the module scans current `INDI:OCCU` facts and upserts rows in
`occupation_standardizer_entries`.

Automatic synchronization updates copied context fields and automatic
normalization values only while an entry is neither reviewed nor manually
changed. Once a manager edits an entry, copied context fields such as date,
place, employer, TYPE, NOTE, and source references are protected from later
automatic overwrites. This allows a manager to save partial edits without
losing them during the next synchronization.

If an `OCCU` fact or split part disappears, the corresponding row is marked
inactive. It is not immediately deleted.

When the imported OhdAB special database changes, the module clears the stored
tree fingerprints. The next manager visit can then refresh automatic
normalization results while preserving reviewed or manually changed entries.
