<?php

declare(strict_types=1);

namespace Hartenthaler\Webtrees\Module\OccupationStandardizer\Application\Service;

use Fisharebest\Webtrees\I18N;
use Hartenthaler\Webtrees\Module\OccupationStandardizer\Infrastructure\Persistence\Schema\OccupationSchema;
use Hartenthaler\Webtrees\Module\OccupationStandardizer\Internationalization\MoreI18N;
use Illuminate\Database\Capsule\Manager as DBManager;

use function array_filter;
use function array_unique;
use function array_values;
use function explode;
use function in_array;
use function implode;

final class OccupationLabelService
{
    private const MODULE_NAME = '_hh_occupation_standardizer_';
    private const BUILTIN_RULE_ORDER_PREFERENCE = 'builtinRuleOrder';
    private const BUILTIN_RULE_STATUS_PREFIX = 'builtinRuleStatus-';

    /** @var list<string> */
    private array $builtin_rule_order;

    /**
     * @param list<string> $builtin_rule_order
     */
    public function __construct(array $builtin_rule_order = [])
    {
        $this->builtin_rule_order = $builtin_rule_order !== [] ? $builtin_rule_order : $this->configuredBuiltinRuleOrder();
    }

    /**
     * @return list<array{label:string,title:string,status:string}>
     */
    public function labelsForOccupation(string $occupation, string $language = ''): array
    {
        return $this->labels((new OccupationNormalizationService($this->normalizationRules(), $this->builtin_rule_order))->normalize($occupation, $language));
    }

    /**
     * @param list<array{part_index:int,original_part_text:string,language?:string,social_status:string,occupation_normalized:string,office:string,qualification:string,code_hisco?:string,code_gnd?:string,code_ohdab?:string,status:string,rule_numbers:string}> $entries
     *
     * @return list<array{label:string,title:string,status:string}>
     */
    public function labelsForEntries(array $entries): array
    {
        return $this->labels($entries);
    }

    /**
     * @param list<array{part_index:int,original_part_text:string,language?:string,social_status:string,occupation_normalized:string,office:string,qualification:string,code_hisco?:string,code_gnd?:string,code_ohdab?:string,status:string,rule_numbers:string}> $entries
     *
     * @return list<array{label:string,title:string,status:string}>
     */
    private function labels(array $entries): array
    {
        $labels = [];

        foreach ($entries as $entry) {
            $title_parts = [];

            if ($entry['social_status'] !== '') {
                $title_parts[] = I18N::translate('Social status') . ': ' . $entry['social_status'];
            }

            if (($entry['language'] ?? '') !== '') {
                $title_parts[] = I18N::translate('Language') . ': ' . $entry['language'];
            }

            if ($entry['occupation_normalized'] !== '') {
                $title_parts[] = MoreI18N::xlate('Occupation') . ': ' . $entry['occupation_normalized'];
            }

            if ($entry['office'] !== '') {
                $title_parts[] = I18N::translate('Office') . ': ' . $entry['office'];
            }

            if ($entry['qualification'] !== '') {
                $title_parts[] = I18N::translate('Qualification') . ': ' . $entry['qualification'];
            }

            if (($entry['code_hisco'] ?? '') !== '') {
                $title_parts[] = 'HISCO: ' . $entry['code_hisco'];
            }

            if (($entry['code_gnd'] ?? '') !== '') {
                $title_parts[] = 'GND: ' . $entry['code_gnd'];
            }

            if (($entry['code_ohdab'] ?? '') !== '') {
                $title_parts[] = 'OhdAB: ' . $entry['code_ohdab'];
            }

            $title_parts[] = MoreI18N::xlate('Status') . ': ' . I18N::translate($entry['status']);
            $title_parts[] = I18N::translate('Rules') . ': ' . $entry['rule_numbers'];

            $labels[] = [
                'label'  => $this->label($entry),
                'title'  => implode("\n", $title_parts),
                'status' => $entry['status'],
            ];
        }

        return $labels;
    }

    /**
     * @param array{part_index:int,original_part_text:string,language?:string,social_status:string,occupation_normalized:string,office:string,qualification:string,code_hisco?:string,code_gnd?:string,code_ohdab?:string,status:string,rule_numbers:string} $entry
     */
    private function label(array $entry): string
    {
        foreach (['occupation_normalized', 'social_status', 'office', 'qualification'] as $key) {
            if ($entry[$key] !== '') {
                return $entry[$key];
            }
        }

        return $entry['original_part_text'];
    }

    /**
     * @return list<array{language:string,original_text:string,social_status:string,occupation_normalized:string,qualification:string,code_hisco:string,code_gnd:string,code_ohdab:string}>
     */
    private function normalizationRules(): array
    {
        if (!DBManager::schema()->hasTable(OccupationSchema::TABLE_NORMALIZATION_RULES)) {
            return [];
        }

        return DBManager::table(OccupationSchema::TABLE_NORMALIZATION_RULES)
            ->where('enabled', '=', true)
            ->get()
            ->map(static fn (object $row): array => [
                'language'              => (string) $row->language,
                'original_text'         => (string) $row->original_text,
                'social_status'         => (string) ($row->social_status ?? ''),
                'occupation_normalized' => (string) ($row->occupation_normalized ?? ''),
                'qualification'         => (string) ($row->qualification ?? ''),
                'code_hisco'            => (string) ($row->code_hisco ?? ''),
                'code_gnd'              => (string) ($row->code_gnd ?? ''),
                'code_ohdab'            => (string) ($row->code_ohdab ?? ''),
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

        return $completed_order;
    }
}
