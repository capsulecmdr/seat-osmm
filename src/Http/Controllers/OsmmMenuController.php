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
    // 1) Load raw native config
    $native = config('package.sidebar') ?? [];

    // 2) SeAT-like sort of raw native
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
    uksort($nativeSorted, function($aKey,$bKey) use ($nativeSorted,$labelOf,$numericPrefixWeight){
        $a = $nativeSorted[$aKey]; $b = $nativeSorted[$bKey];
        $aW = $numericPrefixWeight($aKey); $bW = $numericPrefixWeight($bKey);
        if ($aW !== $bW) return $aW <=> $bW;
        return strnatcasecmp(
            mb_strtolower($labelOf($a,$aKey)),
            mb_strtolower($labelOf($b,$bKey))
        );
    });
    foreach ($nativeSorted as &$p) $sortChildrenAlpha($p);
    unset($p);

    // 3) Build BASE-NATIVE (keep + Administration + Plugins), BEFORE overrides
    $base       = $this->buildBaseNative($nativeSorted);
    $baseNative = $base['menu'];
    $maps       = $base['maps']; // ['admin_segments'=>[], 'plugin_segments'=>[]]

    // 4) Load DB rows (include overrides + visible)
    $dbRowsCol = DB::table('osmm_menu_items')
        ->select(
            'id','parent','order','name',
            'name_override','label_override',
            'icon','route_segment','route','permission',
            'visible',
            'created_at','updated_at'
        )
        ->orderBy('parent')->orderBy('order')->get();
    $dbRowsCol = collect($dbRowsCol)->map(fn($r) => is_array($r) ? (object)$r : $r);

    // 5) Apply overrides AFTER consolidation
    $merged = $this->applyOverridesWithConsolidation($baseNative, $dbRowsCol, $maps);

    // 6) Reordering (parents + children)
    // Build seg lookup for base keys
    $segOfKeyBase = [];
    foreach ($baseNative as $k => $p) $segOfKeyBase[$k] = $p['route_segment'] ?? $k;

    // Map DB parent id -> segment for child ordering
    $segByParentDbId = [];
    foreach ($dbRowsCol as $r) {
        if (is_null($r->parent)) continue;
        if (!isset($segByParentDbId[$r->parent])) {
            $segByParentDbId[$r->parent] = DB::table('osmm_menu_items')->where('id',$r->parent)->value('route_segment');
        }
    }

    $parentOrderBySeg   = [];
    $childOrdersBySeg   = []; // keyed by segment ('administration', 'home', 'plugins-subseg', etc.)
    $pluginsSubOrder    = []; // segment -> order (for sub-entries under Plugins)
    $childOrdersPlugins = []; // subsegment -> list of child moves

    foreach ($dbRowsCol as $r) {
        if (!isset($r->order)) continue;

        if (is_null($r->parent)) {
            $seg = $r->route_segment ?? null;
            if (!$seg) continue;

            // If this is a plugin parent, it's a sub-entry order inside Plugins
            if (in_array($seg, $maps['plugin_segments'] ?? [], true)) {
                $pluginsSubOrder[$seg] = (int)$r->order;
                continue;
            }

            // Otherwise: order a top-level section by its segment
            $parentOrderBySeg[$seg] = (int)$r->order;
            continue;
        }

        // Child orders
        $seg = $segByParentDbId[$r->parent] ?? null;
        if (!$seg) continue;

        if (in_array($seg, $maps['admin_segments'] ?? [], true)) {
            $childOrdersBySeg['administration'][] = [
                'route' => $r->route,
                'name'  => $r->name,
                'order' => (int)$r->order,
            ];
            continue;
        }

        if (in_array($seg, $maps['plugin_segments'] ?? [], true)) {
            $childOrdersPlugins[$seg][] = [
                'route' => $r->route,
                'name'  => $r->name,
                'order' => (int)$r->order,
            ];
            continue;
        }

        $childOrdersBySeg[$seg][] = [
            'route' => $r->route,
            'name'  => $r->name,
            'order' => (int)$r->order,
        ];
    }

    // helper: reposition by desired 1-based order
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

    // Parent reordering: translate segment -> key
    $keyBySeg = [];
    foreach ($baseNative as $k => $p) $keyBySeg[$segOfKeyBase[$k]] = $k;

    $parentOrderByKey = [];
    foreach ($parentOrderBySeg as $seg => $pos) {
        if (isset($keyBySeg[$seg])) $parentOrderByKey[$keyBySeg[$seg]] = (int)$pos;
    }

    $baseParentKeys   = array_keys($baseNative);
    $mergedParentKeys = $reposition($baseParentKeys, $parentOrderByKey);

    $mergedInNativeOrder = [];
    foreach ($mergedParentKeys as $k) if (array_key_exists($k, $merged)) $mergedInNativeOrder[$k] = $merged[$k];

    // Children reorders (use SEGMENT to read orders, not the parent key)
    foreach ($mergedInNativeOrder as $pKey => &$parent) {
        if (empty($parent['entries']) || !is_array($parent['entries'])) continue;

        // default child sort alpha
        $sortChildrenAlpha($parent);

        $seg = $segOfKeyBase[$pKey] ?? ($parent['route_segment'] ?? $pKey);
        $orders = $childOrdersBySeg[$seg] ?? [];

        if (!empty($orders)) {
            $entries = $parent['entries'];
            $indexByRoute = $indexByName = [];
            foreach ($entries as $idx => $e) {
                if (!empty($e['route'])) $indexByRoute[$e['route']] = $idx;
                if (!empty($e['name']))  $indexByName[$e['name']]   = $idx;
            }
            usort($orders, fn($a,$b) => $a['order'] <=> $b['order']);
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

        // Plugins special: reorder sub-entries and their children
        if ($pKey === 'plugins') {
            // sub-entries by sub-segment
            if (!empty($pluginsSubOrder)) {
                $subSegKeys = [];
                foreach ($parent['entries'] as $e) {
                    if (is_array($e) && isset($e['_osmm_subsegment'])) $subSegKeys[] = $e['_osmm_subsegment'];
                }
                $reorderedSubSegs = $reposition($subSegKeys, $pluginsSubOrder);

                $bySeg = [];
                foreach ($parent['entries'] as $e) {
                    $ss = $e['_osmm_subsegment'] ?? null;
                    if ($ss) $bySeg[$ss] = $e;
                }
                $newSubEntries = [];
                foreach ($reorderedSubSegs as $ss) if (isset($bySeg[$ss])) $newSubEntries[] = $bySeg[$ss];
                $parent['entries'] = $newSubEntries;
            }

            // nested children within each plugin sub-entry
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
                    usort($orders, fn($a,$b) => $a['order'] <=> $b['order']);
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

    $mergedSorted = $mergedInNativeOrder;

    // 7) Dropdown data from BASE-NATIVE
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

    $allPermissions = Cache::remember('osmm_permission_options', 300, fn() => $this->collectPermissionOptions());
    $routeSegments  = collect($baseNative)->map(function ($v, $k) {
        $seg = $v['route_segment'] ?? $k;
        return ['value' => $seg, 'label' => ($v['name'] ?? $k) . " [{$seg}]"];
    })->values()->all();

    $menuCatalog    = $this->buildMenuCatalog($baseNative);

    $can = fn ($perm) => empty($perm) || (\auth()->check() && \auth()->user()->can($perm));

    return view('seat-osmm::menu.index', [
        'native'         => $baseNative,
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
    // raw config
    $native = config('package.sidebar') ?? [];

    // sort native (same as index)
    $labelOf = function(array $item, string $fallback = ''): string {
        $label = $item['label'] ?? $item['name'] ?? $fallback;
        try { $label = __($label); } catch (\Throwable $e) {}
        return (string) $label;
    };
    $numericPrefixWeight = fn(string $key) => (preg_match('/^\d+/', $key, $m) ? (int)$m[0] : 1000);
    $sortChildrenAlpha   = function(array &$parent) use ($labelOf) {
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
    uksort($nativeSorted, function($aKey,$bKey) use ($nativeSorted,$labelOf,$numericPrefixWeight){
        $a = $nativeSorted[$aKey]; $b = $nativeSorted[$bKey];
        $aW = $numericPrefixWeight($aKey); $bW = $numericPrefixWeight($bKey);
        if ($aW !== $bW) return $aW <=> $bW;
        return strnatcasecmp(
            mb_strtolower($labelOf($a,$aKey)),
            mb_strtolower($labelOf($b,$bKey))
        );
    });
    foreach ($nativeSorted as &$p) $sortChildrenAlpha($p);
    unset($p);

    // base-native consolidation
    $base       = $this->buildBaseNative($nativeSorted);
    $baseNative = $base['menu'];
    $maps       = $base['maps'];

    // DB rows
    $dbRowsCol = DB::table('osmm_menu_items')
        ->select(
            'id','parent','order','name',
            'name_override','label_override',
            'icon','route_segment','route','permission',
            'visible',
            'created_at','updated_at'
        )
        ->orderBy('parent')->orderBy('order')->get();
    $dbRowsCol = collect($dbRowsCol)->map(fn($r) => is_array($r) ? (object)$r : $r);

    // apply overrides
    $merged = $this->applyOverridesWithConsolidation($baseNative, $dbRowsCol, $maps);

    // reorder (same logic as index, but only return final array)
    $segOfKeyBase = [];
    foreach ($baseNative as $k => $p) $segOfKeyBase[$k] = $p['route_segment'] ?? $k;

    $segByParentDbId = [];
    foreach ($dbRowsCol as $r) {
        if (is_null($r->parent)) continue;
        if (!isset($segByParentDbId[$r->parent])) {
            $segByParentDbId[$r->parent] = DB::table('osmm_menu_items')->where('id',$r->parent)->value('route_segment');
        }
    }

    $parentOrderBySeg   = [];
    $childOrdersBySeg   = [];
    $pluginsSubOrder    = [];
    $childOrdersPlugins = [];
    foreach ($dbRowsCol as $r) {
        if (!isset($r->order)) continue;

        if (is_null($r->parent)) {
            $seg = $r->route_segment ?? null;
            if (!$seg) continue;
            if (in_array($seg, $maps['plugin_segments'] ?? [], true)) {
                $pluginsSubOrder[$seg] = (int)$r->order;
                continue;
            }
            $parentOrderBySeg[$seg] = (int)$r->order;
            continue;
        }

        $seg = $segByParentDbId[$r->parent] ?? null;
        if (!$seg) continue;

        if (in_array($seg, $maps['admin_segments'] ?? [], true)) {
            $childOrdersBySeg['administration'][] = ['route'=>$r->route, 'name'=>$r->name, 'order'=>(int)$r->order];
            continue;
        }
        if (in_array($seg, $maps['plugin_segments'] ?? [], true)) {
            $childOrdersPlugins[$seg][] = ['route'=>$r->route, 'name'=>$r->name, 'order'=>(int)$r->order];
            continue;
        }
        $childOrdersBySeg[$seg][] = ['route'=>$r->route, 'name'=>$r->name, 'order'=>(int)$r->order];
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

    $keyBySeg = [];
    foreach ($baseNative as $k => $p) $keyBySeg[$segOfKeyBase[$k]] = $k;

    $parentOrderByKey = [];
    foreach ($parentOrderBySeg as $seg => $pos) {
        if (isset($keyBySeg[$seg])) $parentOrderByKey[$keyBySeg[$seg]] = (int)$pos;
    }

    $baseParentKeys   = array_keys($baseNative);
    $mergedParentKeys = $reposition($baseParentKeys, $parentOrderByKey);

    $mergedInOrder = [];
    foreach ($mergedParentKeys as $k) if (array_key_exists($k, $merged)) $mergedInOrder[$k] = $merged[$k];

    foreach ($mergedInOrder as $pKey => &$parent) {
        if (empty($parent['entries']) || !is_array($parent['entries'])) continue;

        // default alpha
        $entries = $parent['entries'];
        $entries = array_values(array_filter($entries, 'is_array'));
        usort($entries, function($a,$b){
            $al = __($a['label'] ?? $a['name'] ?? '');
            $bl = __($b['label'] ?? $b['name'] ?? '');
            return strnatcasecmp(mb_strtolower($al), mb_strtolower($bl));
        });

        $seg = $segOfKeyBase[$pKey] ?? ($parent['route_segment'] ?? $pKey);
        $orders = $childOrdersBySeg[$seg] ?? [];

        if (!empty($orders)) {
            $indexByRoute = []; $indexByName = [];
            foreach ($entries as $idx => $e) {
                if (!empty($e['route'])) $indexByRoute[$e['route']] = $idx;
                if (!empty($e['name']))  $indexByName[$e['name']]   = $idx;
            }
            usort($orders, fn($a,$b) => $a['order'] <=> $b['order']);
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
        }

        $parent['entries'] = $entries;

        if ($pKey === 'plugins') {
            if (!empty($pluginsSubOrder)) {
                $subSegKeys = [];
                foreach ($parent['entries'] as $e) {
                    if (is_array($e) && isset($e['_osmm_subsegment'])) $subSegKeys[] = $e['_osmm_subsegment'];
                }
                $reorderedSubSegs = $reposition($subSegKeys, $pluginsSubOrder);

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
                    usort($orders, fn($a,$b) => $a['order'] <=> $b['order']);
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

/**
 * Build the "base-native" menu used on the left:
 * - Keep: home, alliances, characters, corporations, tools
 * - Add:  administration = settings[*] + seatapi + notifications[*]
 * - Add:  plugins = every other native parent grouped as multi-level under Plugins
 *
 * Returns: [menu, maps]
 *   maps['admin_segments']   = ['settings','notifications','api-admin']
 *   maps['plugin_segments']  = ['seat-connector','profile', ...]  // everything else not kept/admin
 */
protected function buildBaseNative(array $nativeSorted): array
{
    // key -> segment
    $segOfKey = [];
    foreach ($nativeSorted as $k => $p) $segOfKey[$k] = $p['route_segment'] ?? $k;

    // Keep these segments as-is:
    $keepSegs  = ['home','alliances','characters','corporations','tools'];
    // Admin buckets come from:
    $adminSegs = ['configuration','notifications','api-admin']; // NOTE: 'configuration' (not 'settings')

    // Collect admin entries (flatten)
    $adminEntries = [];

    // settings = 'configuration'
    $settingsKey = array_search('configuration', $segOfKey, true);
    if ($settingsKey !== false && !empty($nativeSorted[$settingsKey]['entries'])) {
        foreach ($nativeSorted[$settingsKey]['entries'] as $c) if (is_array($c)) $adminEntries[] = $c;
    }

    // SeAT API = 'api-admin'
    $seatApiKey = array_search('api-admin', $segOfKey, true);
    if ($seatApiKey !== false) {
        $seatApi = $nativeSorted[$seatApiKey];
        $adminEntries[] = [
            'name'       => $seatApi['name'] ?? 'SeAT API',
            'label'      => $seatApi['label'] ?? ($seatApi['name'] ?? 'SeAT API'),
            'icon'       => $seatApi['icon'] ?? 'fas fa-exchange-alt',
            'route'      => $seatApi['route'] ?? null,
            'permission' => $seatApi['permission'] ?? null,
        ];
    }

    // notifications
    $notifKey = array_search('notifications', $segOfKey, true);
    if ($notifKey !== false && !empty($nativeSorted[$notifKey]['entries'])) {
        foreach ($nativeSorted[$notifKey]['entries'] as $c) if (is_array($c)) $adminEntries[] = $c;
    }

    // Build Plugins = all parents not in keep/admin
    $pluginSegs = [];
    $pluginSubEntries = [];
    foreach ($nativeSorted as $k => $p) {
        $seg = $segOfKey[$k];
        if (in_array($seg, $keepSegs, true))  continue;
        if (in_array($seg, $adminSegs, true)) continue;

        $pluginSegs[] = $seg;

        $sub = [
            'name'            => $p['name'] ?? $k,
            'label'           => $p['label'] ?? ($p['name'] ?? $k),
            'icon'            => $p['icon'] ?? 'fas fa-puzzle-piece',
            'route'           => $p['route'] ?? null,
            'permission'      => $p['permission'] ?? null,
            '_osmm_subsegment'=> $seg,
        ];
        if (!empty($p['entries']) && is_array($p['entries'])) {
            $sub['entries'] = array_values(array_filter($p['entries'], 'is_array'));
        }
        $pluginSubEntries[] = $sub;
    }

    // Assemble base menu: kept parents first (native order)
    $base = [];
    foreach ($nativeSorted as $k => $p) {
        $seg = $segOfKey[$k];
        if (in_array($seg, $keepSegs, true)) $base[$k] = $p;
    }

    // Add Administration
    $base['administration'] = [
        'name'          => 'Administration',
        'label'         => 'Administration',
        'icon'          => 'fas fa-cogs',
        'route_segment' => 'administration',
        'permission'    => null,
        'entries'       => $adminEntries,
    ];

    // Add Plugins
    $base['plugins'] = [
        'name'          => 'Plugins',
        'label'         => 'Plugins',
        'icon'          => 'fas fa-plug',
        'route_segment' => 'plugins',
        'permission'    => null,
        'entries'       => $pluginSubEntries,
    ];

    /* ---------- Inject Custom Links into Tools (top) ---------- */
    try {
        $raw = function_exists('setting') ? setting('customlinks', true) : null;
    } catch (\Throwable $e) {
        $raw = null;
    }
    // Normalize to a collection
    if (is_array($raw)) {
        $raw = collect($raw);
    } elseif (!($raw instanceof \Illuminate\Support\Collection)) {
        $raw = collect();
    }

    $customLinks = [];
    foreach ($raw as $cl) {
        // support array or object forms
        $name   = is_array($cl) ? ($cl['name'] ?? null) : ($cl->name ?? null);
        $url    = is_array($cl) ? ($cl['url'] ?? null)  : ($cl->url ?? null);
        $icon   = is_array($cl) ? ($cl['icon'] ?? null) : ($cl->icon ?? null);
        $newTab = is_array($cl) ? ($cl['new_tab'] ?? false) : ($cl->new_tab ?? false);

        if (!$name || !$url) continue;

        $customLinks[] = [
            'name'     => $name,
            'label'    => $name,
            'icon'     => $icon ?: 'fas fa-external-link-alt',
            'url'      => $url,
            'external' => true,
            'target'   => $newTab ? '_blank' : null,
        ];
    }

    if (!empty($customLinks)) {
        // Find the actual array key for the 'tools' segment (could be '2tools', etc.)
        $toolsKey = null;
        foreach ($base as $k => $v) {
            $seg = $v['route_segment'] ?? $k;
            if ($seg === 'tools') { $toolsKey = $k; break; }
        }

        if ($toolsKey) {
            $existing = array_values(array_filter($base[$toolsKey]['entries'] ?? [], 'is_array'));
            $merged   = $customLinks;
            if (!empty($existing)) {
                $merged[] = ['divider' => true]; // only add divider if we actually have native tools items
                $merged   = array_merge($merged, $existing);
            }
            $base[$toolsKey]['entries'] = $merged;
        } else {
            // No tools parent? Create one to host custom links (with divider ready for future native)
            $base['tools'] = [
                'name'          => 'Tools',
                'label'         => 'Tools',
                'icon'          => 'fas fa-wrench',
                'route_segment' => 'tools',
                'entries'       => array_merge($customLinks, [['divider'=>true]]),
            ];
        }
    }
    /* ---------- end custom links injection ---------- */

    return [
        'menu' => $base,
        'maps' => [
            'admin_segments'  => $adminSegs,
            'plugin_segments' => $pluginSegs,
        ],
    ];
}



/** Find index of a Plugins sub-entry by its original segment tag. */
protected function findPluginsSubIndex(array $pluginsEntries, string $subseg): ?int
{
    foreach ($pluginsEntries as $i => $e) {
        if (is_array($e) && ($e['_osmm_subsegment'] ?? null) === $subseg) return $i;
    }
    return null;
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

    /**
 * Apply DB overrides AFTER consolidation.
 * $maps['admin_segments'], $maps['plugin_segments']
 */
protected function applyOverridesWithConsolidation(array $baseNative, $dbRowsCol, array $maps): array
{
    $merged   = $baseNative;
    $adminSet = array_flip($maps['admin_segments'] ?? []);
    $plugSet  = array_flip($maps['plugin_segments'] ?? []);

    // ----- Parent rows (parent = NULL)
    foreach ($dbRowsCol as $row0) {
        $row = is_array($row0) ? (object)$row0 : $row0;
        if (!is_null($row->parent)) continue;
        $seg = $row->route_segment;
        if (!$seg) continue;

        // Kept parents: locate actual key by segment
        if (!isset($adminSet[$seg]) && !isset($plugSet[$seg])) {
            $pKey = $this->findParentKeyBySegment($merged, $seg);
            if ($pKey === null) continue;

            // visibility
            if (property_exists($row,'visible') && $row->visible !== null) {
                if ((int)$row->visible === 0) { unset($merged[$pKey]); continue; }
                if ((int)$row->visible === 1) { $merged[$pKey]['permission'] = null; }
            }

            if (!is_null($row->name_override))  $merged[$pKey]['name']  = $row->name_override;
            if (!is_null($row->label_override)) $merged[$pKey]['label'] = $row->label_override;
            if (!is_null($row->icon))           $merged[$pKey]['icon']  = $row->icon;
            if (!is_null($row->route))          $merged[$pKey]['route'] = $row->route;
            if (!is_null($row->permission))     $merged[$pKey]['permission'] = $row->permission;
            continue;
        }

        // Admin sources: skip parent-level override (user can target 'administration' directly if desired)
        if (isset($adminSet[$seg])) continue;

        // Plugins sources: override the corresponding sub-entry under Plugins
        if (isset($plugSet[$seg])) {
            if (empty($merged['plugins']['entries']) || !is_array($merged['plugins']['entries'])) continue;
            $idx = $this->findPluginsSubIndex($merged['plugins']['entries'], $seg);
            if ($idx === null) continue;

            if (property_exists($row,'visible') && $row->visible !== null) {
                if ((int)$row->visible === 0) {
                    array_splice($merged['plugins']['entries'], $idx, 1);
                    continue;
                }
                if ((int)$row->visible === 1) {
                    $merged['plugins']['entries'][$idx]['permission'] = null;
                }
            }

            if (!is_null($row->name_override))  $merged['plugins']['entries'][$idx]['name']  = $row->name_override;
            if (!is_null($row->label_override)) $merged['plugins']['entries'][$idx]['label'] = $row->label_override;
            if (!is_null($row->icon))           $merged['plugins']['entries'][$idx]['icon']  = $row->icon;
            if (!is_null($row->route))          $merged['plugins']['entries'][$idx]['route'] = $row->route;
            if (!is_null($row->permission))     $merged['plugins']['entries'][$idx]['permission'] = $row->permission;
            continue;
        }
    }

    // DB parent id -> segment (for child rows)
    $segByDbParentId = [];
    foreach ($dbRowsCol as $r0) {
        $r = is_array($r0) ? (object)$r0 : $r0;
        if (is_null($r->parent)) continue;
        if (!isset($segByDbParentId[$r->parent])) {
            $segByDbParentId[$r->parent] = DB::table('osmm_menu_items')->where('id',$r->parent)->value('route_segment');
        }
    }

    // ----- Child rows (parent != NULL)
    foreach ($dbRowsCol as $row0) {
        $row = is_array($row0) ? (object)$row0 : $row0;
        if (is_null($row->parent)) continue;

        $srcSeg = $segByDbParentId[$row->parent] ?? $row->route_segment;
        if (!$srcSeg) continue;

        // Administration (flattened)
        if (isset($adminSet[$srcSeg])) {
            if (empty($merged['administration']['entries'])) continue;
            $entries = $merged['administration']['entries'];
            $idx = $this->findChildIndex($entries, $row->route, $row->name);
            if ($idx === null) continue;

            if (property_exists($row,'visible') && $row->visible !== null) {
                if ((int)$row->visible === 0) { array_splice($entries, $idx, 1); $merged['administration']['entries'] = $entries; continue; }
                if ((int)$row->visible === 1) { $entries[$idx]['permission'] = null; }
            }

            if (!is_null($row->name_override))  $entries[$idx]['name']  = $row->name_override;
            if (!is_null($row->label_override)) $entries[$idx]['label'] = $row->label_override;
            if (!is_null($row->icon))           $entries[$idx]['icon']  = $row->icon;
            if (!is_null($row->route))          $entries[$idx]['route'] = $row->route;
            if (!is_null($row->permission))     $entries[$idx]['permission'] = $row->permission;

            $merged['administration']['entries'] = $entries;
            continue;
        }

        // Plugins (nested under a sub-entry)
        if (isset($plugSet[$srcSeg])) {
            if (empty($merged['plugins']['entries']) || !is_array($merged['plugins']['entries'])) continue;
            $pIdx = $this->findPluginsSubIndex($merged['plugins']['entries'], $srcSeg);
            if ($pIdx === null) continue;

            $children = $merged['plugins']['entries'][$pIdx]['entries'] ?? [];
            if (empty($children) || !is_array($children)) continue;

            $idx = $this->findChildIndex($children, $row->route, $row->name);
            if ($idx === null) continue;

            if (property_exists($row,'visible') && $row->visible !== null) {
                if ((int)$row->visible === 0) { array_splice($children, $idx, 1); $merged['plugins']['entries'][$pIdx]['entries'] = $children; continue; }
                if ((int)$row->visible === 1) { $children[$idx]['permission'] = null; }
            }

            if (!is_null($row->name_override))  $children[$idx]['name']  = $row->name_override;
            if (!is_null($row->label_override)) $children[$idx]['label'] = $row->label_override;
            if (!is_null($row->icon))           $children[$idx]['icon']  = $row->icon;
            if (!is_null($row->route))          $children[$idx]['route'] = $row->route;
            if (!is_null($row->permission))     $children[$idx]['permission'] = $row->permission;

            $merged['plugins']['entries'][$pIdx]['entries'] = $children;
            continue;
        }

        // Kept parents: find actual parent key by segment, then override the child
        $pKey = $this->findParentKeyBySegment($merged, $srcSeg);
        if ($pKey === null) continue;
        if (empty($merged[$pKey]['entries']) || !is_array($merged[$pKey]['entries'])) continue;

        $entries = array_values(array_filter($merged[$pKey]['entries'], 'is_array'));
        $idx     = $this->findChildIndex($entries, $row->route, $row->name);
        if ($idx === null) continue;

        if (property_exists($row,'visible') && $row->visible !== null) {
            if ((int)$row->visible === 0) { array_splice($entries, $idx, 1); $merged[$pKey]['entries'] = $entries; continue; }
            if ((int)$row->visible === 1) { $entries[$idx]['permission'] = null; }
        }
        if (!is_null($row->name_override))  $entries[$idx]['name']  = $row->name_override;
        if (!is_null($row->label_override)) $entries[$idx]['label'] = $row->label_override;
        if (!is_null($row->icon))           $entries[$idx]['icon']  = $row->icon;
        if (!is_null($row->route))          $entries[$idx]['route'] = $row->route;
        if (!is_null($row->permission))     $entries[$idx]['permission'] = $row->permission;

        $merged[$pKey]['entries'] = $entries;
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
            // special-case: plugins — flatten grandchildren
            if ($seg === 'plugins') {
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
                        // plugin sub-entry without children — expose it too
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
                // normal parents
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
