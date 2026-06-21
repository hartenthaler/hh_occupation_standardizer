<?php

declare(strict_types=1);

namespace Hartenthaler\Webtrees\Module\OccupationStandardizer\Application\Service;

use function array_filter;
use function array_map;
use function array_values;
use function implode;
use function explode;
use function in_array;
use function mb_strtolower;
use function preg_match;
use function preg_replace;
use function preg_split;
use function str_replace;
use function trim;

final class OccupationNormalizationService
{
    public const STATUS_RECOGNIZED = 'recognized';
    public const STATUS_UNCLEAR = 'unclear';
    public const STATUS_IGNORED = 'ignored';

    private const SOCIAL_STATUS = [
        'bürger' => 'Bürger',
    ];

    private const QUALIFICATIONS = [
        'meister'  => 'Meister',
        'geselle'  => 'Geselle',
        'lehrling' => 'Lehrling',
    ];

    private const MASTER_COMPOUND_EXCEPTIONS = [
        'bürgermeister',
        'schulmeister',
        'werkmeister',
    ];

    /** @var list<array{language:string,original_text:string,social_status:string,occupation_normalized:string,occupation_de_male:string,occupation_de_female:string,occupation_de_neutral:string,occupation_en_male:string,occupation_en_female:string,occupation_en_neutral:string,qualification:string,code_hisco:string,code_gnd:string,code_ohdab:string,code_factgrid:string}> */
    private array $normalization_rules;

    /** @var list<array{language:string,original_text:string,norm_concept_id:int,occupation_normalized:string,occupation_de_male:string,occupation_de_female:string,occupation_de_neutral:string,occupation_en_male:string,occupation_en_female:string,occupation_en_neutral:string,code_hisco:string,code_gnd:string,code_ohdab:string,code_factgrid:string}> */
    private array $ohdab_special_mappings;

    /** @var list<string> */
    private array $builtin_rule_order;

    /**
     * @param list<array{language:string,original_text:string,social_status:string,occupation_normalized:string,occupation_de_male?:string,occupation_de_female?:string,occupation_de_neutral?:string,occupation_en_male?:string,occupation_en_female?:string,occupation_en_neutral?:string,qualification:string,code_hisco:string,code_gnd:string,code_ohdab:string,code_factgrid?:string}> $normalization_rules
     * @param list<string> $builtin_rule_order
     * @param list<array{language:string,original_text:string,norm_concept_id:int,occupation_normalized:string,occupation_de_male:string,occupation_de_female:string,occupation_de_neutral:string,occupation_en_male:string,occupation_en_female:string,occupation_en_neutral:string,code_hisco:string,code_gnd:string,code_ohdab:string,code_factgrid:string}> $ohdab_special_mappings
     */
    public function __construct(array $normalization_rules = [], array $builtin_rule_order = [], array $ohdab_special_mappings = [])
    {
        $this->normalization_rules = $normalization_rules;
        $this->builtin_rule_order = $builtin_rule_order !== [] ? $builtin_rule_order : self::defaultBuiltinRuleOrder();
        $this->ohdab_special_mappings = $ohdab_special_mappings;
    }

    /**
     * @return list<string>
     */
    public static function defaultBuiltinRuleOrder(): array
    {
        return [
            'M2-R001',
            'M2-R010',
            'M2-R020',
            'M2-R030',
            'M2-R032',
            'M2-R031',
            'M2-R050',
            'M4-R100',
            'M2-R090',
        ];
    }

    /**
     * @return list<array{part_index:int,original_part_text:string,language:string,social_status:string,occupation_normalized:string,occupation_de_male:string,occupation_de_female:string,occupation_de_neutral:string,occupation_en_male:string,occupation_en_female:string,occupation_en_neutral:string,office:string,qualification:string,code_hisco:string,code_gnd:string,code_ohdab:string,code_factgrid:string,norm_concept_id:int,status:string,rule_numbers:string}>
     */
    public function normalize(string $occupation, string $language = ''): array
    {
        $entries = [];
        $parts = in_array('M2-R001', $this->builtin_rule_order, true) ? $this->splitOccupation($occupation) : [trim($occupation)];

        foreach (array_values(array_filter($parts, static fn (string $part): bool => $part !== '')) as $index => $part) {
            $entries[] = ['part_index' => $index] + $this->normalizePart($part, $language);
        }

        return $entries;
    }

    /**
     * @return list<string>
     */
    private function splitOccupation(string $occupation): array
    {
        // M2-R001: Split multiple statements by separators and standalone conjunctions.
        $parts = [];

        foreach (preg_split('/\s*[,\/;]\s*/u', $occupation) ?: [] as $chunk) {
            $chunk = trim($chunk);

            if ($chunk === '') {
                continue;
            }

            if (preg_match('/-\s+(und|and)\s+/iu', $chunk) === 1) {
                $parts[] = $chunk;
                continue;
            }

            foreach (preg_split('/\s+(?:und|and)\s+/iu', $chunk) ?: [] as $part) {
                $part = trim($part);

                if ($part !== '') {
                    $parts[] = $part;
                }
            }
        }

        return array_values(array_filter($parts, static fn (string $part): bool => $part !== ''));
    }

    /**
     * @return array{original_part_text:string,language:string,social_status:string,occupation_normalized:string,occupation_de_male:string,occupation_de_female:string,occupation_de_neutral:string,occupation_en_male:string,occupation_en_female:string,occupation_en_neutral:string,office:string,qualification:string,code_hisco:string,code_gnd:string,code_ohdab:string,code_factgrid:string,norm_concept_id:int,status:string,rule_numbers:string}
     */
    private function normalizePart(string $part, string $language): array
    {
        $original = trim($part);
        $lower = mb_strtolower($original);

        $entry = [
            'original_part_text'    => $original,
            'language'              => $language,
            'social_status'         => '',
            'occupation_normalized' => '',
            'occupation_de_male'    => '',
            'occupation_de_female'  => '',
            'occupation_de_neutral' => '',
            'occupation_en_male'    => '',
            'occupation_en_female'  => '',
            'occupation_en_neutral' => '',
            'office'                => '',
            'qualification'         => '',
            'code_hisco'            => '',
            'code_gnd'              => '',
            'code_ohdab'            => '',
            'code_factgrid'         => '',
            'norm_concept_id'       => 0,
            'status'                => self::STATUS_UNCLEAR,
            'rule_numbers'          => '',
        ];

        foreach ($this->builtin_rule_order as $rule_id) {
            if ($rule_id === 'M2-R010' && isset(self::SOCIAL_STATUS[$lower])) {
                // M2-R010: Social status is not an occupation.
                return $this->withRules($entry, [
                    'social_status' => self::SOCIAL_STATUS[$lower],
                    'status'        => self::STATUS_RECOGNIZED,
                ], [$rule_id]);
            }

            if ($rule_id === 'M2-R020' && preg_match('/^(.+)witwe$/iu', $original, $match) === 1) {
                // M2-R020: Widow compounds are hints, not occupations of the current person.
                return $this->withRules($entry, [
                    'social_status' => 'Witwe',
                    'status'        => self::STATUS_UNCLEAR,
                ], [$rule_id]);
            }

            if ($rule_id === 'M2-R030' && preg_match('/^(.+?)\s*:\s*(meister|geselle|lehrling)$/iu', $original, $match) === 1) {
                // M2-R030: Craft qualification after colon.
                return $this->withRules($entry, [
                    'occupation_normalized' => $this->normalizeOccupationName($match[1]),
                    'qualification'         => self::QUALIFICATIONS[mb_strtolower($match[2])],
                    'status'                => self::STATUS_RECOGNIZED,
                ], [$rule_id]);
            }

            if ($rule_id === 'M2-R031' && $lower === 'orgelbaumeister') {
                // M2-R031: Compound craft qualification.
                return $this->withRules($entry, [
                    'occupation_normalized' => 'Orgelbauer',
                    'qualification'         => 'Meister',
                    'status'                => self::STATUS_RECOGNIZED,
                ], [$rule_id]);
            }

            if ($rule_id === 'M2-R032' && in_array($lower, self::MASTER_COMPOUND_EXCEPTIONS, true)) {
                // M2-R032: Independent master compounds are not split.
                return $this->withRules($entry, [
                    'occupation_normalized' => $original,
                    'status'                => self::STATUS_RECOGNIZED,
                ], [$rule_id]);
            }

            if ($rule_id === 'M2-R050') {
                foreach ($this->normalization_rules as $rule) {
                    if ($this->ruleMatches($rule, $original, $language)) {
                        // M2-R050: Site-managed normalization mapping table.
                        return $this->withRules($entry, [
                            'language'              => $rule['language'] !== '' ? $rule['language'] : $language,
                            'social_status'         => $rule['social_status'],
                            'occupation_normalized' => $rule['occupation_normalized'],
                            'occupation_de_male'    => $rule['occupation_de_male'] ?? '',
                            'occupation_de_female'  => $rule['occupation_de_female'] ?? '',
                            'occupation_de_neutral' => $rule['occupation_de_neutral'] ?? '',
                            'occupation_en_male'    => $rule['occupation_en_male'] ?? '',
                            'occupation_en_female'  => $rule['occupation_en_female'] ?? '',
                            'occupation_en_neutral' => $rule['occupation_en_neutral'] ?? '',
                            'qualification'         => $rule['qualification'],
                            'code_hisco'            => $rule['code_hisco'],
                            'code_gnd'              => $rule['code_gnd'],
                            'code_ohdab'            => $rule['code_ohdab'],
                            'code_factgrid'         => $rule['code_factgrid'] ?? '',
                            'status'                => self::STATUS_RECOGNIZED,
                        ], [$rule_id]);
                    }
                }
            }

            if ($rule_id === 'M4-R100') {
                foreach ($this->ohdab_special_mappings as $mapping) {
                    if ($this->ohdabSpecialMappingMatches($mapping, $original, $language)) {
                        // M4-R100: Normalize with external OhdAB special database.
                        return $this->withRules($entry, [
                            'language'              => $mapping['language'] !== '' ? $mapping['language'] : $language,
                            'occupation_normalized' => $mapping['occupation_normalized'],
                            'occupation_de_male'    => $mapping['occupation_de_male'],
                            'occupation_de_female'  => $mapping['occupation_de_female'],
                            'occupation_de_neutral' => $mapping['occupation_de_neutral'],
                            'occupation_en_male'    => $mapping['occupation_en_male'],
                            'occupation_en_female'  => $mapping['occupation_en_female'],
                            'occupation_en_neutral' => $mapping['occupation_en_neutral'],
                            'code_hisco'            => $mapping['code_hisco'],
                            'code_gnd'              => $mapping['code_gnd'],
                            'code_ohdab'            => $mapping['code_ohdab'],
                            'code_factgrid'         => $mapping['code_factgrid'],
                            'norm_concept_id'       => $mapping['norm_concept_id'],
                            'status'                => self::STATUS_RECOGNIZED,
                        ], [$rule_id]);
                    }
                }
            }

            if ($rule_id === 'M2-R090') {
                // M2-R090: Fallback for unknown terms.
                return $this->withRules($entry, [
                    'occupation_normalized' => $original,
                    'status'                => self::STATUS_UNCLEAR,
                ], [$rule_id]);
            }
        }

        return $entry;
    }

    private function normalizeOccupationName(string $occupation): string
    {
        return trim($occupation);
    }

    /**
     * @param array{language:string,original_text:string,social_status:string,occupation_normalized:string,occupation_de_male?:string,occupation_de_female?:string,occupation_de_neutral?:string,occupation_en_male?:string,occupation_en_female?:string,occupation_en_neutral?:string,qualification:string,code_hisco:string,code_gnd:string,code_ohdab:string,code_factgrid?:string} $rule
     */
    private function ruleMatches(array $rule, string $original, string $language): bool
    {
        if (mb_strtolower($rule['original_text']) !== mb_strtolower($original)) {
            return false;
        }

        if ($rule['language'] === '' || $language === '') {
            return true;
        }

        if ($rule['language'] === $language) {
            return true;
        }

        return explode('-', $rule['language'])[0] === explode('-', $language)[0];
    }

    /**
     * @param array{language:string,original_text:string,norm_concept_id:int,occupation_normalized:string,occupation_de_male:string,occupation_de_female:string,occupation_de_neutral:string,occupation_en_male:string,occupation_en_female:string,occupation_en_neutral:string,code_hisco:string,code_gnd:string,code_ohdab:string,code_factgrid:string} $mapping
     */
    private function ohdabSpecialMappingMatches(array $mapping, string $original, string $language): bool
    {
        if ($language === '' || explode('-', $language)[0] !== 'de') {
            return false;
        }

        if ($mapping['language'] !== '' && explode('-', $mapping['language'])[0] !== 'de') {
            return false;
        }

        return $this->matchKey($mapping['original_text']) === $this->matchKey($original);
    }

    /**
     * @param array{original_part_text:string,language:string,social_status:string,occupation_normalized:string,occupation_de_male:string,occupation_de_female:string,occupation_de_neutral:string,occupation_en_male:string,occupation_en_female:string,occupation_en_neutral:string,office:string,qualification:string,code_hisco:string,code_gnd:string,code_ohdab:string,code_factgrid:string,norm_concept_id:int,status:string,rule_numbers:string} $entry
     * @param array<string,int|string> $values
     * @param list<string> $rules
     *
     * @return array{original_part_text:string,language:string,social_status:string,occupation_normalized:string,occupation_de_male:string,occupation_de_female:string,occupation_de_neutral:string,occupation_en_male:string,occupation_en_female:string,occupation_en_neutral:string,office:string,qualification:string,code_hisco:string,code_gnd:string,code_ohdab:string,code_factgrid:string,norm_concept_id:int,status:string,rule_numbers:string}
     */
    private function withRules(array $entry, array $values, array $rules): array
    {
        foreach ($values as $key => $value) {
            $entry[$key] = $value;
        }

        $entry['rule_numbers'] = implode(', ', $rules);

        return $entry;
    }

    private function matchKey(string $value): string
    {
        $key = mb_strtolower(trim($value));
        $key = preg_replace('/\s+/u', ' ', $key) ?? $key;

        return str_replace('–', '-', $key);
    }
}
