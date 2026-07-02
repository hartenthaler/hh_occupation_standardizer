<?php

declare(strict_types=1);

namespace Hartenthaler\Webtrees\Module\OccupationStandardizer\PublicApi;

interface OccupationStandardizerInterface
{
    /**
     * Resolve a raw GEDCOM OCCU value to its first recognized occupation.
     */
    public function standardize(string $raw_occupation, ?string $language = null): ?StandardizedOccupation;

    /**
     * @param iterable<string> $raw_occupations
     *
     * @return array<string,StandardizedOccupation|null>
     */
    public function standardizeMany(iterable $raw_occupations, ?string $language = null): array;
}
