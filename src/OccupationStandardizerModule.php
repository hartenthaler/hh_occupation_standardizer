<?php

declare(strict_types=1);

namespace Hartenthaler\Webtrees\Module\OccupationStandardizer;

use Fig\Http\Message\RequestMethodInterface;
use Fisharebest\Localization\Translation;
use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\DB;
use Fisharebest\Webtrees\Fact;
use Fisharebest\Webtrees\FlashMessages;
use Fisharebest\Webtrees\Gedcom;
use Fisharebest\Webtrees\Http\Exceptions\HttpAccessDeniedException;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Location;
use Fisharebest\Webtrees\Menu;
use Fisharebest\Webtrees\Module\AbstractModule;
use Fisharebest\Webtrees\Module\ModuleConfigInterface;
use Fisharebest\Webtrees\Module\ModuleConfigTrait;
use Fisharebest\Webtrees\Module\ModuleCustomInterface;
use Fisharebest\Webtrees\Module\ModuleCustomTrait;
use Fisharebest\Webtrees\Module\ModuleGlobalInterface;
use Fisharebest\Webtrees\Module\ModuleGlobalTrait;
use Fisharebest\Webtrees\Module\ModuleLanguageInterface;
use Fisharebest\Webtrees\Module\ModuleListInterface;
use Fisharebest\Webtrees\Module\ModuleListTrait;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\ModuleService;
use Fisharebest\Webtrees\Source;
use Fisharebest\Webtrees\Tree;
use Fisharebest\Webtrees\Validator;
use Fisharebest\Webtrees\View;
use Fisharebest\Webtrees\Webtrees;
use Hartenthaler\Webtrees\Module\OccupationStandardizer\Application\Service\OccupationLabelService;
use Hartenthaler\Webtrees\Module\OccupationStandardizer\Application\Service\OccupationNormalizationService;
use Hartenthaler\Webtrees\Module\OccupationStandardizer\Application\Service\OhdabSpecialDatabaseService;
use Hartenthaler\Webtrees\Module\OccupationStandardizer\Infrastructure\Persistence\Schema\OccupationSchema;
use Hartenthaler\Webtrees\Module\OccupationStandardizer\Internationalization\MoreI18N;
use Illuminate\Database\Capsule\Manager as DBManager;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function array_map;
use function array_key_exists;
use function array_search;
use function array_splice;
use function array_values;
use function array_filter;
use function array_unique;
use function assert;
use function class_exists;
use function date;
use function explode;
use function file_exists;
use function implode;
use function in_array;
use function is_array;
use function method_exists;
use function preg_match_all;
use function preg_match;
use function route;
use function sha1;
use function strip_tags;
use function sys_get_temp_dir;
use function tempnam;
use function trim;
use function unlink;

use const UPLOAD_ERR_NO_FILE;
use const UPLOAD_ERR_OK;

final class OccupationStandardizerModule extends AbstractModule implements ModuleCustomInterface, ModuleConfigInterface, ModuleGlobalInterface, ModuleListInterface, RequestHandlerInterface
{
    use ModuleCustomTrait;
    use ModuleConfigTrait;
    use ModuleGlobalTrait;
    use ModuleListTrait;

    private const MODULE_TITLE = 'Occupation Standardizer';
    private const VERSION = '2.2.6.1';
    private const LATEST_VERSION_URL = 'https://raw.githubusercontent.com/hartenthaler/hh_occupation_standardizer/main/latest-version.txt';
    private const SUPPORT_URL = 'https://github.com/hartenthaler/hh_occupation_standardizer';
    private const ROUTE_URL = '/tree/{tree}/occupation-standardizer';
    private const FINGERPRINT_PREFIX = 'tree_occu_';
    private const TASK_SAVE_NORMALIZATION_ENTRY = 'saveNormalizationEntry';
    private const TASK_SAVE_BUILTIN_RULES = 'saveBuiltinRules';
    private const TASK_IMPORT_OHDAB_SPECIAL_DATABASE = 'importOhdabSpecialDatabase';
    private const BUILTIN_RULE_ORDER_PREFERENCE = 'builtinRuleOrder';
    private const BUILTIN_RULE_STATUS_PREFIX = 'builtinRuleStatus-';
    private const TREE_LANGUAGE_PREFIX = 'treeLanguage-';
    private const DEFAULT_OCCUPATION_LANGUAGE = 'de';
    private const NORMALIZATION_STATUSES = [
        OccupationNormalizationService::STATUS_RECOGNIZED,
        OccupationNormalizationService::STATUS_UNCLEAR,
        OccupationNormalizationService::STATUS_IGNORED,
    ];

    public function title(): string
    {
        return I18N::translate('Occupation Standardizer');
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

        $route_map = Registry::routeFactory()->routeMap();
        $route_map->get(static::class, self::ROUTE_URL, $this);
        $route_map->post(static::class . ':save', self::ROUTE_URL, $this);
        $route_map->allows(RequestMethodInterface::METHOD_POST);
    }

    public function getAdminAction(ServerRequestInterface $request): ResponseInterface
    {
        if (!Auth::isAdmin()) {
            throw new HttpAccessDeniedException();
        }

        $this->layout = Webtrees::LAYOUT_ADMINISTRATION;

        return $this->viewResponse($this->name() . '::settings', [
            'builtinRules'       => $this->builtinRuleRows(),
            'description'        => $this->description(),
            'languageOptions'    => $this->languageOptions(),
            'normalizationTerms' => $this->normalizationTermRows(),
            'normalizationTermOptions' => $this->normalizationTermOptions(),
            'normalizationRules' => $this->normalizationRuleRows(),
            'ohdabCategoryStatistics' => $this->ohdabCategoryStatistics(),
            'ohdabSpecialDatabase' => (new OhdabSpecialDatabaseService())->sourceInfo(),
            'title'              => $this->title(),
            'treeLanguages'      => $this->treeLanguageRows(),
            'treeStatistics'     => $this->normalizationTableStatistics(),
        ]);
    }

    public function postAdminAction(ServerRequestInterface $request): ResponseInterface
    {
        if (!Auth::isAdmin()) {
            throw new HttpAccessDeniedException();
        }

        $this->layout = Webtrees::LAYOUT_ADMINISTRATION;

        $params = (array) $request->getParsedBody();

        if ((string) ($params['task'] ?? '') === self::TASK_SAVE_BUILTIN_RULES) {
            $this->saveBuiltinRuleSettings($params);
        }

        if ((string) ($params['task'] ?? '') === self::TASK_IMPORT_OHDAB_SPECIAL_DATABASE) {
            $this->importOhdabSpecialDatabase($request);
        }

        if ((string) ($params['task'] ?? '') === 'saveTreeLanguages') {
            $this->saveTreeLanguages($params);
        }

        if ((string) ($params['task'] ?? '') === 'deleteTreeTable') {
            $this->deleteNormalizationRowsForTree($params);
        }

        if ((string) ($params['task'] ?? '') === 'saveNormalizationRule') {
            $this->saveNormalizationRule($params);
        }

        if ((string) ($params['task'] ?? '') === 'deleteNormalizationRule') {
            $this->deleteNormalizationRule($params);
        }

        if ((string) ($params['task'] ?? '') === 'saveNormalizationTerm') {
            $this->saveNormalizationTermAction($params);
        }

        if ((string) ($params['task'] ?? '') === 'deleteNormalizationTerm') {
            $this->deleteNormalizationTerm($params);
        }

        return $this->getAdminAction($request);
    }

    private function importOhdabSpecialDatabase(ServerRequestInterface $request): void
    {
        $file = $request->getUploadedFiles()['ohdabSpecialDatabase'] ?? null;

        if (!$file instanceof UploadedFileInterface || $file->getError() === UPLOAD_ERR_NO_FILE) {
            FlashMessages::addMessage(I18N::translate('No OhdAB special database file was received.'), 'danger');

            return;
        }

        if ($file->getError() !== UPLOAD_ERR_OK) {
            FlashMessages::addMessage(I18N::translate('The OhdAB special database file could not be uploaded.'), 'danger');

            return;
        }

        $temporary_file = tempnam(sys_get_temp_dir(), 'hh_ohdab_');

        if ($temporary_file === false) {
            FlashMessages::addMessage(I18N::translate('The uploaded file could not be prepared for import.'), 'danger');

            return;
        }

        try {
            $file->moveTo($temporary_file);
            $result = (new OhdabSpecialDatabaseService())->importFile($temporary_file, $file->getClientFilename());
            $message = I18N::translate($result['message']);

            if ($result['row_count'] > 0) {
                $message .= ' ' . I18N::plural('%s row was processed.', '%s rows were processed.', $result['row_count'], I18N::number($result['row_count']));
            }

            FlashMessages::addMessage($message, $result['imported'] ? 'success' : 'warning');
        } catch (\Throwable $ex) {
            FlashMessages::addMessage($ex->getMessage(), 'danger');
        } finally {
            if (file_exists($temporary_file)) {
                unlink($temporary_file);
            }
        }
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

        $query_params = $request->getQueryParams();
        $view = (string) ($query_params['view'] ?? '');

        if ($request->getMethod() === RequestMethodInterface::METHOD_POST) {
            if (!$this->canManageNormalization($tree)) {
                throw new HttpAccessDeniedException();
            }

            $this->saveNormalizationEntry($tree, (array) $request->getParsedBody());
        }

        $can_manage_normalization = $this->canManageNormalization($tree);

        if ($view === '') {
            return $this->viewResponse($this->name() . '::occupation-landing', [
                'hierarchyUrl' => $this->listUrl($tree, ['view' => 'hierarchy']),
                'listUrl'      => $this->listUrl($tree, ['view' => 'list']),
                'title'        => $this->listTitle(),
                'tree'         => $tree,
            ]);
        }

        if ($can_manage_normalization) {
            $this->syncNormalizationRows($tree);
        }

        if ($view === 'hierarchy') {
            $node_id = (int) ($query_params['node_id'] ?? 0);

            return $this->viewResponse($this->name() . '::occupation-hierarchy', [
                'ancestors'   => $this->ohdabHierarchyAncestors($node_id),
                'children'    => $this->ohdabHierarchyChildren($tree, $node_id),
                'currentNode' => $this->ohdabHierarchyNode($node_id),
                'hasSource'   => $this->ohdabHierarchySourceId() > 0,
                'listUrl'     => fn (array $parameters = []): string => $this->listUrl($tree, $parameters),
                'persons'     => $this->ohdabHierarchyPersons($tree, $node_id),
                'title'       => I18N::translate('Occupation hierarchy (OhdAB)'),
                'tree'        => $tree,
            ]);
        }

        return $this->viewResponse($this->name() . '::occupation-list', [
            'canManageNormalization' => $can_manage_normalization,
            'hierarchyUrl'            => $this->listUrl($tree, ['view' => 'hierarchy']),
            'languageOptions'        => $this->languageOptions(),
            'rows'                   => $this->occupationRows($tree, $can_manage_normalization),
            'statusOptions'          => self::NORMALIZATION_STATUSES,
            'title'                  => $this->listTitle(),
            'tree'                   => $tree,
        ]);
    }

    private function occupationQuery(Tree $tree): \Illuminate\Database\Query\Builder
    {
        return DB::table('individuals')
            ->where('i_file', '=', $tree->id())
            ->where('i_gedcom', 'like', "%\n1 OCCU%");
    }

    /**
     * @return Collection<int,array{occupation:string,individual:Individual,date:string,place:string,place_sort:string,employer:string,type:string,note:string,sources:list<string>,normalizations:list<array{label:string,title:string,status:string}>,normalizationEntries:list<array{entry_key:string,part_index:int,original_part_text:string,date:string,place:string,location_xref:string,location_hierarchy:string,employer:string,type:string,note:string,source_xrefs:string,source_names:string,language:string,social_status:string,occupation_normalized:string,occupation_de_male:string,occupation_de_female:string,occupation_de_neutral:string,occupation_en_male:string,occupation_en_female:string,occupation_en_neutral:string,office:string,qualification:string,code_hisco:string,code_gnd:string,code_ohdab:string,code_factgrid:string,norm_concept_id:int,status:string,reviewed:bool,rule_numbers:string}>}>
     */
    private function occupationRows(Tree $tree, bool $can_manage_normalization): Collection
    {
        $rows = new Collection();
        $label_service = new OccupationLabelService($this->activeBuiltinRuleOrder());
        $normalization_rows_by_fact = $can_manage_normalization ? $this->normalizationRowsByFact($tree) : [];

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

                $place_data = $this->placeData($fact);
                $source_data = $this->sourceData($fact);
                $normalization_entries = $normalization_rows_by_fact[$fact->id()] ?? [];

                $rows->push([
                    'occupation'           => $occupation,
                    'individual'           => $individual,
                    'date'                 => $fact->date()->display(),
                    'place'                => $place_data['display'],
                    'place_sort'           => $place_data['sort'],
                    'employer'             => trim($fact->attribute('AGNC')),
                    'type'                 => trim($fact->attribute('TYPE')),
                    'note'                 => trim($fact->attribute('NOTE')),
                    'sources'              => $source_data['names'],
                    'normalizations'       => $normalization_entries !== [] ? $label_service->labelsForEntries($normalization_entries, $individual->sex(), I18N::languageTag()) : $label_service->labelsForOccupation($occupation, $this->occupationLanguage($tree), $individual->sex(), I18N::languageTag(), [
                        'employer' => trim($fact->attribute('AGNC')),
                        'type'     => trim($fact->attribute('TYPE')),
                        'note'     => trim($fact->attribute('NOTE')),
                    ]),
                    'normalizationEntries' => $normalization_entries,
                ]);
            }
        }

        return $rows->sort(static function (array $a, array $b): int {
            return I18N::comparator()($a['occupation'], $b['occupation'])
                ?: I18N::comparator()($a['individual']->sortName(), $b['individual']->sortName());
        })->values();
    }

    /**
     * @return array{id:int,level:int,code:string,label:string,parent_id:int|null}|null
     */
    private function ohdabHierarchyNode(int $node_id): array|null
    {
        if ($node_id <= 0 || !DBManager::schema()->hasTable(OccupationSchema::TABLE_NORM_HIERARCHY_NODES)) {
            return null;
        }

        $source_id = $this->ohdabHierarchySourceId();

        if ($source_id <= 0) {
            return null;
        }

        $node = DBManager::table(OccupationSchema::TABLE_NORM_HIERARCHY_NODES)
            ->where('source_id', '=', $source_id)
            ->where('id', '=', $node_id)
            ->first();

        return $node !== null ? $this->ohdabHierarchyNodeRow($node) : null;
    }

    /**
     * @return list<array{id:int,level:int,code:string,label:string,parent_id:int|null}>
     */
    private function ohdabHierarchyAncestors(int $node_id): array
    {
        $node = $this->ohdabHierarchyNode($node_id);
        $ancestors = [];

        while ($node !== null) {
            array_unshift($ancestors, $node);
            $node = $node['parent_id'] !== null ? $this->ohdabHierarchyNode($node['parent_id']) : null;
        }

        return $ancestors;
    }

    /**
     * @return list<array{id:int,level:int,code:string,label:string,parent_id:int|null,entry_count:int,individual_count:int}>
     */
    private function ohdabHierarchyChildren(Tree $tree, int $node_id): array
    {
        $source_id = $this->ohdabHierarchySourceId();

        if ($source_id <= 0 || !DBManager::schema()->hasTable(OccupationSchema::TABLE_NORM_HIERARCHY_NODES)) {
            return [];
        }

        $query = DBManager::table(OccupationSchema::TABLE_NORM_HIERARCHY_NODES)
            ->where('source_id', '=', $source_id);

        if ($node_id > 0) {
            $query->where('parent_id', '=', $node_id);
        } else {
            $query->whereNull('parent_id');
        }

        $children = $query
            ->orderBy('code')
            ->orderBy('label')
            ->get()
            ->map(fn (object $node): array => $this->ohdabHierarchyNodeRow($node))
            ->all();

        if ($children === []) {
            return [];
        }

        $child_ids = array_map(static fn (array $node): int => $node['id'], $children);
        $counts = $this->ohdabHierarchyCounts($tree, $child_ids);

        return array_map(static function (array $node) use ($counts): array {
            $node_counts = $counts[$node['id']] ?? ['entry_count' => 0, 'individual_count' => 0];
            $node['entry_count'] = $node_counts['entry_count'];
            $node['individual_count'] = $node_counts['individual_count'];

            return $node;
        }, $children);
    }

    /**
     * @param list<int> $node_ids
     *
     * @return array<int,array{entry_count:int,individual_count:int}>
     */
    private function ohdabHierarchyCounts(Tree $tree, array $node_ids): array
    {
        if (
            $node_ids === []
            || !DBManager::schema()->hasTable(OccupationSchema::TABLE_NORMALIZED_ENTRIES)
            || !DBManager::schema()->hasTable(OccupationSchema::TABLE_NORM_CONCEPT_HIERARCHY)
        ) {
            return [];
        }

        $counts = [];
        $rows = DBManager::table(OccupationSchema::TABLE_NORM_CONCEPT_HIERARCHY . ' AS links')
            ->join(OccupationSchema::TABLE_NORMALIZED_ENTRIES . ' AS entries', 'entries.norm_concept_id', '=', 'links.concept_id')
            ->where('entries.tree_id', '=', $tree->id())
            ->where('entries.is_active', '=', true)
            ->whereIn('links.node_id', $node_ids)
            ->select([
                'links.node_id',
                'entries.individual_xref',
            ])
            ->get();

        foreach ($rows as $row) {
            $node_id = (int) $row->node_id;
            $counts[$node_id] ??= [
                'entry_count'      => 0,
                'individual_xrefs' => [],
            ];
            $counts[$node_id]['entry_count']++;
            $counts[$node_id]['individual_xrefs'][(string) $row->individual_xref] = true;
        }

        return array_map(static fn (array $count): array => [
            'entry_count'      => $count['entry_count'],
            'individual_count' => count($count['individual_xrefs']),
        ], $counts);
    }

    /**
     * @return Collection<int,array{individual:Individual,label:string,label_title:string,original_part_text:string,date:string,place:string,source_names:string}>
     */
    private function ohdabHierarchyPersons(Tree $tree, int $node_id): Collection
    {
        $rows = new Collection();

        if (
            $node_id <= 0
            || !DBManager::schema()->hasTable(OccupationSchema::TABLE_NORMALIZED_ENTRIES)
            || !DBManager::schema()->hasTable(OccupationSchema::TABLE_NORM_CONCEPT_HIERARCHY)
        ) {
            return $rows;
        }

        $label_service = new OccupationLabelService($this->activeBuiltinRuleOrder());

        foreach ($this->ohdabHierarchyEntryRows($tree, $node_id) as $entry_row) {
            $individual = Registry::individualFactory()->make((string) $entry_row->individual_xref, $tree);

            if (!$individual instanceof Individual || !$individual->canShow()) {
                continue;
            }

            $entry = $this->normalizationEntryArray($entry_row);
            $labels = $label_service->labelsForEntries([$entry], $individual->sex(), I18N::languageTag());

            $rows->push([
                'individual'         => $individual,
                'label'              => $labels[0]['label'] ?? (string) $entry_row->occupation_normalized,
                'label_title'        => $labels[0]['title'] ?? '',
                'original_part_text' => (string) $entry_row->original_part_text,
                'date'               => (string) ($entry_row->date ?? ''),
                'place'              => (string) (($entry_row->location_hierarchy ?? '') !== '' ? $entry_row->location_hierarchy : ($entry_row->place ?? '')),
                'source_names'       => (string) ($entry_row->source_names ?? ''),
            ]);
        }

        return $rows->sort(static function (array $a, array $b): int {
            return I18N::comparator()($a['individual']->sortName(), $b['individual']->sortName())
                ?: I18N::comparator()($a['label'], $b['label']);
        })->values();
    }

    /**
     * @return Collection<int,object>
     */
    private function ohdabHierarchyEntryRows(Tree $tree, int $node_id): Collection
    {
        return DBManager::table(OccupationSchema::TABLE_NORMALIZED_ENTRIES . ' AS entries')
            ->join(OccupationSchema::TABLE_NORM_CONCEPT_HIERARCHY . ' AS links', 'links.concept_id', '=', 'entries.norm_concept_id')
            ->where('entries.tree_id', '=', $tree->id())
            ->where('entries.is_active', '=', true)
            ->where('links.node_id', '=', $node_id)
            ->orderBy('entries.individual_xref')
            ->orderBy('entries.original_part_text')
            ->select([
                'entries.individual_xref',
                'entries.part_index',
                'entries.original_part_text',
                'entries.date',
                'entries.place',
                'entries.location_hierarchy',
                'entries.source_names',
                'entries.language',
                'entries.social_status',
                'entries.occupation_normalized',
                'entries.occupation_de_male',
                'entries.occupation_de_female',
                'entries.occupation_de_neutral',
                'entries.occupation_en_male',
                'entries.occupation_en_female',
                'entries.occupation_en_neutral',
                'entries.office',
                'entries.qualification',
                'entries.code_hisco',
                'entries.code_gnd',
                'entries.code_ohdab',
                'entries.code_factgrid',
                'entries.norm_concept_id',
                'entries.status',
                'entries.rule_numbers',
            ])
            ->get();
    }

    /**
     * @return array{part_index:int,original_part_text:string,language:string,social_status:string,occupation_normalized:string,occupation_de_male:string,occupation_de_female:string,occupation_de_neutral:string,occupation_en_male:string,occupation_en_female:string,occupation_en_neutral:string,office:string,qualification:string,code_hisco:string,code_gnd:string,code_ohdab:string,code_factgrid:string,norm_concept_id:int,status:string,rule_numbers:string}
     */
    private function normalizationEntryArray(object $entry): array
    {
        return [
            'part_index'            => (int) $entry->part_index,
            'original_part_text'    => (string) $entry->original_part_text,
            'language'              => (string) ($entry->language ?? ''),
            'social_status'         => (string) ($entry->social_status ?? ''),
            'occupation_normalized' => (string) ($entry->occupation_normalized ?? ''),
            'occupation_de_male'    => (string) ($entry->occupation_de_male ?? ''),
            'occupation_de_female'  => (string) ($entry->occupation_de_female ?? ''),
            'occupation_de_neutral' => (string) ($entry->occupation_de_neutral ?? ''),
            'occupation_en_male'    => (string) ($entry->occupation_en_male ?? ''),
            'occupation_en_female'  => (string) ($entry->occupation_en_female ?? ''),
            'occupation_en_neutral' => (string) ($entry->occupation_en_neutral ?? ''),
            'office'                => (string) ($entry->office ?? ''),
            'qualification'         => (string) ($entry->qualification ?? ''),
            'code_hisco'            => (string) ($entry->code_hisco ?? ''),
            'code_gnd'              => (string) ($entry->code_gnd ?? ''),
            'code_ohdab'            => (string) ($entry->code_ohdab ?? ''),
            'code_factgrid'         => (string) ($entry->code_factgrid ?? ''),
            'norm_concept_id'       => (int) ($entry->norm_concept_id ?? 0),
            'status'                => (string) $entry->status,
            'rule_numbers'          => (string) $entry->rule_numbers,
        ];
    }

    /**
     * @return array{id:int,level:int,code:string,label:string,parent_id:int|null}
     */
    private function ohdabHierarchyNodeRow(object $node): array
    {
        return [
            'id'        => (int) $node->id,
            'level'     => (int) $node->level,
            'code'      => (string) $node->code,
            'label'     => (string) $node->label,
            'parent_id' => $node->parent_id !== null ? (int) $node->parent_id : null,
        ];
    }

    private function ohdabHierarchySourceId(): int
    {
        if (!DBManager::schema()->hasTable(OccupationSchema::TABLE_NORM_SOURCES)) {
            return 0;
        }

        return (int) (DBManager::table(OccupationSchema::TABLE_NORM_SOURCES)
            ->where('source_key', '=', OhdabSpecialDatabaseService::SOURCE_KEY)
            ->value('id') ?? 0);
    }

    /**
     * @return array{display:string,sort:string,place:string,location_xref:string,location_hierarchy:string}
     */
    private function placeData(Fact $fact): array
    {
        $place = $fact->place();
        $place_name = $place->gedcomName();
        $location_xref = $this->locationXref($fact);
        $location_hierarchy = $this->locationHierarchy($fact, $location_xref);

        if ($location_hierarchy !== '') {
            return [
                'display'            => '<span title="' . e($place_name) . '">' . e($location_hierarchy) . '</span>',
                'sort'               => $location_hierarchy,
                'place'              => $place_name,
                'location_xref'      => $location_xref,
                'location_hierarchy' => $location_hierarchy,
            ];
        }

        return [
            'display'            => $place_name !== '' ? $place->shortName() : '',
            'sort'               => $place_name,
            'place'              => $place_name,
            'location_xref'      => $location_xref,
            'location_hierarchy' => '',
        ];
    }

    private function locationXref(Fact $fact): string
    {
        if (preg_match('/\n2 PLAC\b[^\n]*(?:\n[3-9].*)*/', $fact->gedcom(), $place_match) !== 1) {
            return '';
        }

        if (preg_match('/\n3 _LOC @(' . Gedcom::REGEX_XREF . ')@/', $place_match[0], $loc_match) !== 1) {
            return '';
        }

        return $loc_match[1];
    }

    private function locationHierarchy(Fact $fact, string $location_xref): string
    {
        if ($location_xref === '') {
            return '';
        }

        $location = Registry::locationFactory()->make($location_xref, $fact->record()->tree());

        if (!$location instanceof Location || !$location->canShow()) {
            return '';
        }

        if (method_exists($location, 'namesAsPlaceStringsAt') && class_exists(\Vesta\Model\GedcomDateInterval::class)) {
            $date_interval = \Vesta\Model\GedcomDateInterval::create($fact->attribute('DATE'), true);
            $names = $location->namesAsPlaceStringsAt($date_interval);
            $name = (string) ($names->first() ?? '');

            if ($name !== '') {
                return $name;
            }
        }

        return trim(strip_tags($location->fullName()));
    }

    private function canManageNormalization(Tree $tree): bool
    {
        return Auth::isManager($tree) || Auth::isAdmin();
    }

    /**
     * @return array<string,list<array{entry_key:string,part_index:int,original_part_text:string,date:string,place:string,location_xref:string,location_hierarchy:string,employer:string,type:string,note:string,source_xrefs:string,source_names:string,language:string,social_status:string,occupation_normalized:string,occupation_de_male:string,occupation_de_female:string,occupation_de_neutral:string,occupation_en_male:string,occupation_en_female:string,occupation_en_neutral:string,office:string,qualification:string,code_hisco:string,code_gnd:string,code_ohdab:string,code_factgrid:string,norm_concept_id:int,status:string,reviewed:bool,rule_numbers:string}>>
     */
    private function normalizationRowsByFact(Tree $tree): array
    {
        if (!DBManager::schema()->hasTable(OccupationSchema::TABLE_NORMALIZED_ENTRIES)) {
            return [];
        }

        return DBManager::table(OccupationSchema::TABLE_NORMALIZED_ENTRIES)
            ->where('tree_id', '=', $tree->id())
            ->where('is_active', '=', true)
            ->orderBy('part_index')
            ->get()
            ->groupBy('fact_id')
            ->map(static fn (Collection $entries): array => $entries
                ->map(static fn (object $entry): array => [
                    'entry_key'             => (string) $entry->entry_key,
                    'part_index'            => (int) $entry->part_index,
                    'original_part_text'    => (string) $entry->original_part_text,
                    'date'                  => (string) ($entry->date ?? ''),
                    'place'                 => (string) ($entry->place ?? ''),
                    'location_xref'         => (string) ($entry->location_xref ?? ''),
                    'location_hierarchy'    => (string) ($entry->location_hierarchy ?? ''),
                    'employer'              => (string) ($entry->employer ?? ''),
                    'type'                  => (string) ($entry->type ?? ''),
                    'note'                  => (string) ($entry->note ?? ''),
                    'source_xrefs'          => (string) ($entry->source_xrefs ?? ''),
                    'source_names'          => (string) ($entry->source_names ?? ''),
                    'language'              => (string) ($entry->language ?? ''),
                    'social_status'         => (string) ($entry->social_status ?? ''),
                    'occupation_normalized' => (string) ($entry->occupation_normalized ?? ''),
                    'occupation_de_male'    => (string) ($entry->occupation_de_male ?? ''),
                    'occupation_de_female'  => (string) ($entry->occupation_de_female ?? ''),
                    'occupation_de_neutral' => (string) ($entry->occupation_de_neutral ?? ''),
                    'occupation_en_male'    => (string) ($entry->occupation_en_male ?? ''),
                    'occupation_en_female'  => (string) ($entry->occupation_en_female ?? ''),
                    'occupation_en_neutral' => (string) ($entry->occupation_en_neutral ?? ''),
                    'office'                => (string) ($entry->office ?? ''),
                    'qualification'         => (string) ($entry->qualification ?? ''),
                    'code_hisco'            => (string) ($entry->code_hisco ?? ''),
                    'code_gnd'              => (string) ($entry->code_gnd ?? ''),
                    'code_ohdab'            => (string) ($entry->code_ohdab ?? ''),
                    'code_factgrid'         => (string) ($entry->code_factgrid ?? ''),
                    'code_wikidata'         => (string) ($entry->code_wikidata ?? ''),
                    'norm_concept_id'       => (int) ($entry->norm_concept_id ?? 0),
                    'status'                => (string) $entry->status,
                    'reviewed'              => (bool) $entry->reviewed,
                    'rule_numbers'          => (string) $entry->rule_numbers,
                ])
                ->all())
            ->all();
    }

    /**
     * @param array<string,mixed> $params
     */
    private function saveNormalizationEntry(Tree $tree, array $params): void
    {
        if ((string) ($params['task'] ?? '') !== self::TASK_SAVE_NORMALIZATION_ENTRY) {
            return;
        }

        $entry_key = (string) ($params['entryKey'] ?? '');
        $status = (string) ($params['status'] ?? OccupationNormalizationService::STATUS_UNCLEAR);

        if (!in_array($status, self::NORMALIZATION_STATUSES, true)) {
            $status = OccupationNormalizationService::STATUS_UNCLEAR;
        }

        $updated = DBManager::table(OccupationSchema::TABLE_NORMALIZED_ENTRIES)
            ->where('tree_id', '=', $tree->id())
            ->where('entry_key', '=', $entry_key)
            ->where('is_active', '=', true)
            ->update([
                'date'                  => trim((string) ($params['date'] ?? '')),
                'place'                 => trim((string) ($params['place'] ?? '')),
                'location_xref'         => trim((string) ($params['locationXref'] ?? '')),
                'location_hierarchy'    => trim((string) ($params['locationHierarchy'] ?? '')),
                'employer'              => trim((string) ($params['employer'] ?? '')),
                'type'                  => trim((string) ($params['type'] ?? '')),
                'note'                  => trim((string) ($params['note'] ?? '')),
                'source_xrefs'          => trim((string) ($params['sourceXrefs'] ?? '')),
                'source_names'          => trim((string) ($params['sourceNames'] ?? '')),
                'language'              => trim((string) ($params['language'] ?? '')),
                'social_status'         => trim((string) ($params['socialStatus'] ?? '')),
                'occupation_normalized' => trim((string) ($params['occupationNormalized'] ?? '')),
                'occupation_de_male'    => '',
                'occupation_de_female'  => '',
                'occupation_de_neutral' => '',
                'occupation_en_male'    => '',
                'occupation_en_female'  => '',
                'occupation_en_neutral' => '',
                'office'                => trim((string) ($params['office'] ?? '')),
                'qualification'         => trim((string) ($params['qualification'] ?? '')),
                'code_hisco'            => trim((string) ($params['codeHisco'] ?? '')),
                'code_gnd'              => trim((string) ($params['codeGnd'] ?? '')),
                'code_ohdab'            => trim((string) ($params['codeOhdab'] ?? '')),
                'code_factgrid'         => trim((string) ($params['codeFactgrid'] ?? '')),
                'code_wikidata'         => trim((string) ($params['codeWikidata'] ?? '')),
                'norm_concept_id'       => 0,
                'status'                => $status,
                'reviewed'              => (string) ($params['reviewed'] ?? '') === '1',
                'manually_changed'      => true,
                'updated_at'            => date('Y-m-d H:i:s'),
            ]);

        if ($updated > 0) {
            FlashMessages::addMessage(I18N::translate('The normalization entry has been updated.'), 'success');

            return;
        }

        FlashMessages::addMessage(I18N::translate('The normalization entry could not be updated.'), 'warning');
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
            $this->deactivateDuplicateNormalizationRows($tree, date('Y-m-d H:i:s'));

            return;
        }

        $normalizer = new OccupationNormalizationService($this->normalizationRules(), $this->activeBuiltinRuleOrder(), $this->ohdabSpecialMappings());
        $tree_language = $this->occupationLanguage($tree);
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
                $place_data = $this->placeData($fact);

                foreach ($normalizer->normalize($occupation, $tree_language, [
                    'employer' => trim($fact->attribute('AGNC')),
                    'type'     => trim($fact->attribute('TYPE')),
                    'note'     => trim($fact->attribute('NOTE')),
                ]) as $entry) {
                    $entry_key = sha1($tree->id() . '|' . $individual->xref() . '|' . $fact->id() . '|' . $entry['part_index']);
                    $seen_keys[] = $entry_key;

                    $this->syncNormalizationEntry($tree, $individual, $fact, $entry_key, $entry, $source_data, $place_data, $now);
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

        $this->deactivateDuplicateNormalizationRows($tree, $now);
    }

    /**
     * @param array{part_index:int,original_part_text:string,language:string,social_status:string,occupation_normalized:string,occupation_de_male:string,occupation_de_female:string,occupation_de_neutral:string,occupation_en_male:string,occupation_en_female:string,occupation_en_neutral:string,office:string,qualification:string,code_hisco:string,code_gnd:string,code_ohdab:string,code_factgrid:string,norm_concept_id:int,status:string,rule_numbers:string} $entry
     * @param array{xrefs:list<string>,names:list<string>} $source_data
     * @param array{display:string,sort:string,place:string,location_xref:string,location_hierarchy:string} $place_data
     */
    private function syncNormalizationEntry(Tree $tree, Individual $individual, Fact $fact, string $entry_key, array $entry, array $source_data, array $place_data, string $now): void
    {
        $context = [
            'tree_id'            => $tree->id(),
            'individual_xref'    => $individual->xref(),
            'fact_id'            => $fact->id(),
            'part_index'         => $entry['part_index'],
            'original_fact_text' => trim($fact->value()),
            'original_part_text' => $entry['original_part_text'],
            'date'               => trim($fact->attribute('DATE')),
            'place'              => $place_data['place'],
            'location_xref'      => $place_data['location_xref'],
            'location_hierarchy' => $place_data['location_hierarchy'],
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

        if ($existing === null) {
            $existing = DBManager::table(OccupationSchema::TABLE_NORMALIZED_ENTRIES)
                ->where('tree_id', '=', $tree->id())
                ->where('individual_xref', '=', $individual->xref())
                ->where('fact_id', '=', $fact->id())
                ->where('part_index', '=', $entry['part_index'])
                ->where('is_active', '=', true)
                ->orderByDesc('reviewed')
                ->orderByDesc('manually_changed')
                ->orderByDesc('last_seen_at')
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->first();
        }

        if ($existing !== null) {
            $values = [
                'entry_key'    => $entry_key,
                'is_active'    => true,
                'last_seen_at' => $now,
                'updated_at'   => $now,
            ];

            if (!(bool) ($existing->manually_changed ?? false)) {
                $values += $context;
            }

            if (!(bool) $existing->reviewed && !(bool) ($existing->manually_changed ?? false)) {
                $values += $this->automaticNormalizationValues($entry);
            }

            DBManager::table(OccupationSchema::TABLE_NORMALIZED_ENTRIES)
                ->where('id', '=', $existing->id)
                ->update($values);

            $this->deactivateDuplicateNormalizationEntries($tree, $individual->xref(), $fact->id(), $entry['part_index'], (int) $existing->id, $now);

            return;
        }

        $inserted_id = (int) DBManager::table(OccupationSchema::TABLE_NORMALIZED_ENTRIES)->insertGetId([
            'entry_key'        => $entry_key,
            'reviewed'         => false,
            'manually_changed' => false,
            'created_at'       => $now,
        ] + $context + $this->automaticNormalizationValues($entry));

        $this->deactivateDuplicateNormalizationEntries($tree, $individual->xref(), $fact->id(), $entry['part_index'], $inserted_id, $now);
    }

    private function deactivateDuplicateNormalizationRows(Tree $tree, string $now): void
    {
        if (!DBManager::schema()->hasTable(OccupationSchema::TABLE_NORMALIZED_ENTRIES)) {
            return;
        }

        $rows = DBManager::table(OccupationSchema::TABLE_NORMALIZED_ENTRIES)
            ->where('tree_id', '=', $tree->id())
            ->where('is_active', '=', true)
            ->get(['id', 'individual_xref', 'fact_id', 'part_index', 'reviewed', 'manually_changed', 'last_seen_at', 'updated_at']);

        $groups = [];

        foreach ($rows as $row) {
            $key = $row->individual_xref . "\0" . $row->fact_id . "\0" . $row->part_index;
            $groups[$key][] = $row;
        }

        foreach ($groups as $group) {
            if (count($group) < 2) {
                continue;
            }

            $keep = $group[0];

            foreach ($group as $row) {
                if ($this->isBetterDuplicateNormalizationRow($row, $keep)) {
                    $keep = $row;
                }
            }

            $this->deactivateDuplicateNormalizationEntries(
                $tree,
                (string) $keep->individual_xref,
                (string) $keep->fact_id,
                (int) $keep->part_index,
                (int) $keep->id,
                $now
            );
        }
    }

    private function isBetterDuplicateNormalizationRow(object $candidate, object $current): bool
    {
        if ((bool) $candidate->reviewed !== (bool) $current->reviewed) {
            return (bool) $candidate->reviewed;
        }

        if ((bool) $candidate->manually_changed !== (bool) $current->manually_changed) {
            return (bool) $candidate->manually_changed;
        }

        $candidate_seen = max((string) ($candidate->last_seen_at ?? ''), (string) ($candidate->updated_at ?? ''));
        $current_seen = max((string) ($current->last_seen_at ?? ''), (string) ($current->updated_at ?? ''));

        if ($candidate_seen !== $current_seen) {
            return $candidate_seen > $current_seen;
        }

        return (int) $candidate->id > (int) $current->id;
    }

    private function deactivateDuplicateNormalizationEntries(Tree $tree, string $individual_xref, string $fact_id, int $part_index, int $keep_id, string $now): void
    {
        DBManager::table(OccupationSchema::TABLE_NORMALIZED_ENTRIES)
            ->where('tree_id', '=', $tree->id())
            ->where('individual_xref', '=', $individual_xref)
            ->where('fact_id', '=', $fact_id)
            ->where('part_index', '=', $part_index)
            ->where('is_active', '=', true)
            ->where('id', '<>', $keep_id)
            ->update([
                'is_active'  => false,
                'updated_at' => $now,
            ]);
    }

    /**
     * @param array{part_index:int,original_part_text:string,language:string,social_status:string,occupation_normalized:string,occupation_de_male:string,occupation_de_female:string,occupation_de_neutral:string,occupation_en_male:string,occupation_en_female:string,occupation_en_neutral:string,office:string,qualification:string,code_hisco:string,code_gnd:string,code_ohdab:string,code_factgrid:string,norm_concept_id:int,status:string,rule_numbers:string} $entry
     *
     * @return array{language:string,social_status:string,occupation_normalized:string,occupation_de_male:string,occupation_de_female:string,occupation_de_neutral:string,occupation_en_male:string,occupation_en_female:string,occupation_en_neutral:string,office:string,qualification:string,code_hisco:string,code_gnd:string,code_ohdab:string,code_factgrid:string,norm_concept_id:int,status:string,rule_numbers:string}
     */
    private function automaticNormalizationValues(array $entry): array
    {
        return [
            'language'              => $entry['language'],
            'social_status'         => $entry['social_status'],
            'occupation_normalized' => $entry['occupation_normalized'],
            'occupation_de_male'    => $entry['occupation_de_male'],
            'occupation_de_female'  => $entry['occupation_de_female'],
            'occupation_de_neutral' => $entry['occupation_de_neutral'],
            'occupation_en_male'    => $entry['occupation_en_male'],
            'occupation_en_female'  => $entry['occupation_en_female'],
            'occupation_en_neutral' => $entry['occupation_en_neutral'],
            'office'                => $entry['office'],
            'qualification'         => $entry['qualification'],
            'code_hisco'            => $entry['code_hisco'],
            'code_gnd'              => $entry['code_gnd'],
            'code_ohdab'            => $entry['code_ohdab'],
            'code_factgrid'         => $entry['code_factgrid'],
            'code_wikidata'         => $entry['code_wikidata'],
            'norm_concept_id'       => (int) ($entry['norm_concept_id'] ?? 0),
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
        $metadata_table = DBManager::table(OccupationSchema::TABLE_METADATA);

        if ($metadata_table->where('setting_name', '=', $setting_name)->exists()) {
            DBManager::table(OccupationSchema::TABLE_METADATA)
                ->where('setting_name', '=', $setting_name)
                ->update(['setting_value' => $setting_value]);

            return;
        }

        try {
            DBManager::table(OccupationSchema::TABLE_METADATA)->insert([
                'setting_name'  => $setting_name,
                'setting_value' => $setting_value,
            ]);
        } catch (QueryException $ex) {
            if (($ex->errorInfo[1] ?? null) !== 1062) {
                throw $ex;
            }

            DBManager::table(OccupationSchema::TABLE_METADATA)
                ->where('setting_name', '=', $setting_name)
                ->update(['setting_value' => $setting_value]);
        }
    }

    /**
     * @return list<array{id:string,label:string,description:string,enabled:bool}>
     */
    private function builtinRuleRows(): array
    {
        $enabled_rules = $this->enabledBuiltinRuleIds();
        $definitions = $this->builtinRuleDefinitions();

        return array_map(
            static fn (string $rule_id): array => [
                'id'          => $rule_id,
                'label'       => $definitions[$rule_id]['label'],
                'description' => $definitions[$rule_id]['description'],
                'enabled'     => in_array($rule_id, $enabled_rules, true),
            ],
            $this->storedBuiltinRuleOrder()
        );
    }

    /**
     * @return array<string,array{label:string,description:string}>
     */
    private function builtinRuleDefinitions(): array
    {
        return [
            'M2-R001' => [
                'label'       => I18N::translate('Split multiple statements'),
                'description' => I18N::translate('Splits occupation facts by separators and conjunctions.'),
            ],
            'M2-R010' => [
                'label'       => I18N::translate('Social status is not an occupation'),
                'description' => I18N::translate('Recognizes social status terms such as citizen without counting them as occupations.'),
            ],
            'M2-R020' => [
                'label'       => I18N::translate('Widow compounds'),
                'description' => I18N::translate('Recognizes widow compounds as social status hints.'),
            ],
            'M2-R021' => [
                'label'       => I18N::translate('Kinship-derived compounds'),
                'description' => I18N::translate('Recognizes compounds such as daughter, son, or wife as social status hints.'),
            ],
            'M2-R030' => [
                'label'       => I18N::translate('Craft qualification after colon'),
                'description' => I18N::translate('Separates craft qualifications such as master, journeyman, or apprentice after a colon.'),
            ],
            'M2-R031' => [
                'label'       => I18N::translate('Compound craft qualification'),
                'description' => I18N::translate('Separates known compound craft qualifications from the normalized occupation.'),
            ],
            'M2-R032' => [
                'label'       => I18N::translate('Independent master compounds are not split'),
                'description' => I18N::translate('Keeps independent terms such as schoolmaster or mayor as one occupation.'),
            ],
            'M2-R040' => [
                'label'       => I18N::translate('Context-based occupation refinement'),
                'description' => I18N::translate('Refines broad occupation terms using context from the occupation text or employer field.'),
            ],
            'M2-R050' => [
                'label'       => I18N::translate('Site-managed normalization mapping table'),
                'description' => I18N::translate('Applies the locally maintained mapping table for original and normalized occupation terms.'),
            ],
            'M4-R100' => [
                'label'       => I18N::translate('Normalize with external OhdAB special database'),
                'description' => I18N::translate('Uses the imported German OhdAB special database for normalized occupation terms.'),
            ],
            'M2-R090' => [
                'label'       => I18N::translate('Fallback for unknown terms'),
                'description' => I18N::translate('Keeps unknown terms as unclear normalized occupation proposals.'),
            ],
        ];
    }

    /**
     * @return list<string>
     */
    private function storedBuiltinRuleOrder(): array
    {
        return $this->completeBuiltinRuleOrder(explode(',', $this->getPreference(
            self::BUILTIN_RULE_ORDER_PREFERENCE,
            implode(',', OccupationNormalizationService::defaultBuiltinRuleOrder())
        )));
    }

    /**
     * @return list<string>
     */
    private function activeBuiltinRuleOrder(): array
    {
        $enabled_rules = $this->enabledBuiltinRuleIds();

        return array_values(array_filter(
            $this->storedBuiltinRuleOrder(),
            static fn (string $rule_id): bool => in_array($rule_id, $enabled_rules, true)
        ));
    }

    /**
     * @return list<string>
     */
    private function enabledBuiltinRuleIds(): array
    {
        return array_values(array_filter(
            OccupationNormalizationService::defaultBuiltinRuleOrder(),
            fn (string $rule_id): bool => $this->getPreference(self::BUILTIN_RULE_STATUS_PREFIX . $rule_id, '1') === '1'
        ));
    }

    /**
     * @param array<string,mixed> $params
     */
    private function saveBuiltinRuleSettings(array $params): void
    {
        $order = (string) ($params['resetBuiltinRuleOrder'] ?? '') === '1'
            ? OccupationNormalizationService::defaultBuiltinRuleOrder()
            : $this->completeBuiltinRuleOrder(is_array($params['builtinRuleOrder'] ?? null) ? $params['builtinRuleOrder'] : []);

        $this->setPreference(self::BUILTIN_RULE_ORDER_PREFERENCE, implode(',', $order));

        foreach (OccupationNormalizationService::defaultBuiltinRuleOrder() as $rule_id) {
            $this->setPreference(
                self::BUILTIN_RULE_STATUS_PREFIX . $rule_id,
                (string) ($params[self::BUILTIN_RULE_STATUS_PREFIX . $rule_id] ?? '') === '1' ? '1' : '0'
            );
        }

        $this->clearOccupationFingerprints();
        FlashMessages::addMessage(I18N::translate('The rule settings have been saved.'), 'success');
    }

    /**
     * @param array<mixed> $order
     *
     * @return list<string>
     */
    private function completeBuiltinRuleOrder(array $order): array
    {
        $default_order = OccupationNormalizationService::defaultBuiltinRuleOrder();
        $completed_order = [];

        foreach ($order as $rule_id) {
            $rule_id = (string) $rule_id;

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

        $ohdab_index = array_search('M4-R100', $completed_order, true);
        $fallback_index = array_search('M2-R090', $completed_order, true);

        if ($ohdab_index !== false && $fallback_index !== false && $ohdab_index > $fallback_index) {
            array_splice($completed_order, $ohdab_index, 1);
            $fallback_index = array_search('M2-R090', $completed_order, true);
            array_splice($completed_order, (int) $fallback_index, 0, ['M4-R100']);
        }

        return $completed_order;
    }

    /**
     * @return array<string,string>
     */
    private function languageOptions(): array
    {
        return Registry::container()
            ->get(ModuleService::class)
            ->findByInterface(ModuleLanguageInterface::class, true, true)
            ->mapWithKeys(static function (ModuleLanguageInterface $module): array {
                $locale = $module->locale();

                return [$locale->languageTag() => $locale->endonym() . ' (' . $locale->languageTag() . ')'];
            })
            ->sort()
            ->all();
    }

    /**
     * @return list<array{id:int,language:string,normalized_key:string,occupation_de_male:string,occupation_de_female:string,occupation_de_neutral:string,occupation_en_male:string,occupation_en_female:string,occupation_en_neutral:string,code_hisco:string,code_gnd:string,code_ohdab:string,code_factgrid:string}>
     */
    private function normalizationTermRows(): array
    {
        if (!DBManager::schema()->hasTable(OccupationSchema::TABLE_NORMALIZATION_TERMS)) {
            return [];
        }

        return DBManager::table(OccupationSchema::TABLE_NORMALIZATION_TERMS)
            ->orderBy('language')
            ->orderBy('occupation_de_male')
            ->get()
            ->map(static fn (object $row): array => [
                'id'                   => (int) $row->id,
                'language'             => (string) ($row->language ?? self::DEFAULT_OCCUPATION_LANGUAGE),
                'normalized_key'       => (string) $row->normalized_key,
                'occupation_de_male'    => (string) ($row->occupation_de_male ?? ''),
                'occupation_de_female'  => (string) ($row->occupation_de_female ?? ''),
                'occupation_de_neutral' => (string) ($row->occupation_de_neutral ?? ''),
                'occupation_en_male'    => (string) ($row->occupation_en_male ?? ''),
                'occupation_en_female'  => (string) ($row->occupation_en_female ?? ''),
                'occupation_en_neutral' => (string) ($row->occupation_en_neutral ?? ''),
                'code_hisco'           => (string) ($row->code_hisco ?? ''),
                'code_gnd'             => (string) ($row->code_gnd ?? ''),
                'code_ohdab'           => (string) ($row->code_ohdab ?? ''),
                'code_factgrid'        => (string) ($row->code_factgrid ?? ''),
                'code_wikidata'        => (string) ($row->code_wikidata ?? ''),
            ])
            ->all();
    }

    /**
     * @return array<int,string>
     */
    private function normalizationTermOptions(): array
    {
        $options = [0 => I18N::translate('No normalized term')];

        foreach ($this->normalizationTermRows() as $term) {
            $label = $this->keyMasculineForm($term['language'], $term['occupation_de_male'], $term['occupation_en_male']);
            $label = $label !== '' ? $label : $term['normalized_key'];
            $options[$term['id']] = $term['language'] . ': ' . $label;
        }

        return $options;
    }

    /**
     * @return list<array{id:int,language:string,original_text:string,normalized_term_id:int,normalized_key:string,occupation_de_male:string,occupation_de_female:string,occupation_de_neutral:string,occupation_en_male:string,occupation_en_female:string,occupation_en_neutral:string,social_status:string,occupation_normalized:string,qualification:string,code_hisco:string,code_gnd:string,code_ohdab:string,code_factgrid:string,enabled:bool}>
     */
    private function normalizationRuleRows(): array
    {
        if (!DBManager::schema()->hasTable(OccupationSchema::TABLE_NORMALIZATION_RULES)) {
            return [];
        }

        return DBManager::table(OccupationSchema::TABLE_NORMALIZATION_RULES . ' AS rules')
            ->leftJoin(OccupationSchema::TABLE_NORMALIZATION_TERMS . ' AS terms', 'terms.id', '=', 'rules.normalized_term_id')
            ->select([
                'rules.id',
                'rules.language',
                'rules.original_text',
                'rules.normalized_term_id',
                'rules.social_status',
                'rules.qualification',
                'rules.enabled',
                'terms.normalized_key',
                'terms.occupation_de_male',
                'terms.occupation_de_female',
                'terms.occupation_de_neutral',
                'terms.occupation_en_male',
                'terms.occupation_en_female',
                'terms.occupation_en_neutral',
                'terms.code_hisco',
                'terms.code_gnd',
                'terms.code_ohdab',
                'terms.code_factgrid',
                'terms.code_wikidata',
            ])
            ->orderBy('rules.language')
            ->orderBy('rules.original_text')
            ->get()
            ->map(static fn (object $row): array => [
                'id'                    => (int) $row->id,
                'language'              => (string) $row->language,
                'original_text'         => (string) $row->original_text,
                'normalized_term_id'    => (int) ($row->normalized_term_id ?? 0),
                'normalized_key'        => (string) ($row->normalized_key ?? ''),
                'occupation_de_male'    => (string) ($row->occupation_de_male ?? ''),
                'occupation_de_female'  => (string) ($row->occupation_de_female ?? ''),
                'occupation_de_neutral' => (string) ($row->occupation_de_neutral ?? ''),
                'occupation_en_male'    => (string) ($row->occupation_en_male ?? ''),
                'occupation_en_female'  => (string) ($row->occupation_en_female ?? ''),
                'occupation_en_neutral' => (string) ($row->occupation_en_neutral ?? ''),
                'social_status'         => (string) ($row->social_status ?? ''),
                'occupation_normalized' => (string) ($row->occupation_de_male ?? ''),
                'qualification'         => (string) ($row->qualification ?? ''),
                'code_hisco'            => (string) ($row->code_hisco ?? ''),
                'code_gnd'              => (string) ($row->code_gnd ?? ''),
                'code_ohdab'            => (string) ($row->code_ohdab ?? ''),
                'code_factgrid'         => (string) ($row->code_factgrid ?? ''),
                'code_wikidata'         => (string) ($row->code_wikidata ?? ''),
                'enabled'               => (bool) $row->enabled,
            ])
            ->all();
    }

    /**
     * @return list<array{language:string,original_text:string,social_status:string,occupation_normalized:string,occupation_de_male:string,occupation_de_female:string,occupation_de_neutral:string,occupation_en_male:string,occupation_en_female:string,occupation_en_neutral:string,qualification:string,code_hisco:string,code_gnd:string,code_ohdab:string,code_factgrid:string}>
     */
    private function normalizationRules(): array
    {
        return array_map(
            static fn (array $rule): array => [
                'language'              => $rule['language'],
                'original_text'         => $rule['original_text'],
                'social_status'         => $rule['social_status'],
                'occupation_normalized' => $rule['occupation_normalized'],
                'occupation_de_male'    => $rule['occupation_de_male'],
                'occupation_de_female'  => $rule['occupation_de_female'],
                'occupation_de_neutral' => $rule['occupation_de_neutral'],
                'occupation_en_male'    => $rule['occupation_en_male'],
                'occupation_en_female'  => $rule['occupation_en_female'],
                'occupation_en_neutral' => $rule['occupation_en_neutral'],
                'qualification'         => $rule['qualification'],
                'code_hisco'            => $rule['code_hisco'],
                'code_gnd'              => $rule['code_gnd'],
                'code_ohdab'            => $rule['code_ohdab'],
                'code_factgrid'         => $rule['code_factgrid'],
                'code_wikidata'         => $rule['code_wikidata'],
            ],
            array_values(array_filter(
                $this->normalizationRuleRows(),
                static fn (array $rule): bool => $rule['enabled']
            ))
        );
    }

    /**
     * @return list<array{language:string,original_text:string,norm_concept_id:int,occupation_normalized:string,occupation_de_male:string,occupation_de_female:string,occupation_de_neutral:string,occupation_en_male:string,occupation_en_female:string,occupation_en_neutral:string,code_hisco:string,code_gnd:string,code_ohdab:string,code_factgrid:string}>
     */
    private function ohdabSpecialMappings(): array
    {
        return (new OhdabSpecialDatabaseService())->mappings();
    }

    /**
     * @param array<string,mixed> $params
     */
    private function saveNormalizationRule(array $params): void
    {
        $id = (int) ($params['ruleId'] ?? 0);
        $original_text = trim((string) ($params['originalText'] ?? ''));
        $language = trim((string) ($params['language'] ?? ''));
        $normalized_term_id = (int) ($params['normalizedTermId'] ?? 0);

        if ($original_text === '' || $language === '') {
            FlashMessages::addMessage(I18N::translate('The normalization rule was not saved because language or original text is missing.'), 'warning');

            return;
        }

        $values = [
            'language'              => $language,
            'original_text'         => $original_text,
            'normalized_term_id'    => $normalized_term_id > 0 ? $normalized_term_id : null,
            'social_status'         => trim((string) ($params['socialStatus'] ?? '')),
            'qualification'         => trim((string) ($params['qualification'] ?? '')),
            'enabled'               => (string) ($params['enabled'] ?? '') === '1',
            'updated_at'            => date('Y-m-d H:i:s'),
        ];

        if ($id > 0) {
            DBManager::table(OccupationSchema::TABLE_NORMALIZATION_RULES)
                ->where('id', '=', $id)
                ->update($values);
        } else {
            DBManager::table(OccupationSchema::TABLE_NORMALIZATION_RULES)->updateOrInsert(
                [
                    'language'      => $language,
                    'original_text' => $original_text,
                ],
                ['created_at' => date('Y-m-d H:i:s')] + $values
            );
        }

        $this->clearOccupationFingerprints();
        FlashMessages::addMessage(I18N::translate('The normalization rule has been saved.'), 'success');
    }

    /**
     * @param array<string,mixed> $params
     */
    private function saveNormalizationTermAction(array $params): void
    {
        $id = (int) ($params['termId'] ?? 0);
        $language = trim((string) ($params['language'] ?? self::DEFAULT_OCCUPATION_LANGUAGE));
        $occupation_de_male = trim((string) ($params['occupationDeMale'] ?? ''));
        $occupation_en_male = trim((string) ($params['occupationEnMale'] ?? ''));
        $key_masculine_form = $this->keyMasculineForm($language, $occupation_de_male, $occupation_en_male);

        if ($language === '' || $key_masculine_form === '') {
            FlashMessages::addMessage(I18N::translate('The normalized term was not saved because language or language-specific masculine form is missing.'), 'warning');

            return;
        }

        $normalized_key = $this->normalizedTermKey($language, $key_masculine_form);
        $conflict_query = DBManager::table(OccupationSchema::TABLE_NORMALIZATION_TERMS)
            ->where('normalized_key', '=', $normalized_key);

        if ($id > 0) {
            $conflict_query->where('id', '<>', $id);
        }

        if ($conflict_query->exists()) {
            FlashMessages::addMessage(I18N::translate('The normalized term was not saved because the combination of language and masculine form already exists.'), 'warning');

            return;
        }

        $values = [
            'language'               => $language,
            'normalized_key'         => $normalized_key,
            'occupation_de_male'     => $occupation_de_male,
            'occupation_de_female'   => trim((string) ($params['occupationDeFemale'] ?? '')),
            'occupation_de_neutral'  => trim((string) ($params['occupationDeNeutral'] ?? '')),
            'occupation_en_male'     => $occupation_en_male,
            'occupation_en_female'   => trim((string) ($params['occupationEnFemale'] ?? '')),
            'occupation_en_neutral'  => trim((string) ($params['occupationEnNeutral'] ?? '')),
            'code_hisco'             => trim((string) ($params['codeHisco'] ?? '')),
            'code_gnd'               => trim((string) ($params['codeGnd'] ?? '')),
            'code_ohdab'             => trim((string) ($params['codeOhdab'] ?? '')),
            'code_factgrid'          => trim((string) ($params['codeFactgrid'] ?? '')),
            'code_wikidata'          => trim((string) ($params['codeWikidata'] ?? '')),
            'updated_at'             => date('Y-m-d H:i:s'),
        ];

        if ($id > 0) {
            DBManager::table(OccupationSchema::TABLE_NORMALIZATION_TERMS)
                ->where('id', '=', $id)
                ->update($values);
        } else {
            DBManager::table(OccupationSchema::TABLE_NORMALIZATION_TERMS)->updateOrInsert(
                ['normalized_key' => $normalized_key],
                ['created_at' => date('Y-m-d H:i:s')] + $values
            );
        }

        $this->clearOccupationFingerprints();
        FlashMessages::addMessage(I18N::translate('The normalized term has been saved.'), 'success');
    }

    private function normalizedTermKey(string $language, string $occupation): string
    {
        return trim($language) . ':' . trim($occupation);
    }

    private function keyMasculineForm(string $language, string $occupation_de_male, string $occupation_en_male): string
    {
        $primary_language = explode('-', trim($language))[0] ?? '';

        if ($primary_language === 'en') {
            return trim($occupation_en_male) !== '' ? trim($occupation_en_male) : trim($occupation_de_male);
        }

        return trim($occupation_de_male) !== '' ? trim($occupation_de_male) : trim($occupation_en_male);
    }

    /**
     * @param array<string,mixed> $params
     */
    private function deleteNormalizationRule(array $params): void
    {
        $id = (int) ($params['ruleId'] ?? 0);

        if ($id > 0) {
            DBManager::table(OccupationSchema::TABLE_NORMALIZATION_RULES)
                ->where('id', '=', $id)
                ->delete();

            $this->clearOccupationFingerprints();
            FlashMessages::addMessage(I18N::translate('The normalization rule has been deleted.'), 'success');
        }
    }

    /**
     * @param array<string,mixed> $params
     */
    private function deleteNormalizationTerm(array $params): void
    {
        $id = (int) ($params['termId'] ?? 0);

        if ($id <= 0) {
            return;
        }

        $used = DBManager::table(OccupationSchema::TABLE_NORMALIZATION_RULES)
            ->where('normalized_term_id', '=', $id)
            ->exists();

        if ($used) {
            FlashMessages::addMessage(I18N::translate('The normalized term cannot be deleted because it is used by normalization rules.'), 'warning');

            return;
        }

        DBManager::table(OccupationSchema::TABLE_NORMALIZATION_TERMS)
            ->where('id', '=', $id)
            ->delete();

        $this->clearOccupationFingerprints();
        FlashMessages::addMessage(I18N::translate('The normalized term has been deleted.'), 'success');
    }

    private function clearOccupationFingerprints(): void
    {
        DBManager::table(OccupationSchema::TABLE_METADATA)
            ->where('setting_name', 'like', self::FINGERPRINT_PREFIX . '%')
            ->delete();
    }

    private function occupationLanguage(Tree $tree): string
    {
        return $this->getPreference(
            self::TREE_LANGUAGE_PREFIX . $tree->id(),
            self::DEFAULT_OCCUPATION_LANGUAGE
        );
    }

    /**
     * @return list<array{tree_id:int,tree_name:string,tree_title:string,tree_language:string}>
     */
    private function treeLanguageRows(): array
    {
        return DBManager::table('gedcom AS tree')
            ->leftJoin('gedcom_setting AS title', static function ($join): void {
                $join
                    ->on('title.gedcom_id', '=', 'tree.gedcom_id')
                    ->where('title.setting_name', '=', 'title');
            })
            ->orderBy('title.setting_value')
            ->orderBy('tree.gedcom_name')
            ->select([
                'tree.gedcom_id AS tree_id',
                'tree.gedcom_name AS tree_name',
                'title.setting_value AS tree_title',
            ])
            ->get()
            ->map(fn (object $row): array => [
                'tree_id'       => (int) $row->tree_id,
                'tree_name'     => (string) $row->tree_name,
                'tree_title'    => (string) ($row->tree_title ?? $row->tree_name),
                'tree_language' => $this->getPreference(
                    self::TREE_LANGUAGE_PREFIX . (int) $row->tree_id,
                    self::DEFAULT_OCCUPATION_LANGUAGE
                ),
            ])
            ->all();
    }

    /**
     * @param array<string,mixed> $params
     */
    private function saveTreeLanguages(array $params): void
    {
        $languages = is_array($params['treeLanguage'] ?? null) ? $params['treeLanguage'] : [];
        $language_options = $this->languageOptions();

        foreach ($this->treeLanguageRows() as $tree_language) {
            $tree_id = $tree_language['tree_id'];
            $language = trim((string) ($languages[$tree_id] ?? self::DEFAULT_OCCUPATION_LANGUAGE));

            if (!array_key_exists($language, $language_options)) {
                $language = self::DEFAULT_OCCUPATION_LANGUAGE;
            }

            $this->setPreference(self::TREE_LANGUAGE_PREFIX . $tree_id, $language);
        }

        $this->clearOccupationFingerprints();
        FlashMessages::addMessage(I18N::translate('The family tree occupation languages have been saved.'), 'success');
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
     * @return array{original_total:int,split_total:int,assigned_total:int,categories:list<array{category:string,count:int,percentage:float}>}
     */
    private function ohdabCategoryStatistics(): array
    {
        $empty_statistics = [
            'original_total' => 0,
            'split_total'    => 0,
            'assigned_total' => 0,
            'categories'     => [],
        ];

        foreach ([
            OccupationSchema::TABLE_NORMALIZED_ENTRIES,
            OccupationSchema::TABLE_NORM_CONCEPT_HIERARCHY,
            OccupationSchema::TABLE_NORM_HIERARCHY_NODES,
        ] as $table) {
            if (!DBManager::schema()->hasTable($table)) {
                return $empty_statistics;
            }
        }

        $active_entries = DBManager::table(OccupationSchema::TABLE_NORMALIZED_ENTRIES)
            ->where('is_active', '=', true);
        $split_total = (int) $active_entries->count();
        $original_total = (int) DBManager::table(OccupationSchema::TABLE_NORMALIZED_ENTRIES)
            ->where('is_active', '=', true)
            ->distinct()
            ->get(['tree_id', 'fact_id'])
            ->count();

        $category_rows = DBManager::table(OccupationSchema::TABLE_NORMALIZED_ENTRIES . ' AS entries')
            ->join(OccupationSchema::TABLE_NORM_CONCEPT_HIERARCHY . ' AS links', static function ($join): void {
                $join
                    ->on('links.concept_id', '=', 'entries.norm_concept_id')
                    ->where('links.position', '=', 1);
            })
            ->join(OccupationSchema::TABLE_NORM_HIERARCHY_NODES . ' AS nodes', 'nodes.id', '=', 'links.node_id')
            ->where('entries.is_active', '=', true)
            ->where('entries.norm_concept_id', '>', 0)
            ->groupBy('nodes.label')
            ->orderBy('nodes.label')
            ->select([
                'nodes.label AS category',
                DB::raw('COUNT(*) AS category_count'),
            ])
            ->get();

        $assigned_total = (int) $category_rows->sum('category_count');

        return [
            'original_total' => $original_total,
            'split_total'    => $split_total,
            'assigned_total' => $assigned_total,
            'categories'     => $category_rows
                ->map(static fn (object $row): array => [
                    'category'   => (string) $row->category,
                    'count'      => (int) $row->category_count,
                    'percentage' => $assigned_total > 0 ? (int) $row->category_count / $assigned_total : 0.0,
                ])
                ->all(),
        ];
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
