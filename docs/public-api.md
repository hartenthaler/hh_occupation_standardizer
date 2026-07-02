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

The result exposes `canonicalLabel()`, `hiscoCode()`, `hisclass()`, and
`hiscamScore()`. HISCLASS and HISCAM currently return `null` because the module
does not yet store these classifications.

The optional language is a BCP-47 language hint such as `de` or `de-DE`.
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
    $occupation = $standardizer->standardize('Kieffer', 'de');
    $label = $occupation?->canonicalLabel();
}
```

Consumers must treat the provider as optional because the module may be absent
or disabled. `standardizeMany()` should be preferred for dashboards and other
bulk consumers. It initializes the normalization data once and returns an array
keyed by each distinct input string.
