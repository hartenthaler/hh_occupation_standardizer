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
use function preg_split;
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

    /** @var list<array{language:string,original_text:string,social_status:string,occupation_normalized:string,occupation_de_male:string,occupation_de_female:string,occupation_en_male:string,occupation_en_female:string,qualification:string,code_hisco:string,code_gnd:string,code_ohdab:string}> */
    private array $normalization_rules;

    /** @var list<string> */
    private array $builtin_rule_order;

    /**
     * @param list<array{language:string,original_text:string,social_status:string,occupation_normalized:string,occupation_de_male?:string,occupation_de_female?:string,occupation_en_male?:string,occupation_en_female?:string,qualification:string,code_hisco:string,code_gnd:string,code_ohdab:string}> $normalization_rules
     * @param list<string> $builtin_rule_order
     */
    public function __construct(array $normalization_rules = [], array $builtin_rule_order = [])
    {
        $this->normalization_rules = $normalization_rules;
        $this->builtin_rule_order = $builtin_rule_order !== [] ? $builtin_rule_order : self::defaultBuiltinRuleOrder();
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
            'M2-R031',
            'M2-R032',
            'M2-R050',
            'M2-R090',
        ];
    }

    /**
     * @return list<array{part_index:int,original_part_text:string,language:string,social_status:string,occupation_normalized:string,occupation_de_male:string,occupation_de_female:string,occupation_en_male:string,occupation_en_female:string,office:string,qualification:string,code_hisco:string,code_gnd:string,code_ohdab:string,status:string,rule_numbers:string}>
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
     * @return array{original_part_text:string,language:string,social_status:string,occupation_normalized:string,occupation_de_male:string,occupation_de_female:string,occupation_en_male:string,occupation_en_female:string,office:string,qualification:string,code_hisco:string,code_gnd:string,code_ohdab:string,status:string,rule_numbers:string}
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
            'occupation_en_male'    => '',
            'occupation_en_female'  => '',
            'office'                => '',
            'qualification'         => '',
            'code_hisco'            => '',
            'code_gnd'              => '',
            'code_ohdab'            => '',
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
                            'occupation_en_male'    => $rule['occupation_en_male'] ?? '',
                            'occupation_en_female'  => $rule['occupation_en_female'] ?? '',
                            'qualification'         => $rule['qualification'],
                            'code_hisco'            => $rule['code_hisco'],
                            'code_gnd'              => $rule['code_gnd'],
                            'code_ohdab'            => $rule['code_ohdab'],
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
     * @param array{language:string,original_text:string,social_status:string,occupation_normalized:string,occupation_de_male?:string,occupation_de_female?:string,occupation_en_male?:string,occupation_en_female?:string,qualification:string,code_hisco:string,code_gnd:string,code_ohdab:string} $rule
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
     * @param array{original_part_text:string,language:string,social_status:string,occupation_normalized:string,occupation_de_male:string,occupation_de_female:string,occupation_en_male:string,occupation_en_female:string,office:string,qualification:string,code_hisco:string,code_gnd:string,code_ohdab:string,status:string,rule_numbers:string} $entry
     * @param array<string,string> $values
     * @param list<string> $rules
     *
     * @return array{original_part_text:string,language:string,social_status:string,occupation_normalized:string,occupation_de_male:string,occupation_de_female:string,occupation_en_male:string,occupation_en_female:string,office:string,qualification:string,code_hisco:string,code_gnd:string,code_ohdab:string,status:string,rule_numbers:string}
     */
    private function withRules(array $entry, array $values, array $rules): array
    {
        foreach ($values as $key => $value) {
            $entry[$key] = $value;
        }

        $entry['rule_numbers'] = implode(', ', $rules);

        return $entry;
    }
}
