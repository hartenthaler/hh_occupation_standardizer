# **webtrees** module: Occupation Standardizer

[![License: GPL v3](https://img.shields.io/badge/License-GPL%20v3-blue.svg)](http://www.gnu.org/licenses/gpl-3.0)

![webtrees major version](https://img.shields.io/badge/webtrees-v2.2.x-green)

This [webtrees](https://www.webtrees.net) module helps analyze, normalize, classify, and display historical occupation entries in genealogical sources.

This is a beta version. Do not use it in a productive system without careful testing.

<a name="Contents"></a>
## 📚 Contents

* [Purpose](#Purpose)
* [Main features](#MainFeatures)
* [User roles](#UserRoles)
* [Occupation landing page](#LandingPage)
* [Occupation list](#OccupationList)
* [Occupation labels](#OccupationLabels)
* [Occupation profile pages](#OccupationProfiles)
* [Occupation hierarchies](#OccupationHierarchies)
* [Administration](#Administration)
* [Data sources](#DataSources)
* [Screenshots](#Screenshots)
* [Documentation](#Documentation)
* [Requirements](#Requirements)
* [Installation](#Installation)
* [Translation](#Translation)
* [Credits](#Credits)
* [License](#License)

<a name="Purpose"></a>
## 🎯 Purpose

Historical church book entries and historical address books often combine occupations, social status, offices, 
honorary offices, employers, and qualifications in a single phrase.
For example, `Bürger und Weingärtner` contains both a social status and an occupation, while `Arztwitwe` may point to the former occupation of a deceased husband rather than to the occupation of the recorded woman.

Occupation names are among the most frequent person-specific details in genealogical sources.
They are useful for social-structural analysis, local history, economic history, medical history, and many other research questions.
Occupation classifications may focus on activity profiles and industries, education and qualification levels, or social prestige and social structures.

Occupation terms are also often gender-specific, for example `Magd`/`Knecht`, `Arzt`/`Ärztin`, or `Orgelbauer/in`.
For searching, grouping, and analysis, these variants should point to the same normalized occupation concept.
For display next to a concrete person, however, the module should use the appropriate gender-specific or neutral label where available.

The module is deliberately conservative: it does not change GEDCOM data automatically.
The original occupation text remains the genealogical source value.
Normalized interpretations are stored in module-owned database tables and can be reviewed separately.
A later transfer of selected module-owned information back into GEDCOM is intended, but the exact form and target structures still need to be clarified.

<a name="MainFeatures"></a>
## ⚙️ Main Features

The module currently provides:

* a new webtrees list-menu entry for occupations
* a landing page with entry points for occupation facts, OhdAB hierarchy, HISCO hierarchy, and a top-10 chart
* a read-only occupation overview for visitors and members
* editable normalization rows for managers and administrators
* occupation labels next to `INDI:OCCU` facts
* occupation profile pages for normalized occupations
* OhdAB hierarchy browsing based on an imported tailored OhdAB extract
* bundled HISCO catalog tables for local HISCO lookups
* HISCO hierarchy browsing based on stored HISCO identifiers
* links to external identifiers such as OhdAB, FactGrid, GND, Wikidata, and HISCO
* module settings for rules, tree languages, normalization mappings, norm-data import, and maintenance

The module currently focuses on individual `INDI:OCCU` facts.
Other possible places for occupation-related information, such as military rank, education, offices, or custom GEDCOM structures, are tracked separately.

<a name="UserRoles"></a>
## 👥 User Roles

**Visitors and members**

Visitors and members can use the list-menu entry and see occupation data that webtrees already allows them to see.
The module respects webtrees privacy checks for individuals and occupation facts.
They can browse the occupation landing page, occupation list, hierarchy views, and occupation profile pages.

**Managers**

Managers can open the occupation list for a tree and thereby create or synchronize the module-owned normalization rows for that tree.
They can edit stored normalization entries, copied OCCU context fields, identifiers, status values, and the explicit reviewed flag.
These edits affect only the module tables, not the GEDCOM data.

**Administrators**

Administrators can configure site-wide module behavior in the control panel.
They can maintain built-in rule order and activation, tree language defaults, local normalization terms and mapping rules, imported OhdAB data, and maintenance actions such as deleting module-owned rows for a selected tree.

<a name="LandingPage"></a>
## 🧭 Occupation Landing Page

The list-menu entry opens a landing page for the active family tree.
It provides three main action tiles:

* **Occupations** - opens the list of original occupation facts.
* **Occupation hierarchy (OhdAB)** - opens the hierarchy from imported OhdAB norm data.
* **Occupation hierarchy (HISCO)** - opens the hierarchy from the bundled HISCO catalog.

The landing page also shows a top-10 chart of the most common visible normalized occupation entries in the active family tree.
This chart is based on active module-owned normalization rows for the selected tree.

<a name="OccupationList"></a>
## 📋 Occupation List

The occupation list reads individual `OCCU` facts and shows:

* original occupation text
* individual
* date
* place from `PLAC`, with linked shared-place hierarchy from `_LOC` when available
* employer or responsible agency from `AGNC`
* `TYPE`
* `NOTE`
* linked sources
* normalization labels and, for managers, editable normalization entries

If no occupation facts exist in the selected family tree, the list remains available and shows a suitable message.

One original occupation phrase can create several module rows.
For example, a phrase can be split into separate entries for status, occupation, office, or qualification.
Copied context fields remain editable because, after splitting, a date, place, source, employer, or note may apply to only one of the interpreted parts.

<a name="OccupationLabels"></a>
## 🏷️ Occupation Labels

Labels are shown next to occupation facts on the standard facts-and-events tab and in supported Vesta fact views.
The label text is selected from the normalized occupation term.
If available, the module prefers gender-specific or neutral labels and chooses German or English according to the user's language.

The label tooltip can show:

* language
* normalized occupation
* German and English masculine, feminine, and neutral forms
* social status
* office
* qualification
* OhdAB hierarchy
* HISCO hierarchy
* external identifiers
* normalization status and applied rule numbers

Labels link to occupation profile pages when a normalized concept id is available.

<a name="OccupationProfiles"></a>
## 🧾 Occupation Profile Pages

Each normalized occupation can have a profile page for the active tree.
The URL uses the module's internal concept id, for example:

```text
/tree/<tree>/occupation-standardizer?view=occupation&source=ohdab&concept_id=<id>
```

The profile page combines internal module data and external authority information.
It can show:

* normalized occupation details
* external identifiers and source data
* OhdAB hierarchy
* HISCO hierarchy, when a HISCO code is available
* visible individuals in the active tree who exercised this occupation
* original occupation variants mapped to this normalized occupation
* places
* time span statistics
* source references

External authority information is cached in module-owned tables where applicable.
The profile page shows source references for displayed external data.

<a name="OccupationHierarchies"></a>
## 🌳 Occupation Hierarchies

**OhdAB hierarchy**

The OhdAB hierarchy is available after importing a tailored German OhdAB Excel extract.
The hierarchy browser starts at the top level and allows drilling down into lower levels.
For each visible level, the module shows occupation-entry counts and individual counts for the active family tree.
A persons tab lists visible individuals for the selected hierarchy entry.

**HISCO hierarchy**

The module ships a normalized English HISCO catalog in `resources/data/hisco`.
It is imported into local module tables on first use and is used to resolve HISCO identifiers without calling an external service.
The HISCO hierarchy browser shows major groups, minor groups, unit groups, and occupations.
For each selected level, matching persons are listed if their normalized occupation entries contain a HISCO identifier.

<a name="Administration"></a>
## 🛠️ Administration

The control-panel settings provide:

* built-in normalization rules with activation and ordering
* a reset action for the default rule order
* import of a tailored German OhdAB XLSX file
* OhdAB category statistics
* HISCO catalog table statistics
* HISCO category statistics
* normalized occupation terms with German and English gendered labels
* external identifiers for normalized occupation terms
* site-wide mapping rules from original text and language to normalized terms
* per-tree default occupation language
* module table statistics per family tree
* deletion of module-owned normalization data for a selected tree

Managers and administrators can edit normalization rows in the occupation list.
Administrators configure reusable rules and norm data in the control panel.

<a name="DataSources"></a>
## 🔗 Data Sources

**OhdAB**

The first M4 workflow supports a tailored German OhdAB Excel extract.
The uploaded file is imported into module-owned norm tables and then deleted.
The module stores original spellings, normalized concepts, FactGrid identifiers, and OhdAB hierarchy.
The rule "Normalize with external OhdAB special database" applies only to German occupation terms and runs after the local mapping table and before the fallback rule.

**HISCO**

The bundled HISCO catalog is based on:

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

The English source labels and descriptions are preserved.
Upper hierarchy levels are prepared for translated labels.

**External identifiers**

The module can store and display identifiers for:

* OhdAB
* HISCO
* FactGrid
* GND
* Wikidata

<a name="Screenshots"></a>
## 🖼 Screenshots

The screenshot shows the module settings in the webtrees control panel.

<p align="center"><img src="docs/img/control_panel.jpg" alt="Screenshot of Occupation Standardizer control panel" align="center" width="80%"></p>

<a name="Documentation"></a>
## 📖 Documentation

Additional documentation:

* [CHANGELOG.md](CHANGELOG.md) - release notes and noteworthy changes.
* [docs/normalization-rules.md](docs/normalization-rules.md) - implemented normalization rules.
* [docs/database-schema.md](docs/database-schema.md) - module-owned database tables.
* [docs/data-scope.md](docs/data-scope.md) - active-tree and site-wide data scope.
* [docs/occupation-portal.md](docs/occupation-portal.md) - occupation profile page concept and current scope.

Useful background information and norm data sources:

* [OhdAB database on FactGrid](https://database.factgrid.de/wiki/FactGrid:OhdAB-Datenbank) - historical occupation database used as an external norm source for German occupation terms.

<a name="Requirements"></a>
## 📌 Requirements

This module requires **webtrees** version 2.2.

<a name="Installation"></a>
## 📥 Installation

Install and use [Custom Module Manager](https://github.com/Jefferson49/CustomModuleManager) for an easy and convenient installation of **webtrees** custom modules.

* Open the Custom Module Manager view in **webtrees**, scroll to "Occupation Standardizer", and click the "Install Module" button.

**Manual installation**:

1. Make a database backup.
2. Download the [latest release](https://github.com/hartenthaler/hh_occupation_standardizer/releases/latest).
3. Unzip the package into your `webtrees/modules_v4` directory on your web server.
4. Rename the folder to `hh_occupation_standardizer`.

**Finish installation**:

1. Login to **webtrees** as administrator.
2. Go to <span class="pointer">Control Panel / Modules / Lists</span>.
3. Enable the module. It will be called "Occupation Standardizer".
4. Open the module settings.
5. Configure normalization rules, tree languages, optional OhdAB imports, and mapping tables.
6. Open the occupation landing page from the webtrees lists menu for each family tree.

<a name="Translation"></a>
## 🌍 Translation

The module is prepared for translation using gettext files in `resources/lang`.
Strings that are already translated by webtrees core are routed through the module's helper class and are intentionally not added to the module translation catalog.
Module-specific strings, including normalization status values such as `recognized`, `unclear`, and `ignored`, remain in the module catalog so translation tools can extract them reliably.

Normalization data itself can contain language-specific labels.
German and English masculine, feminine, and neutral occupation labels are supported for normalized occupation terms.

<a name="Credits"></a>
## 🙏 Credits

Developed by Hermann Hartenthaler with support from OpenAI Codex and JetBrains PhpStorm.

Special thanks to Katrin Moeller for creating a tree-specific Excel file after matching the occupation data with OhdAB.

<a name="License"></a>
## 📄 License

* Copyright (C) 2026 Hermann Hartenthaler
* Derived from **webtrees** - Copyright 2026 webtrees development team.

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
at your option, any later version.
