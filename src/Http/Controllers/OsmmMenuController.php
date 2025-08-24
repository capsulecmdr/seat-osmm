<?php

namespace CapsuleCmdr\SeatOsmm\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class OsmmMenuController extends Controller
{
    /** CONFIG PAGE: side-by-side tree view (native vs merged) + CRUD tools */
    public function index()
    {
        // 1) Load sources ---------------------------------------------------------
        $native = config('package.sidebar') ?? [];

        // Raw DB rows (for center list + explicit order)
        $dbRowsCol = DB::table('osmm_menu_items')
            ->select(
                'id','parent','order','name',
                'name_override','label_override', // << include overrides
                'icon','route_segment','route','permission',
                'visible',
                'created_at','updated_at'
            )
            ->orderBy('parent')
            ->orderBy('order')
            ->get();

        // Normalize defensively (avoid array/stdClass mix)
        $dbRowsCol = collect($dbRowsCol)->map(fn($r) => is_array($r) ? (object)$r : $r);

        // Apply DB overrides directly from rows (do NOT pass buildDbOverrides here)
        $merged = $this->applyOverrides($native, $dbRowsCol);

        // 2) Sort Native like SeAT ------------------------------------------------
        $labelOf = function(array $item, string $fallback = ''): string {
            $label = $item['label'] ?? $item['name'] ?? $fallback;
            try { $label = __($label); } catch (\Throwable $e) {}
            return (string) $label;
        };
        $numericPrefixWeight = function(string $key): int {
            return preg_match('/^\d+/', $key, $m) ? (int) $m[0] : 1000;
        };
        $sortChildrenAlpha = function(array &$parent) use ($labelOf) {
            if (empty($parent['entries']) || !is_array($parent['entries'])) return;
            $entries = [];
            foreach ($parent['entries'] as $e) if (is_array($e)) $entries[] = $e;
            usort($entries, function($a,$b) use ($labelOf){
                return strnatcasecmp(
                    mb_strtolower($labelOf($a, $a['name'] ?? '')),
                    mb_strtolower($labelOf($b, $b['name'] ?? ''))
                );
            });
            $parent['entries'] = $entries;
        };
        $nativeSorted = $native;
        // parents
        uksort($nativeSorted, function($aKey,$bKey) use ($nativeSorted,$labelOf,$numericPrefixWeight){
            $a = $nativeSorted[$aKey]; $b = $nativeSorted[$bKey];
            $aW = $numericPrefixWeight($aKey); $bW = $numericPrefixWeight($bKey);
            if ($aW !== $bW) return $aW <=> $bW;
            return strnatcasecmp(
                mb_strtolower($labelOf($a,$aKey)),
                mb_strtolower($labelOf($b,$bKey))
            );
        });
        // children (alpha)
        foreach ($nativeSorted as &$p) $sortChildrenAlpha($p);
        unset($p);

        // 3) Prepare helpers for explicit reordering ------------------------------
        $segOfKey = [];
        foreach ($nativeSorted as $k => $p) $segOfKey[$k] = $p['route_segment'] ?? $k;

        $parentDbIdBySeg = DB::table('osmm_menu_items')
            ->whereNull('parent')->whereNotNull('route_segment')->pluck('id','route_segment')->all();
        $segByParentDbId = array_flip($parentDbIdBySeg);

        $parentOrderBySeg = [];
        $childOrdersBySeg = [];
        foreach ($dbRowsCol as $r0) {
            $r = is_array($r0) ? (object)$r0 : $r0; // defensive
            if (!isset($r->order)) continue;
            if (is_null($r->parent)) {
                if (!empty($r->route_segment)) $parentOrderBySeg[$r->route_segment] = (int)$r->order;
            } else {
                $seg = $segByParentDbId[$r->parent] ?? null;
                if (!$seg) continue;
                $childOrdersBySeg[$seg] = $childOrdersBySeg[$seg] ?? [];
                $childOrdersBySeg[$seg][] = [
                    'route' => $r->route,
                    'name'  => $r->name,
                    'order' => (int)$r->order,
                ];
            }
        }

        $reposition = function(array $keys, array $orderMap) {
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
        };

        // 4) Build Merged in *native* order first, then apply DB 'order' ----------
        $nativeParentKeys = array_keys($nativeSorted);

        $keyBySeg = [];
        foreach ($nativeSorted as $k => $p) $keyBySeg[$segOfKey[$k]] = $k;

        $parentOrderByKey = [];
        foreach ($parentOrderBySeg as $seg => $pos) {
            if (isset($keyBySeg[$seg])) $parentOrderByKey[$keyBySeg[$seg]] = (int)$pos;
        }

        $mergedParentKeys = $reposition($nativeParentKeys, $parentOrderByKey);

        $mergedInNativeOrder = [];
        foreach ($mergedParentKeys as $k) if (array_key_exists($k, $merged)) $mergedInNativeOrder[$k] = $merged[$k];

        foreach ($mergedInNativeOrder as $pKey => &$parent) {
            $sortChildrenAlpha($parent);

            $seg = $segOfKey[$pKey] ?? ($parent['route_segment'] ?? $pKey);
            if (empty($childOrdersBySeg[$seg]) || empty($parent['entries'])) continue;

            $entries = $parent['entries'];
            $indexByRoute = [];
            $indexByName  = [];
            foreach ($entries as $idx => $e) {
                if (!empty($e['route'])) $indexByRoute[$e['route']] = $idx;
                if (!empty($e['name']))  $indexByName[$e['name']]   = $idx;
            }

            usort($childOrdersBySeg[$seg], fn($a,$b) => $a['order'] <=> $b['order']);

            foreach ($childOrdersBySeg[$seg] as $req) {
                $keyIdx = null;
                if (!empty($req['route']) && isset($indexByRoute[$req['route']])) {
                    $keyIdx = $indexByRoute[$req['route']];
                } elseif (!empty($req['name']) && isset($indexByName[$req['name']])) {
                    $keyIdx = $indexByName[$req['name']];
                }
                if ($keyIdx === null) continue;

                $item = $entries[$keyIdx];
                array_splice($entries, $keyIdx, 1);

                $insertAt = (int)$req['order'] - 1;
                if ($insertAt < 0) $insertAt = 0;
                if ($insertAt > count($entries)) $insertAt = count($entries);

                array_splice($entries, $insertAt, 0, [$item]);

                $indexByRoute = $indexByName = [];
                foreach ($entries as $idx => $e) {
                    if (!empty($e['route'])) $indexByRoute[$e['route']] = $idx;
                    if (!empty($e['name']))  $indexByName[$e['name']]   = $idx;
                }
            }

            $parent['entries'] = $entries;
        }
        unset($parent);

        $mergedSorted = $mergedInNativeOrder;

        // 5) Dropdown data --------------------------------------------------------
        $parentOptions = collect($nativeSorted)->map(function ($v, $k) {
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

        $allPermissions = Cache::remember('osmm_permission_options', 300, fn() => $this->collectPermissionOptions());
        $routeSegments  = Cache::remember('osmm_route_segment_options', 300, fn() => $this->collectRouteSegmentOptions());

        $menuCatalog = $this->buildMenuCatalog($nativeSorted);

        // 6) Permission checker for rendering sidebars ---------------------------
        $can = fn ($perm) => empty($perm) || (\auth()->check() && \auth()->user()->can($perm));

        // 7) Render ---------------------------------------------------------------
        return view('seat-osmm::menu.index', [
            'native'         => $nativeSorted,
            'merged'         => $mergedSorted,
            'dbRows'         => $dbRowsCol,
            'parentOptions'  => $parentOptions,
            'allPermissions' => $allPermissions,
            'routeSegments'  => $routeSegments,
            'can'            => $can,
            'menuCatalog'    => $menuCatalog,
        ]);
    }

    protected function collectPermissionOptions(): array
    {
        $fromConfig = collect(config('package.sidebar') ?? [])
            ->flatMap(function ($p) {
                return array_filter([
                    $p['permission'] ?? null,
                    ...collect($p['entries'] ?? [])->pluck('permission')->all(),
                ]);
            })->all();

        $fromDb = DB::table('osmm_menu_items')
            ->whereNotNull('permission')->distinct()->pluck('permission')->all();

        $fromPermsTable = [];
        if (Schema::hasTable('permissions')) {
            $fromPermsTable = DB::table('permissions')->pluck('title')->all();
        }

        return collect([$fromConfig, $fromDb, $fromPermsTable])
            ->flatten()->filter()->unique()->sort()->values()->all();
    }

    protected function collectRouteSegmentOptions(): array
    {
        $native = config('package.sidebar') ?? [];

        $fromConfig = collect($native)->map(function ($v, $k) {
            $seg = $v['route_segment'] ?? $k;
            return [
                'value' => $seg,
                'label' => ($v['name'] ?? $k) . " [{$seg}]",
            ];
        });

        $fromDb = DB::table('osmm_menu_items')
            ->whereNull('parent')
            ->whereNotNull('route_segment')
            ->distinct()
            ->pluck('route_segment')
            ->map(fn($seg) => ['value' => $seg, 'label' => $seg]);

        return $fromConfig
            ->merge($fromDb)
            ->filter(fn($o) => filled($o['value']))
            ->unique('value')
            ->sortBy('label', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }

    /** API: merged menu as JSON for app consumption */
    public function jsonMerged()
    {
        return response()->json($this->menu());
    }

    /** API: native package.sidebar as JSON */
    public function jsonNative()
    {
        return response()->json(config('package.sidebar') ?? []);
    }

    /** API: current overrides as JSON (config-shaped) */
    public function jsonOverrides()
    {
        return response()->json($this->buildDbOverrides());
    }

    /** Helper for views: returns merged array */
    public function menu(): array
    {
        $seat     = config('package.sidebar') ?? [];
        $dbRowsCol = DB::table('osmm_menu_items')->get();
        $dbRowsCol = collect($dbRowsCol)->map(fn($r) => is_array($r) ? (object)$r : $r);
        return $this->applyOverrides($seat, $dbRowsCol);
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
        // allow both string and int versions of 0/1
        'visible'        => ['nullable', Rule::in([0,1,'0','1'])],
        'order'          => 'nullable|integer|min:1',
    ]);

    // normalize empties -> null
    foreach (['name_override','label_override','icon','route','permission','order','visible'] as $k) {
        if (array_key_exists($k, $data) && $data[$k] === '') $data[$k] = null;
    }
    // coerce visible to int 0/1 or null
    if (array_key_exists('visible', $data)) {
        $data['visible'] = ($data['visible'] === '' || $data['visible'] === null)
            ? null
            : (int) $data['visible'];
    }

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
        // ensure one parent row per segment
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

    Cache::forget('osmm_menu_rows');
    return back()->with('status', 'Parent override saved.');
}


    public function upsertChild(Request $request)
{
    $data = $request->validate([
        'id'             => 'nullable|integer|exists:osmm_menu_items,id',
        'parent_id'      => 'nullable|integer|exists:osmm_menu_items,id',
        'route_segment'  => 'nullable|string|max:150', // used to resolve/create parent if parent_id missing
        'target_route'   => 'nullable|string|max:190', // identity of native child (match primary)
        'target_name'    => 'nullable|string|max:150', // identity fallback for route-less children
        'name_override'  => 'nullable|string|max:150',
        'label_override' => 'nullable|string|max:190',
        'icon'           => 'nullable|string|max:150',
        'route'          => 'nullable|string|max:190', // override route (can differ from target_route)
        'permission'     => 'nullable|string|max:190',
        // allow both string and int versions of 0/1
        'visible'        => ['nullable', Rule::in([0,1,'0','1'])],
        'order'          => 'nullable|integer|min:1',
    ]);

    // normalize empties -> null
    foreach (['name_override','label_override','icon','route','permission','order','visible'] as $k) {
        if (array_key_exists($k, $data) && $data[$k] === '') $data[$k] = null;
    }
    // coerce visible to int 0/1 or null
    if (array_key_exists('visible', $data)) {
        $data['visible'] = ($data['visible'] === '' || $data['visible'] === null)
            ? null
            : (int) $data['visible'];
    }

    // Resolve parent id (by parent_id or route_segment)
    $parentId = $data['parent_id'] ?? null;
    if (!$parentId && !empty($data['route_segment'])) {
        $parentId = DB::table('osmm_menu_items')
            ->whereNull('parent')
            ->where('route_segment', $data['route_segment'])
            ->value('id');

        // create placeholder parent row if not present yet
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

    // If editing existing row by id
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
        Cache::forget('osmm_menu_rows');
        return back()->with('status', 'Child override saved.');
    }

    // Otherwise, locate an existing child row by identity (target_route / target_name)
    $existingId = null;

    if (!empty($data['target_route'])) {
        $existingId = DB::table('osmm_menu_items')
            ->where('parent', $parentId)
            ->where('route', $data['target_route'])
            ->value('id');
    }

    if (!$existingId && !empty($data['target_name'])) {
        // Route-less child: match by stored identity name
        $existingId = DB::table('osmm_menu_items')
            ->where('parent', $parentId)
            ->whereNull('route')
            ->where('name', $data['target_name'])
            ->value('id');
    }

    if ($existingId) {
        // Update found row
        DB::table('osmm_menu_items')->where('id', $existingId)->update([
            'name_override'  => $data['name_override']  ?? null,
            'label_override' => $data['label_override'] ?? null,
            'icon'           => $data['icon']           ?? null,
            // allow overriding the route (if not provided, keep existing)
            'route'          => $data['route']          ?? DB::raw('`route`'),
            'permission'     => $data['permission']     ?? null,
            'visible'        => $data['visible']        ?? null,
            'order'          => $data['order']          ?? null,
            'updated_at'     => now(),
        ]);
    } else {
        // Create new child override row, binding identity for future matches
        DB::table('osmm_menu_items')->insert([
            'parent'         => $parentId,
            'name'           => $data['target_name']   ?? null,                     // identity fallback
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

    Cache::forget('osmm_menu_rows');
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

        Cache::forget('osmm_menu_rows');
        return back()->with('ok', 'Override deleted.');
    }

    public function resetAll()
    {
        DB::table('osmm_menu_items')->truncate();
        Cache::forget('osmm_menu_rows');
        return back()->with('ok', 'All overrides cleared.');
    }

    /** Build overrides as config-like shape (kept for JSON/debug) */
    protected function buildDbOverrides(): array
    {
        $rows = Cache::remember('osmm_menu_rows', 60, function () {
            return DB::table('osmm_menu_items')
                ->select('id','parent','order','name','icon','route_segment','route','permission')
                ->orderBy('parent')->orderBy('order')->get();
        });

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

    /** Apply DB overrides into native menu (override-only; no new items). */
    protected function applyOverrides(array $native, $dbRowsCol): array
    {
        $merged = $native;

        // ----- Parents
        foreach ($dbRowsCol as $row0) {
            $row = is_array($row0) ? (object)$row0 : $row0;
            if (!is_null($row->parent)) continue;
            $seg = $row->route_segment;
            if (!$seg) continue;

            $key = $this->findParentKeyBySegment($merged, $seg);
            if ($key === null) continue;

            // VISIBILITY override
            if ($row->visible !== null) {
                if ((int)$row->visible === 0) { // force hide
                    unset($merged[$key]);
                    continue; // nothing else to apply
                }
                if ((int)$row->visible === 1) { // force show
                    $merged[$key]['permission'] = null;
                }
            }

            // Display overrides
            if (!is_null($row->name_override))  $merged[$key]['name']  = $row->name_override;
            if (!is_null($row->label_override)) $merged[$key]['label'] = $row->label_override;

            // Other attributes
            if (!is_null($row->icon))           $merged[$key]['icon']  = $row->icon;
            if (!is_null($row->route))          $merged[$key]['route'] = $row->route;
            if (!is_null($row->permission))     $merged[$key]['permission'] = $row->permission;
        }

        // ----- Children
        $segByParentId = [];
        foreach ($dbRowsCol as $r0) {
            $r = is_array($r0) ? (object)$r0 : $r0;
            if (is_null($r->parent)) continue;
            if (!isset($segByParentId[$r->parent])) {
                $segByParentId[$r->parent] = DB::table('osmm_menu_items')
                    ->where('id', $r->parent)->value('route_segment');
            }
        }

        foreach ($dbRowsCol as $row0) {
            $row = is_array($row0) ? (object)$row0 : $row0;
            if (is_null($row->parent)) continue;

            $seg = $segByParentId[$row->parent] ?? $row->route_segment;
            if (!$seg) continue;

            $pKey = $this->findParentKeyBySegment($merged, $seg);
            if ($pKey === null) continue;

            $entries = $merged[$pKey]['entries'] ?? [];
            if (empty($entries) || !is_array($entries)) continue;

            $seq = [];
            foreach ($entries as $e) if (is_array($e)) $seq[] = $e;

            $idx = $this->findChildIndex($seq, $row->route, $row->name);
            if ($idx === null) continue;

            // VISIBILITY override
            if ($row->visible !== null) {
                if ((int)$row->visible === 0) { // force hide
                    array_splice($seq, $idx, 1);
                    $merged[$pKey]['entries'] = $seq;
                    continue; // removed, stop processing this row
                }
                if ((int)$row->visible === 1) { // force show
                    $seq[$idx]['permission'] = null;
                }
            }

            // Display overrides
            if (!is_null($row->name_override))  $seq[$idx]['name']  = $row->name_override;
            if (!is_null($row->label_override)) $seq[$idx]['label'] = $row->label_override;

            // Other attributes
            if (!is_null($row->icon))           $seq[$idx]['icon']  = $row->icon;
            if (!is_null($row->route))          $seq[$idx]['route'] = $row->route;
            if (!is_null($row->permission))     $seq[$idx]['permission'] = $row->permission;

            $merged[$pKey]['entries'] = $seq;
        }

        return $merged;
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

    /** Build child index keyed by route (preferred) or name. */
    protected function indexChildrenByKey(array $entries): array
    {
        $idx = [];
        foreach ($entries as $e) {
            if (!is_array($e)) continue;
            $k = $e['route'] ?? ($e['name'] ?? null);
            if ($k) $idx[$k] = $e;
        }
        return $idx;
    }

    protected function mergeFields(array $base, array $ovr, array $keys): array
    {
        foreach ($keys as $k) {
            if (array_key_exists($k, $ovr) && $ovr[$k] !== null) {
                $base[$k] = $ovr[$k];
            }
        }
        return $base;
    }

    protected function menuLabel(array $item, string $fallback = ''): string
    {
        $label = $item['label'] ?? $item['name'] ?? $fallback;
        try { $label = __($label); } catch (\Throwable $e) {}
        return trim((string) $label);
    }

    protected function keyWeight(string $key): int
    {
        if (preg_match('/^\d+/', $key, $m)) return (int) $m[0];
        return 1000;
    }

    protected function entryWeight(?array $override = null, ?int $fallback = null): int
    {
        if ($override && isset($override['order'])) return (int) $override['order'];
        return $fallback ?? 1000;
    }

    protected function sortLikeSeat(array $menu, array $dbRows = []): array
    {
        $byParent = collect($dbRows)->groupBy(fn($r) => $r['parent'] ?? null);

        uksort($menu, function ($aKey, $bKey) use ($menu, $byParent) {
            $a   = $menu[$aKey];
            $b   = $menu[$bKey];

            $aDb = optional($byParent->get(null))->firstWhere('route_segment', $a['route_segment'] ?? $aKey);
            $bDb = optional($byParent->get(null))->firstWhere('route_segment', $b['route_segment'] ?? $bKey);

            $aW  = $this->entryWeight($aDb, $this->keyWeight($aKey));
            $bW  = $this->entryWeight($bDb, $this->keyWeight($bKey));
            if ($aW !== $bW) return $aW <=> $bW;

            $aL = Str::lower($this->menuLabel($a, $aKey));
            $bL = Str::lower($this->menuLabel($b, $bKey));
            return strnatcasecmp($aL, $bL);
        });

        foreach ($menu as $pKey => &$parent) {
            if (empty($parent['entries'])) continue;

            $entries = [];
            foreach ($parent['entries'] as $e) { $entries[] = $e; }

            $parentDb = $byParent->get($parent['route_segment'] ?? $pKey) ?? collect();

            usort($entries, function ($a, $b) use ($parentDb) {
                $aDb = $parentDb->first(function ($row) use ($a) {
                    return ($row['route'] ?? null) === ($a['route'] ?? null)
                        || ($row['name'] ?? null) === ($a['name'] ?? null);
                });
                $bDb = $parentDb->first(function ($row) use ($b) {
                    return ($row['route'] ?? null) === ($b['route'] ?? null)
                        || ($row['name'] ?? null) === ($b['name'] ?? null);
                });

                $aW = $this->entryWeight($aDb);
                $bW = $this->entryWeight($bDb);
                if ($aW !== $bW) return $aW <=> $bW;

                $aL = Str::lower($this->menuLabel($a));
                $bL = Str::lower($this->menuLabel($b));
                return strnatcasecmp($aL, $bL);
            });

            $parent['entries'] = $entries;
        }
        unset($parent);

        return $menu;
    }

    protected function buildMenuCatalog(array $nativeSorted): array
    {
        $parentIds = DB::table('osmm_menu_items')
            ->whereNull('parent')
            ->whereNotNull('route_segment')
            ->pluck('id', 'route_segment')
            ->all();

        $catalog = [];
        foreach ($nativeSorted as $key => $p) {
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
                'db_parent_id'=> $parentIds[$seg] ?? null,
            ];

            $children = [];
            foreach (($p['entries'] ?? []) as $c) {
                if (!is_array($c)) continue;
                $children[] = [
                    'segment'    => $seg,
                    'name'       => $c['name'] ?? null,
                    'label'      => __($c['label'] ?? $c['name'] ?? ''),
                    'route'      => $c['route'] ?? null,
                    'icon'       => $c['icon'] ?? null,
                    'permission' => $c['permission'] ?? null,
                ];
            }

            $routes = [];
            if (!empty($parent['route'])) $routes[$parent['route']] = $parent['route'];
            foreach ($children as $c) {
                if (!empty($c['route'])) $routes[$c['route']] = $c['route'];
            }

            $catalog[$seg] = [
                'parent'   => $parent,
                'children' => $children,
                'routes'   => array_values($routes),
            ];
        }
        return $catalog;
    }

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
}
