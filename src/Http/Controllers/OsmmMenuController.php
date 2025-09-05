<?php

namespace CapsuleCmdr\SeatOsmm\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Support\Collection;
use CapsuleCmdr\SeatOsmm\Models\OsmmSetting;
use Illuminate\Support\Facades\Gate;

class OsmmMenuController extends Controller
{
    /* ==================== Constants ==================== */
    private const KEEP_SEGS  = ['home','alliances','characters','corporations','tools'];
    private const ADMIN_SEGS = ['configuration','notifications','api-admin']; // 'configuration' == settings
    private const IGNORE_SEGS = ['profile'];
    private const SEG_TOOLS  = 'tools';
    private const SEG_PLUGS  = 'plugins';
    private const SEG_ADMIN  = 'administration';

    private const CACHE_ROWS   = 'osmm_menu_rows';
    private const CACHE_MERGED = 'osmm_menu_merged';

    /* ==================== Public: UI & APIs ==================== */

    /** CONFIG PAGE: side-by-side tree view (native vs merged) + CRUD tools */
    public function index()
    {
        // Build full merged menu (cached), and also capture baseNative (pre-merged) for dropdowns/catalog.
        ['baseNative' => $baseNative, 'merged' => $merged, 'maps' => $maps, 'dbRows' => $dbRows] = $this->buildMergedMenu();

        // Dropdown: parent options derived from baseNative
        $parentOptions = collect($baseNative)->map(function ($v, $k) {
            $seg      = $v['route_segment'] ?? $k;
            $parentId = DB::table('osmm_menu_items')->whereNull('parent')->where('route_segment', $seg)->value('id');
            return [
                'key'       => $k,
                'name'      => $v['name'] ?? $k,
                'seg'       => $seg,
                'label'     => ($v['name'] ?? $k)." [{$seg}]",
                'parent_id' => $parentId,
            ];
        })->values()->all();

        $routeSegments = collect($baseNative)->map(function ($v, $k) {
            $seg = $v['route_segment'] ?? $k;
            return ['value' => $seg, 'label' => ($v['name'] ?? $k) . " [{$seg}]"];
        })->values()->all();

        $menuCatalog = $this->buildMenuCatalog($baseNative);

        $allPermissions = $this->collectPermissionOptionsFrom($this->getNativeConfig(), $dbRows);

        $osmmMenuMode = 0; // default when not set
        try {
            if (function_exists('osmm_setting')) {
                $osmmMenuMode = (int) osmm_setting('osmm_override_menu', 0);
            } else {
                // fallback direct read from model if helper not loaded
                $osmmMenuMode = (int) (OsmmSetting::get('osmm_override_menu', 0));
            }
        } catch (\Throwable $e) {
            $osmmMenuMode = 0;
        }

        $modeLabelMap = [0 => 'Off', 1 => 'Off', 2 => 'Sidebar', 3 => 'Topbar'];

        $can = fn ($perm) => empty($perm) || (\auth()->check() && \auth()->user()->can($perm));

        return view('seat-osmm::menu.index', [
            'native'         => $baseNative,
            'merged'         => $merged,
            'dbRows'         => $dbRows,
            'parentOptions'  => $parentOptions,
            'allPermissions' => $allPermissions,
            'routeSegments'  => $routeSegments,
            'can'            => $can,
            'menuCatalog'    => $menuCatalog,
            'osmmMenuMode' => $osmmMenuMode,
        ]);
    }

    /** API: merged menu as JSON for app consumption */
    public function jsonMerged(Request $request)
    {
        ['merged' => $merged] = $this->buildMergedMenu();
        if (!$request->boolean('raw')) {
            $merged = $this->pruneByAuth($merged, auth()->user());
        }
        return response()->json($merged);
    }

    /** API: native package.sidebar as JSON */
    public function jsonNative()
    {
        return response()->json($this->getNativeConfig());
    }

    /** API: current overrides as JSON (config-shaped) */
    public function jsonOverrides()
    {
        return response()->json($this->buildDbOverrides());
    }

    /** Helper for views: returns merged array */
    public function menu(): array
    {
        ['merged' => $merged] = $this->buildMergedMenu();
        return $this->pruneByAuth($merged, auth()->user());
    }

    /* ==================== CRUD for overrides ==================== */

    public function upsertParent(Request $request)
    {
        $data = $request->validate([
            'id'             => 'nullable|integer|exists:osmm_menu_items,id',
            'route_segment'  => 'required|string|max:150',
            'name_override'  => 'nullable|string|max:150',
            'label_override' => 'nullable|string|max:190',
            'icon'           => 'nullable|string|max:150',
            'route'          => 'nullable|string|max:190',
            'permission'     => 'nullable|string|max:190',
            'visible'        => ['nullable', Rule::in([0,1,'0','1'])],
            'order'          => 'nullable|integer|min:1',
        ]);

        $data = $this->normalizeCrud($data);

        if (!empty($data['id'])) {
            DB::table('osmm_menu_items')->where('id', $data['id'])->update([
                'route_segment'  => $data['route_segment'],
                'name_override'  => $data['name_override']  ?? null,
                'label_override' => $data['label_override'] ?? null,
                'icon'           => $data['icon']           ?? null,
                'route'          => $data['route']          ?? null,
                'permission'     => $data['permission']     ?? null,
                'visible'        => $data['visible']        ?? null,
                'order'          => $data['order']          ?? null,
                'updated_at'     => now(),
            ]);
        } else {
            $row = DB::table('osmm_menu_items')
                ->whereNull('parent')
                ->where('route_segment', $data['route_segment'])
                ->first();

            if ($row) {
                DB::table('osmm_menu_items')->where('id', $row->id)->update([
                    'name_override'  => $data['name_override']  ?? null,
                    'label_override' => $data['label_override'] ?? null,
                    'icon'           => $data['icon']           ?? null,
                    'route'          => $data['route']          ?? null,
                    'permission'     => $data['permission']     ?? null,
                    'visible'        => $data['visible']        ?? null,
                    'order'          => $data['order']          ?? null,
                    'updated_at'     => now(),
                ]);
            } else {
                DB::table('osmm_menu_items')->insert([
                    'parent'         => null,
                    'route_segment'  => $data['route_segment'],
                    'name_override'  => $data['name_override']  ?? null,
                    'label_override' => $data['label_override'] ?? null,
                    'icon'           => $data['icon']           ?? null,
                    'route'          => $data['route']          ?? null,
                    'permission'     => $data['permission']     ?? null,
                    'visible'        => $data['visible']        ?? null,
                    'order'          => $data['order']          ?? null,
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ]);
            }
        }

        $this->forgetMenuCaches();
        return back()->with('status', 'Parent override saved.');
    }

    public function upsertChild(Request $request)
    {
        $data = $request->validate([
            'id'             => 'nullable|integer|exists:osmm_menu_items,id',
            'parent_id'      => 'nullable|integer|exists:osmm_menu_items,id',
            'route_segment'  => 'nullable|string|max:150',
            'target_route'   => 'nullable|string|max:190',
            'target_name'    => 'nullable|string|max:150',
            'name_override'  => 'nullable|string|max:150',
            'label_override' => 'nullable|string|max:190',
            'icon'           => 'nullable|string|max:150',
            'route'          => 'nullable|string|max:190',
            'permission'     => 'nullable|string|max:190',
            'visible'        => ['nullable', Rule::in([0,1,'0','1'])],
            'order'          => 'nullable|integer|min:1',
        ]);

        $data = $this->normalizeCrud($data);

        // Resolve parent id (by parent_id or route_segment)
        $parentId = $data['parent_id'] ?? null;
        if (!$parentId && !empty($data['route_segment'])) {
            $parentId = DB::table('osmm_menu_items')
                ->whereNull('parent')
                ->where('route_segment', $data['route_segment'])
                ->value('id');

            if (!$parentId) {
                $parentId = DB::table('osmm_menu_items')->insertGetId([
                    'parent'         => null,
                    'route_segment'  => $data['route_segment'],
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ]);
            }
        }

        if (!$parentId) {
            return back()->withErrors(['parent_id' => 'Parent section is required.']);
        }

        if (!empty($data['id'])) {
            DB::table('osmm_menu_items')->where('id', $data['id'])->update([
                'parent'         => $parentId,
                'name_override'  => $data['name_override']  ?? null,
                'label_override' => $data['label_override'] ?? null,
                'icon'           => $data['icon']           ?? null,
                'route'          => $data['route']          ?? null,
                'permission'     => $data['permission']     ?? null,
                'visible'        => $data['visible']        ?? null,
                'order'          => $data['order']          ?? null,
                'updated_at'     => now(),
            ]);
            $this->forgetMenuCaches();
            return back()->with('status', 'Child override saved.');
        }

        // Identity match for an existing child (prefer route, fallback to name when route is null)
        $existingId = null;

        if (!empty($data['target_route'])) {
            $existingId = DB::table('osmm_menu_items')
                ->where('parent', $parentId)
                ->where('route', $data['target_route'])
                ->value('id');
        }

        if (!$existingId && !empty($data['target_name'])) {
            $existingId = DB::table('osmm_menu_items')
                ->where('parent', $parentId)
                ->whereNull('route')
                ->where('name', $data['target_name'])
                ->value('id');
        }

        if ($existingId) {
            DB::table('osmm_menu_items')->where('id', $existingId)->update([
                'name_override'  => $data['name_override']  ?? null,
                'label_override' => $data['label_override'] ?? null,
                'icon'           => $data['icon']           ?? null,
                'route'          => $data['route']          ?? DB::raw('`route`'),
                'permission'     => $data['permission']     ?? null,
                'visible'        => $data['visible']        ?? null,
                'order'          => $data['order']          ?? null,
                'updated_at'     => now(),
            ]);
        } else {
            DB::table('osmm_menu_items')->insert([
                'parent'         => $parentId,
                'name'           => $data['target_name']   ?? null,
                'route'          => $data['route']         ?? ($data['target_route'] ?? null),
                'name_override'  => $data['name_override']  ?? null,
                'label_override' => $data['label_override'] ?? null,
                'icon'           => $data['icon']           ?? null,
                'permission'     => $data['permission']     ?? null,
                'visible'        => $data['visible']        ?? null,
                'order'          => $data['order']          ?? null,
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);
        }

        $this->forgetMenuCaches();
        return back()->with('status', 'Child override saved.');
    }

    public function delete(Request $req)
    {
        $data = $req->validate([
            'id' => ['required','integer','exists:osmm_menu_items,id'],
            'cascade' => ['sometimes','boolean'],
        ]);

        if (!empty($data['cascade'])) {
            DB::table('osmm_menu_items')->where('parent', $data['id'])->delete();
        }
        DB::table('osmm_menu_items')->where('id', $data['id'])->delete();

        $this->forgetMenuCaches();
        return back()->with('ok', 'Override deleted.');
    }

    public function resetAll()
    {
        DB::table('osmm_menu_items')->truncate();
        $this->forgetMenuCaches();
        return back()->with('ok', 'All overrides cleared.');
    }

    /* ==================== Build: Native → Base → Overrides → Reorders ==================== */

    /**
     * Full pipeline, cached:
     *  - load & sort native
     *  - build base-native (keep/admin/plugins)
     *  - inject custom links (tools)
     *  - load DB rows (cached)
     *  - precompute parentId→segment map
     *  - apply overrides
     *  - apply reorders
     *
     * @return array{baseNative: array, merged: array, maps: array, dbRows: \Illuminate\Support\Collection}
     */
    private function buildMergedMenu(): array
    {
        return Cache::remember(self::CACHE_MERGED, 60, function () {
            $nativeSorted = $this->sortNativeConfig($this->getNativeConfig());

            ['menu' => $baseNative, 'maps' => $maps] = $this->buildBaseNative($nativeSorted);

            // Inject custom links into Tools once
            $this->injectCustomLinks($baseNative);

            // DB rows (cached separately)
            $dbRows = $this->fetchMenuRows();

            // Precompute parentId -> route_segment to avoid N+1
            $parentSegById = $this->mapParentIdToSegment($dbRows);

            // Overrides
            $merged = $this->applyOverridesWithConsolidation($baseNative, $dbRows, $maps, $parentSegById);

            // Reorders
            $merged = $this->applyReorders($merged, $baseNative, $dbRows, $maps, $parentSegById);

            return [
                'baseNative' => $baseNative,
                'merged'     => $merged,
                'maps'       => $maps,
                'dbRows'     => $dbRows,
            ];
        });
    }

    /** Load native config. */
    private function getNativeConfig(): array
    {
        $nativeConfig = config('package.sidebar') ?? [];
        return $nativeConfig;
    }

    /** Sort native parents and children like SeAT. */
    private function sortNativeConfig(array $native): array
    {
        $sorted = $native;

        uksort($sorted, function($aKey,$bKey) use ($sorted){
            $a = $sorted[$aKey]; $b = $sorted[$bKey];
            $aW = $this->numericPrefixWeight($aKey);
            $bW = $this->numericPrefixWeight($bKey);
            if ($aW !== $bW) return $aW <=> $bW;

            return strnatcasecmp(
                mb_strtolower($this->labelOf($a,$aKey)),
                mb_strtolower($this->labelOf($b,$bKey))
            );
        });

        foreach ($sorted as &$p) $this->sortChildrenAlpha($p);
        unset($p);

        return $sorted;
    }

    /**
     * Build the "base-native" menu:
     * - Keep: home, alliances, characters, corporations, tools
     * - Add:  administration = settings[*] + seatapi + notifications[*]
     * - Add:  plugins = every other native parent grouped as multi-level under Plugins
     *
     * @return array{menu: array, maps: array}
     */
    protected function buildBaseNative(array $nativeSorted): array
{
    // --- helpers ------------------------------------------------------------
    $resolveLabel = function (array $node): string {
        $label = $node['label'] ?? ($node['name'] ?? '');

        if (!is_string($label)) {
            return (string) $label;
        }

        // Pipe pluralization: "singular|plural"
        if (strpos($label, '|') !== false) {
            $count = array_key_exists('count', $node)
                ? (int) $node['count']
                : (!empty($node['plural']) ? 2 : 1);
            return trans_choice($label, $count);
        }

        // Translation keys (with or without pluralization)
        if (str_contains($label, '::') || str_contains($label, '.')) {
            $count = array_key_exists('count', $node)
                ? (int) $node['count']
                : (!empty($node['plural']) ? 2 : 1);
            // trans_choice works for both pluralized and non-pluralized keys
            return trans_choice($label, $count);
        }

        return $label;
    };

    $normalizeNode = function (array $node) use (&$normalizeNode, $resolveLabel): array {
        $node['label'] = $resolveLabel($node);
        if (!empty($node['entries']) && is_array($node['entries'])) {
            $node['entries'] = array_values(array_map(function ($child) use ($normalizeNode) {
                return is_array($child) ? $normalizeNode($child) : $child;
            }, array_filter($node['entries'], 'is_array')));
        }
        return $node;
    };

    // --- key -> segment map -------------------------------------------------
    $segOfKey = [];
    foreach ($nativeSorted as $k => $p) {
        $segOfKey[$k] = $p['route_segment'] ?? $k;
    }

    // --- Collect admin entries (flatten) -----------------------------------
    $adminEntries = [];

    $adminize = function (array $node) use ($normalizeNode): array {
        $node = $normalizeNode($node);
        if (empty($node['permission'])) {
            $node['permission'] = 'global.superuser'; // fallback for admin items
        }
        return $node;
    };

    // settings = 'configuration'
    $settingsKey = array_search('configuration', $segOfKey, true);
    if ($settingsKey !== false && !empty($nativeSorted[$settingsKey]['entries'])) {
        foreach ($nativeSorted[$settingsKey]['entries'] as $c) {
            if (is_array($c)) $adminEntries[] = $adminize($c);
        }
    }

    // SeAT API = 'api-admin'
    $seatApiKey = array_search('api-admin', $segOfKey, true);
    if ($seatApiKey !== false) {
        $seatApi = $nativeSorted[$seatApiKey];
        $adminEntries[] = $adminize([
            'name'       => $seatApi['name'] ?? 'SeAT API',
            'label'      => $seatApi['label'] ?? ($seatApi['name'] ?? 'SeAT API'),
            'icon'       => $seatApi['icon'] ?? 'fas fa-exchange-alt',
            'route'      => $seatApi['route'] ?? null,
            'permission' => $seatApi['permission'] ?? null,
        ]);
    }

    // notifications
    $notifKey = array_search('notifications', $segOfKey, true);
    if ($notifKey !== false && !empty($nativeSorted[$notifKey]['entries'])) {
        foreach ($nativeSorted[$notifKey]['entries'] as $c) {
            if (is_array($c)) $adminEntries[] = $adminize($c);
        }
    }

    // --- Build Plugins = all parents not in KEEP or ADMIN -------------------
    $pluginSegs = [];
    $pluginSubEntries = [];
    foreach ($nativeSorted as $k => $p) {
        $seg = $segOfKey[$k];
        if (in_array($seg, self::KEEP_SEGS, true))  continue;
        if (in_array($seg, self::ADMIN_SEGS, true)) continue;
        if (in_array($seg, self::IGNORE_SEGS, true)) continue;

        $pluginSegs[] = $seg;

        $sub = [
            'name'             => $p['name'] ?? $k,
            'label'            => $p['label'] ?? ($p['name'] ?? $k),
            'icon'             => $p['icon'] ?? 'fas fa-puzzle-piece',
            'route'            => $p['route'] ?? null,
            'permission'       => $p['permission'] ?? null,
            '_osmm_subsegment' => $seg,
            'entries'          => [],
        ];

        if (!empty($p['entries']) && is_array($p['entries'])) {
            $sub['entries'] = array_values(array_map(function ($child) use ($normalizeNode) {
                return is_array($child) ? $normalizeNode($child) : $child;
            }, array_filter($p['entries'], 'is_array')));
        }

        $pluginSubEntries[] = $normalizeNode($sub);
    }

    // --- Assemble base: kept parents first (native order) -------------------
    $base = [];
    foreach ($nativeSorted as $k => $p) {
        $seg = $segOfKey[$k];
        if (in_array($seg, self::KEEP_SEGS, true)) {
            $base[$k] = $normalizeNode($p);
        }
    }

    // --- Add Administration --------------------------------------------------
    $base[self::SEG_ADMIN] = [
        'name'          => 'Administration',
        'label'         => 'Administration',
        'icon'          => 'fas fa-cogs',
        'route_segment' => self::SEG_ADMIN,
        'permission'    => 'global.superuser',
        'entries'       => $adminEntries,
    ];

    // --- Add Plugins ---------------------------------------------------------
    $base[self::SEG_PLUGS] = [
        'name'          => 'Plugins',
        'label'         => 'Plugins',
        'icon'          => 'fas fa-plug',
        'route_segment' => self::SEG_PLUGS,
        'permission'    => null,
        'entries'       => $pluginSubEntries,
    ];

    return [
        'menu' => $base,
        'maps' => [
            'admin_segments'  => self::ADMIN_SEGS,
            'plugin_segments' => $pluginSegs,
        ],
    ];
}


    private function resolveLabel(array $p): string
    {
        $label = $p['label'] ?? ($p['name'] ?? '');

        // If it's a pipe string, use plural flag to pick singular/plural
        if (is_string($label) && strpos($label, '|') !== false) {
            $count = !empty($p['plural']) ? 2 : 1;   // 1 = singular, 2 = plural
            return trans_choice($label, $count);
        }

        // If it looks like a translation key, resolve it
        if (is_string($label) && (str_contains($label, '::') || str_contains($label, '.'))) {
            // If caller provided a 'count', prefer it for languages with complex plurals
            $count = array_key_exists('count', $p) ? (int) $p['count'] : (!empty($p['plural']) ? 2 : 1);
            return trans_choice($label, $count); // safe even if not pluralized; falls back to __
        }

        // Plain text fallback
        return (string) $label;
    }

    /** Inject a normalized custom-links block at the top of Tools, followed by a single divider. */
    protected function injectCustomLinks(array &$menu): void
    {
        // Find "tools"
        $toolsKey = null;
        foreach ($menu as $k => $p) {
            $seg = $p['route_segment'] ?? $k;
            if ($seg === self::SEG_TOOLS) { $toolsKey = $k; break; }
        }
        if ($toolsKey === null) return;

        $entries = $menu[$toolsKey]['entries'] ?? [];
        $entries = is_array($entries) ? array_values(array_filter($entries, 'is_array')) : [];

        // strip all prior custom items & dividers
        $others = [];
        foreach ($entries as $e) {
            if (!empty($e['_osmm_custom'])) continue;
            if (!empty($e['divider']))      continue;
            $others[] = $e;
        }

        // build fresh custom block from settings('customlinks')
        $links = collect();
        try {
            if (function_exists('setting')) {
                $links = setting('customlinks', true) ?? collect();
            }
        } catch (\Throwable $e) { /* ignore */ }

        if (!($links instanceof \Illuminate\Support\Collection)) {
            $links = collect($links);
        }

        $customItems = [];
        $seen = [];
        foreach ($links as $l) {
            $name   = $l->name    ?? $l['name']    ?? null;
            $url    = $l->url     ?? $l['url']     ?? null;
            $icon   = $l->icon    ?? $l['icon']    ?? 'fas fa-link';
            $newTab = $l->new_tab ?? $l['new_tab'] ?? false;

            if (!$name || !$url) continue;
            $key = strtolower(trim($url));
            if (isset($seen[$key])) continue;
            $seen[$key] = true;

            $customItems[] = [
                '_osmm_custom' => true,
                'name'         => $name,
                'label'        => $name,
                'icon'         => $icon,
                'url'          => $url,
                'target'       => $newTab ? '_blank' : null,
            ];
        }

        // Rebuild: custom..., divider, others...
        $menu[$toolsKey]['entries'] = !empty($customItems)
            ? array_merge($customItems, [['divider' => true]], $others)
            : $others;
    }

    /** Cached DB rows for menu overrides. */
    private function fetchMenuRows(): Collection
    {
        return Cache::remember(self::CACHE_ROWS, 60, function () {
            return DB::table('osmm_menu_items')
                ->select(
                    'id','parent','order','name',
                    'name_override','label_override',
                    'icon','route_segment','route','permission',
                    'visible','created_at','updated_at'
                )
                ->orderBy('parent')->orderBy('order')
                ->get()
                ->map(fn($r) => (object) $r);
        });
    }

    /** Map parent row id → route_segment (no N+1). */
    private function mapParentIdToSegment(Collection $rows): array
    {
        $parentIds = $rows->pluck('parent')->filter()->unique()->values();
        if ($parentIds->isEmpty()) return [];

        return DB::table('osmm_menu_items')
            ->whereIn('id', $parentIds)
            ->pluck('route_segment', 'id')
            ->toArray(); // [parent_id => segment]
    }

    /**
     * Apply DB overrides AFTER consolidation.
     *
     * @param array $baseNative
     * @param \Illuminate\Support\Collection $dbRows
     * @param array $maps
     * @param array $parentSegById
     */
    protected function applyOverridesWithConsolidation(array $baseNative, Collection $dbRows, array $maps, array $parentSegById): array
    {
        $merged   = $baseNative;
        $adminSet = array_flip($maps['admin_segments'] ?? []);
        $plugSet  = array_flip($maps['plugin_segments'] ?? []);

        // ----- Parent rows (parent = NULL)
        foreach ($dbRows as $row) {
            if (!is_null($row->parent)) continue;
            $seg = $row->route_segment;
            if (!$seg) continue;

            // Kept parents (non-admin non-plugins)
            if (!isset($adminSet[$seg]) && !isset($plugSet[$seg])) {
                $pKey = $this->findParentKeyBySegment($merged, $seg);
                if ($pKey === null) continue;

                if ($row->visible !== null) {
                    if ((int)$row->visible === 0) { unset($merged[$pKey]); continue; }
                    if ((int)$row->visible === 1) { $merged[$pKey]['permission'] = null; }
                }

                if ($row->name_override !== null)  $merged[$pKey]['name']  = $row->name_override;
                if ($row->label_override !== null) $merged[$pKey]['label'] = $row->label_override;
                if ($row->icon !== null)           $merged[$pKey]['icon']  = $row->icon;
                if ($row->route !== null)          $merged[$pKey]['route'] = $row->route;
                if ($row->permission !== null)     $merged[$pKey]['permission'] = $row->permission;
                continue;
            }

            // Admin parents: skip (user can target 'administration' directly)
            if (isset($adminSet[$seg])) continue;

            // Plugins: override the corresponding sub-entry
            if (isset($plugSet[$seg])) {
                if (empty($merged[self::SEG_PLUGS]['entries']) || !is_array($merged[self::SEG_PLUGS]['entries'])) continue;
                $idx = $this->findPluginsSubIndex($merged[self::SEG_PLUGS]['entries'], $seg);
                if ($idx === null) continue;

                if ($row->visible !== null) {
                    if ((int)$row->visible === 0) { array_splice($merged[self::SEG_PLUGS]['entries'], $idx, 1); continue; }
                    if ((int)$row->visible === 1) { $merged[self::SEG_PLUGS]['entries'][$idx]['permission'] = null; }
                }

                if ($row->name_override !== null)  $merged[self::SEG_PLUGS]['entries'][$idx]['name']  = $row->name_override;
                if ($row->label_override !== null) $merged[self::SEG_PLUGS]['entries'][$idx]['label'] = $row->label_override;
                if ($row->icon !== null)           $merged[self::SEG_PLUGS]['entries'][$idx]['icon']  = $row->icon;
                if ($row->route !== null)          $merged[self::SEG_PLUGS]['entries'][$idx]['route'] = $row->route;
                if ($row->permission !== null)     $merged[self::SEG_PLUGS]['entries'][$idx]['permission'] = $row->permission;
                continue;
            }
        }

        // ----- Child rows (parent != NULL)
        foreach ($dbRows as $row) {
            if (is_null($row->parent)) continue;

            $srcSeg = $parentSegById[$row->parent] ?? $row->route_segment;
            if (!$srcSeg) continue;

            // Administration (flattened)
            if (isset($adminSet[$srcSeg])) {
                if (empty($merged[self::SEG_ADMIN]['entries'])) continue;
                $entries = $merged[self::SEG_ADMIN]['entries'];
                $idx = $this->findChildIndex($entries, $row->route, $row->name);
                if ($idx === null) continue;

                if ($row->visible !== null) {
                    if ((int)$row->visible === 0) { array_splice($entries, $idx, 1); $merged[self::SEG_ADMIN]['entries'] = $entries; continue; }
                    if ((int)$row->visible === 1) { $entries[$idx]['permission'] = null; }
                }

                if ($row->name_override !== null)  $entries[$idx]['name']  = $row->name_override;
                if ($row->label_override !== null) $entries[$idx]['label'] = $row->label_override;
                if ($row->icon !== null)           $entries[$idx]['icon']  = $row->icon;
                if ($row->route !== null)          $entries[$idx]['route'] = $row->route;
                if ($row->permission !== null)     $entries[$idx]['permission'] = $row->permission;

                $merged[self::SEG_ADMIN]['entries'] = $entries;
                continue;
            }

            // Plugins (nested under sub-entry)
            if (isset($plugSet[$srcSeg])) {
                if (empty($merged[self::SEG_PLUGS]['entries']) || !is_array($merged[self::SEG_PLUGS]['entries'])) continue;
                $pIdx = $this->findPluginsSubIndex($merged[self::SEG_PLUGS]['entries'], $srcSeg);
                if ($pIdx === null) continue;

                $children = $merged[self::SEG_PLUGS]['entries'][$pIdx]['entries'] ?? [];
                if (empty($children) || !is_array($children)) continue;

                $idx = $this->findChildIndex($children, $row->route, $row->name);
                if ($idx === null) continue;

                if ($row->visible !== null) {
                    if ((int)$row->visible === 0) { array_splice($children, $idx, 1); $merged[self::SEG_PLUGS]['entries'][$pIdx]['entries'] = $children; continue; }
                    if ((int)$row->visible === 1) { $children[$idx]['permission'] = null; }
                }

                if ($row->name_override !== null)  $children[$idx]['name']  = $row->name_override;
                if ($row->label_override !== null) $children[$idx]['label'] = $row->label_override;
                if ($row->icon !== null)           $children[$idx]['icon']  = $row->icon;
                if ($row->route !== null)          $children[$idx]['route'] = $row->route;
                if ($row->permission !== null)     $children[$idx]['permission'] = $row->permission;

                $merged[self::SEG_PLUGS]['entries'][$pIdx]['entries'] = $children;
                continue;
            }

            // Kept parents
            $pKey = $this->findParentKeyBySegment($merged, $srcSeg);
            if ($pKey === null) continue;
            if (empty($merged[$pKey]['entries']) || !is_array($merged[$pKey]['entries'])) continue;

            $entries = array_values(array_filter($merged[$pKey]['entries'], 'is_array'));
            $idx     = $this->findChildIndex($entries, $row->route, $row->name);
            if ($idx === null) continue;

            if ($row->visible !== null) {
                if ((int)$row->visible === 0) { array_splice($entries, $idx, 1); $merged[$pKey]['entries'] = $entries; continue; }
                if ((int)$row->visible === 1) { $entries[$idx]['permission'] = null; }
            }
            if ($row->name_override !== null)  $entries[$idx]['name']  = $row->name_override;
            if ($row->label_override !== null) $entries[$idx]['label'] = $row->label_override;
            if ($row->icon !== null)           $entries[$idx]['icon']  = $row->icon;
            if ($row->route !== null)          $entries[$idx]['route'] = $row->route;
            if ($row->permission !== null)     $entries[$idx]['permission'] = $row->permission;

            $merged[$pKey]['entries'] = $entries;
        }

        return $merged;
    }

    /**
     * Apply reorders for parents and children (handles Tools pinning and Plugins suborders).
     */
    private function applyReorders(array $merged, array $baseNative, Collection $dbRows, array $maps, array $parentSegById): array
    {
        // Build segment of each base key
        $segOfKeyBase = [];
        foreach ($baseNative as $k => $p) $segOfKeyBase[$k] = $p['route_segment'] ?? $k;

        $adminSet = array_flip($maps['admin_segments'] ?? []);
        $plugSet  = array_flip($maps['plugin_segments'] ?? []);

        $parentOrderBySeg   = [];
        $childOrdersBySeg   = [];
        $pluginsSubOrder    = [];
        $childOrdersPlugins = [];

        foreach ($dbRows as $r) {
            if (!isset($r->order)) continue;

            if (is_null($r->parent)) {
                $seg = $r->route_segment ?? null;
                if (!$seg) continue;
                if (isset($plugSet[$seg])) {
                    $pluginsSubOrder[$seg] = (int)$r->order;
                    continue;
                }
                $parentOrderBySeg[$seg] = (int)$r->order;
                continue;
            }

            $seg = $parentSegById[$r->parent] ?? null;
            if (!$seg) continue;

            if (isset($adminSet[$seg])) {
                $childOrdersBySeg[self::SEG_ADMIN][] = [
                    'route' => $r->route, 'name' => $r->name, 'order' => (int)$r->order,
                ];
                continue;
            }
            if (isset($plugSet[$seg])) {
                $childOrdersPlugins[$seg][] = [
                    'route' => $r->route, 'name' => $r->name, 'order' => (int)$r->order,
                ];
                continue;
            }
            $childOrdersBySeg[$seg][] = [
                'route' => $r->route, 'name' => $r->name, 'order' => (int)$r->order,
            ];
        }

        // Parent reorder mapping (by segment -> key)
        $keyBySeg = [];
        foreach ($baseNative as $k => $p) $keyBySeg[$segOfKeyBase[$k]] = $k;

        $parentOrderByKey = [];
        foreach ($parentOrderBySeg as $seg => $pos) {
            if (isset($keyBySeg[$seg])) $parentOrderByKey[$keyBySeg[$seg]] = (int)$pos;
        }

        $baseParentKeys   = array_keys($baseNative);
        $mergedParentKeys = $this->reposition($baseParentKeys, $parentOrderByKey);

        $mergedInOrder = [];
        foreach ($mergedParentKeys as $k) if (array_key_exists($k, $merged)) $mergedInOrder[$k] = $merged[$k];

        // Children reorders (with Tools special handling)
        foreach ($mergedInOrder as $pKey => &$parent) {
            if (empty($parent['entries']) || !is_array($parent['entries'])) continue;

            // Default alpha
            $this->sortChildrenAlpha($parent);

            // Keep Tools custom block pinned at top
            if ($pKey === self::SEG_TOOLS) {
                $parent['entries'] = $this->toolsKeepCustomBlockFirst($parent['entries']);
            }

            // Apply child reorders
            $seg = $segOfKeyBase[$pKey] ?? ($parent['route_segment'] ?? $pKey);
            $orders = $childOrdersBySeg[$seg] ?? [];

            if (!empty($orders)) {
                if ($pKey === self::SEG_TOOLS) {
                    $parent['entries'] = $this->reorderToolsNonCustomOnly($parent['entries'], $orders);
                } else {
                    $entries = $parent['entries'];
                    $indexByRoute = []; $indexByName = [];
                    foreach ($entries as $idx => $e) {
                        if (!empty($e['route'])) $indexByRoute[$e['route']] = $idx;
                        if (!empty($e['name']))  $indexByName[$e['name']]   = $idx;
                    }
                    usort($orders, fn($a,$b) => ($a['order'] ?? 0) <=> ($b['order'] ?? 0));
                    foreach ($orders as $req) {
                        $keyIdx = null;
                        if (!empty($req['route']) && isset($indexByRoute[$req['route']])) $keyIdx = $indexByRoute[$req['route']];
                        elseif (!empty($req['name']) && isset($indexByName[$req['name']])) $keyIdx = $indexByName[$req['name']];
                        if ($keyIdx === null) continue;

                        $item = $entries[$keyIdx];
                        array_splice($entries, $keyIdx, 1);

                        $insertAt = max(0, min((int)$req['order'] - 1, count($entries)));
                        array_splice($entries, $insertAt, 0, [$item]);

                        // rebuild indices
                        $indexByRoute = $indexByName = [];
                        foreach ($entries as $i => $e) {
                            if (!empty($e['route'])) $indexByRoute[$e['route']] = $i;
                            if (!empty($e['name']))  $indexByName[$e['name']]   = $i;
                        }
                    }
                    $parent['entries'] = $entries;
                }
            }

            // Plugins: sub-segment ordering + nested child reorders
            if ($pKey === self::SEG_PLUGS) {
                if (!empty($pluginsSubOrder)) {
                    $subSegKeys = [];
                    foreach ($parent['entries'] as $e) {
                        if (is_array($e) && isset($e['_osmm_subsegment'])) $subSegKeys[] = $e['_osmm_subsegment'];
                    }
                    $reorderedSubSegs = $this->reposition($subSegKeys, $pluginsSubOrder);
                    $bySeg = [];
                    foreach ($parent['entries'] as $e) {
                        $ss = $e['_osmm_subsegment'] ?? null;
                        if ($ss) $bySeg[$ss] = $e;
                    }
                    $newSubEntries = [];
                    foreach ($reorderedSubSegs as $ss) if (isset($bySeg[$ss])) $newSubEntries[] = $bySeg[$ss];
                    $parent['entries'] = $newSubEntries;
                }
                if (!empty($parent['entries'])) {
                    foreach ($parent['entries'] as &$sub) {
                        $ss = $sub['_osmm_subsegment'] ?? null;
                        if (!$ss || empty($childOrdersPlugins[$ss]) || empty($sub['entries'])) continue;

                        $entries = $sub['entries'];
                        $indexByRoute = $indexByName = [];
                        foreach ($entries as $idx => $e) {
                            if (!empty($e['route'])) $indexByRoute[$e['route']] = $idx;
                            if (!empty($e['name']))  $indexByName[$e['name']]   = $idx;
                        }
                        $orders = $childOrdersPlugins[$ss];
                        usort($orders, fn($a,$b) => ($a['order'] ?? 0) <=> ($b['order'] ?? 0));
                        foreach ($orders as $req) {
                            $keyIdx = null;
                            if (!empty($req['route']) && isset($indexByRoute[$req['route']])) $keyIdx = $indexByRoute[$req['route']];
                            elseif (!empty($req['name']) && isset($indexByName[$req['name']])) $keyIdx = $indexByName[$req['name']];
                            if ($keyIdx === null) continue;

                            $item = $entries[$keyIdx];
                            array_splice($entries, $keyIdx, 1);

                            $insertAt = max(0, min((int)$req['order'] - 1, count($entries)));
                            array_splice($entries, $insertAt, 0, [$item]);

                            $indexByRoute = $indexByName = [];
                            foreach ($entries as $i => $e) {
                                if (!empty($e['route'])) $indexByRoute[$e['route']] = $i;
                                if (!empty($e['name']))  $indexByName[$e['name']]   = $i;
                            }
                        }
                        $sub['entries'] = $entries;
                    }
                    unset($sub);
                }
            }
        }
        unset($parent);

        return $mergedInOrder;
    }

    /* ==================== Tools helpers ==================== */

    /**
     * Keep custom block (flag _osmm_custom) at top, single divider, others alpha.
     */
    protected function toolsKeepCustomBlockFirst(array $entries): array
    {
        $custom = [];
        $others = [];
        foreach ($entries as $e) {
            if (!is_array($e)) continue;
            if (!empty($e['_osmm_custom'])) continue; // will rebuild block from inject step
            if (!empty($e['divider'])) continue;      // skip; we’ll add single divider later
            $others[] = $e;
        }

        // Sort others alpha
        $parent = ['entries' => $others];
        $this->sortChildrenAlpha($parent);
        $others = $parent['entries'];

        // Detect whether a custom block exists by presence of any _osmm_custom in original list
        $hasCustom = false;
        foreach ($entries as $e) {
            if (!empty($e['_osmm_custom'])) { $hasCustom = true; break; }
        }

        if ($hasCustom) {
            // We assume injectCustomLinks already placed the proper custom block
            // at the top of the list earlier. Here we just retain divider placement.
            // Recompose: custom block (from entries), a divider, others.

            $rebuiltCustom = [];
            foreach ($entries as $e) {
                if (!empty($e['_osmm_custom'])) $rebuiltCustom[] = $e;
            }
            if (!empty($rebuiltCustom)) {
                return array_merge($rebuiltCustom, [['divider' => true]], $others);
            }
        }

        return $others;
    }

    /**
     * Apply DB reorders to Tools **only within the non-custom portion**.
     */
    protected function reorderToolsNonCustomOnly(array $entries, array $requests): array
    {
        // Split custom block + divider from others
        $customBlock = [];
        $others      = [];
        $seenDivider = false;

        foreach ($entries as $e) {
            if (!is_array($e)) continue;
            if (!$seenDivider && !empty($e['_osmm_custom'])) {
                $customBlock[] = $e;
                continue;
            }
            if (!$seenDivider && !empty($e['divider'])) {
                $seenDivider = true;
                continue; // we’ll re-add a single divider later
            }
            $others[] = $e;
        }

        // Build indices for others only
        $indexByRoute = $indexByName = [];
        foreach ($others as $idx => $e) {
            if (!empty($e['route'])) $indexByRoute[$e['route']] = $idx;
            if (!empty($e['name']))  $indexByName[$e['name']]   = $idx;
        }

        usort($requests, fn($a,$b) => ($a['order'] ?? 0) <=> ($b['order'] ?? 0));

        foreach ($requests as $req) {
            $keyIdx = null;
            if (!empty($req['route']) && isset($indexByRoute[$req['route']])) {
                $keyIdx = $indexByRoute[$req['route']];
            } elseif (!empty($req['name']) && isset($indexByName[$req['name']])) {
                $keyIdx = $indexByName[$req['name']];
            }
            if ($keyIdx === null) continue;

            $item = $others[$keyIdx];
            array_splice($others, $keyIdx, 1);

            $insertAt = max(0, min((int)$req['order'] - 1, count($others)));
            array_splice($others, $insertAt, 0, [$item]);

            // rebuild indices
            $indexByRoute = $indexByName = [];
            foreach ($others as $i => $e) {
                if (!empty($e['route'])) $indexByRoute[$e['route']] = $i;
                if (!empty($e['name']))  $indexByName[$e['name']]   = $i;
            }
        }

        // Rebuild: custom..., divider, others...
        return !empty($customBlock)
            ? array_merge($customBlock, [['divider' => true]], $others)
            : $others;
    }

    /* ==================== Option builders & JSON helpers ==================== */

    protected function collectPermissionOptionsFrom(array $native, Collection $dbRows): array
    {
        $fromConfig = collect($native)
            ->flatMap(function ($p) {
                return array_filter([
                    $p['permission'] ?? null,
                    ...collect($p['entries'] ?? [])->pluck('permission')->all(),
                ]);
            })->all();

        $fromDb = $dbRows->pluck('permission')->filter()->unique()->values()->all();

        $fromPermsTable = [];
        if (Schema::hasTable('permissions')) {
            $fromPermsTable = DB::table('permissions')->pluck('title')->all();
        }

        return collect([$fromConfig, $fromDb, $fromPermsTable])
            ->flatten()->filter()->unique()->sort()->values()->all();
    }

    /** Build overrides as config-like shape (kept for JSON/debug) */
    protected function buildDbOverrides(): array
    {
        $rows = $this->fetchMenuRows();

        $parents = $rows->whereNull('parent')->values();
        $childByParent = $rows->whereNotNull('parent')->groupBy('parent');

        $out = [];
        foreach ($parents as $p) {
            $key = $p->route_segment ?: $p->name;
            if (!$key) continue;

            $entry = [
                'name'          => $p->name,
                'icon'          => $p->icon,
                'route_segment' => $p->route_segment,
                'route'         => $p->route,
                'permission'    => $p->permission,
            ];

            $kids = [];
            foreach (($childByParent[$p->id] ?? collect()) as $c) {
                $kids[] = [
                    'name'       => $c->name,
                    'icon'       => $c->icon,
                    'route'      => $c->route,
                    'permission' => $c->permission,
                ];
            }
            if ($kids) $entry['entries'] = $kids;

            $out[$key] = $entry;
        }
        return $out;
    }

    protected function buildMenuCatalog(array $baseNative): array
    {
        $catalog = [];

        foreach ($baseNative as $key => $p) {
            if (!is_array($p)) continue;
            $seg = $p['route_segment'] ?? $key;

            $parent = [
                'key'         => $key,
                'segment'     => $seg,
                'name'        => $p['name'] ?? $key,
                'label'       => __($p['label'] ?? $p['name'] ?? $key),
                'route'       => $p['route'] ?? null,
                'icon'        => $p['icon'] ?? null,
                'permission'  => $p['permission'] ?? null,
            ];

            $children = [];
            $routes   = [];
            if (!empty($p['entries']) && is_array($p['entries'])) {
                if ($seg === self::SEG_PLUGS) {
                    foreach ($p['entries'] as $sub) {
                        if (!is_array($sub)) continue;
                        $prefix = ($sub['name'] ?? $sub['_osmm_subsegment'] ?? 'Plugin');
                        if (!empty($sub['entries']) && is_array($sub['entries'])) {
                            foreach ($sub['entries'] as $gc) {
                                if (!is_array($gc)) continue;
                                $label = __($gc['label'] ?? $gc['name'] ?? '');
                                $children[] = [
                                    'segment'    => $seg,
                                    'name'       => $gc['name'] ?? null,
                                    'label'      => $prefix . ' > ' . $label,
                                    'route'      => $gc['route'] ?? null,
                                    'icon'       => $gc['icon'] ?? null,
                                    'permission' => $gc['permission'] ?? null,
                                ];
                                if (!empty($gc['route'])) $routes[$gc['route']] = $gc['route'];
                            }
                        } else {
                            $label = __($sub['label'] ?? $sub['name'] ?? '');
                            $children[] = [
                                'segment'    => $seg,
                                'name'       => $sub['name'] ?? null,
                                'label'      => $prefix,
                                'route'      => $sub['route'] ?? null,
                                'icon'       => $sub['icon'] ?? null,
                                'permission' => $sub['permission'] ?? null,
                            ];
                            if (!empty($sub['route'])) $routes[$sub['route']] = $sub['route'];
                        }
                    }
                } else {
                    foreach ($p['entries'] as $c) {
                        if (!is_array($c)) continue;
                        $children[] = [
                            'segment'    => $seg,
                            'name'       => $c['name'] ?? null,
                            'label'      => __($c['label'] ?? $c['name'] ?? ''),
                            'route'      => $c['route'] ?? null,
                            'icon'       => $c['icon'] ?? null,
                            'permission' => $c['permission'] ?? null,
                        ];
                        if (!empty($c['route'])) $routes[$c['route']] = $c['route'];
                    }
                }
            }

            $catalog[$seg] = [
                'parent'   => $parent,
                'children' => $children,
                'routes'   => array_values($routes),
            ];
        }

        return $catalog;
    }

    /* ==================== Low-level utilities ==================== */

    /** Normalize request fields and visible flag. */
    private function normalizeCrud(array $data, array $keys = ['name_override','label_override','icon','route','permission','order','visible']): array
    {
        foreach ($keys as $k) {
            if (array_key_exists($k, $data) && $data[$k] === '') $data[$k] = null;
        }
        if (array_key_exists('visible', $data)) {
            $data['visible'] = ($data['visible'] === '' || $data['visible'] === null)
                ? null
                : (int) $data['visible'];
        }
        return $data;
    }

    /** Forget all related caches. */
    private function forgetMenuCaches(): void
    {
        Cache::forget(self::CACHE_ROWS);
        Cache::forget(self::CACHE_MERGED);
    }

    /** Find the parent key in the menu by its route_segment (falls back to key). */
    protected function findParentKeyBySegment(array $menu, string $segment): ?string
    {
        foreach ($menu as $k => $p) {
            $seg = $p['route_segment'] ?? $k;
            if ($seg === $segment) return $k;
        }
        return null;
    }

    /** Find child index by route (preferred) or name. */
    protected function findChildIndex(array $entries, ?string $route, ?string $name): ?int
    {
        if ($route) {
            foreach ($entries as $i => $e) {
                if (!empty($e['route']) && $e['route'] === $route) return $i;
            }
        }
        if ($name) {
            foreach ($entries as $i => $e) {
                if (!empty($e['name']) && $e['name'] === $name) return $i;
            }
        }
        return null;
    }

    /** Child alpha sort helper (mutates $parent). */
    private function sortChildrenAlpha(array &$parent): void
    {
        if (empty($parent['entries']) || !is_array($parent['entries'])) return;
        $entries = array_values(array_filter($parent['entries'], 'is_array'));
        usort($entries, function($a,$b){
            return strnatcasecmp(
                mb_strtolower($this->labelOf($a, $a['name'] ?? '')),
                mb_strtolower($this->labelOf($b, $b['name'] ?? ''))
            );
        });
        $parent['entries'] = $entries;
    }

    /** Label utility with translation. */
    private function labelOf(array $item, string $fallback = ''): string
    {
        $label = $item['label'] ?? $item['name'] ?? $fallback;
        return (string) __($label);
    }

    /** Numeric prefix ordering used by SeAT-like menus (default weight 1000). */
    private function numericPrefixWeight(string $key): int
    {
        return preg_match('/^\d+/', $key, $m) ? (int) $m[0] : 1000;
    }

    /** Generic array re-position utility using 1-based positions. */
    private function reposition(array $keys, array $orderMap): array
    {
        $indexed = array_values($keys);
        $pairs = [];
        foreach ($orderMap as $k => $pos) $pairs[] = [$k, max(1,(int)$pos)];
        usort($pairs, fn($a,$b) => $a[1] <=> $b[1]);
        foreach ($pairs as [$key, $pos]) {
            $i = array_search($key, $indexed, true);
            if ($i === false) continue;
            array_splice($indexed, $i, 1);
            $insertAt = min(max($pos-1, 0), count($indexed));
            array_splice($indexed, $insertAt, 0, [$key]);
        }
        return $indexed;
    }

    /** Find index of a Plugins sub-entry by its original segment tag. */
    protected function findPluginsSubIndex(array $pluginsEntries, string $subseg): ?int
    {
        foreach ($pluginsEntries as $i => $e) {
            if (is_array($e) && ($e['_osmm_subsegment'] ?? null) === $subseg) return $i;
        }
        return null;
    }

    public function saveMenuOverride(Request $request)
    {
        // Accept strings from the hidden field OR numeric values
        $val = $request->input('osmm_menu_mode');

        // Normalize to 1..3 (1=off, 2=sidebar, 3=topbar). Treat "0" as "off".
        $map = [
            '0' => 1, 0 => 1, 'off' => 1, '1' => 1, 1 => 1,
            '2' => 2, 2 => 2, 'sidebar' => 2,
            '3' => 3, 3 => 3, 'topbar' => 3,
        ];
        $mode = $map[$val] ?? 1;

        // Create or update the key
        OsmmSetting::updateOrCreate(
            ['key' => 'osmm_override_menu'],
            ['value' => $mode]
        );

        return back()->with('status', 'Menu override saved.');
    }

/** Centralized permission check: honors roles, direct grants, Gate. */
private function userHasPermission($user, string $perm): bool
{
    if (!$user) return false;

    // This covers Spatie roles/permissions, SeAT’s ACL wiring, etc.
    if (method_exists($user, 'can') && $user->can($perm)) {
        return true;
    }

    // Fallback to Gate just in case
    try {
        return Gate::forUser($user)->allows($perm);
    } catch (\Throwable $e) {
        return false;
    }
}

/** Is a single node visible to the user? */
private function entryVisible(array $e, $user): bool
{
    // Single permission
    if (!empty($e['permission']) && is_string($e['permission'])) {
        return $this->userHasPermission($user, $e['permission']);
    }

    // Any-of list (if you use it)
    if (!empty($e['permissions']) && is_array($e['permissions'])) {
        foreach ($e['permissions'] as $perm) {
            if ($this->userHasPermission($user, (string) $perm)) return true;
        }
        return false;
    }

    // No permission key => public
    return true;
}

/**
 * Recursively prune by auth.
 * Keep a parent if EITHER:
 *  - the parent itself is visible, OR
 *  - it has at least one visible child (we’ll strip its route to make it a pure group).
 */
private function pruneByAuth(array $nodes, $user): array
{
    $out = [];

    foreach ($nodes as $key => $node) {
        $children = is_array($node['entries'] ?? null) ? $node['entries'] : [];

        // Prune children first
        if (!empty($children)) {
            $children = $this->pruneByAuth($children, $user);
        }

        $node['entries'] = $children;

        $selfVisible   = $this->entryVisible($node, $user);
        $hasVisibleKids = !empty($children);

        if ($selfVisible || $hasVisibleKids) {
            // If parent isn’t allowed but has visible kids, remove its route so it’s just a group header.
            if (!$selfVisible && isset($node['route'])) {
                unset($node['route']);
            }
            $out[$key] = $node;
        }
    }

    return $out;
}


}
