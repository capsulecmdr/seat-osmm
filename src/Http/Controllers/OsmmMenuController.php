<?php

namespace CapsuleCmdr\SeatOsmm\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class OsmmMenuController extends Controller
{
    /** CONFIG PAGE: side-by-side tree view (native vs merged) + CRUD tools */
    public function index()
    {
        // Native SeAT config
        $native = config('package.sidebar') ?? [];

        // DB overrides shaped like config
        $overrides = $this->buildDbOverrides();

        // Merged final menu (DB non-null wins)
        $merged = $this->applyOverrides($native, $overrides);

        // Useful lists for UI selects (parents, permissions from data, etc.)
        $parentOptions = $this->parentSelectOptions();

        return view('seat-osmm::menu.index', compact('native', 'overrides', 'merged', 'parentOptions'));
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
}
