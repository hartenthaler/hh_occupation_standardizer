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

`standardize()` returns one `StandardizedOccupation` or `null`.
`standardizeMany()` returns an array keyed by the distinct original input
strings; each value is either a `StandardizedOccupation` or `null`.

The result exposes `canonicalLabel()`, `hiscoCode()`, `hisclass()`, and
`hiscamScore()`. HISCLASS and HISCAM currently return `null` because the module
does not yet store these classifications.

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
    $label = $occupation?->canonicalLabel();
}
```

Consumers must treat the provider as optional because the module may be absent
or disabled. `standardizeMany()` should be preferred for dashboards and other
bulk consumers. It initializes the normalization data once and returns an array
keyed by each distinct input string.
