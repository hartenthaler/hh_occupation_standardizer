# HISCO catalog data

This directory contains a normalized copy of the English HISCO catalog used by
the module for local lookup of HISCO identifiers.

## Source

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

The source data was downloaded from the IISH Data Collection and normalized
into separate CSV files before being added to this module.

## Files

- `hisco_major_group.csv`: top-level HISCO major groups
- `hisco_minor_group.csv`: HISCO minor groups
- `hisco_unit_group.csv`: HISCO unit groups
- `hisco_occupation.csv`: individual HISCO occupations
- `hisco_hierarchy_de.csv`: curated German labels and descriptions for major, minor, and unit groups
- `hisco_hierarchy_de_notes.md`: translation notes for the German hierarchy file
- `hisco_hiscam_occ1950.xlsx`: unique HISCO mappings to HISCAM U1, HISCAM NL, and OCC1950
- `hisco_hisclass.xlsx`: unique HISCO mappings to HISCLASS and HISCLASS 5
- `hisco_schema.sql`: documentation of the module-specific database structure

The module imports these files into its own database tables on first use. The
English original labels and descriptions are preserved. German hierarchy labels
and descriptions are stored separately and are reimported only when the checksum
of `hisco_hierarchy_de.csv` changes. The two classification workbooks have a
separate combined fingerprint and are reimported whenever either workbook
changes. The source value `-9` is treated as missing.

## Classification schemes

**HISCLASS** is a categorical scheme comprising twelve social classes, ranging
from higher managers to unskilled farm workers. It maps HISCO codes according
to dimensions such as manual or non-manual work, skill level, and supervisory
function. The international historical scheme was developed to support
comparisons across periods, countries, and languages.

Source: Marco H. D. van Leeuwen and Ineke Maas, *HISCLASS: A Historical Social
Class Scheme*, Leuven University Press, 2011:
https://datasets.iisg.amsterdam/dataset.xhtml?persistentId=hdl%3A10622%2FHEFSW2

**HISCLASS 5** is a simplified five-class aggregation of HISCLASS. It combines
the twelve classes into broader groups such as the elite and lower middle
class, making it suitable for analyses that require a coarser resolution.

Source: `cedarfoundation/hisco` R package documentation:
https://github.com/cedarfoundation/hisco

**HISCAM U1** is version 1 of the universal, cross-national HISCAM occupational
status scale. It was derived from patterns of intergenerational occupational
mobility and assigns a continuous status score to HISCO codes independently of
the country from which the observations originate.

**HISCAM NL** is the corresponding country-specific scale for the Netherlands.

Source: Paul S. Lambert, Richard L. Zijdeman, Marco H. D. van Leeuwen, Ineke
Maas, and Ken Prandy, "The Construction of HISCAM", *Historical Methods* 46
(2013), 77-89:
https://www.researchgate.net/publication/235929717

**OCC1950** is the occupational classification based on the 1950 standard of
the U.S. Census Bureau. It is also used to derive status measures such as
NPBOSS, OCCSCORE, and SEI. The bundled crosswalk connects HISCO-based
classifications and status scales with measures based on OCC1950.

Source: HISCO-OCC1950 Crosswalk, DANS Data Station:
https://easy.dans.knaw.nl/ui/datasets/id/easy-dataset:73810
