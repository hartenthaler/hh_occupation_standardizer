# Data scope

This module uses two different scopes.

## Active family tree

Views reached from the webtrees lists menu are scoped to the active family tree.

- The occupation list reads `INDI:OCCU` facts from the active tree only.
- The OhdAB occupation hierarchy shows module-owned occupation entries for the active tree only.
- Normalized occupation entry rows are stored with `tree_id` and are synchronized per tree.

## Website-wide settings

The module settings page is website-wide unless a family tree is explicitly selected or listed.

Website-wide data includes:

- imported OhdAB special database data
- normalized occupation terms
- normalization mapping rules
- enabled and ordered built-in normalization rules

Tree-specific settings and actions on the settings page are explicit:

- default occupation language per family tree
- statistics for existing module-owned table data per family tree
- deletion of module-owned table data for a selected family tree

This distinction is intentional. Shared normalization rules and reference data can be reused across trees, while original occupation facts and generated module entries remain tied to one tree.
