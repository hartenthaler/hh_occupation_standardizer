<?php

declare(strict_types=1);

namespace Hartenthaler\Webtrees\Module\OccupationStandardizer;

use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Http\Exceptions\HttpAccessDeniedException;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Module\AbstractModule;
use Fisharebest\Webtrees\Module\ModuleConfigInterface;
use Fisharebest\Webtrees\Module\ModuleConfigTrait;
use Fisharebest\Webtrees\Module\ModuleCustomInterface;
use Fisharebest\Webtrees\Module\ModuleCustomTrait;
use Fisharebest\Webtrees\Module\ModuleGlobalInterface;
use Fisharebest\Webtrees\Module\ModuleGlobalTrait;
use Fisharebest\Webtrees\View;
use Fisharebest\Webtrees\Webtrees;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class OccupationStandardizerModule extends AbstractModule implements ModuleCustomInterface, ModuleConfigInterface, ModuleGlobalInterface
{
    use ModuleCustomTrait;
    use ModuleConfigTrait;
    use ModuleGlobalTrait;

    private const MODULE_TITLE = 'Occupation Standardizer';
    private const VERSION = '2.2.6.0';
    private const LATEST_VERSION_URL = 'https://raw.githubusercontent.com/hartenthaler/hh_occupation_standardizer/main/latest-version.txt';
    private const SUPPORT_URL = 'https://github.com/hartenthaler/hh_occupation_standardizer';

    public function title(): string
    {
        return I18N::translate(self::MODULE_TITLE);
    }

    public function description(): string
    {
        return I18N::translate('Helps standardize historical occupation entries in genealogical sources.');
    }

    public function customModuleAuthorName(): string
    {
        return 'Hermann Hartenthaler';
    }

    public function customModuleVersion(): string
    {
        return self::VERSION;
    }

    public function customModuleLatestVersionUrl(): string
    {
        return self::LATEST_VERSION_URL;
    }

    public function customModuleSupportUrl(): string
    {
        return self::SUPPORT_URL;
    }

    public function resourcesFolder(): string
    {
        return strtr(__DIR__ . '/../resources/', DIRECTORY_SEPARATOR, '/');
    }

    public function boot(): void
    {
        View::registerNamespace($this->name(), $this->resourcesFolder() . 'views/');
    }

    public function getAdminAction(ServerRequestInterface $request): ResponseInterface
    {
        if (!Auth::isAdmin()) {
            throw new HttpAccessDeniedException();
        }

        $this->layout = Webtrees::LAYOUT_ADMINISTRATION;

        return $this->viewResponse($this->name() . '::settings', [
            'title' => $this->title(),
            'description' => $this->description(),
        ]);
    }

    public function bodyContent(): string
    {
        return '';
    }
}
