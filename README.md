# webtrees module: Occupation Standardizer

[![License: GPL v3](https://img.shields.io/badge/License-GPL%20v3-blue.svg)](http://www.gnu.org/licenses/gpl-3.0)

![webtrees major version](https://img.shields.io/badge/webtrees-v2.2.x-green)

This [webtrees](https://www.webtrees.net) module is intended to help administrators analyze and standardize historical occupation entries in genealogical sources.

Current module version: **2.2.6.0**.

## Purpose

Historical church book entries often combine occupations, social status, offices, honorary offices, and spelling variants in a single phrase.

This module will support the separation and standardization of these elements, for example:

* separating status from occupation, such as `Bürger und Weingärtner`
* normalizing spelling variants, such as `Kieffer` to `Küfer`, `Schuster` to `Schuhmacher`, or `Beck` to `Bäcker`
* treating craft grades such as master, journeyman, or apprentice as qualifiers rather than separate occupations
* keeping genuine master-compound occupations such as schoolmaster or mayor intact
* preserving the original wording from the source while showing the standardized form as an additional value

## Status

This repository is in its initial planning stage.

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
