<?php

declare(strict_types=1);

namespace Hartenthaler\Webtrees\Module\OccupationStandardizer;

use Composer\Autoload\ClassLoader;
use Hartenthaler\Webtrees\Module\OccupationStandardizer\OccupationStandardizerModule;

$loader = new ClassLoader();
$loader->addPsr4('Hartenthaler\\Webtrees\\Module\\OccupationStandardizer\\', __DIR__ . DIRECTORY_SEPARATOR . 'src');
$loader->register();

return new OccupationStandardizerModule();
