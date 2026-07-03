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
