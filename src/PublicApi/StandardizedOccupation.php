<?php

declare(strict_types=1);

namespace Hartenthaler\Webtrees\Module\OccupationStandardizer\PublicApi;

final class StandardizedOccupation
{
    public function __construct(
        private string $canonical_label,
        private ?string $hisco_code = null,
        private ?string $hisclass = null,
        private ?float $hiscam_score = null,
    ) {
    }

    public function canonicalLabel(): string
    {
        return $this->canonical_label;
    }

    public function hiscoCode(): ?string
    {
        return $this->hisco_code;
    }

    public function hisclass(): ?string
    {
        return $this->hisclass;
    }

    public function hiscamScore(): ?float
    {
        return $this->hiscam_score;
    }
}
