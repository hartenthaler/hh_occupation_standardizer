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
| `language` | `string(35)` nullable | Language tag of the original occupation text, e.g. `de` or `de-DE`. Initial value comes from the tree preference `LANGUAGE`; managers can override it per row. |
| `social_status` | `string(255)` nullable | Social status such as `Bürger` or `Witwe`. |
| `occupation_normalized` | `string(255)` nullable | Normalized occupation label shown as the main badge when present. |
| `office` | `string(255)` nullable | Office or honorary office, separated from occupation. |
| `qualification` | `string(255)` nullable | Qualification or craft grade such as `Meister`, `Geselle`, or `Lehrling`. |
| `code_hisco` | `string(64)` nullable | HISCO code. |
| `code_gnd` | `string(64)` nullable | GND identifier. |
| `code_ohdab` | `string(64)` nullable | OhdAB identifier. |
| `status` | `string(32)` | Normalization status, currently `recognized`, `unclear`, or `ignored`. |
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
| `normalized_key` | `string(255)` unique | Stable local normalized key used to link spelling variants. |
| `occupation_de_male` | `string(255)` nullable | German masculine form. |
| `occupation_de_female` | `string(255)` nullable | German feminine form. |
| `occupation_en_male` | `string(255)` nullable | English masculine form. |
| `occupation_en_female` | `string(255)` nullable | English feminine form. |
| `code_hisco` | `string(64)` nullable | HISCO code for the normalized term. |
| `code_gnd` | `string(64)` nullable | GND identifier for the normalized term. |
| `code_ohdab` | `string(64)` nullable | OhdAB identifier for the normalized term. |
| `created_at` | timestamp | Creation time of the term. |
| `updated_at` | timestamp nullable | Last update time. |

### Indexes

| Index | Columns | Purpose |
| --- | --- | --- |
| unique | `normalized_key` | Ensure one shared target term for one normalized key. |

## `occupation_standardizer_rules`

Stores site-wide normalization mapping rules maintained by administrators in
the module settings. These rules map a language-specific original text to one
normalized term in `occupation_standardizer_terms`. They are applied after
built-in local rules and before the fallback rule. They are intended for local
knowledge such as language-specific variants and spelling conventions.

The first seeded German examples are:

- `Ärztin` -> `Arzt`
- `Beck` -> `Bäcker`
- `Kieffer` -> `Küfer`
- `Orgelbauerin` -> `Orgelbauer`
- `Schuster` -> `Schuhmacher`

| Column | Type | Meaning |
| --- | --- | --- |
| `id` | auto-increment integer | Internal rule id. |
| `language` | `string(35)` | Language tag of the original text. A rule with `de` also matches a tree language such as `de-DE`. |
| `original_text` | `string(255)` | Original occupation text to match. Matching is case-insensitive. |
| `normalized_term_id` | integer nullable | Linked normalized term in `occupation_standardizer_terms`. |
| `social_status` | `string(255)` nullable | Social status to set. |
| `occupation_normalized` | `string(255)` nullable | Legacy cached normalized occupation used by older installations. New logic reads the linked term. |
| `qualification` | `string(255)` nullable | Qualification to set. |
| `code_hisco` | `string(64)` nullable | Legacy cached HISCO code. New logic reads the linked term. |
| `code_gnd` | `string(64)` nullable | Legacy cached GND identifier. New logic reads the linked term. |
| `code_ohdab` | `string(64)` nullable | Legacy cached OhdAB identifier. New logic reads the linked term. |
| `enabled` | boolean | Whether the rule is active. |
| `created_at` | timestamp | Creation time of the rule. |
| `updated_at` | timestamp nullable | Last update time. |

### Indexes

| Index | Columns | Purpose |
| --- | --- | --- |
| `idx_occ_std_rule_text` unique | `language`, `original_text` | Ensure one active mapping definition per language and original text. |

## Synchronization Notes

When a manager opens the occupation list, the module compares the current tree
fingerprint with the value in `occupation_standardizer_metadata`. If it changed,
the module scans current `INDI:OCCU` facts and upserts rows in
`occupation_standardizer_entries`.

Automatic synchronization updates copied context fields and automatic
normalization values only while an entry is neither reviewed nor manually
changed. This allows a manager to save partial edits without losing them during
the next synchronization.

If an `OCCU` fact or split part disappears, the corresponding row is marked
inactive. It is not immediately deleted.
