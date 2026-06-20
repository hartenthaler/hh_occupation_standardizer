<?php

declare(strict_types=1);

namespace Hartenthaler\Webtrees\Module\OccupationStandardizer;

use Fisharebest\Localization\Translation;
use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\DB;
use Fisharebest\Webtrees\Fact;
use Fisharebest\Webtrees\FlashMessages;
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
use Hartenthaler\Webtrees\Module\OccupationStandardizer\Application\Service\OccupationLabelService;
use Hartenthaler\Webtrees\Module\OccupationStandardizer\Application\Service\OccupationNormalizationService;
use Hartenthaler\Webtrees\Module\OccupationStandardizer\Infrastructure\Persistence\Schema\OccupationSchema;
use Hartenthaler\Webtrees\Module\OccupationStandardizer\Internationalization\MoreI18N;
use Illuminate\Database\Capsule\Manager as DBManager;
use Illuminate\Support\Collection;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function array_map;
use function array_values;
use function assert;
use function class_exists;
use function date;
use function file_exists;
use function implode;
use function in_array;
use function preg_match_all;
use function route;
use function sha1;
use function strip_tags;
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
    private const FINGERPRINT_PREFIX = 'tree_occu_';

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
        (new OccupationSchema())->ensureSchema();

        View::registerNamespace($this->name(), $this->resourcesFolder() . 'views/');
        View::registerCustomView('::fact', $this->name() . '::fact');

        if (class_exists(\Vesta\VestaUtils::class)) {
            View::registerCustomView(\Vesta\VestaUtils::vestaViewsNamespace() . '::fact', $this->name() . '::vesta-fact');
        }

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
            'treeStatistics' => $this->normalizationTableStatistics(),
        ]);
    }

    public function postAdminAction(ServerRequestInterface $request): ResponseInterface
    {
        if (!Auth::isAdmin()) {
            throw new HttpAccessDeniedException();
        }

        $this->layout = Webtrees::LAYOUT_ADMINISTRATION;

        $params = (array) $request->getParsedBody();

        if ((string) ($params['task'] ?? '') === 'deleteTreeTable') {
            $this->deleteNormalizationRowsForTree($params);
        }

        return $this->getAdminAction($request);
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

        if (Auth::isManager($tree)) {
            $this->syncNormalizationRows($tree);
        }

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
     * @return Collection<int,array{occupation:string,individual:Individual,date:string,place:string,place_sort:string,employer:string,type:string,note:string,sources:list<string>,normalizations:list<array{label:string,title:string,status:string}>}>
     */
    private function occupationRows(Tree $tree): Collection
    {
        $rows = new Collection();
        $label_service = new OccupationLabelService();

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
                $source_data = $this->sourceData($fact);

                $rows->push([
                    'occupation'     => $occupation,
                    'individual'     => $individual,
                    'date'           => $fact->date()->display(),
                    'place'          => $place->gedcomName() !== '' ? $place->shortName() : '',
                    'place_sort'     => $place->gedcomName(),
                    'employer'       => trim($fact->attribute('AGNC')),
                    'type'           => trim($fact->attribute('TYPE')),
                    'note'           => trim($fact->attribute('NOTE')),
                    'sources'        => $source_data['names'],
                    'normalizations' => $label_service->labelsForOccupation($occupation),
                ]);
            }
        }

        return $rows->sort(static function (array $a, array $b): int {
            return I18N::comparator()($a['occupation'], $b['occupation'])
                ?: I18N::comparator()($a['individual']->sortName(), $b['individual']->sortName());
        })->values();
    }

    /**
     * @return array{xrefs:list<string>,names:list<string>}
     */
    private function sourceData(Fact $fact): array
    {
        $sources = [];
        $xrefs = [];

        preg_match_all('/\n2 SOUR @([^@]+)@/u', $fact->gedcom(), $matches);

        foreach ($matches[1] as $xref) {
            $source = Registry::sourceFactory()->make($xref, $fact->record()->tree());

            if ($source instanceof Source && $source->canShow()) {
                $xrefs[] = $xref;
                $sources[] = trim(strip_tags($source->fullName()));
            }
        }

        return [
            'xrefs' => $xrefs,
            'names' => $sources,
        ];
    }

    private function syncNormalizationRows(Tree $tree): void
    {
        $fingerprint = $this->occupationFingerprint($tree);
        $fingerprint_setting = self::FINGERPRINT_PREFIX . $tree->id();

        if ($this->metadataValue($fingerprint_setting) === $fingerprint) {
            return;
        }

        $normalizer = new OccupationNormalizationService();
        $now = date('Y-m-d H:i:s');
        $seen_keys = [];

        foreach ($this->occupationQuery($tree)->select(['i_id AS xref', 'i_gedcom AS gedcom'])->get() as $row) {
            $individual = Registry::individualFactory()->make($row->xref, $tree, $row->gedcom);
            assert($individual instanceof Individual);

            foreach ($individual->facts(['OCCU'], false, null, true) as $fact) {
                assert($fact instanceof Fact);

                $occupation = trim($fact->value());

                if ($occupation === '') {
                    continue;
                }

                $source_data = $this->sourceData($fact);

                foreach ($normalizer->normalize($occupation) as $entry) {
                    $entry_key = sha1($tree->id() . '|' . $individual->xref() . '|' . $fact->id() . '|' . $entry['part_index']);
                    $seen_keys[] = $entry_key;

                    $this->syncNormalizationEntry($tree, $individual, $fact, $entry_key, $entry, $source_data, $now);
                }
            }
        }

        $stale_query = DBManager::table(OccupationSchema::TABLE_NORMALIZED_ENTRIES)
            ->where('tree_id', '=', $tree->id());

        if ($seen_keys !== []) {
            $stale_query->whereNotIn('entry_key', array_values($seen_keys));
        }

        $stale_query->update([
            'is_active'  => false,
            'updated_at' => $now,
        ]);

        $this->setMetadataValue($fingerprint_setting, $fingerprint);
    }

    /**
     * @param array{part_index:int,original_part_text:string,social_status:string,occupation_normalized:string,office:string,qualification:string,code:string,status:string,rule_numbers:string} $entry
     * @param array{xrefs:list<string>,names:list<string>} $source_data
     */
    private function syncNormalizationEntry(Tree $tree, Individual $individual, Fact $fact, string $entry_key, array $entry, array $source_data, string $now): void
    {
        $context = [
            'tree_id'            => $tree->id(),
            'individual_xref'    => $individual->xref(),
            'fact_id'            => $fact->id(),
            'part_index'         => $entry['part_index'],
            'original_fact_text' => trim($fact->value()),
            'original_part_text' => $entry['original_part_text'],
            'date'               => trim($fact->attribute('DATE')),
            'place'              => $fact->place()->gedcomName(),
            'employer'           => trim($fact->attribute('AGNC')),
            'type'               => trim($fact->attribute('TYPE')),
            'note'               => trim($fact->attribute('NOTE')),
            'source_xrefs'       => implode('; ', $source_data['xrefs']),
            'source_names'       => implode('; ', $source_data['names']),
            'is_active'          => true,
            'last_seen_at'       => $now,
            'updated_at'         => $now,
        ];

        $existing = DBManager::table(OccupationSchema::TABLE_NORMALIZED_ENTRIES)
            ->where('entry_key', '=', $entry_key)
            ->first();

        if ($existing !== null) {
            $values = $context;

            if (!(bool) $existing->reviewed) {
                $values += $this->automaticNormalizationValues($entry);
            }

            DBManager::table(OccupationSchema::TABLE_NORMALIZED_ENTRIES)
                ->where('entry_key', '=', $entry_key)
                ->update($values);

            return;
        }

        DBManager::table(OccupationSchema::TABLE_NORMALIZED_ENTRIES)->insert([
            'entry_key'  => $entry_key,
            'reviewed'   => false,
            'created_at' => $now,
        ] + $context + $this->automaticNormalizationValues($entry));
    }

    /**
     * @param array{part_index:int,original_part_text:string,social_status:string,occupation_normalized:string,office:string,qualification:string,code:string,status:string,rule_numbers:string} $entry
     *
     * @return array{social_status:string,occupation_normalized:string,office:string,qualification:string,code:string,status:string,rule_numbers:string}
     */
    private function automaticNormalizationValues(array $entry): array
    {
        return [
            'social_status'         => $entry['social_status'],
            'occupation_normalized' => $entry['occupation_normalized'],
            'office'                => $entry['office'],
            'qualification'         => $entry['qualification'],
            'code'                  => $entry['code'],
            'status'                => $entry['status'],
            'rule_numbers'          => $entry['rule_numbers'],
        ];
    }

    private function occupationFingerprint(Tree $tree): string
    {
        $parts = [];

        foreach ($this->occupationQuery($tree)->select(['i_id AS xref', 'i_gedcom AS gedcom'])->orderBy('i_id')->get() as $row) {
            $individual = Registry::individualFactory()->make($row->xref, $tree, $row->gedcom);
            assert($individual instanceof Individual);

            foreach ($individual->facts(['OCCU'], false, null, true) as $fact) {
                assert($fact instanceof Fact);

                $parts[] = $individual->xref() . '|' . $fact->id() . '|' . $fact->gedcom();
            }
        }

        return sha1(implode("\n", $parts));
    }

    private function metadataValue(string $setting_name): string
    {
        $row = DBManager::table(OccupationSchema::TABLE_METADATA)
            ->where('setting_name', '=', $setting_name)
            ->first();

        return $row->setting_value ?? '';
    }

    private function setMetadataValue(string $setting_name, string $setting_value): void
    {
        DBManager::table(OccupationSchema::TABLE_METADATA)->updateOrInsert(
            ['setting_name' => $setting_name],
            ['setting_value' => $setting_value]
        );
    }

    /**
     * @return list<array{tree_id:int,tree_name:string,tree_title:string,total_entries:int,active_entries:int,inactive_entries:int,reviewed_entries:int}>
     */
    private function normalizationTableStatistics(): array
    {
        if (!DBManager::schema()->hasTable(OccupationSchema::TABLE_NORMALIZED_ENTRIES)) {
            return [];
        }

        return DBManager::table(OccupationSchema::TABLE_NORMALIZED_ENTRIES . ' AS entries')
            ->join('gedcom AS tree', 'tree.gedcom_id', '=', 'entries.tree_id')
            ->leftJoin('gedcom_setting AS title', static function ($join): void {
                $join
                    ->on('title.gedcom_id', '=', 'tree.gedcom_id')
                    ->where('title.setting_name', '=', 'title');
            })
            ->groupBy(['entries.tree_id', 'tree.gedcom_name', 'title.setting_value'])
            ->orderBy('title.setting_value')
            ->orderBy('tree.gedcom_name')
            ->select([
                'entries.tree_id',
                'tree.gedcom_name AS tree_name',
                'title.setting_value AS tree_title',
                DB::raw('COUNT(*) AS total_entries'),
                DB::raw('SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) AS active_entries'),
                DB::raw('SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) AS inactive_entries'),
                DB::raw('SUM(CASE WHEN reviewed = 1 THEN 1 ELSE 0 END) AS reviewed_entries'),
            ])
            ->get()
            ->map(static fn (object $row): array => [
                'tree_id'          => (int) $row->tree_id,
                'tree_name'        => (string) $row->tree_name,
                'tree_title'       => (string) ($row->tree_title ?? $row->tree_name),
                'total_entries'    => (int) $row->total_entries,
                'active_entries'   => (int) $row->active_entries,
                'inactive_entries' => (int) $row->inactive_entries,
                'reviewed_entries' => (int) $row->reviewed_entries,
            ])
            ->all();
    }

    /**
     * @param array<string,mixed> $params
     */
    private function deleteNormalizationRowsForTree(array $params): void
    {
        if (!DBManager::schema()->hasTable(OccupationSchema::TABLE_NORMALIZED_ENTRIES)) {
            FlashMessages::addMessage(I18N::translate('There is no occupation standardization table to delete.'), 'warning');

            return;
        }

        $confirmed = (string) ($params['confirmDelete'] ?? '') === '1';
        $tree_id = (int) ($params['treeId'] ?? 0);
        $known_tree_ids = array_values(array_map(
            static fn (array $statistics): int => $statistics['tree_id'],
            $this->normalizationTableStatistics()
        ));

        if (!$confirmed || !in_array($tree_id, $known_tree_ids, true)) {
            FlashMessages::addMessage(I18N::translate('The table was not deleted because the confirmation was missing or the selected tree was invalid.'), 'warning');

            return;
        }

        $deleted = DBManager::table(OccupationSchema::TABLE_NORMALIZED_ENTRIES)
            ->where('tree_id', '=', $tree_id)
            ->delete();

        if (DBManager::schema()->hasTable(OccupationSchema::TABLE_METADATA)) {
            DBManager::table(OccupationSchema::TABLE_METADATA)
                ->where('setting_name', '=', self::FINGERPRINT_PREFIX . $tree_id)
                ->delete();
        }

        FlashMessages::addMessage(I18N::plural(
            'Deleted %s occupation standardization entry.',
            'Deleted %s occupation standardization entries.',
            $deleted,
            I18N::number($deleted)
        ), 'success');
    }

}
