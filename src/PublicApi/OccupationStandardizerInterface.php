<?php

declare(strict_types=1);

namespace Hartenthaler\Webtrees\Module\OccupationStandardizer\PublicApi;

interface OccupationStandardizerInterface
{
    /**
     * Resolve a raw GEDCOM OCCU value to its first recognized occupation.
     *
     * @param string      $raw_occupation Original value of an INDI:OCCU fact
     * @param string|null $language       BCP-47 language of the original value
     */
    public function standardize(string $raw_occupation, ?string $language = null): ?StandardizedOccupation;

    /**
     * @param iterable<string> $raw_occupations Original INDI:OCCU values
     * @param string|null      $language        BCP-47 language of every original value
     *
     * @return array<string,StandardizedOccupation|null>
     */
    public function standardizeMany(iterable $raw_occupations, ?string $language = null): array;
}
