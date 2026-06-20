<?php

declare(strict_types=1);

namespace Hartenthaler\Webtrees\Module\OccupationStandardizer\Internationalization;

use Fisharebest\Webtrees\I18N;

class MoreI18N
{
    public static function xlate(string $message, ...$args): string
    {
        return I18N::translate($message, ...$args);
    }
}
