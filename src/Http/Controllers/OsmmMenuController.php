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
        $dbRowsCol = \DB::table('osmm_menu_items')
            ->select('id','parent','order','name','icon','route_segment','route','permission','created_at','updated_at')
            ->orderBy('parent')->orderBy('order')->get();

        // Overrides -> merged menu
        $overrides = $this->buildDbOverrides();       // your existing helper
        $merged    = $this->applyOverrides($native, $overrides); // your existing helper

        // 2) Sort Native like SeAT ------------------------------------------------
        $labelOf = function(array $item, string $fallback = ''): string {
            $label = $item['label'] ?? $item['name'] ?? $fallback;
            try { $label = __($label); } catch (\Throwable $e) {}
            return (string) $label;
        };
        $numericPrefixWeight = function(string $key): int {
            // SeAT often uses numeric prefixes to pin items (e.g. "0home")
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
        // Build segment map: parent key -> segment
        $segOfKey = [];
        foreach ($nativeSorted as $k => $p) $segOfKey[$k] = $p['route_segment'] ?? $k;

        // Map: route_segment -> parent DB id (for matching children rows)
        $parentDbIdBySeg = \DB::table('osmm_menu_items')
            ->whereNull('parent')->whereNotNull('route_segment')->pluck('id','route_segment')->all();
        // Inverse: parent DB id -> segment
        $segByParentDbId = array_flip($parentDbIdBySeg);

        // Explicit orders from DB
        $parentOrderBySeg = [];               // e.g. ['alliances' => 99]
        $childOrdersBySeg = [];               // e.g. ['alliances' => [['route'=>'seatcore::...','order'=>3], ...]]
        foreach ($dbRowsCol as $r) {
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

        // Helper: reposition items in a list by 1-based "order" (clamped)
        $reposition = function(array $keys, array $orderMap) {
            // $orderMap: map key => pos (1-based)
            // Process by ascending desired pos to keep intuitive outcomes
            $indexed = array_values($keys);
            // Build pairs [key, pos]
            $pairs = [];
            foreach ($orderMap as $k => $pos) $pairs[] = [$k, max(1,(int)$pos)];
            usort($pairs, fn($a,$b) => $a[1] <=> $b[1]);

            foreach ($pairs as [$key, $pos]) {
                $i = array_search($key, $indexed, true);
                if ($i === false) continue;
                array_splice($indexed, $i, 1); // remove
                $insertAt = min(max($pos-1, 0), count($indexed)); // clamp to end
                array_splice($indexed, $insertAt, 0, [$key]); // insert
            }
            return $indexed;
        };

        // 4) Build Merged in *native* order first, then apply DB 'order' ----------
        // a) Parent order: start with native keys
        $nativeParentKeys = array_keys($nativeSorted);

        // Map segment->key so we can translate DB orders (by segment) to actual keys
        $keyBySeg = [];
        foreach ($nativeSorted as $k => $p) $keyBySeg[$segOfKey[$k]] = $k;

        // Translate parent orders (seg->pos) into key->pos for the *merged* menu
        $parentOrderByKey = [];
        foreach ($parentOrderBySeg as $seg => $pos) {
            if (isset($keyBySeg[$seg])) $parentOrderByKey[$keyBySeg[$seg]] = (int)$pos;
        }

        // Reorder parent keys
        $mergedParentKeys = $reposition($nativeParentKeys, $parentOrderByKey);

        // Rebuild merged assoc in that order
        $mergedInNativeOrder = [];
        foreach ($mergedParentKeys as $k) if (array_key_exists($k, $merged)) $mergedInNativeOrder[$k] = $merged[$k];

        // b) For each parent, default-sort children like Native (alpha), then apply DB orders
        foreach ($mergedInNativeOrder as $pKey => &$parent) {
            // default child sort (same method as native)
            $sortChildrenAlpha($parent);

            $seg = $segOfKey[$pKey] ?? ($parent['route_segment'] ?? $pKey);
            if (empty($childOrdersBySeg[$seg]) || empty($parent['entries'])) continue;

            // Prepare child list and indexes (by route then by name)
            $entries = $parent['entries'];
            $indexByRoute = [];
            $indexByName  = [];
            foreach ($entries as $idx => $e) {
                if (!empty($e['route'])) $indexByRoute[$e['route']] = $idx;
                if (!empty($e['name']))  $indexByName[$e['name']]   = $idx;
            }

            // Build order map for existing items (by key = current index placeholder)
            // We'll translate into "reposition on the fly" using array_splice on the entries array.
            // Sort requested moves by desired position
            usort($childOrdersBySeg[$seg], fn($a,$b) => $a['order'] <=> $b['order']);

            foreach ($childOrdersBySeg[$seg] as $req) {
                $keyIdx = null;
                if (!empty($req['route']) && isset($indexByRoute[$req['route']])) {
                    $keyIdx = $indexByRoute[$req['route']];
                } elseif (!empty($req['name']) && isset($indexByName[$req['name']])) {
                    $keyIdx = $indexByName[$req['name']];
                }
                if ($keyIdx === null) continue; // not present in merged entries (skip)

                // remove the item
                $item = $entries[$keyIdx];
                array_splice($entries, $keyIdx, 1);

                // recompute length & clamp target
                $insertAt = (int)$req['order'] - 1;
                if ($insertAt < 0) $insertAt = 0;
                if ($insertAt > count($entries)) $insertAt = count($entries);

                // insert at new position
                array_splice($entries, $insertAt, 0, [$item]);

                // rebuild indexes after mutation
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
            $parentId = \DB::table('osmm_menu_items')->whereNull('parent')->where('route_segment', $seg)->value('id');
            return [
                'key'       => $k,
                'name'      => $v['name'] ?? $k,
                'seg'       => $seg,
                'label'     => ($v['name'] ?? $k)." [{$seg}]",
                'parent_id' => $parentId,
            ];
        })->values()->all();

        $allPermissions = \Cache::remember('osmm_permission_options', 300, fn() => $this->collectPermissionOptions());
        $routeSegments  = \Cache::remember('osmm_route_segment_options', 300, fn() => $this->collectRouteSegmentOptions());

        $menuCatalog = $this->buildMenuCatalog($nativeSorted);

        // 6) Permission checker for rendering sidebars ---------------------------
        $can = fn ($perm) => empty($perm) || (\auth()->check() && \auth()->user()->can($perm));

        // 7) Render ---------------------------------------------------------------
        return view('seat-osmm::menu.index', [
            'native'         => $nativeSorted,     // native order (SeAT-style)
            'merged'         => $mergedSorted,     // native order + explicit DB repositions
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
            // Adjust table/column if your SeAT version differs
            $fromPermsTable = DB::table('permissions')->pluck('title')->all();
        }

        return collect([$fromConfig, $fromDb, $fromPermsTable])
            ->flatten()->filter()->unique()->sort()->values()->all();
    }

    protected function collectRouteSegmentOptions(): array
    {
        $native = config('package.sidebar') ?? [];

        // From native config: seg = route_segment or fallback to the key
        $fromConfig = collect($native)->map(function ($v, $k) {
            $seg = $v['route_segment'] ?? $k;
            return [
                'value' => $seg,
                'label' => ($v['name'] ?? $k) . " [{$seg}]",
            ];
        });

        // From DB parents that already have a route_segment
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
        $seat = config('package.sidebar') ?? [];
        $db   = $this->buildDbOverrides();
        return $this->applyOverrides($seat, $db);
    }

    /* ==================== CRUD for overrides ==================== */

    /** Create or update a parent (top-level) override */
    public function upsertParent(Request $req)
    {
        $data = $req->validate([
            'id'            => ['nullable', 'integer', 'exists:osmm_menu_items,id'],
            'name'          => ['nullable', 'string', 'max:150'],
            'icon'          => ['nullable', 'string', 'max:150'],
            'route_segment' => ['required_without:id', 'nullable', 'string', 'max:150'],
            'route'         => ['nullable', 'string', 'max:190'],
            'permission'    => ['nullable', 'string', 'max:190'],
            'order'         => ['nullable', 'integer', 'min:1'],
        ]);

        $values = Arr::only($data, ['name','icon','route_segment','route','permission','order']);
        $values['parent'] = null;

        if (!empty($data['id'])) {
            DB::table('osmm_menu_items')->where('id', $data['id'])->update($values);
        } else {
            DB::table('osmm_menu_items')->insert($values);
        }

        Cache::forget('osmm_menu_rows');
        return back()->with('ok', 'Parent saved.');
    }

    /** Create or update a child override */
    public function upsertChild(Request $req)
    {
        $data = $req->validate([
            'id'         => ['nullable','integer','exists:osmm_menu_items,id'],
            'parent_id'  => ['required_without:id','integer','exists:osmm_menu_items,id'],
            'name'       => ['nullable','string','max:150'],
            'icon'       => ['nullable','string','max:150'],
            'route'      => ['nullable','string','max:190'],
            'permission' => ['nullable','string','max:190'],
            'order'      => ['nullable','integer','min:1'],
        ]);

        $values = Arr::only($data, ['name','icon','route','permission','order']);
        if (empty($data['id'])) {
            $values['parent'] = $data['parent_id'];
            $values['route_segment'] = null;
            DB::table('osmm_menu_items')->insert($values);
        } else {
            DB::table('osmm_menu_items')->where('id', $data['id'])->update($values);
        }

        Cache::forget('osmm_menu_rows');
        return back()->with('ok', 'Child saved.');
    }

    /** Delete an override (parent or child) */
    public function delete(Request $req)
    {
        $data = $req->validate([
            'id' => ['required','integer','exists:osmm_menu_items,id'],
            'cascade' => ['sometimes','boolean'],
        ]);

        if (!empty($data['cascade'])) {
            // delete children first
            DB::table('osmm_menu_items')->where('parent', $data['id'])->delete();
        }
        DB::table('osmm_menu_items')->where('id', $data['id'])->delete();

        Cache::forget('osmm_menu_rows');
        return back()->with('ok', 'Override deleted.');
    }

    /** Quick reset all overrides (danger) */
    public function resetAll()
    {
        DB::table('osmm_menu_items')->truncate();
        Cache::forget('osmm_menu_rows');
        return back()->with('ok', 'All overrides cleared.');
    }

    /* ==================== Internals ==================== */

    /** Build overrides from osmm_menu_items into config-like shape keyed by parent route_segment (fallback name). */
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

    /** Non-null overlay */
    protected function mergeFields(array $base, array $ovr, array $keys): array
    {
        foreach ($keys as $k) {
            if (array_key_exists($k, $ovr) && $ovr[$k] !== null) {
                $base[$k] = $ovr[$k];
            }
        }
        return $base;
    }

    /** Final merge: DB overrides into SeAT config (DB non-null wins) */
    protected function applyOverrides(array $seat, array $db): array
    {
        foreach ($seat as $topKey => $parent) {
            $matchKey = $parent['route_segment'] ?? $topKey;

            if (isset($db[$matchKey])) {
                $seat[$topKey] = $this->mergeFields(
                    $parent, $db[$matchKey],
                    ['name','icon','route_segment','route','permission','label','plural']
                );

                $seatKids = $parent['entries'] ?? [];
                $dbKids   = $db[$matchKey]['entries'] ?? [];

                if (is_array($seatKids) && is_array($dbKids)) {
                    $dbIdx = $this->indexChildrenByKey($dbKids);

                    foreach ($seatKids as $i => $child) {
                        if (!is_array($child)) continue;
                        $ck = $child['route'] ?? ($child['name'] ?? null);
                        if ($ck && isset($dbIdx[$ck])) {
                            $seat[$topKey]['entries'][$i] = $this->mergeFields(
                                $child, $dbIdx[$ck],
                                ['name','icon','route','permission','label','plural']
                            );
                        }
                    }

                    // OPTIONAL: include DB-only children
                    // foreach ($dbIdx as $ck => $dbChild) {
                    //     $found = collect($seat[$topKey]['entries'] ?? [])
                    //         ->contains(fn($c) => ($c['route'] ?? ($c['name'] ?? null)) === $ck);
                    //     if (!$found) $seat[$topKey]['entries'][] = $dbChild;
                    // }
                }
            }
        }
        return $seat;
    }

    /** For selects: list route_segments of native parents for easy linking */
    protected function parentSelectOptions(): array
    {
        $native = config('package.sidebar') ?? [];
        $opts = [];
        foreach ($native as $k => $v) {
            $label = ($v['name'] ?? $k) . '  [' . ($v['route_segment'] ?? $k) . ']';
            $opts[] = [
                'key'   => $k,
                'name'  => $v['name'] ?? $k,
                'seg'   => $v['route_segment'] ?? $k,
                'label' => $label,
            ];
        }
        return $opts;
    }

    protected function menuLabel(array $item, string $fallback = ''): string
    {
        $label = $item['label'] ?? $item['name'] ?? $fallback;
        // Translate labels like 'web::seat.home' if present
        try { $label = __($label); } catch (\Throwable $e) {}
        return trim((string) $label);
    }

    protected function keyWeight(string $key): int
    {
        // Honor numeric prefixes like "0home" that SeAT uses to pin items
        if (preg_match('/^\d+/', $key, $m)) return (int) $m[0];
        return 1000;
    }

    protected function entryWeight(?array $override = null, ?int $fallback = null): int
    {
        // If you store an 'order' in osmm_menu_items, prefer it
        if ($override && isset($override['order'])) return (int) $override['order'];
        return $fallback ?? 1000;
    }

    /**
     * Sort array like SeAT:
     * - Parents: by DB order (if any), else by numeric key prefix (e.g. "0home"), else alpha by label
     * - Children: by DB order (if any), else alpha by label
     * Also sorts using natural, case-insensitive comparison on the *translated* label.
     *
     * @param array $menu (config-like structure)
     * @param array $dbRows rows from osmm_menu_items (we’ll index them by parent/route/name)
     */
    protected function sortLikeSeat(array $menu, array $dbRows = []): array
    {
        // index DB rows for quick lookup
        $byParent = collect($dbRows)->groupBy(fn($r) => $r['parent'] ?? null);

        // ----- sort parents
        uksort($menu, function ($aKey, $bKey) use ($menu, $byParent) {
            $a   = $menu[$aKey];
            $b   = $menu[$bKey];

            // DB override order for parents (parent = null, route_segment identifies)
            $aDb = optional($byParent->get(null))->firstWhere('route_segment', $a['route_segment'] ?? $aKey);
            $bDb = optional($byParent->get(null))->firstWhere('route_segment', $b['route_segment'] ?? $bKey);

            $aW  = $this->entryWeight($aDb, $this->keyWeight($aKey));
            $bW  = $this->entryWeight($bDb, $this->keyWeight($bKey));
            if ($aW !== $bW) return $aW <=> $bW;

            $aL = Str::lower($this->menuLabel($a, $aKey));
            $bL = Str::lower($this->menuLabel($b, $bKey));
            return strnatcasecmp($aL, $bL);
        });

        // ----- sort children within each parent
        foreach ($menu as $pKey => &$parent) {
            if (empty($parent['entries'])) continue;

            // Normalize entries to a sequential array
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
        // Map: route_segment => existing parent override DB id (if any)
        $parentIds = \DB::table('osmm_menu_items')
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

            // Build route options (parent route + child routes)
            $routes = [];
            if (!empty($parent['route'])) $routes[$parent['route']] = $parent['route'];
            foreach ($children as $c) {
                if (!empty($c['route'])) $routes[$c['route']] = $c['route'];
            }

            $catalog[$seg] = [
                'parent'   => $parent,
                'children' => $children,
                'routes'   => array_values($routes), // unique list
            ];
        }
        return $catalog;
    }

}
