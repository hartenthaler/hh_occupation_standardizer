<?php

declare(strict_types=1);

namespace Hartenthaler\Webtrees\Module\OccupationStandardizer\Application\Service;

use function rawurlencode;
use function trim;

final class ExternalIdentifierService
{
    /** @var array<string,string> */
    private const URL_PATTERNS = [
        'factgrid' => 'https://database.factgrid.de/wiki/Item:%s',
        'gnd'      => 'https://d-nb.info/gnd/%s',
        'gnd-explorer' => 'https://explore.gnd.network/gnd/%s/relations',
        'wikidata' => 'https://www.wikidata.org/wiki/%s',
    ];

    public function url(string $identifier_type, string $code): string
    {
        $code = trim($code);

        if ($code === '' || !isset(self::URL_PATTERNS[$identifier_type])) {
            return '';
        }

        return sprintf(self::URL_PATTERNS[$identifier_type], rawurlencode($code));
    }
}
