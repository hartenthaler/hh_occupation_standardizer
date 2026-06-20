<?php

declare(strict_types=1);

namespace Hartenthaler\Webtrees\Module\OccupationStandardizer\Application\Service;

use Fisharebest\Webtrees\I18N;
use Hartenthaler\Webtrees\Module\OccupationStandardizer\Internationalization\MoreI18N;

use function implode;

final class OccupationLabelService
{
    /**
     * @return list<array{label:string,title:string,status:string}>
     */
    public function labelsForOccupation(string $occupation): array
    {
        return $this->labels((new OccupationNormalizationService())->normalize($occupation));
    }

    /**
     * @param list<array{part_index:int,original_part_text:string,social_status:string,occupation_normalized:string,office:string,qualification:string,code:string,status:string,rule_numbers:string}> $entries
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
     * @param array{part_index:int,original_part_text:string,social_status:string,occupation_normalized:string,office:string,qualification:string,code:string,status:string,rule_numbers:string} $entry
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
}
