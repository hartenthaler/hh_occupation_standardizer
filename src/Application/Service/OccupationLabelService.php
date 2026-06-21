<?php

declare(strict_types=1);

namespace Hartenthaler\Webtrees\Module\OccupationStandardizer\Application\Service;

use Fisharebest\Webtrees\I18N;
use Hartenthaler\Webtrees\Module\OccupationStandardizer\Infrastructure\Persistence\Schema\OccupationSchema;
use Hartenthaler\Webtrees\Module\OccupationStandardizer\Internationalization\MoreI18N;
use Illuminate\Database\Capsule\Manager as DBManager;

use function implode;

final class OccupationLabelService
{
    /**
     * @return list<array{label:string,title:string,status:string}>
     */
    public function labelsForOccupation(string $occupation, string $language = ''): array
    {
        return $this->labels((new OccupationNormalizationService($this->normalizationRules()))->normalize($occupation, $language));
    }

    /**
     * @param list<array{part_index:int,original_part_text:string,language?:string,social_status:string,occupation_normalized:string,office:string,qualification:string,code:string,code_hisco?:string,code_gnd?:string,code_ohdab?:string,status:string,rule_numbers:string}> $entries
     *
     * @return list<array{label:string,title:string,status:string}>
     */
    public function labelsForEntries(array $entries): array
    {
        return $this->labels($entries);
    }

    /**
     * @param list<array{part_index:int,original_part_text:string,language?:string,social_status:string,occupation_normalized:string,office:string,qualification:string,code:string,code_hisco?:string,code_gnd?:string,code_ohdab?:string,status:string,rule_numbers:string}> $entries
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

            if ($entry['code'] !== '') {
                $title_parts[] = I18N::translate('Code') . ': ' . $entry['code'];
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
     * @param array{part_index:int,original_part_text:string,language?:string,social_status:string,occupation_normalized:string,office:string,qualification:string,code:string,code_hisco?:string,code_gnd?:string,code_ohdab?:string,status:string,rule_numbers:string} $entry
     */
    private function label(array $entry): string
    {
        foreach (['occupation_normalized', 'social_status', 'office', 'qualification', 'code'] as $key) {
            if ($entry[$key] !== '') {
                return $entry[$key];
            }
        }

        return $entry['original_part_text'];
    }

    /**
     * @return list<array{language:string,original_text:string,social_status:string,occupation_normalized:string,qualification:string,code:string,code_hisco:string,code_gnd:string,code_ohdab:string}>
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
                'code'                  => (string) ($row->code ?? ''),
                'code_hisco'            => (string) ($row->code_hisco ?? ''),
                'code_gnd'              => (string) ($row->code_gnd ?? ''),
                'code_ohdab'            => (string) ($row->code_ohdab ?? ''),
            ])
            ->all();
    }
}
