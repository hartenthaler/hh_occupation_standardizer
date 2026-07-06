<?php

declare(strict_types=1);

namespace Hartenthaler\Webtrees\Module\OccupationStandardizer\Application\Service;

use Fisharebest\Webtrees\Fact;
use Fisharebest\Webtrees\I18N;
use Hartenthaler\Webtrees\Module\OccupationStandardizer\Infrastructure\Persistence\Schema\OccupationSchema;
use Hartenthaler\Webtrees\Module\OccupationStandardizer\Internationalization\MoreI18N;
use Illuminate\Database\Capsule\Manager as DBManager;

use function array_filter;
use function array_search;
use function array_splice;
use function array_unique;
use function array_values;
use function explode;
use function in_array;
use function implode;
use function trim;

final class OccupationLabelService
{
    private const MODULE_NAME = '_hh_occupation_standardizer_';
    private const BUILTIN_RULE_ORDER_PREFERENCE = 'builtinRuleOrder';
    private const BUILTIN_RULE_STATUS_PREFIX = 'builtinRuleStatus-';
    private const TREE_LANGUAGE_PREFIX = 'treeLanguage-';
    private const DEFAULT_OCCUPATION_LANGUAGE = 'de';

    /** @var list<string> */
    private array $builtin_rule_order;
    private OhdabSpecialDatabaseService $ohdab_special_database_service;
    private HiscoCatalogService $hisco_catalog_service;
    /** @var array<string,string> */
    private array $hisco_hierarchy_cache = [];
    /** @var array<int,string> */
    private array $ohdab_hierarchy_cache = [];

    /**
     * @param list<string> $builtin_rule_order
     */
    public function __construct(array $builtin_rule_order = [])
    {
        $this->builtin_rule_order = $builtin_rule_order !== [] ? $builtin_rule_order : $this->configuredBuiltinRuleOrder();
        $this->ohdab_special_database_service = new OhdabSpecialDatabaseService();
        $this->hisco_catalog_service = new HiscoCatalogService();
    }

    /**
     * @param array{employer?:string,type?:string,note?:string} $context
     *
     * @return list<array{label:string,title:string,status:string,norm_concept_id:int}>
     */
    public function labelsForOccupation(string $occupation, string $language = '', string $sex = 'U', string $user_language = '', array $context = []): array
    {
        return $this->labels(
            (new OccupationNormalizationService(
                $this->normalizationRules(),
                $this->builtin_rule_order,
                $this->ohdab_special_database_service->mappings(),
                $this->hisco_catalog_service->normalizationIndex()
            ))->normalize($occupation, $language, $context),
            $sex,
            $user_language
        );
    }

    /**
     * @return list<array{label:string,title:string,status:string,norm_concept_id:int}>
     */
    public function labelsForFact(Fact $fact, string $sex = 'U', string $user_language = ''): array
    {
        $entries = $this->entriesForFact($fact);

        if ($entries !== []) {
            return $this->labelsForEntries($entries, $sex, $user_language);
        }

        return $this->labelsForOccupation(
            $fact->value(),
            $this->occupationLanguage((int) $fact->record()->tree()->id()),
            $sex,
            $user_language,
            [
                'employer' => trim($fact->attribute('AGNC')),
                'type'     => trim($fact->attribute('TYPE')),
                'note'     => trim($fact->attribute('NOTE')),
            ]
        );
    }

    /**
     * @param list<array{part_index:int,original_part_text:string,language?:string,social_status:string,occupation_normalized:string,occupation_de_male?:string,occupation_de_female?:string,occupation_de_neutral?:string,occupation_en_male?:string,occupation_en_female?:string,occupation_en_neutral?:string,office:string,qualification:string,code_hisco?:string,code_gnd?:string,code_ohdab?:string,code_factgrid?:string,norm_concept_id?:int,status:string,rule_numbers:string}> $entries
     *
     * @return list<array{label:string,title:string,status:string,norm_concept_id:int}>
     */
    public function labelsForEntries(array $entries, string $sex = 'U', string $user_language = ''): array
    {
        return $this->labels($entries, $sex, $user_language);
    }

    /**
     * @param list<array{part_index:int,original_part_text:string,language?:string,social_status:string,occupation_normalized:string,occupation_de_male?:string,occupation_de_female?:string,occupation_de_neutral?:string,occupation_en_male?:string,occupation_en_female?:string,occupation_en_neutral?:string,office:string,qualification:string,code_hisco?:string,code_gnd?:string,code_ohdab?:string,code_factgrid?:string,norm_concept_id?:int,status:string,rule_numbers:string}> $entries
     *
     * @return list<array{label:string,title:string,status:string,norm_concept_id:int}>
     */
    private function labels(array $entries, string $sex, string $user_language): array
    {
        $labels = [];
        $user_language = $user_language !== '' ? $user_language : I18N::languageTag();

        foreach ($entries as $entry) {
            $entry = $this->enrichedEntry($entry);
            $title_parts = [];

            if ($entry['social_status'] !== '') {
                $title_parts[] = MoreI18N::xlate('Social status') . ': ' . $entry['social_status'];
            }

            if (($entry['language'] ?? '') !== '') {
                $title_parts[] = MoreI18N::xlate('Language') . ': ' . $entry['language'];
            }

            if ($entry['occupation_normalized'] !== '') {
                $title_parts[] = MoreI18N::xlate('Occupation') . ': ' . $entry['occupation_normalized'];
            }

            if (($entry['occupation_status'] ?? '') !== '') {
                $title_parts[] = I18N::translate('Occupation status') . ': ' . match ($entry['occupation_status']) {
                    'former' => I18N::translate('Former'),
                    default  => $entry['occupation_status'],
                };
            }

            if (($entry['occupation_de_male'] ?? '') !== '') {
                $title_parts[] = I18N::translate('German masculine form') . ': ' . $entry['occupation_de_male'];
            }

            if (($entry['occupation_de_female'] ?? '') !== '') {
                $title_parts[] = I18N::translate('German feminine form') . ': ' . $entry['occupation_de_female'];
            }

            if (($entry['occupation_de_neutral'] ?? '') !== '') {
                $title_parts[] = I18N::translate('German neutral form') . ': ' . $entry['occupation_de_neutral'];
            }

            if (($entry['occupation_en_male'] ?? '') !== '') {
                $title_parts[] = I18N::translate('English masculine form') . ': ' . $entry['occupation_en_male'];
            }

            if (($entry['occupation_en_female'] ?? '') !== '') {
                $title_parts[] = I18N::translate('English feminine form') . ': ' . $entry['occupation_en_female'];
            }

            if (($entry['occupation_en_neutral'] ?? '') !== '') {
                $title_parts[] = I18N::translate('English neutral form') . ': ' . $entry['occupation_en_neutral'];
            }

            if ($entry['office'] !== '') {
                $title_parts[] = MoreI18N::xlate('Office') . ': ' . $entry['office'];
            }

            if ($entry['qualification'] !== '') {
                $title_parts[] = I18N::translate('Qualification') . ': ' . $entry['qualification'];
            }

            if (($entry['code_ohdab'] ?? '') !== '') {
                $title_parts[] = $this->identifierTitle('OhdAB', $entry['code_ohdab']);
            }

            $hierarchy = $this->ohdabHierarchy((int) ($entry['norm_concept_id'] ?? 0));

            if ($hierarchy !== '') {
                $title_parts[] = I18N::translate('OhdAB hierarchy') . ': ' . $hierarchy;
            }

            if (($entry['code_hisco'] ?? '') !== '') {
                $title_parts[] = $this->identifierTitle('HISCO', $entry['code_hisco']);

                $hisco_hierarchy = $this->hiscoHierarchy((string) $entry['code_hisco']);

                if ($hisco_hierarchy !== '') {
                    $title_parts[] = I18N::translate('HISCO hierarchy') . ': ' . $hisco_hierarchy;
                }
            }

            if (($entry['code_gnd'] ?? '') !== '') {
                $title_parts[] = $this->identifierTitle('GND', $entry['code_gnd']);
            }

            if (($entry['code_factgrid'] ?? '') !== '') {
                $title_parts[] = $this->identifierTitle('FactGrid', $entry['code_factgrid']);
            }

            if (($entry['code_wikidata'] ?? '') !== '') {
                $title_parts[] = $this->identifierTitle('Wikidata', $entry['code_wikidata']);
            }

            $title_parts[] = MoreI18N::xlate('Status') . ': ' . $this->statusLabel($entry['status']);
            $title_parts[] = MoreI18N::xlate('Rules') . ': ' . $entry['rule_numbers'];

            $label = [
                'label'           => $this->label($entry, $sex, $user_language),
                'title'           => implode("\n", $title_parts),
                'status'          => $entry['status'],
                'norm_concept_id' => (int) ($entry['norm_concept_id'] ?? 0),
            ];
            $label_key = implode("\0", [$label['label'], $label['title'], $label['status'], (string) $label['norm_concept_id']]);
            $labels[$label_key] = $label;
        }

        return array_values($labels);
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            OccupationNormalizationService::STATUS_RECOGNIZED => I18N::translate('recognized'),
            OccupationNormalizationService::STATUS_UNCLEAR    => I18N::translate('unclear'),
            OccupationNormalizationService::STATUS_IGNORED    => I18N::translate('ignored'),
            default                                           => $status,
        };
    }

    private function identifierTitle(string $label, string $code): string
    {
        return $label . ': ' . $code;
    }

    private function hiscoHierarchy(string $code): string
    {
        $code = trim($code);

        if ($code === '') {
            return '';
        }

        if (isset($this->hisco_hierarchy_cache[$code])) {
            return $this->hisco_hierarchy_cache[$code];
        }

        $row = $this->hisco_catalog_service->occupation($code, I18N::languageTag());

        if ($row === null) {
            return $this->hisco_hierarchy_cache[$code] = '';
        }

        $parts = [
            trim($row['label']),
            trim($row['unit']['code'] . ' ' . $row['unit']['label']),
            trim($row['minor']['code'] . ' ' . $row['minor']['label']),
            trim($row['major']['code'] . ' ' . $row['major']['label']),
        ];

        return $this->hisco_hierarchy_cache[$code] = implode(' > ', array_values(array_filter($parts)));
    }

    private function ohdabHierarchy(int $concept_id): string
    {
        if ($concept_id <= 0) {
            return '';
        }

        if (isset($this->ohdab_hierarchy_cache[$concept_id])) {
            return $this->ohdab_hierarchy_cache[$concept_id];
        }

        $parts = [];

        foreach ($this->ohdab_special_database_service->hierarchyRows($concept_id) as $row) {
            $parts[] = trim($row['label']);
        }

        return $this->ohdab_hierarchy_cache[$concept_id] = implode(' > ', array_values(array_filter($parts)));
    }

    /**
     * @return list<array{part_index:int,original_part_text:string,language:string,social_status:string,occupation_normalized:string,occupation_de_male:string,occupation_de_female:string,occupation_de_neutral:string,occupation_en_male:string,occupation_en_female:string,occupation_en_neutral:string,office:string,qualification:string,code_hisco:string,code_gnd:string,code_ohdab:string,code_factgrid:string,norm_concept_id:int,status:string,rule_numbers:string}>
     */
    private function entriesForFact(Fact $fact): array
    {
        if (!DBManager::schema()->hasTable(OccupationSchema::TABLE_NORMALIZED_ENTRIES)) {
            return [];
        }

        return DBManager::table(OccupationSchema::TABLE_NORMALIZED_ENTRIES)
            ->where('tree_id', '=', $fact->record()->tree()->id())
            ->where('individual_xref', '=', $fact->record()->xref())
            ->where('fact_id', '=', $fact->id())
            ->where('is_active', '=', true)
            ->orderBy('part_index')
            ->get()
            ->map(static fn (object $entry): array => [
                'part_index'            => (int) $entry->part_index,
                'original_part_text'    => (string) $entry->original_part_text,
                'language'              => (string) ($entry->language ?? ''),
                'social_status'         => (string) ($entry->social_status ?? ''),
                'occupation_normalized' => (string) ($entry->occupation_normalized ?? ''),
                'occupation_de_male'    => (string) ($entry->occupation_de_male ?? ''),
                'occupation_de_female'  => (string) ($entry->occupation_de_female ?? ''),
                'occupation_de_neutral' => (string) ($entry->occupation_de_neutral ?? ''),
                'occupation_en_male'    => (string) ($entry->occupation_en_male ?? ''),
                'occupation_en_female'  => (string) ($entry->occupation_en_female ?? ''),
                'occupation_en_neutral' => (string) ($entry->occupation_en_neutral ?? ''),
                'occupation_status'     => (string) ($entry->occupation_status ?? ''),
                'office'                => (string) ($entry->office ?? ''),
                'qualification'         => (string) ($entry->qualification ?? ''),
                'code_hisco'            => (string) ($entry->code_hisco ?? ''),
                'code_gnd'              => (string) ($entry->code_gnd ?? ''),
                'code_ohdab'            => (string) ($entry->code_ohdab ?? ''),
                'code_factgrid'         => (string) ($entry->code_factgrid ?? ''),
                'code_wikidata'         => (string) ($entry->code_wikidata ?? ''),
                'norm_concept_id'       => (int) ($entry->norm_concept_id ?? 0),
                'status'                => (string) $entry->status,
                'rule_numbers'          => (string) $entry->rule_numbers,
            ])
            ->all();
    }

    private function occupationLanguage(int $tree_id): string
    {
        return (string) (DBManager::table('module_setting')
            ->where('module_name', '=', self::MODULE_NAME)
            ->where('setting_name', '=', self::TREE_LANGUAGE_PREFIX . $tree_id)
            ->value('setting_value') ?? self::DEFAULT_OCCUPATION_LANGUAGE);
    }

    /**
     * @param array{part_index:int,original_part_text:string,language?:string,social_status:string,occupation_normalized:string,occupation_de_male?:string,occupation_de_female?:string,occupation_de_neutral?:string,occupation_en_male?:string,occupation_en_female?:string,occupation_en_neutral?:string,office:string,qualification:string,code_hisco?:string,code_gnd?:string,code_ohdab?:string,code_factgrid?:string,norm_concept_id?:int,status:string,rule_numbers:string} $entry
     */
    private function label(array $entry, string $sex, string $user_language): string
    {
        $localized_forms = $this->localizedGenderForms($entry, $user_language);
        $fallback_forms = $this->fallbackGenderForms($entry, $user_language);

        $label = $this->genderedLabel($localized_forms, $sex);

        if ($label !== '') {
            return $label;
        }

        $label = $this->genderedLabel($fallback_forms, $sex);

        if ($label !== '') {
            return $label;
        }

        foreach (['occupation_normalized', 'social_status', 'office', 'qualification'] as $key) {
            if ($entry[$key] !== '') {
                return $entry[$key];
            }
        }

        return $entry['original_part_text'];
    }

    /**
     * @param array{male:string,female:string,neutral:string} $forms
     */
    private function genderedLabel(array $forms, string $sex): string
    {
        if ($sex === 'F' && $forms['female'] !== '') {
            return $forms['female'];
        }

        if ($sex === 'M' && $forms['male'] !== '') {
            return $forms['male'];
        }

        if (!in_array($sex, ['F', 'M'], true) && $forms['neutral'] !== '') {
            return $forms['neutral'];
        }

        $fallback_forms = $sex === 'F'
            ? [$forms['neutral'], $forms['male']]
            : ($sex === 'M' ? [$forms['neutral'], $forms['female']] : [$forms['male'], $forms['female']]);

        foreach ($fallback_forms as $fallback_form) {
            if ($fallback_form !== '') {
                return $fallback_form;
            }
        }

        return '';
    }

    /**
     * @param array{occupation_de_male?:string,occupation_de_female?:string,occupation_de_neutral?:string,occupation_en_male?:string,occupation_en_female?:string,occupation_en_neutral?:string} $entry
     *
     * @return array{male:string,female:string,neutral:string}
     */
    private function localizedGenderForms(array $entry, string $user_language): array
    {
        $prefer_german = explode('-', $user_language)[0] === 'de';
        $male_key = $prefer_german ? 'occupation_de_male' : 'occupation_en_male';
        $female_key = $prefer_german ? 'occupation_de_female' : 'occupation_en_female';
        $neutral_key = $prefer_german ? 'occupation_de_neutral' : 'occupation_en_neutral';

        return [
            'male'    => (string) ($entry[$male_key] ?? ''),
            'female'  => (string) ($entry[$female_key] ?? ''),
            'neutral' => (string) ($entry[$neutral_key] ?? ''),
        ];
    }

    /**
     * @param array{occupation_de_male?:string,occupation_de_female?:string,occupation_de_neutral?:string,occupation_en_male?:string,occupation_en_female?:string,occupation_en_neutral?:string} $entry
     *
     * @return array{male:string,female:string,neutral:string}
     */
    private function fallbackGenderForms(array $entry, string $user_language): array
    {
        $prefer_german = explode('-', $user_language)[0] === 'de';
        $male_key = $prefer_german ? 'occupation_en_male' : 'occupation_de_male';
        $female_key = $prefer_german ? 'occupation_en_female' : 'occupation_de_female';
        $neutral_key = $prefer_german ? 'occupation_en_neutral' : 'occupation_de_neutral';

        return [
            'male'    => (string) ($entry[$male_key] ?? ''),
            'female'  => (string) ($entry[$female_key] ?? ''),
            'neutral' => (string) ($entry[$neutral_key] ?? ''),
        ];
    }

    /**
     * @param array{part_index:int,original_part_text:string,language?:string,social_status:string,occupation_normalized:string,occupation_de_male?:string,occupation_de_female?:string,occupation_de_neutral?:string,occupation_en_male?:string,occupation_en_female?:string,occupation_en_neutral?:string,office:string,qualification:string,code_hisco?:string,code_gnd?:string,code_ohdab?:string,code_factgrid?:string,norm_concept_id?:int,status:string,rule_numbers:string} $entry
     *
     * @return array{part_index:int,original_part_text:string,language?:string,social_status:string,occupation_normalized:string,occupation_de_male?:string,occupation_de_female?:string,occupation_de_neutral?:string,occupation_en_male?:string,occupation_en_female?:string,occupation_en_neutral?:string,office:string,qualification:string,code_hisco?:string,code_gnd?:string,code_ohdab?:string,code_factgrid?:string,norm_concept_id?:int,status:string,rule_numbers:string}
     */
    private function enrichedEntry(array $entry): array
    {
        $language = trim((string) ($entry['language'] ?? ''));
        $occupation = trim((string) ($entry['occupation_normalized'] ?? ''));

        if ($language === '' || $occupation === '' || !DBManager::schema()->hasTable(OccupationSchema::TABLE_NORMALIZATION_TERMS)) {
            return $entry;
        }

        $term = DBManager::table(OccupationSchema::TABLE_NORMALIZATION_TERMS)
            ->where('normalized_key', '=', $language . ':' . $occupation)
            ->first();

        if ($term === null) {
            return $entry;
        }

        foreach ([
            'occupation_de_male',
            'occupation_de_female',
            'occupation_de_neutral',
            'occupation_en_male',
            'occupation_en_female',
            'occupation_en_neutral',
            'code_hisco',
            'code_gnd',
            'code_ohdab',
            'code_factgrid',
            'code_wikidata',
        ] as $key) {
            if (($entry[$key] ?? '') === '' && (string) ($term->{$key} ?? '') !== '') {
                $entry[$key] = (string) $term->{$key};
            }
        }

        return $entry;
    }

    /**
     * @return list<array{language:string,original_text:string,social_status:string,occupation_normalized:string,occupation_de_male:string,occupation_de_female:string,occupation_de_neutral:string,occupation_en_male:string,occupation_en_female:string,occupation_en_neutral:string,qualification:string,code_hisco:string,code_gnd:string,code_ohdab:string,code_factgrid:string}>
     */
    private function normalizationRules(): array
    {
        if (!DBManager::schema()->hasTable(OccupationSchema::TABLE_NORMALIZATION_RULES)) {
            return [];
        }

        return DBManager::table(OccupationSchema::TABLE_NORMALIZATION_RULES . ' AS rules')
            ->leftJoin(OccupationSchema::TABLE_NORMALIZATION_TERMS . ' AS terms', 'terms.id', '=', 'rules.normalized_term_id')
            ->select([
                'rules.language',
                'rules.original_text',
                'rules.social_status',
                'rules.qualification',
                'terms.occupation_de_male',
                'terms.occupation_de_female',
                'terms.occupation_de_neutral',
                'terms.occupation_en_male',
                'terms.occupation_en_female',
                'terms.occupation_en_neutral',
                'terms.code_hisco',
                'terms.code_gnd',
                'terms.code_ohdab',
                'terms.code_factgrid',
                'terms.code_wikidata',
            ])
            ->where('rules.enabled', '=', true)
            ->get()
            ->map(static fn (object $row): array => [
                'language'              => (string) $row->language,
                'original_text'         => (string) $row->original_text,
                'social_status'         => (string) ($row->social_status ?? ''),
                'occupation_normalized' => (string) ($row->occupation_de_male ?? ''),
                'occupation_de_male'    => (string) ($row->occupation_de_male ?? ''),
                'occupation_de_female'  => (string) ($row->occupation_de_female ?? ''),
                'occupation_de_neutral' => (string) ($row->occupation_de_neutral ?? ''),
                'occupation_en_male'    => (string) ($row->occupation_en_male ?? ''),
                'occupation_en_female'  => (string) ($row->occupation_en_female ?? ''),
                'occupation_en_neutral' => (string) ($row->occupation_en_neutral ?? ''),
                'qualification'         => (string) ($row->qualification ?? ''),
                'code_hisco'            => (string) ($row->code_hisco ?? ''),
                'code_gnd'              => (string) ($row->code_gnd ?? ''),
                'code_ohdab'            => (string) ($row->code_ohdab ?? ''),
                'code_factgrid'         => (string) ($row->code_factgrid ?? ''),
                'code_wikidata'         => (string) ($row->code_wikidata ?? ''),
            ])
            ->all();
    }

    /**
     * @return list<string>
     */
    private function configuredBuiltinRuleOrder(): array
    {
        $default_order = OccupationNormalizationService::defaultBuiltinRuleOrder();
        $stored_order = (string) (DBManager::table('module_setting')
            ->where('module_name', '=', self::MODULE_NAME)
            ->where('setting_name', '=', self::BUILTIN_RULE_ORDER_PREFERENCE)
            ->value('setting_value') ?? implode(',', $default_order));
        $order = $this->completeBuiltinRuleOrder(explode(',', $stored_order));
        $enabled_rules = array_values(array_filter(
            $default_order,
            static fn (string $rule_id): bool => (string) (DBManager::table('module_setting')
                ->where('module_name', '=', self::MODULE_NAME)
                ->where('setting_name', '=', self::BUILTIN_RULE_STATUS_PREFIX . $rule_id)
                ->value('setting_value') ?? '1') === '1'
        ));

        return array_values(array_filter(
            $order,
            static fn (string $rule_id): bool => in_array($rule_id, $enabled_rules, true)
        ));
    }

    /**
     * @param array<string> $order
     *
     * @return list<string>
     */
    private function completeBuiltinRuleOrder(array $order): array
    {
        $default_order = OccupationNormalizationService::defaultBuiltinRuleOrder();
        $completed_order = [];

        foreach ($order as $rule_id) {
            if (in_array($rule_id, $default_order, true)) {
                $completed_order[] = $rule_id;
            }
        }

        $completed_order = array_values(array_unique($completed_order));

        foreach ($default_order as $rule_id) {
            if (!in_array($rule_id, $completed_order, true)) {
                $completed_order[] = $rule_id;
            }
        }

        $ohdab_index = array_search('M4-R100', $completed_order, true);
        $fallback_index = array_search('M2-R090', $completed_order, true);

        if ($ohdab_index !== false && $fallback_index !== false && $ohdab_index > $fallback_index) {
            array_splice($completed_order, $ohdab_index, 1);
            $fallback_index = array_search('M2-R090', $completed_order, true);
            array_splice($completed_order, (int) $fallback_index, 0, ['M4-R100']);
        }

        return $completed_order;
    }
}
