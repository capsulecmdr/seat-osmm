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
    // ---- 1) Source data ----------------------------------------------------
    $native    = config('package.sidebar') ?? [];

    // Raw DB rows (collection) for center column and for sorting hints
    $dbRowsCol = \DB::table('osmm_menu_items')
        ->select('id','parent','order','name','icon','route_segment','route','permission','created_at','updated_at')
        ->orderBy('parent')->orderBy('order')
        ->get();

    // Build overrides -> merge them into native
    $overrides = $this->buildDbOverrides();
    $merged    = $this->applyOverrides($native, $overrides);

    // ---- 2) Sorting (to match SeAT sidebar) --------------------------------
    // Helpers inline to keep this self-contained
    $labelOf = function(array $item, string $fallback = '') {
        $label = $item['label'] ?? $item['name'] ?? $fallback;
        try { $label = __($label); } catch (\Throwable $e) {}
        return (string) $label;
    };
    $numericPrefixWeight = function(string $key) {
        return preg_match('/^\d+/', $key, $m) ? (int) $m[0] : 1000;
    };

    // Parent order map from DB (parent rows have parent = null)
    $parentOrderMap = [];
    foreach ($dbRowsCol as $r) {
        if (is_null($r->parent) && $r->route_segment && isset($r->order)) {
            $parentOrderMap[$r->route_segment] = (int) $r->order;
        }
    }

    // Child order maps (match by route first, fallback to name)
    $childOrderByRoute = $childOrderByName = [];
    foreach ($dbRowsCol as $r) {
        if (!is_null($r->parent) && isset($r->order)) {
            if (!empty($r->route)) $childOrderByRoute[$r->route] = (int) $r->order;
            if (!empty($r->name))  $childOrderByName[$r->name]   = (int) $r->order;
        }
    }

    $sortParents = function(array &$menu) use ($labelOf, $numericPrefixWeight, $parentOrderMap) {
        uksort($menu, function ($aKey, $bKey) use ($menu, $labelOf, $numericPrefixWeight, $parentOrderMap) {
            $a = $menu[$aKey]; $b = $menu[$bKey];

            $aSeg = $a['route_segment'] ?? $aKey;
            $bSeg = $b['route_segment'] ?? $bKey;

            // 1) DB order (if present)
            $aW = $parentOrderMap[$aSeg] ?? null;
            $bW = $parentOrderMap[$bSeg] ?? null;

            // 2) Numeric key prefix like "0home"
            $aW = $aW ?? $numericPrefixWeight($aKey);
            $bW = $bW ?? $numericPrefixWeight($bKey);

            if ($aW !== $bW) return $aW <=> $bW;

            // 3) Alpha by translated label
            $aL = mb_strtolower($labelOf($a, $aKey));
            $bL = mb_strtolower($labelOf($b, $bKey));
            return strnatcasecmp($aL, $bL);
        });
    };

    $sortChildren = function(array &$menu) use ($labelOf, $childOrderByRoute, $childOrderByName) {
        foreach ($menu as &$parent) {
            if (empty($parent['entries']) || !is_array($parent['entries'])) continue;

            // Normalize to a sequential array
            $entries = [];
            foreach ($parent['entries'] as $e) if (is_array($e)) $entries[] = $e;

            usort($entries, function ($a, $b) use ($labelOf, $childOrderByRoute, $childOrderByName) {
                // Weight from DB.order, matched by route first, then name
                $aW = $a['route'] ?? null; $aW = $aW && isset($childOrderByRoute[$aW]) ? $childOrderByRoute[$aW] : (
                      (isset($a['name']) && isset($childOrderByName[$a['name']])) ? $childOrderByName[$a['name']] : 1000);
                $bW = $b['route'] ?? null; $bW = $bW && isset($childOrderByRoute[$bW]) ? $childOrderByRoute[$bW] : (
                      (isset($b['name']) && isset($childOrderByName[$b['name']])) ? $childOrderByName[$b['name']] : 1000);

                if ($aW !== $bW) return $aW <=> $bW;

                $aL = mb_strtolower($labelOf($a));
                $bL = mb_strtolower($labelOf($b));
                return strnatcasecmp($aL, $bL);
            });

            $parent['entries'] = $entries;
        }
        unset($parent);
    };

    $nativeSorted = $native;
    $sortParents($nativeSorted);
    $sortChildren($nativeSorted);

    $mergedSorted = $merged;
    $sortParents($mergedSorted);
    $sortChildren($mergedSorted);

    // ---- 3) Dropdown data --------------------------------------------------
    // Parent options (for Create Child + edit form)
    $parentOptions = collect($nativeSorted)->map(function ($v, $k) {
        $seg      = $v['route_segment'] ?? $k;
        $parentId = \DB::table('osmm_menu_items')->whereNull('parent')->where('route_segment', $seg)->value('id');
        return [
            'key'       => $k,
            'name'      => $v['name'] ?? $k,
            'seg'       => $seg,
            'label'     => ($v['name'] ?? $k) . " [{$seg}]",
            'parent_id' => $parentId,
        ];
    })->values()->all();

    // Permissions + route segments (cached; helpers you added earlier)
    $allPermissions = \Cache::remember('osmm_permission_options', 300, fn() => $this->collectPermissionOptions());
    $routeSegments  = \Cache::remember('osmm_route_segment_options', 300, fn() => $this->collectRouteSegmentOptions());

    // ---- 4) Permission checker for the two sidebars ------------------------
    $can = fn ($perm) => empty($perm) || (\auth()->check() && \auth()->user()->can($perm));

    // ---- 5) Render ----------------------------------------------------------
    return view('seat-osmm::menu.index', [
        'native'         => $nativeSorted,
        'merged'         => $mergedSorted,
        'dbRows'         => $dbRowsCol,      // collection used by the center list partial
        'parentOptions'  => $parentOptions,
        'allPermissions' => $allPermissions, // array of ['value','label'] or strings, per your helper
        'routeSegments'  => $routeSegments,  // array of ['value','label']
        'can'            => $can,
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
}
