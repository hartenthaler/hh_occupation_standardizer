<?php

declare(strict_types=1);

namespace Hartenthaler\Webtrees\Module\OccupationStandardizer;

use Fisharebest\Localization\Translation;
use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\DB;
use Fisharebest\Webtrees\Fact;
use Fisharebest\Webtrees\Http\Exceptions\HttpAccessDeniedException;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Menu;
use Fisharebest\Webtrees\Module\AbstractModule;
use Fisharebest\Webtrees\Module\ModuleConfigInterface;
use Fisharebest\Webtrees\Module\ModuleConfigTrait;
use Fisharebest\Webtrees\Module\ModuleCustomInterface;
use Fisharebest\Webtrees\Module\ModuleCustomTrait;
use Fisharebest\Webtrees\Module\ModuleGlobalInterface;
use Fisharebest\Webtrees\Module\ModuleGlobalTrait;
use Fisharebest\Webtrees\Module\ModuleListInterface;
use Fisharebest\Webtrees\Module\ModuleListTrait;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Source;
use Fisharebest\Webtrees\Tree;
use Fisharebest\Webtrees\Validator;
use Fisharebest\Webtrees\View;
use Fisharebest\Webtrees\Webtrees;
use Hartenthaler\Webtrees\Module\OccupationStandardizer\Internationalization\MoreI18N;
use Illuminate\Support\Collection;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function assert;
use function file_exists;
use function preg_match_all;
use function route;
use function trim;

final class OccupationStandardizerModule extends AbstractModule implements ModuleCustomInterface, ModuleConfigInterface, ModuleGlobalInterface, ModuleListInterface, RequestHandlerInterface
{
    use ModuleCustomTrait;
    use ModuleConfigTrait;
    use ModuleGlobalTrait;
    use ModuleListTrait;

    private const MODULE_TITLE = 'Occupation Standardizer';
    private const VERSION = '2.2.6.0';
    private const LATEST_VERSION_URL = 'https://raw.githubusercontent.com/hartenthaler/hh_occupation_standardizer/main/latest-version.txt';
    private const SUPPORT_URL = 'https://github.com/hartenthaler/hh_occupation_standardizer';
    private const ROUTE_URL = '/tree/{tree}/occupation-standardizer';

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

    public function customTranslations(string $language): array
    {
        $file = $this->resourcesFolder() . 'lang/' . $language . '.mo';

        if (file_exists($file)) {
            return (new Translation($file))->asArray();
        }

        return [];
    }

    public function resourcesFolder(): string
    {
        return strtr(__DIR__ . '/../resources/', DIRECTORY_SEPARATOR, '/');
    }

    public function boot(): void
    {
        View::registerNamespace($this->name(), $this->resourcesFolder() . 'views/');

        Registry::routeFactory()->routeMap()
            ->get(static::class, self::ROUTE_URL, $this);
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

    public function listTitle(): string
    {
        return MoreI18N::xlate('Occupations');
    }

    public function listMenuClass(): string
    {
        return 'menu-list-occupation-standardizer';
    }

    /**
     * @param array<bool|int|string|array<string>|null> $parameters
     */
    public function listUrl(Tree $tree, array $parameters = []): string
    {
        $parameters['tree'] = $tree->name();

        return route(static::class, $parameters);
    }

    /**
     * @return array<string>
     */
    public function listUrlAttributes(): array
    {
        return [];
    }

    public function listMenu(Tree $tree): Menu|null
    {
        return new Menu(
            $this->listTitle(),
            $this->listUrl($tree),
            $this->listMenuClass(),
            $this->listUrlAttributes()
        );
    }

    public function listIsEmpty(Tree $tree): bool
    {
        return !$this->occupationQuery($tree)->exists();
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $tree = Validator::attributes($request)->tree();
        $user = Validator::attributes($request)->user();

        Auth::checkComponentAccess($this, ModuleListInterface::class, $tree, $user);

        return $this->viewResponse($this->name() . '::occupation-list', [
            'rows'  => $this->occupationRows($tree),
            'title' => $this->listTitle(),
            'tree'  => $tree,
        ]);
    }

    private function occupationQuery(Tree $tree): \Illuminate\Database\Query\Builder
    {
        return DB::table('individuals')
            ->where('i_file', '=', $tree->id())
            ->where('i_gedcom', 'like', "%\n1 OCCU%");
    }

    /**
     * @return Collection<int,array{occupation:string,individual:Individual,date:string,place:string,place_sort:string,employer:string,type:string,note:string,sources:list<string>}>
     */
    private function occupationRows(Tree $tree): Collection
    {
        $rows = new Collection();

        foreach ($this->occupationQuery($tree)->select(['i_id AS xref', 'i_gedcom AS gedcom'])->get() as $row) {
            $individual = Registry::individualFactory()->make($row->xref, $tree, $row->gedcom);
            assert($individual instanceof Individual);

            if (!$individual->canShow()) {
                continue;
            }

            foreach ($individual->facts(['OCCU']) as $fact) {
                assert($fact instanceof Fact);

                if (!$fact->canShow()) {
                    continue;
                }

                $occupation = trim($fact->value());

                if ($occupation === '') {
                    continue;
                }

                $place = $fact->place();

                $rows->push([
                    'occupation' => $occupation,
                    'individual' => $individual,
                    'date'       => $fact->date()->display(),
                    'place'      => $place->gedcomName() !== '' ? $place->shortName() : '',
                    'place_sort' => $place->gedcomName(),
                    'employer'   => trim($fact->attribute('AGNC')),
                    'type'       => trim($fact->attribute('TYPE')),
                    'note'       => trim($fact->attribute('NOTE')),
                    'sources'    => $this->sourceNames($fact),
                ]);
            }
        }

        return $rows->sort(static function (array $a, array $b): int {
            return I18N::comparator()($a['occupation'], $b['occupation'])
                ?: I18N::comparator()($a['individual']->sortName(), $b['individual']->sortName());
        })->values();
    }

    /**
     * @return list<string>
     */
    private function sourceNames(Fact $fact): array
    {
        $sources = [];

        preg_match_all('/\n2 SOUR @([^@]+)@/u', $fact->gedcom(), $matches);

        foreach ($matches[1] as $xref) {
            $source = Registry::sourceFactory()->make($xref, $fact->record()->tree());

            if ($source instanceof Source && $source->canShow()) {
                $sources[] = $source->fullName();
            }
        }

        return $sources;
    }
}
