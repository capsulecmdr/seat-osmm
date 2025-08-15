<?php

namespace CapsuleCmdr\SeatOsmm\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Seat\Eseye\Eseye;
use Carbon\Carbon;
use Seat\Eveapi\Models\Killmails\KillmailDetail as KD;
use Seat\Eveapi\Models\Killmails\KillmailAttacker as KA;
use Seat\Eveapi\Models\Killmails\KillmailVictim as KV;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Seat\Eveapi\Models\Industry\CharacterMining as CM;
use Seat\Eveapi\Models\Sde\InvType as InvType;
use Seat\Eveapi\Models\Sde\InvGroup as InvGroup;
use Seat\Eveapi\Models\Wallet\CharacterWalletJournal as CWJ;
use Seat\Eveapi\Models\Wallet\CharacterWalletBalance as CWB;
use Seat\Eveapi\Models\Assets\CharacterAsset as CA;
use Illuminate\Support\Facades\Log;
use CapsuleCmdr\SeatOsmm\Support\Esi\EsiCall;
use Seat\Eveapi\Models\Character\CharacterInfo;


class HomeOverrideController extends Controller
{

    public function index()
    {
        $user = Auth::user();

        $inWar = $this->userInActiveWar();

        $km = $this->buildMonthlyKillmailCumulative();

        $mining = $this->buildMonthlyMiningMtd();

        $walletBalance30 = $this->buildWalletBalanceLast30d();

        $walletByChar = $this->buildWalletPerCharacter();

        $homeElements = collect(config('osmm.home_elements', []))->sortBy('order');

        $allocation = $this->buildAssetAllocationHierarchy();

        $skillsChars = $user->characters()
        ->select('character_infos.character_id', 'character_infos.name')
        ->distinct()
        ->get()
        ->map(fn($c) => ['id' => (int) $c->character_id, 'name' => $c->name])
        ->values();

        $characterId = $user->characters()->first()?->character_id;
        $publicInfo = $characterId ? $this->getPublicCharacterInfo($characterId) : null;        
        $blueprints = $characterId ? $this->getPublicCharacterInfo($characterId) : null;

        // $publicInfo = $this->getPublicCharacterInfo($user->characters()->first()->character_id);

        // $blueprints = $this->blueprintsView($user->characters()->first()->character_id);


        return view('seat-osmm::home', compact('homeElements','inWar','km','mining','walletBalance30','walletByChar','allocation','skillsChars','publicInfo','blueprints'));
    }
    public function getPublicCharacterInfo(int $character_id)
    {
        // Build and execute the ESI call
        $call = EsiCall::make('/characters/{character_id}/')
            ->get()
            ->pathParams(['character_id' => $character_id])
            ->run();

        // Check for success and return appropriate response
        if (!$call->ok()) {
            return response()->json([
                'error'   => 'ESI request failed',
                'details' => $call->error(),
            ], $call->status() ?: 500);
        }

        return response()->json($call->data());
    }
    public function blueprintsView(int $character_id)
    {
        // Fetch all pages of blueprints for this character
        $call = EsiCall::make('/characters/{character_id}/blueprints/')
            ->get()
            ->pathParams(['character_id' => $character_id])
            ->withSeatUser(Auth::user(), $character_id)
            ->autoPaginate();

        $blueprints = [];
        $error = null;

        if ($call->ok()) {
            $blueprints = $call->data();
        } else {
            $error = $call->error();
        }

        // Pass data to the Blade view
        return response()->json($call->data());
    }

    public function userInActiveWar(): bool
    {
        $esi = app(EsiCall::class);
        $user = Auth::user();
        if (!$user) return false;

        $charsTable = (new CharacterInfo)->getTable(); // usually "character_infos"

        $charIds = $user->characters()
            ->select("{$charsTable}.character_id")
            ->pluck("{$charsTable}.character_id")
            ->unique()
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->all();

        if (empty($charIds)) return false;

        foreach ($charIds as $cid) {
            try {
                $cinfo  = (array) $esi->get("characters/{$cid}/");
                $corpId = $cinfo['corporation_id'] ?? null;
                $allyId = $cinfo['alliance_id'] ?? null;

                if ($corpId && $this->hasActiveWars("corporations/{$corpId}/wars")) return true;
                if ($allyId && $this->hasActiveWars("alliances/{$allyId}/wars"))   return true;

            } catch (\Throwable $e) {
                // ignore and keep checking
            }
        }

        return false;
    }

    private function hasActiveWars(string $path): bool
    {
        $esi = app(EsiCall::class);
        try {
            foreach ((array) $esi->get($path) as $warId) {
                $war = (array) $esi->get("wars/{$warId}/");
                if (!empty($war['started']) && empty($war['finished'])) return true;
            }
        } catch (\Throwable $e) {}
        return false;
    }

    private function buildMonthlyKillmailCumulative(): array
    {
        $user = auth()->user();

        // All linked character IDs (disambiguate the column to avoid ambiguous select)
        $char_ids = $user->characters()
            ->select('character_infos.character_id')
            ->distinct()
            ->pluck('character_infos.character_id');

        if ($char_ids->isEmpty()) {
            $days_in_month = Carbon::now('UTC')->endOfMonth()->day;
            return [
                'days'            => range(1, $days_in_month),
                'cum_wins'        => array_fill(0, $days_in_month, 0),
                'cum_total'       => array_fill(0, $days_in_month, 0),
                'total_wins'      => 0,
                'total_losses'    => 0,
                'total_killmails' => 0,
                'month'           => Carbon::now('UTC')->format('Y-m'),
            ];
        }

        // Current month window (UTC)
        $now   = Carbon::now('UTC');
        $start = $now->copy()->startOfMonth();
        $end   = $now->copy()->endOfMonth();
        $days_in_month = $end->day;

        // Losses: victim is one of our chars  -> get killmail_ids, then their times in window
        $loss_mail_ids = KV::whereIn('character_id', $char_ids)->pluck('killmail_id');
        $loss_times = $loss_mail_ids->isNotEmpty()
            ? KD::whereIn('killmail_id', $loss_mail_ids)
                ->whereBetween('killmail_time', [$start, $end])
                ->pluck('killmail_time')
            : collect();

        // Wins: any of our chars is an attacker (dedupe killmail_id), then times in window
        $win_mail_ids = KA::whereIn('character_id', $char_ids)->distinct()->pluck('killmail_id');
        $win_times = $win_mail_ids->isNotEmpty()
            ? KD::whereIn('killmail_id', $win_mail_ids)
                ->whereBetween('killmail_time', [$start, $end])
                ->pluck('killmail_time')
            : collect();

        // Per-day counts (1..EOM), then cumulative
        $wins_per_day  = array_fill(1, $days_in_month, 0);
        $total_per_day = array_fill(1, $days_in_month, 0);

        foreach ($win_times as $ts) {
            $d = Carbon::parse($ts, 'UTC')->day;
            $wins_per_day[$d]  += 1;
            $total_per_day[$d] += 1;
        }
        foreach ($loss_times as $ts) {
            $d = Carbon::parse($ts, 'UTC')->day;
            $total_per_day[$d] += 1;
        }

        $cum_wins = $cum_total = [];
        $acc_w = 0; $acc_t = 0;
        for ($d = 1; $d <= $days_in_month; $d++) {
            $acc_w += $wins_per_day[$d];
            $acc_t += $total_per_day[$d];
            $cum_wins[]  = $acc_w;
            $cum_total[] = $acc_t;
        }

        $total_wins   = $win_times->count();
        $total_losses = $loss_times->count();

        return [
            'days'            => range(1, $days_in_month),
            'cum_wins'        => $cum_wins,              // cumulative wins by day
            'cum_total'       => $cum_total,             // cumulative wins+losses by day
            'total_wins'      => $total_wins,
            'total_losses'    => $total_losses,
            'total_killmails' => $total_wins + $total_losses,
            'month'           => $start->format('Y-m'),
        ];
    }

    // Example: expose a route JSON for your chart to consume
    public function monthlyKillmailSeries()
    {
        return response()->json($this->buildMonthlyKillmailCumulative());
    }

    private function buildMonthlyMiningMtd(): array
    {
        $user = Auth::user();

        // All linked character IDs (disambiguate to avoid ambiguous select)
        $char_ids = $user->characters()
            ->select('character_infos.character_id')
            ->distinct()
            ->pluck('character_infos.character_id');

        $now = Carbon::now('UTC');
        $year = $now->year;
        $month = $now->month;
        $days_in_month = $now->copy()->endOfMonth()->day;
        $today_day = $now->day;

        // Pull this month’s mining rows
        $rows = CM::whereIn('character_id', $char_ids)
            ->where('year', $year)
            ->where('month', $month)
            ->get(['date', 'type_id', 'quantity']);

        if ($rows->isEmpty()) {
            return [
                'month'    => sprintf('%04d-%02d', $year, $month),
                'days'     => range(1, $days_in_month),
                'asteroid' => array_fill(0, $days_in_month, 0),
                'ice'      => array_fill(0, $days_in_month, 0),
                'moon'     => array_fill(0, $days_in_month, 0),
                'cum_isk'  => array_fill(0, $days_in_month, 0.0),
            ];
        }

        // ----- Category mapping via SDE groups -----
        $type_ids = $rows->pluck('type_id')->unique();
        $types  = InvType::whereIn('typeID', $type_ids)->get(['typeID', 'groupID']);
        $groups = InvGroup::whereIn('groupID', $types->pluck('groupID')->unique())
            ->get(['groupID', 'groupName'])
            ->keyBy('groupID');

        $categoryOf = function (int $type_id) use ($types, $groups): string {
            $t = $types->firstWhere('typeID', $type_id);
            $g = $t ? $groups->get($t->groupID) : null;
            $name = strtolower($g->groupName ?? '');
            if (strpos($name, 'ice') !== false)  return 'Ice';
            if (strpos($name, 'moon') !== false) return 'Moon';
            return 'Asteroid';
        };

        // ----- Price map from universe_prices/market_prices -----
        $priceMap = $this->priceMapForTypeIds($type_ids,['adjusted_price']);

        // Per-day buckets
        $perDayUnits = array_fill(1, $days_in_month, ['Asteroid' => 0, 'Ice' => 0, 'Moon' => 0]);
        $perDayIsk   = array_fill(1, $days_in_month, 0.0);

        foreach ($rows as $r) {
            $day = Carbon::parse($r->date, 'UTC')->day; // 1..EOM
            $qty = (int) $r->quantity;
            $cat = $categoryOf((int) $r->type_id);

            $perDayUnits[$day][$cat] += $qty;

            $px = (float) ($priceMap[(int) $r->type_id] ?? 0.0);
            $perDayIsk[$day] += $px * $qty;
        }

        // Build series (bars visible through today; line is cumulative ISK through today)
        $days     = range(1, $days_in_month);
        $asteroid = []; $ice = []; $moon = []; $cumISK = [];
        $running  = 0.0;

        foreach ($days as $d) {
            $a = $perDayUnits[$d]['Asteroid'];
            $i = $perDayUnits[$d]['Ice'];
            $m = $perDayUnits[$d]['Moon'];

            $asteroid[] = ($d <= $today_day) ? $a : 0;
            $ice[]      = ($d <= $today_day) ? $i : 0;
            $moon[]     = ($d <= $today_day) ? $m : 0;

            if ($d <= $today_day) $running += $perDayIsk[$d];
            $cumISK[] = $running;
        }

        // NEW: average ISK/day across days elapsed (avoid dividing by 0)
        $days_elapsed   = max(0, min($today_day, $days_in_month));
        $mtd_isk        = $running; // same as end($cumISK)
        $avg_isk_per_day = $days_elapsed > 0 ? ($mtd_isk / $days_elapsed) : 0.0;

        return [
            'month'    => sprintf('%04d-%02d', $year, $month),
            'days'     => $days,
            'asteroid' => $asteroid,
            'ice'      => $ice,
            'moon'     => $moon,
            'cum_isk'  => $cumISK,
            'mtd_isk' => $mtd_isk,
            'avg_isk_per_day' => $avg_isk_per_day,
        ];
    }

    /**
     * Price helper: returns type_id => price (float).
     * Uses the same DB connection as CharacterMining; prefers universe_prices, else market_prices.
     * Picks: average_price -> adjusted_price -> average -> sell_price -> buy_price.
     */
    private function priceMapForTypeIds($type_ids, array $preference = ['sell_price','average_price','adjusted_price','average','buy_price'])
    {
        $ids = collect($type_ids)->unique()->values();
        if ($ids->isEmpty()) return collect();

        $conn  = (new CM)->getConnectionName();
        $table = \Schema::connection($conn)->hasTable('universe_prices') ? 'universe_prices'
            : (\Schema::connection($conn)->hasTable('market_prices')   ? 'market_prices'   : null);

        if (!$table) return collect()->mapWithKeys(fn($id)=>[(int)$id=>0.0]);

        $cols = array_unique(array_merge(['type_id'], $preference)); // only fetch what we use
        $rows = \DB::connection($conn)->table($table)
            ->whereIn('type_id', $ids)
            ->get($cols)
            ->keyBy('type_id');

        return $ids->mapWithKeys(function ($id) use ($rows, $preference) {
            $r = $rows->get($id);
            $price = 0.0;
            if ($r) {
                foreach ($preference as $col) {
                    if (isset($r->{$col}) && $r->{$col} !== null) {
                        $candidate = (float) $r->{$col};
                        // optional: skip zeros/negatives
                        if ($candidate > 0) { $price = $candidate; break; }
                    }
                }
            }
            return [(int)$id => $price];
        });
    }

    private function buildWalletBalanceLast30d(): array
    {
        $user = Auth::user();

        // All linked character IDs (disambiguate)
        $char_ids = $user->characters()
            ->select('character_infos.character_id')
            ->distinct()
            ->pluck('character_infos.character_id');

        $end   = Carbon::now('UTC')->endOfDay();
        $start = Carbon::now('UTC')->subDays(29)->startOfDay();

        // Build day index (YYYY-MM-DD -> 0..29)
        $period = new \DatePeriod($start, new \DateInterval('P1D'), $end->copy()->addDay());
        $days = [];
        $ix = [];
        $i = 0;
        foreach ($period as $d) {
            $key = $d->format('Y-m-d');
            $days[] = $key;
            $ix[$key] = $i++;
        }
        $per_day = array_fill(0, count($days), 0.0);

        if ($char_ids->isNotEmpty()) {
            // If your journal uses ref_date instead of date, change the select below to ['ref_date as date','amount']
            $rows = CWJ::whereIn('character_id', $char_ids)
                ->whereBetween('date', [$start->toDateTimeString(), $end->toDateTimeString()])
                ->get(['date','amount']);

            foreach ($rows as $r) {
                $k = Carbon::parse($r->date, 'UTC')->format('Y-m-d');
                if (isset($ix[$k])) {
                    $per_day[$ix[$k]] += (float) $r->amount; // inflow +, outflow -
                }
            }
        }

        // Today's real total (sum across characters)
        $today_total = $char_ids->isNotEmpty()
            ? (float) CWB::whereIn('character_id', $char_ids)->sum('balance')
            : 0.0;

        // Reconstruct absolute balance series that ends at today's total
        $sum_last30 = array_sum($per_day);
        $start_balance = $today_total - $sum_last30;

        $balances = [];
        $running  = $start_balance;
        foreach ($per_day as $delta) {
            $running += $delta;
            $balances[] = $running;
        }

        return [
            'days'     => $days,        // e.g. ['2025-07-12', ... '2025-08-10']
            'balances' => $balances,    // absolute total per day
            'today'    => $today_total, // convenience for header
            'updated'  => Carbon::now('UTC')->toIso8601String(),
        ];
    }

    private function buildWalletPerCharacter(): array
    {
        $user = Auth::user();

        // Linked character IDs (disambiguate the column)
        $charIds = $user->characters()
            ->select('character_infos.character_id')
            ->distinct()
            ->pluck('character_infos.character_id');

        if ($charIds->isEmpty()) {
            return ['rows' => [], 'updated' => now('UTC')->toIso8601String()];
        }

        // Grab balances
        $balances = CWB::whereIn('character_id', $charIds)
            ->get(['character_id', 'balance']);

        // Get names from character_infos on the same connection as CWB
        $conn = (new CWB)->getConnectionName();
        $names = DB::connection($conn)
            ->table('character_infos')
            ->whereIn('character_id', $balances->pluck('character_id'))
            ->pluck('name', 'character_id');

        // Build rows: [ "Character Name", balance ]
        $rows = $balances
            ->map(fn ($r) => [ (string) ($names[$r->character_id] ?? $r->character_id), (float) $r->balance ])
            ->sortByDesc(fn ($row) => $row[1])
            ->values()
            ->all();

        return [
            'rows'    => $rows,
            'updated' => now('UTC')->toIso8601String(),
        ];
    }

    /**
     * Build hierarchical allocation across Region → System → LocationType → Location → ItemBucket.
     * - Uses SDE + SeAT caches only (no ESI).
     * - Counts ALL assets (containers + contents).
     * - Labels are clean; tooltips show abbreviated ISK.
     */
    private function buildAssetAllocationHierarchy(): array
{
    $user = Auth::user();
    $now  = now('UTC')->toIso8601String();

    // Linked characters
    $char_ids = $user->characters()
        ->select('character_infos.character_id')
        ->distinct()
        ->pluck('character_infos.character_id');

    if ($char_ids->isEmpty()) {
        return ['nodes' => [], 'updated' => $now];
    }

    // Assets (lean columns)
    $CA        = new CA;
    $assetsTbl = $CA->getTable();
    $eveConn   = $CA->getConnectionName() ?: config('database.default');
    $sdeConn   = config('database.connections.sde') ? 'sde' : $eveConn;

    $cols = ['character_id', 'type_id', 'quantity', 'location_id'];
    $has_item_id = Schema::connection($eveConn)->hasColumn($assetsTbl, 'item_id');
    if ($has_item_id) $cols[] = 'item_id';

    $assets = CA::whereIn('character_id', $char_ids)->get($cols);
    if ($assets->isEmpty()) {
        return ['nodes' => [], 'updated' => $now];
    }

    // ---- Price map ----
    $type_ids = $assets->pluck('type_id')->unique();
    $priceMap = $this->priceMapForTypeIds(
        $type_ids,
        ['adjusted_price','average_price','sell_price','average','buy_price']
    );

    // ---- Item bucket mapping via SDE groups ----
    $types  = InvType::whereIn('typeID', $type_ids)->get(['typeID','groupID']);
    $groups = InvGroup::whereIn('groupID', $types->pluck('groupID')->unique())
        ->get(['groupID','groupName'])->keyBy('groupID');

    $bucketOf = function (int $type_id) use ($types, $groups): string {
        $t = $types->firstWhere('typeID', $type_id);
        $g = $t ? ($groups[$t->groupID] ?? null) : null;
        $name = strtolower($g->groupName ?? '');
        if (str_contains($name,'ship'))       return 'Ships';
        if (str_contains($name,'module'))     return 'Modules';
        if (str_contains($name,'ammunition')) return 'Ammo';
        if (str_contains($name,'charge'))     return 'Ammo';
        if (str_contains($name,'blueprint'))  return 'Blueprints';
        if (str_contains($name,'mineral'))    return 'Minerals';
        if (str_contains($name,'ore'))        return 'Ore';
        if (str_contains($name,'planetary') || str_contains($name,'pi')) return 'PI';
        if (str_contains($name,'salvage'))    return 'Salvage';
        return 'Other';
    };

    // ---- Walk containers up to the top "place" (station/structure/system) ----
    $itemParent = [];
    if ($has_item_id) {
        foreach ($assets as $a) {
            if (!is_null($a->item_id)) {
                $itemParent[(string)$a->item_id] = (string)$a->location_id;
            }
        }
    }
    $topLocationId = function (string $loc) use ($itemParent): string {
        $guard = 0;
        while (isset($itemParent[$loc]) && $guard++ < 25) {
            $loc = $itemParent[$loc];
        }
        return $loc;
    };

    // ---- Aggregate ISK by: topLoc → bucket ----
    $byLocBucket = [];  // [locId => [bucket => isk]]
    $topLocIds   = [];

    foreach ($assets as $a) {
        $qty = (int) $a->quantity;
        if ($qty <= 0) continue;
        $px = (float) ($priceMap[(int)$a->type_id] ?? 0.0);
        if ($px <= 0) continue;

        $isk    = $px * $qty;
        $bucket = $bucketOf((int)$a->type_id);
        $locTop = $topLocationId((string)$a->location_id);

        $byLocBucket[$locTop][$bucket] = ($byLocBucket[$locTop][$bucket] ?? 0.0) + $isk;
        $topLocIds[$locTop] = true;
    }

    if (empty($byLocBucket)) {
        return ['nodes' => [], 'updated' => $now];
    }

    // ---- Resolve location meta (SDE + SeAT caches only) ----
    $locMeta = $this->resolveLocationMeta(array_keys($topLocIds), $eveConn, $sdeConn);

    // ---- Build hierarchy sums ----
    $rootId    = 'root';
    $nodes     = [];
    $sumRegion = [];   // [regionId => total]
    $sumSystem = [];   // [regionId][systemId] => total
    $sumType   = [];   // [regionId][systemId][locType] => total
    $sumLoc    = [];   // [locId => total]

    foreach ($byLocBucket as $locId => $buckets) {
        $meta = $locMeta[$locId] ?? [
            'loc_type'     => 'Unknown',
            'loc_name'     => "Unknown Location",
            'system_id'    => 'unknown',
            'system_name'  => 'Unknown System',
            'region_id'    => 'unknown',
            'region_name'  => 'Unknown Region',
        ];
        $locTotal = array_sum($buckets);

        $sumLoc[$locId] = $locTotal;
        $r = (string)$meta['region_id'];
        $s = (string)$meta['system_id'];
        $t = (string)$meta['loc_type'];

        $sumRegion[$r]           = ($sumRegion[$r] ?? 0.0) + $locTotal;
        $sumSystem[$r][$s]       = ($sumSystem[$r][$s] ?? 0.0) + $locTotal;
        $sumType[$r][$s][$t]     = ($sumType[$r][$s][$t] ?? 0.0) + $locTotal;
    }

    // ---- Nodes (NO tooltips / NO abbreviations; JS handles that) ----
    // Root
    $rootColor = array_sum($sumRegion);
    $nodes[] = ['id' => $rootId, 'parent' => null, 'label' => 'Assets', 'value' => 0.0, 'color' => (float)$rootColor];

    // Regions → Systems → LocationType → Location → ItemBucket
    foreach ($sumRegion as $regionId => $rTotal) {
        $rName = ($regionId !== 'unknown')
            ? ($locMeta['__regions__'][$regionId] ?? "Region $regionId")
            : 'Unknown Region';

        $rid = "region:$regionId";
        $nodes[] = ['id' => $rid, 'parent' => $rootId, 'label' => $rName, 'value' => 0.0, 'color' => (float)$rTotal];

        foreach ($sumSystem[$regionId] as $systemId => $sTotal) {
            $sName = ($systemId !== 'unknown')
                ? ($locMeta['__systems__'][$systemId] ?? "System $systemId")
                : 'Unknown System';

            $sid = "system:$systemId";
            $nodes[] = ['id' => $sid, 'parent' => $rid, 'label' => $sName, 'value' => 0.0, 'color' => (float)$sTotal];

            foreach ($sumType[$regionId][$systemId] as $locType => $tTotal) {
                $tid = "ltype:$locType@sys:$systemId";
                $nodes[] = ['id' => $tid, 'parent' => $sid, 'label' => $locType, 'value' => 0.0, 'color' => (float)$tTotal];

                // Locations in this (region, system, type)
                foreach ($byLocBucket as $locId => $buckets) {
                    $meta = $locMeta[$locId] ?? null;
                    if (!$meta) continue;
                    if ((string)$meta['region_id'] !== (string)$regionId) continue;
                    if ((string)$meta['system_id'] !== (string)$systemId) continue;
                    if ((string)$meta['loc_type']  !== (string)$locType)  continue;

                    $lid   = "loc:$locId";
                    $lname = $meta['loc_name'];
                    $ltot  = (float)($sumLoc[$locId] ?? 0.0);

                    $nodes[] = ['id' => $lid, 'parent' => $tid, 'label' => $lname, 'value' => 0.0, 'color' => $ltot];

                    foreach ($buckets as $bucket => $isk) {
                        $bid = "bucket:$bucket@loc:$locId";
                        $val = (float)$isk;
                        $nodes[] = ['id' => $bid, 'parent' => $lid, 'label' => $bucket, 'value' => $val, 'color' => $val];
                    }
                }
            }
        }
    }

    return ['nodes' => $nodes, 'updated' => $now];
}


    /**
     * Resolve location metadata (NO ESI):
     * - NPC stations:   universe_stations (SeAT) → system_id
     * - Upwell structs: universe_structures (SeAT) → system_id
     * - Solar systems:  SDE mapSolarSystems
     * Then map system → region via SDE.
     *
     * @param array $locIds string|int location_id values (top-level)
     * @param string $eveConn
     * @param string $sdeConn
     * @return array  [locId => [loc_type,loc_name,system_id,system_name,region_id,region_name], '__systems__'=>[sysId=>name], '__regions__'=>[regId=>name]]
     */

private function resolveLocationMeta(array $locIds, ?string $eveConn = null, ?string $sdeConn = null): array
{
    // Normalize IDs
    $locIdsStr = array_values(array_unique(array_map('strval', $locIds)));
    $locIdsInt = array_map('intval', $locIdsStr);

    // Scan all configured connections for a given table and return the first that has it
    $allConnections = array_keys(config('database.connections') ?? []);
    $pickConn = function (string $table, array $prefs = []) use ($allConnections): ?string {
        $order = array_values(array_unique(array_merge($prefs, $allConnections)));
        foreach ($order as $conn) {
            if (!config("database.connections.$conn")) continue;
            try {
                if (Schema::connection($conn)->hasTable($table)) return $conn;
            } catch (\Throwable $e) {}
        }
        return null;
    };

    // Try preferred names first, then fall back to scanning everything
    $prefsEve = array_values(array_filter([$eveConn, 'eve', config('database.default')]));
    $prefsSde = array_values(array_filter([$sdeConn, 'sde', 'eve', config('database.default')]));

    $connStations     = $pickConn('universe_stations',       $prefsEve);
    $connStructures   = $pickConn('universe_structures',     $prefsEve);
    $connSystems      = $pickConn('universe_systems',        $prefsEve);
    $connRegions      = $pickConn('universe_regions',        $prefsEve);
    $connConst        = $pickConn('universe_constellations', $prefsEve);
    $connNames        = $pickConn('universe_names',          $prefsEve);
    $connStaStations  = $pickConn('staStations',             $prefsSde); // legacy SDE
    $connSdeSolar     = $pickConn('mapSolarSystems',         $prefsSde);
    $connSdeRegion    = $pickConn('mapRegions',              $prefsSde);

    // Detect system_id column on caches
    $sysColStations = null;
    if ($connStations) {
        $cols = Schema::connection($connStations)->getColumnListing('universe_stations');
        $sysColStations = in_array('system_id', $cols, true) ? 'system_id'
                        : (in_array('solar_system_id', $cols, true) ? 'solar_system_id' : null);
    }
    $sysColStructures = null;
    if ($connStructures) {
        $cols = Schema::connection($connStructures)->getColumnListing('universe_structures');
        $sysColStructures = in_array('system_id', $cols, true) ? 'system_id'
                          : (in_array('solar_system_id', $cols, true) ? 'solar_system_id' : null);
    }

    $meta      = [];
    $systemIds = [];

    // --- NPC stations (universe_stations) ---
    if ($connStations) {
        $q = DB::connection($connStations)->table('universe_stations')
            ->selectRaw('station_id as id, name')
            ->whereIn('station_id', $locIdsInt);
        if ($sysColStations) $q->addSelect(DB::raw("$sysColStations as system_id"));
        foreach ($q->get() as $r) {
            $id    = (string)$r->id;
            $sysId = isset($r->system_id) ? (string)$r->system_id : 'unknown';
            $meta[$id] = [
                'loc_type'    => 'NPC Station',
                'loc_name'    => $r->name ?: "Station $id",
                'system_id'   => $sysId,
                'system_name' => null,
                'region_id'   => null,
                'region_name' => null,
            ];
            if ($sysId !== 'unknown') $systemIds[$sysId] = true;
        }
    }

    // --- Fallback for stations: SDE staStations (legacy) ---
    if ($connStaStations) {
        $missing = array_values(array_diff($locIdsStr, array_keys($meta)));
        if (!empty($missing)) {
            $rows = DB::connection($connStaStations)->table('staStations')
                ->selectRaw('stationID as id, stationName as name, solarSystemID')
                ->whereIn('stationID', array_map('intval', $missing))
                ->get();
            foreach ($rows as $r) {
                $id = (string)$r->id;
                $meta[$id] = [
                    'loc_type'    => 'NPC Station',
                    'loc_name'    => $r->name ?: "Station $id",
                    'system_id'   => (string)$r->solarSystemID,
                    'system_name' => null,
                    'region_id'   => null,
                    'region_name' => null,
                ];
                $systemIds[(string)$r->solarSystemID] = true;
            }
        }
    }

    // --- Upwell structures (universe_structures) ---
    if ($connStructures) {
        $missing = array_values(array_diff($locIdsStr, array_keys($meta)));
        if (!empty($missing)) {
            $q = DB::connection($connStructures)->table('universe_structures')
                ->selectRaw('structure_id as id, name')
                ->whereIn('structure_id', array_map('intval', $missing));
            if ($sysColStructures) $q->addSelect(DB::raw("$sysColStructures as system_id"));
            foreach ($q->get() as $r) {
                $id    = (string)$r->id;
                $sysId = isset($r->system_id) ? (string)$r->system_id : 'unknown';
                $meta[$id] = [
                    'loc_type'    => 'Upwell',
                    'loc_name'    => $r->name ?: "Structure $id",
                    'system_id'   => $sysId,
                    'system_name' => null,
                    'region_id'   => null,
                    'region_name' => null,
                ];
                if ($sysId !== 'unknown') $systemIds[$sysId] = true;
            }
        }
    }

    // --- “Assets in space”: treat as Solar System nodes ---
    $missing = array_values(array_diff($locIdsStr, array_keys($meta)));
    if (!empty($missing)) {
        if ($connSdeSolar) {
            $rows = DB::connection($connSdeSolar)->table('mapSolarSystems')
                ->selectRaw('solarSystemID as id')
                ->whereIn('solarSystemID', array_map('intval', $missing))->get();
            foreach ($rows as $r) {
                $id = (string)$r->id;
                $meta[$id] = [
                    'loc_type'    => 'Solar System',
                    'loc_name'    => 'System Space',
                    'system_id'   => $id,
                    'system_name' => null,
                    'region_id'   => null,
                    'region_name' => null,
                ];
                $systemIds[$id] = true;
            }
        } elseif ($connSystems) {
            $rows = DB::connection($connSystems)->table('universe_systems')
                ->selectRaw('system_id as id')
                ->whereIn('system_id', array_map('intval', $missing))->get();
            foreach ($rows as $r) {
                $id = (string)$r->id;
                $meta[$id] = [
                    'loc_type'    => 'Solar System',
                    'loc_name'    => 'System Space',
                    'system_id'   => $id,
                    'system_name' => null,
                    'region_id'   => null,
                    'region_name' => null,
                ];
                $systemIds[$id] = true;
            }
        }
    }

    // --- Any remaining: “Other” ---
    foreach (array_values(array_diff($locIdsStr, array_keys($meta))) as $id) {
        $meta[$id] = [
            'loc_type'    => 'Other',
            'loc_name'    => "Location $id",
            'system_id'   => 'unknown',
            'system_name' => null,
            'region_id'   => 'unknown',
            'region_name' => null,
        ];
    }

    // --- Build system → (name, region_id) ---
    $systems = []; // [sysId => ['name'=>..., 'region_id'=>...]]
    $regions = []; // [regId => name]

    if (!empty($systemIds)) {
        $sysIdsInt = array_map('intval', array_keys($systemIds));

        if ($connSdeSolar && $connSdeRegion) {
            // SDE path
            $sysRows = DB::connection($connSdeSolar)->table('mapSolarSystems')
                ->selectRaw('solarSystemID as id, solarSystemName as name, regionID')
                ->whereIn('solarSystemID', $sysIdsInt)->get();
            $regIds = [];
            foreach ($sysRows as $r) {
                $systems[(string)$r->id] = ['name' => $r->name, 'region_id' => (string)$r->regionID];
                $regIds[(string)$r->regionID] = true;
            }
            if (!empty($regIds)) {
                $regRows = DB::connection($connSdeRegion)->table('mapRegions')
                    ->selectRaw('regionID as id, regionName as name')
                    ->whereIn('regionID', array_map('intval', array_keys($regIds)))->get();
                foreach ($regRows as $r) $regions[(string)$r->id] = $r->name;
            }

        } elseif ($connSystems) {
            // SeAT caches path
            $sysCols = Schema::connection($connSystems)->getColumnListing('universe_systems');
            $hasSysRegion = in_array('region_id', $sysCols, true);
            $hasConst     = in_array('constellation_id', $sysCols, true) && $connConst;

            $sysRows = DB::connection($connSystems)->table('universe_systems')
                ->whereIn('system_id', $sysIdsInt)->get();

            $regionIds = [];
            $constIds  = [];

            foreach ($sysRows as $r) {
                $sid = (string)$r->system_id;
                $systems[$sid] = ['name' => ($r->name ?? "System $sid"), 'region_id' => 'unknown'];
                if ($hasSysRegion && !empty($r->region_id)) {
                    $systems[$sid]['region_id'] = (string)$r->region_id;
                    $regionIds[(string)$r->region_id] = true;
                } elseif ($hasConst && !empty($r->constellation_id)) {
                    $constIds[(string)$r->constellation_id] = true;
                    $systems[$sid]['_constellation'] = (string)$r->constellation_id;
                }
            }

            if (!empty($constIds)) {
                $cRows = DB::connection($connConst)->table('universe_constellations')
                    ->selectRaw('constellation_id, region_id')
                    ->whereIn('constellation_id', array_map('intval', array_keys($constIds)))->get();
                $cMap = [];
                foreach ($cRows as $cr) $cMap[(string)$cr->constellation_id] = (string)$cr->region_id;
                foreach ($systems as $sid => &$s) {
                    if (($s['_constellation'] ?? null) && isset($cMap[$s['_constellation']])) {
                        $s['region_id'] = $cMap[$s['_constellation']];
                        $regionIds[$s['region_id']] = true;
                    }
                    unset($s['_constellation']);
                }
                unset($s);
            }

            if (!empty($regionIds) && $connRegions) {
                $regRows = DB::connection($connRegions)->table('universe_regions')
                    ->selectRaw('region_id as id, name')
                    ->whereIn('region_id', array_map('intval', array_keys($regionIds)))->get();
                foreach ($regRows as $r) $regions[(string)$r->id] = $r->name ?? ("Region {$r->id}");
            }
        }

        // Optional: names via cache table if present
        if ($connNames) {
            // system names
            $needSys = [];
            foreach ($systems as $sid => $s) if (empty($s['name']) || str_starts_with($s['name'], 'System ')) $needSys[] = (int)$sid;
            if (!empty($needSys)) {
                $nRows = DB::connection($connNames)->table('universe_names')
                    ->selectRaw('entity_id, name')->whereIn('entity_id', $needSys)->get();
                foreach ($nRows as $n) {
                    $sid = (string)$n->entity_id;
                    if (isset($systems[$sid])) $systems[$sid]['name'] = $n->name;
                }
            }
            // region names
            $needReg = [];
            foreach ($systems as $sid => $s) if (!empty($s['region_id']) && $s['region_id'] !== 'unknown' && empty($regions[$s['region_id']])) $needReg[] = (int)$s['region_id'];
            if (!empty($needReg)) {
                $nRows = DB::connection($connNames)->table('universe_names')
                    ->selectRaw('entity_id, name')->whereIn('entity_id', array_unique($needReg))->get();
                foreach ($nRows as $n) $regions[(string)$n->entity_id] = $n->name;
            }
        }
    }

    // Fill back into meta
    foreach ($meta as $id => &$m) {
        $sid = (string)$m['system_id'];
        if ($sid !== 'unknown' && isset($systems[$sid])) {
            $m['system_name'] = $systems[$sid]['name'] ?? "System $sid";
            $rid              = $systems[$sid]['region_id'] ?? 'unknown';
            $m['region_id']   = $rid;
            $m['region_name'] = $regions[$rid] ?? ($rid !== 'unknown' ? "Region $rid" : 'Unknown Region');
        } else {
            $m['system_name'] = $m['system_name'] ?? 'Unknown System';
            $m['region_id']   = $m['region_id']   ?? 'unknown';
            $m['region_name'] = $m['region_name'] ?? 'Unknown Region';
        }
    }
    unset($m);

    $sysNames = [];
    foreach ($systems as $sid => $v) $sysNames[$sid] = $v['name'] ?? "System $sid";
    $meta['__systems__'] = $sysNames;
    $meta['__regions__'] = $regions;

    return $meta;
}




    

}
