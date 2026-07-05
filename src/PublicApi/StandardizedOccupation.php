<?php

declare(strict_types=1);

namespace Hartenthaler\Webtrees\Module\OccupationStandardizer\PublicApi;

use function explode;
use function mb_strtolower;
use function mb_strtoupper;
use function str_replace;
use function trim;

final class StandardizedOccupation
{
    public function __construct(
        private string $canonical_label,
        private string $canonical_key,
        private string $occupation_de_male = '',
        private string $occupation_de_female = '',
        private string $occupation_de_neutral = '',
        private string $occupation_en_male = '',
        private string $occupation_en_female = '',
        private string $occupation_en_neutral = '',
        private ?string $hisco_code = null,
        private ?string $hisclass = null,
        private ?float $hiscam_score = null,
        private ?int $hisclass_5 = null,
        private ?float $hiscam_nl = null,
        private ?int $occ1950 = null,
        private ?string $occupation_status = null,
    ) {
    }

    public function canonicalLabel(): string
    {
        return $this->canonical_label;
    }

    public function canonicalKey(): string
    {
        return $this->canonical_key;
    }

    /**
     * @return array{de:array{male:string,female:string,neutral:string},en:array{male:string,female:string,neutral:string}}
     */
    public function labelForms(): array
    {
        return [
            'de' => [
                'male'    => $this->occupation_de_male,
                'female'  => $this->occupation_de_female,
                'neutral' => $this->occupation_de_neutral,
            ],
            'en' => [
                'male'    => $this->occupation_en_male,
                'female'  => $this->occupation_en_female,
                'neutral' => $this->occupation_en_neutral,
            ],
        ];
    }

    public function displayLabel(string $language, ?string $sex = null): string
    {
        $language = explode('-', str_replace('_', '-', mb_strtolower(trim($language))))[0];
        $forms = $this->labelForms();
        $language_order = $language === 'de' ? ['de', 'en'] : ['en', 'de'];

        foreach ($language_order as $language_code) {
            $label = $this->genderedLabel($forms[$language_code], $sex);

            if ($label !== '') {
                return $label;
            }
        }

        return $this->canonical_label;
    }

    public function hiscoCode(): ?string
    {
        return $this->hisco_code;
    }

    public function occupationStatus(): ?string
    {
        return $this->occupation_status;
    }

    public function hisclass(): ?string
    {
        return $this->hisclass;
    }

    public function hiscamScore(): ?float
    {
        return $this->hiscam_score;
    }

    public function hisclass5(): ?int
    {
        return $this->hisclass_5;
    }

    public function hiscamU1(): ?float
    {
        return $this->hiscam_score;
    }

    public function hiscamNl(): ?float
    {
        return $this->hiscam_nl;
    }

    public function occ1950(): ?int
    {
        return $this->occ1950;
    }

    /**
     * @param array{male:string,female:string,neutral:string} $forms
     */
    private function genderedLabel(array $forms, ?string $sex): string
    {
        $sex = $sex !== null ? mb_strtoupper(trim($sex)) : '';
        $preferred_order = match ($sex) {
            'F'     => ['female', 'neutral', 'male'],
            'M'     => ['male', 'neutral', 'female'],
            default => ['neutral', 'male', 'female'],
        };

        foreach ($preferred_order as $form) {
            if ($forms[$form] !== '') {
                return $forms[$form];
            }
        }

        return '';
    }
}
