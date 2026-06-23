<?php

declare(strict_types=1);

namespace Hartenthaler\Webtrees\Module\OccupationStandardizer\Application\Service;

use function rawurlencode;
use function str_replace;
use function trim;

final class ExternalIdentifierService
{
    /** @var array<string,string> */
    private const URL_PATTERNS = [
        'factgrid' => 'https://database.factgrid.de/wiki/Item:%s',
        'gnd'      => 'https://d-nb.info/gnd/%s',
        'gnd-explorer' => 'https://explore.gnd.network/gnd/%s/relations',
        'hisco'    => 'https://druid.datalegend.net/HistoryOfWork/HISCO-latest/browser?resource=https%3A%2F%2Fiisg.amsterdam%2Fresource%2Fhisco%2Fcode%2Fhisco%2F{code}',
        'wikidata' => 'https://www.wikidata.org/wiki/%s',
    ];

    public function url(string $identifier_type, string $code): string
    {
        $code = trim($code);

        if ($code === '' || !isset(self::URL_PATTERNS[$identifier_type])) {
            return '';
        }

        if ($identifier_type === 'hisco') {
            $code = str_replace(['-', '.'], '', $code);

            return str_replace('{code}', rawurlencode($code), self::URL_PATTERNS[$identifier_type]);
        }

        return sprintf(self::URL_PATTERNS[$identifier_type], rawurlencode($code));
    }
}
