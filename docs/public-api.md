# Public read-only API

The module publishes a small read-only API for other webtrees modules. Consumers
can use normalized occupation results without depending on module-owned database
tables or internal service classes.

## Contract

The stable public classes are:

- `Hartenthaler\Webtrees\Module\OccupationStandardizer\PublicApi\OccupationStandardizerInterface`
- `Hartenthaler\Webtrees\Module\OccupationStandardizer\PublicApi\StandardizedOccupation`

The interface provides:

```php
public function standardize(string $raw_occupation, ?string $language = null): ?StandardizedOccupation;

public function standardizeMany(iterable $raw_occupations, ?string $language = null): array;
```

Parameters:

- `raw_occupation` is one original occupation value as recorded in a GEDCOM
  `INDI:OCCU` fact.
- `raw_occupations` is an iterable collection of such original occupation
  values. Duplicate strings share one result because the returned array is
  keyed by the input string.
- `language` optionally identifies the language in which the original
  occupation term is written. It accepts a BCP-47 language tag such as `de` or
  `de-DE` and helps distinguish otherwise ambiguous terms. It does not select
  the language of the returned canonical label. In `standardizeMany()`, the
  language applies to every supplied term.

`standardize()` returns one immutable `StandardizedOccupation` value object or
`null`. `standardizeMany()` returns an array keyed by the distinct original
input strings; each value is either a `StandardizedOccupation` or `null`.

## Result and labels

Normalization and display are deliberately separate:

- `canonicalLabel()` returns the normalized grouping term, for example `Arzt`.
  It is not a person-specific display label.
- `canonicalKey()` returns the normalization-language and canonical-label
  combination, for example `de:Arzt`. Consumers should prefer this key over a
  display label when grouping results from the same normalization vocabulary.
- `labelForms()` returns the available masculine, feminine, and neutral forms
  in German and English.
- `displayLabel($language, $sex)` selects a display form for a concrete user
  language and GEDCOM sex value. `F` prefers the feminine form, `M` the
  masculine form, and `X`, `U`, an empty value, or any other value the neutral
  form. Missing forms fall back within the requested language, then to the
  other supported language, and finally to `canonicalLabel()`.
- `hiscoCode()` exposes the HISCO identifier.
- `hisclass()` and `hisclass5()` expose the twelve-class and aggregated
  five-class HISCLASS values.
- `hiscamU1()` and `hiscamNl()` expose the universal and Netherlands-specific
  HISCAM scores. The existing `hiscamScore()` method remains an alias for
  `hiscamU1()` for compatibility.
- `occ1950()` exposes the optional OCC1950 classification code.

The classification methods return `null` when no HISCO identifier is known or
the bundled crosswalk contains no value for that identifier. The source value
`-9` is treated as missing.

The language passed to `displayLabel()` is independent of the language passed
to `standardize()`: the former selects an output label, while the latter
describes the original input term.

Unknown terms, ignored terms, and values containing only a social status return
`null`. If a raw value contains several recognized occupations, the singular
method returns the first recognized occupation in source order.

## Discovering the provider

Consumers should find the active module through webtrees `ModuleService` and
the public interface instead of constructing an internal service or reading
module tables:

```php
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\ModuleService;
use Hartenthaler\Webtrees\Module\OccupationStandardizer\PublicApi\OccupationStandardizerInterface;

$standardizer = Registry::container()
    ->get(ModuleService::class)
    ->findByInterface(OccupationStandardizerInterface::class, true, true)
    ->first();

if ($standardizer instanceof OccupationStandardizerInterface) {
    $occupation = $standardizer->standardize('Ärztin', 'de');

    $grouping_key = $occupation?->canonicalKey();       // de:Arzt
    $canonical = $occupation?->canonicalLabel();        // Arzt
    $german_label = $occupation?->displayLabel('de', 'F');
    $english_label = $occupation?->displayLabel('en', 'F');
    $hisclass = $occupation?->hisclass();
    $hisclass5 = $occupation?->hisclass5();
    $hiscam_u1 = $occupation?->hiscamU1();
    $hiscam_nl = $occupation?->hiscamNl();
    $occ1950 = $occupation?->occ1950();
}
```

Consumers must treat the provider as optional because the module may be absent
or disabled. `standardizeMany()` should be preferred for dashboards and other
bulk consumers. It initializes the normalization data once and returns an array
keyed by each distinct input string.
