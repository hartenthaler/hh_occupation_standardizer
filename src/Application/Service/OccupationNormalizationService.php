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

    private const MASTER_QUALIFICATION_COMPOUNDS = [
        'bäckermeister'    => 'Bäcker',
        'müllermeister'    => 'Müller',
        'orgelbaumeister'  => 'Orgelbauer',
        'schmiedemeister'  => 'Schmied',
        'schneidermeister' => 'Schneider',
    ];

    private const QUALIFICATION_COMPOUND_EXCEPTIONS = [
        'junggeselle',
    ];

    /** @var list<array{language:string,original_text:string,social_status:string,occupation_normalized:string,occupation_de_male:string,occupation_de_female:string,occupation_de_neutral:string,occupation_en_male:string,occupation_en_female:string,occupation_en_neutral:string,qualification:string,code_hisco:string,code_gnd:string,code_ohdab:string,code_factgrid:string,code_wikidata:string}> */
    private array $normalization_rules;

    /** @var list<array{language:string,original_text:string,norm_concept_id:int,occupation_normalized:string,occupation_de_male:string,occupation_de_female:string,occupation_de_neutral:string,occupation_en_male:string,occupation_en_female:string,occupation_en_neutral:string,code_hisco:string,code_gnd:string,code_ohdab:string,code_factgrid:string,code_wikidata:string}> */
    private array $ohdab_special_mappings;

    /** @var list<string> */
    private array $builtin_rule_order;

    /**
     * @param list<array{language:string,original_text:string,social_status:string,occupation_normalized:string,occupation_de_male?:string,occupation_de_female?:string,occupation_de_neutral?:string,occupation_en_male?:string,occupation_en_female?:string,occupation_en_neutral?:string,qualification:string,code_hisco:string,code_gnd:string,code_ohdab:string,code_factgrid?:string,code_wikidata?:string}> $normalization_rules
     * @param list<string> $builtin_rule_order
     * @param list<array{language:string,original_text:string,norm_concept_id:int,occupation_normalized:string,occupation_de_male:string,occupation_de_female:string,occupation_de_neutral:string,occupation_en_male:string,occupation_en_female:string,occupation_en_neutral:string,code_hisco:string,code_gnd:string,code_ohdab:string,code_factgrid:string,code_wikidata:string}> $ohdab_special_mappings
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
            'M2-R002',
            'M2-R001',
            'M2-R060',
            'M2-R010',
            'M2-R020',
            'M2-R021',
            'M2-R030',
            'M2-R032',
            'M2-R031',
            'M2-R040',
            'M2-R050',
            'M4-R100',
            'M2-R090',
        ];
    }

    /**
     * @param array{employer?:string,type?:string,note?:string} $context
     *
     * @return list<array{part_index:int,original_part_text:string,language:string,social_status:string,occupation_normalized:string,occupation_de_male:string,occupation_de_female:string,occupation_de_neutral:string,occupation_en_male:string,occupation_en_female:string,occupation_en_neutral:string,office:string,qualification:string,code_hisco:string,code_gnd:string,code_ohdab:string,code_factgrid:string,code_wikidata:string,norm_concept_id:int,status:string,rule_numbers:string}>
     */
    public function normalize(string $occupation, string $language = '', array $context = []): array
    {
        $entries = [];
        $parts = $this->preprocessOccupation($occupation, $language);

        foreach ($parts as $index => $part) {
            $entry = ['part_index' => $index] + $this->normalizePart($part['text'], $language, $context);
            $rules = [...$part['rules'], ...array_values(array_filter(array_map('trim', explode(',', $entry['rule_numbers']))))];
            $entry['rule_numbers'] = implode(', ', array_values(array_unique($rules)));

            $entries[] = $entry;
        }

        return $entries;
    }

    /**
     * @return list<array{text:string,rules:list<string>}>
     */
    private function preprocessOccupation(string $occupation, string $language): array
    {
        $parts = [['text' => trim($occupation), 'rules' => []]];

        foreach ($this->builtin_rule_order as $rule_id) {
            if ($rule_id === 'M2-R002') {
                $expanded_parts = [];

                foreach ($parts as $part) {
                    $expanded = $this->expandSharedHeadword($part['text'], $language);
                    $rules = count($expanded) > 1 ? [...$part['rules'], $rule_id] : $part['rules'];

                    foreach ($expanded as $text) {
                        $expanded_parts[] = ['text' => $text, 'rules' => $rules];
                    }
                }

                $parts = $expanded_parts;
            }

            if ($rule_id === 'M2-R001') {
                $split_parts = [];

                foreach ($parts as $part) {
                    $split = $this->splitOccupation($part['text']);
                    $rules = count($split) > 1 ? [...$part['rules'], $rule_id] : $part['rules'];

                    foreach ($split as $text) {
                        $split_parts[] = ['text' => $text, 'rules' => $rules];
                    }
                }

                $parts = $split_parts;
            }
        }

        return array_values(array_filter($parts, static fn (array $part): bool => $part['text'] !== ''));
    }

    /**
     * @return list<string>
     */
    private function expandSharedHeadword(string $occupation, string $language): array
    {
        if (
            explode('-', $language)[0] !== 'de'
            || preg_match('/^(.+?)-\s+(?:und|sowie)\s+(.+)$/iu', trim($occupation), $match) !== 1
        ) {
            return [trim($occupation)];
        }

        $first_stem = trim($match[1]);
        $second_occupation = trim($match[2]);
        $length = mb_strlen($second_occupation);

        for ($offset = 1; $offset < $length; $offset++) {
            $shared_headword = mb_substr($second_occupation, $offset);
            $first_occupation = $first_stem . $shared_headword;

            if (
                $this->isKnownOccupationExpression($first_occupation, $language)
                && $this->isKnownOccupationExpression($second_occupation, $language)
            ) {
                return [$first_occupation, $second_occupation];
            }
        }

        return [trim($occupation)];
    }

    private function isKnownOccupationExpression(string $occupation, string $language): bool
    {
        $lower = mb_strtolower($occupation);

        if (
            isset(self::MASTER_QUALIFICATION_COMPOUNDS[$lower])
            || preg_match('/^.+(?:geselle|lehrling)$/iu', $occupation) === 1
        ) {
            return true;
        }

        foreach ($this->normalization_rules as $rule) {
            if ($this->ruleMatches($rule, $occupation, $language)) {
                return true;
            }
        }

        foreach ($this->ohdab_special_mappings as $mapping) {
            if ($this->ohdabSpecialMappingMatches($mapping, $occupation, $language)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function splitOccupation(string $occupation): array
    {
        // M2-R001: Split multiple statements by separators and standalone conjunctions.
        $parts = [];
        $gender_slash_placeholder = "\x1F";
        $occupation = preg_replace(
            '/\/(?=(?:in(?:nen)?|r)(?:\b|-))/u',
            $gender_slash_placeholder,
            $occupation
        ) ?? $occupation;

        foreach (preg_split('/\s*[,\/;&+]\s*/u', $occupation) ?: [] as $chunk) {
            $chunk = trim(str_replace($gender_slash_placeholder, '/', $chunk));

            if ($chunk === '') {
                continue;
            }

            if (preg_match('/-\s+(und|and|sowie)\s+/iu', $chunk) === 1) {
                $parts[] = $chunk;
                continue;
            }

            foreach (preg_split('/\s+(?:und|and|sowie)\s+/iu', $chunk) ?: [] as $part) {
                $part = trim($part);

                if ($part !== '') {
                    $parts[] = $part;
                }
            }
        }

        return array_values(array_filter($parts, static fn (string $part): bool => $part !== ''));
    }

    /**
     * @param array{employer?:string,type?:string,note?:string} $context
     *
     * @return array{original_part_text:string,language:string,social_status:string,occupation_normalized:string,occupation_de_male:string,occupation_de_female:string,occupation_de_neutral:string,occupation_en_male:string,occupation_en_female:string,occupation_en_neutral:string,office:string,qualification:string,code_hisco:string,code_gnd:string,code_ohdab:string,code_factgrid:string,code_wikidata:string,norm_concept_id:int,status:string,rule_numbers:string}
     */
    private function normalizePart(string $part, string $language, array $context): array
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
            'occupation_status'     => '',
            'office'                => '',
            'qualification'         => '',
            'code_hisco'            => '',
            'code_gnd'              => '',
            'code_ohdab'            => '',
            'code_factgrid'         => '',
            'code_wikidata'         => '',
            'norm_concept_id'       => 0,
            'status'                => self::STATUS_UNCLEAR,
            'rule_numbers'          => '',
        ];

        foreach ($this->builtin_rule_order as $rule_id) {
            if (
                $rule_id === 'M2-R060'
                && (
                    preg_match('/^(?:ehemalig(?:e|er|es|en|em)?|gewesen(?:e|er|es|en|em)?)\s+(.+)$/iu', $original, $match) === 1
                    || preg_match('/^(.+?)\s+(?:a\.\s*D\.|i\.\s*R\.)$/iu', $original, $match) === 1
                )
            ) {
                // M2-R060: Former occupation.
                $normalized = $this->normalizePart(trim($match[1]), $language, $context);
                $normalized['original_part_text'] = $original;
                $normalized['occupation_status'] = 'former';
                $rules = array_values(array_filter(array_map('trim', explode(',', $normalized['rule_numbers']))));
                $normalized['rule_numbers'] = implode(', ', array_values(array_unique([$rule_id, ...$rules])));

                return $normalized;
            }

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

            if ($rule_id === 'M2-R021' && preg_match('/^(.+?)(tochter|sohn|gattin|ehefrau)$/iu', $original, $match) === 1) {
                // M2-R021: Kinship-derived compounds are hints, not occupations of the current person.
                return $this->withRules($entry, [
                    'social_status' => match (mb_strtolower($match[2])) {
                        'tochter' => 'Tochter',
                        'sohn'    => 'Sohn',
                        default   => 'Gattin',
                    },
                    'status'        => self::STATUS_UNCLEAR,
                ], [$rule_id]);
            }

            if ($rule_id === 'M2-R030' && preg_match('/^(.+?)\s*:\s*(meister|geselle|lehrling)$/iu', $original, $match) === 1) {
                // M2-R030: Craft qualification after colon.
                $occupation = $this->normalizeOccupationName($match[1]);
                $entry = $this->withRules($entry, [
                    'occupation_normalized' => $this->normalizeOccupationName($match[1]),
                    'qualification'         => self::QUALIFICATIONS[mb_strtolower($match[2])],
                    'status'                => self::STATUS_RECOGNIZED,
                ], [$rule_id]);

                return $this->enrichMappedOccupation($entry, $occupation, $language, [$rule_id]);
            }

            if (
                $rule_id === 'M2-R031'
                && (
                    isset(self::MASTER_QUALIFICATION_COMPOUNDS[$lower])
                    || (
                        !in_array($lower, self::QUALIFICATION_COMPOUND_EXCEPTIONS, true)
                        && preg_match('/^(.+?)(geselle|lehrling)$/iu', $original, $match) === 1
                    )
                )
            ) {
                // M2-R031: Compound craft qualification.
                $occupation = self::MASTER_QUALIFICATION_COMPOUNDS[$lower] ?? $this->normalizeOccupationName($match[1]);
                $qualification = isset(self::MASTER_QUALIFICATION_COMPOUNDS[$lower])
                    ? 'Meister'
                    : self::QUALIFICATIONS[mb_strtolower($match[2])];
                $entry = $this->withRules($entry, [
                    'occupation_normalized' => $occupation,
                    'qualification'         => $qualification,
                    'status'                => self::STATUS_RECOGNIZED,
                ], [$rule_id]);

                return $this->enrichMappedOccupation($entry, $occupation, $language, [$rule_id]);
            }

            if ($rule_id === 'M2-R032' && in_array($lower, self::MASTER_COMPOUND_EXCEPTIONS, true)) {
                // M2-R032: Independent master compounds are not split.
                $entry = $this->withRules($entry, [
                    'occupation_normalized' => $original,
                    'status'                => self::STATUS_RECOGNIZED,
                ], [$rule_id]);

                return $this->enrichMappedOccupation($entry, $original, $language, [$rule_id]);
            }

            if ($rule_id === 'M2-R040' && preg_match('/^arbeiter\s*(?::\s*fabrik|\(\s*fabrik\s*\))$/iu', $original) === 1) {
                // M2-R040: Context-based occupation refinement.
                return $this->withRules($entry, [
                    'occupation_normalized' => 'Fabrikarbeiter',
                    'occupation_de_male'    => 'Fabrikarbeiter',
                    'occupation_de_female'  => 'Fabrikarbeiterin',
                    'occupation_de_neutral' => 'Fabrikarbeiter/in',
                    'occupation_en_male'    => 'factory worker',
                    'occupation_en_female'  => 'factory worker',
                    'occupation_en_neutral' => 'factory worker',
                    'status'                => self::STATUS_RECOGNIZED,
                ], [$rule_id]);
            }

            if (
                $rule_id === 'M2-R040'
                && in_array($lower, ['angestellter', 'angestellte'], true)
                && preg_match('/(^|[^\pL])bank(?:en)?($|[^\pL])/iu', (string) ($context['employer'] ?? '')) === 1
            ) {
                // M2-R040: Context-based occupation refinement.
                return $this->withRules($entry, [
                    'occupation_normalized' => 'Bankangestellter',
                    'occupation_de_male'    => 'Bankangestellter',
                    'occupation_de_female'  => 'Bankangestellte',
                    'occupation_de_neutral' => 'Bankangestellte/r',
                    'occupation_en_male'    => 'bank clerk',
                    'occupation_en_female'  => 'bank clerk',
                    'occupation_en_neutral' => 'bank clerk',
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
                            'code_wikidata'         => $rule['code_wikidata'] ?? '',
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
                            'code_wikidata'         => $mapping['code_wikidata'],
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
     * @param array{original_part_text:string,language:string,social_status:string,occupation_normalized:string,occupation_de_male:string,occupation_de_female:string,occupation_de_neutral:string,occupation_en_male:string,occupation_en_female:string,occupation_en_neutral:string,office:string,qualification:string,code_hisco:string,code_gnd:string,code_ohdab:string,code_factgrid:string,code_wikidata:string,norm_concept_id:int,status:string,rule_numbers:string} $entry
     * @param list<string> $rules
     *
     * @return array{original_part_text:string,language:string,social_status:string,occupation_normalized:string,occupation_de_male:string,occupation_de_female:string,occupation_de_neutral:string,occupation_en_male:string,occupation_en_female:string,occupation_en_neutral:string,office:string,qualification:string,code_hisco:string,code_gnd:string,code_ohdab:string,code_factgrid:string,code_wikidata:string,norm_concept_id:int,status:string,rule_numbers:string}
     */
    private function enrichMappedOccupation(array $entry, string $occupation, string $language, array $rules): array
    {
        foreach ($this->builtin_rule_order as $rule_id) {
            if ($rule_id === 'M2-R050') {
                foreach ($this->normalization_rules as $rule) {
                    if ($this->ruleMatches($rule, $occupation, $language)) {
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
                            'code_hisco'            => $rule['code_hisco'],
                            'code_gnd'              => $rule['code_gnd'],
                            'code_ohdab'            => $rule['code_ohdab'],
                            'code_factgrid'         => $rule['code_factgrid'] ?? '',
                            'code_wikidata'         => $rule['code_wikidata'] ?? '',
                            'status'                => self::STATUS_RECOGNIZED,
                        ], [...$rules, $rule_id]);
                    }
                }
            }

            if ($rule_id === 'M4-R100') {
                foreach ($this->ohdab_special_mappings as $mapping) {
                    if ($this->ohdabSpecialMappingMatches($mapping, $occupation, $language)) {
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
                            'code_wikidata'         => $mapping['code_wikidata'],
                            'norm_concept_id'       => $mapping['norm_concept_id'],
                            'status'                => self::STATUS_RECOGNIZED,
                        ], [...$rules, $rule_id]);
                    }
                }
            }
        }

        return $entry;
    }

    /**
     * @param array{language:string,original_text:string,social_status:string,occupation_normalized:string,occupation_de_male?:string,occupation_de_female?:string,occupation_de_neutral?:string,occupation_en_male?:string,occupation_en_female?:string,occupation_en_neutral?:string,qualification:string,code_hisco:string,code_gnd:string,code_ohdab:string,code_factgrid?:string,code_wikidata?:string} $rule
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
     * @param array{language:string,original_text:string,norm_concept_id:int,occupation_normalized:string,occupation_de_male:string,occupation_de_female:string,occupation_de_neutral:string,occupation_en_male:string,occupation_en_female:string,occupation_en_neutral:string,code_hisco:string,code_gnd:string,code_ohdab:string,code_factgrid:string,code_wikidata:string} $mapping
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
     * @param array{original_part_text:string,language:string,social_status:string,occupation_normalized:string,occupation_de_male:string,occupation_de_female:string,occupation_de_neutral:string,occupation_en_male:string,occupation_en_female:string,occupation_en_neutral:string,office:string,qualification:string,code_hisco:string,code_gnd:string,code_ohdab:string,code_factgrid:string,code_wikidata:string,norm_concept_id:int,status:string,rule_numbers:string} $entry
     * @param array<string,int|string> $values
     * @param list<string> $rules
     *
     * @return array{original_part_text:string,language:string,social_status:string,occupation_normalized:string,occupation_de_male:string,occupation_de_female:string,occupation_de_neutral:string,occupation_en_male:string,occupation_en_female:string,occupation_en_neutral:string,office:string,qualification:string,code_hisco:string,code_gnd:string,code_ohdab:string,code_factgrid:string,code_wikidata:string,norm_concept_id:int,status:string,rule_numbers:string}
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
