# Normalization Rules

This document lists the normalization rules currently implemented by the module.
Each rule has a stable rule number that also appears in the source code.

## M2-R001: Split multiple statements

Multiple occupation statements are split into separate normalization entries.

Recognized separators:

- comma: `,`
- slash: `/`
- semicolon: `;`
- German conjunction: `und`
- English conjunction: `and`

Hyphenated compounds such as `Orgel- und Harmoniumbauer` are not split by `und` in this first implementation.

## M2-R010: Social status is not an occupation

`Bürger` is treated as social status and is not copied to the normalized occupation field.

## M2-R020: Widow compounds

Compounds such as `Arztwitwe` are recognized as hints, not as occupations of the current person.

In M2a the current person receives the social status `Witwe` and the entry is marked as `unclear`.
The later inference that the deceased husband had the occupation `Arzt` before the recorded date is intentionally not written to GEDCOM and is left for a later milestone.

## M2-R021: Kinship-derived compounds

Compounds such as `Ökonomiebesitzerstochter`, `Bauerssohn`, `Arztgattin`, and `Arztehefrau` are recognized as hints, not as occupations of the current person.

In M2a the current person receives the social status `Tochter`, `Sohn`, or `Gattin` and the entry is marked as `unclear`.
The leading term is a hint about a related person, for example the father or husband. This referenced-person information is not yet stored in separate fields.

## M2-R030: Craft qualification after colon

Occupation statements such as `Orgelbauer: Meister` are split into:

- normalized occupation: `Orgelbauer`
- qualification: `Meister`

The same pattern applies to `Geselle` and `Lehrling`.

## M2-R032: Independent master compounds are not split

Independent compounds such as `Schulmeister`, `Bürgermeister`, and `Werkmeister` are not split into occupation plus qualification.

## M2-R031: Compound craft qualification

`Orgelbaumeister` is normalized as:

- normalized occupation: `Orgelbauer`
- qualification: `Meister`

This rule is applied after M2-R032 so independent master compounds can be protected before any broader craft-qualification logic is added.

## M2-R040: Context-based occupation refinement

Broad occupation terms can be refined when the occupation text or copied context fields provide a reliable context.

The first implemented cases are intentionally narrow:

- `Angestellter` or `Angestellte` with employer `Bank` or `Banken` is normalized as `Bankangestellter`.
- `Arbeiter: Fabrik` is normalized as `Fabrikarbeiter`.

This rule uses context without changing GEDCOM. The original occupation text and copied context fields remain available for review.

## M2-R050: Site-managed normalization mapping table

Administrators can maintain a site-wide mapping table in the module settings.
These rules are applied after the built-in local rules and before the external OhdAB special database rule.
Historical spelling variants are ordinary entries in this table.

The table can store:

- language of the original occupation text
- original occupation text
- normalized occupation
- social status
- qualification
- HISCO code
- GND identifier
- OhdAB identifier
- FactGrid item id
- Wikidata item id

The first seeded German examples are:

- `Ärztin` -> `Arzt`
- `Beck` -> `Bäcker`
- `Kieffer` -> `Küfer`
- `Orgelbauerin` -> `Orgelbauer`
- `Schuster` -> `Schuhmacher`

## M4-R100: Normalize with external OhdAB special database

Administrators can upload a tailored German OhdAB Excel file in the module
settings. The module imports it into its own norm tables and deletes the
temporary uploaded file afterwards.

This rule applies only to occupation parts whose language is German (`de` or a
German language tag such as `de-DE`). It runs after the site-managed
normalization mapping table and before the fallback rule.

On a match, the rule copies the normalized occupation label, the OhdAB id and
the FactGrid id into the occupation entry, and links the entry to the imported
norm concept. The OhdAB hierarchy is stored once in separate norm tables and is
shown as context in occupation labels without duplicating the hierarchy text in
every entry.

## M2-R090: Fallback for unknown terms

If no specific rule applies, the original term is copied as the normalized occupation and the entry is marked as `unclear`.

## Synchronization Strategy

The module stores normalization results in its own database tables. It does not write normalized occupations back to GEDCOM.

The tables are created and synchronized only when a manager opens the occupation list. Visitors and editors still see the live labels in the list view, but they do not create or update module tables.

Synchronization is incremental:

- A tree-level fingerprint of all current `INDI:OCCU` facts is stored in module metadata.
- If the fingerprint has not changed, no database synchronization is performed.
- If it has changed, current OCCU parts are inserted or updated by their stable entry key.
- Automatic normalization fields are updated only while an entry is not yet marked as reviewed or manually changed.
- Copied context fields are updated only while an entry is not manually changed.
- Entries that no longer correspond to a current OCCU part are marked inactive instead of being deleted.

This preserves later review decisions and manual corrections while still reacting to added, changed, or deleted OCCU facts.

Managers can delete the stored normalization data for a selected tree from the module settings. This removes only module-owned table rows and the stored OCCU fingerprint for that tree. GEDCOM data is not changed; opening the occupation list as a manager can recreate the module rows from the current OCCU facts.
