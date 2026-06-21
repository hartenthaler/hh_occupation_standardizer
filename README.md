# webtrees module: Occupation Standardizer

[![License: GPL v3](https://img.shields.io/badge/License-GPL%20v3-blue.svg)](http://www.gnu.org/licenses/gpl-3.0)

![webtrees major version](https://img.shields.io/badge/webtrees-v2.2.x-green)

This [webtrees](https://www.webtrees.net) module helps analyze and standardize historical occupation entries in genealogical sources.

Current module version: **2.2.6.0**.

## Purpose

Historical church book entries often combine occupations, social status, offices, honorary offices, employers, and spelling variants in a single phrase.

This module supports the separation and standardization of these elements, for example:

* separating status from occupation, such as `Bürger und Weingärtner`
* normalizing spelling variants, such as `Kieffer` to `Küfer`, `Schuster` to `Schuhmacher`, or `Beck` to `Bäcker`
* treating craft grades such as master, journeyman, or apprentice as qualifiers rather than separate occupations
* keeping genuine master-compound occupations such as schoolmaster or mayor intact
* preserving the original wording from the source while showing the standardized form as an additional value

## Current functionality

The first milestone provides a read-only occupation inventory as a new item in the webtrees lists menu.

The list reads only individual `OCCU` facts and shows:

* original occupation text
* individual
* date
* place from `PLAC`, with the linked shared-place hierarchy from `_LOC` when available
* employer or responsible agency from `AGNC`
* `TYPE`
* `NOTE`
* linked sources

The module does not change GEDCOM data in this milestone.

The second milestone adds first automatic normalization suggestions. These suggestions are stored in a module-specific database table, one entry per detected occupation part. The GEDCOM data remains unchanged.

Managers and administrators can edit the stored normalization entries directly in the occupation list. Saving a correction does not automatically mark the entry as reviewed; the reviewed flag is an explicit decision. Manual changes are kept in the module table and do not modify GEDCOM data.

Administrators can maintain a site-wide normalization mapping table in the module settings. These rules can normalize language-specific variants such as feminine occupational forms and can store identifiers for HISCO, GND, and OhdAB.

The currently implemented normalization rules are documented in [docs/normalization-rules.md](docs/normalization-rules.md).
The module-owned database tables are documented in [docs/database-schema.md](docs/database-schema.md).

## Preliminary M4 workflow

M4 prepares the use of external occupation norm data, especially OhdAB and FactGrid. The intended provisional workflow is:

1. Run a webtrees family tree against the full OhdAB occupation database.
2. Extract only the occupation names that are relevant for this family tree into a tailored Excel file.
3. Import this tailored Excel file completely into the module.
4. Use the imported data as a local German norm source for occupation normalization.

This approach avoids importing the full OhdAB source into every webtrees installation. The full source is much larger and contains tens of thousands of occupation names, while a family-tree-specific extract can stay small, auditable, and practical for module-owned tables.

The tailored Excel file is currently expected to be German-language norm data. It can therefore only be applied to occupation terms whose language is `de`. The planned rule is "Normalize with external OhdAB special database". It should run after the local mapping table and before the fallback rule for unknown terms.

When the rule finds a match, the module should use the matched norm concept to add or update identifiers such as OhdAB and FactGrid, and to make the OhdAB hierarchy available without duplicating the hierarchy text in every occupation-list row.

One open question remains for new occupation terms that are later added to the webtrees family tree and are not yet present in the tailored Excel extract. A later M4 step needs a practical workflow for these individual additions, for example by searching the full OhdAB source on demand or by maintaining a small supplemental local mapping.

## Roadmap

* M1: OCCU inventory and read-only preview.
* M2: Local normalization rules, module-owned normalization table, occupation labels, and first manual editing of stored normalization entries.
* M3: Review refinements and reusable normalization rules, including extended editing of copied OCCU context fields and promoting manual corrections to reusable rules.
* M4: External norm data and exchange, including evaluation of FactGrid/OhdAB, Wikidata, GND, HISCO, licensing, hierarchy mapping, and export formats.
* M5: Integration with `webtrees-statistics` through normalized and aggregated occupation data instead of building separate statistics in this module.

## Scope

This module focuses on collecting, reviewing, and standardizing occupation data. Statistical charts should not be duplicated here. Once normalized occupation data is available, integration with Rico Sonntag's `webtrees-statistics` module can be considered through separate pull requests.

## Requirements

This module requires **webtrees** version 2.2.

## Installation

Copy the folder `hh_occupation_standardizer` into `webtrees/modules_v4` and enable the module in the webtrees control panel.

## Credits

Developed by Hermann Hartenthaler with support from OpenAI Codex and JetBrains PhpStorm.

## License

* Copyright (C) 2026 Hermann Hartenthaler
* Derived from **webtrees** - Copyright 2026 webtrees development team.

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
at your option, any later version.
