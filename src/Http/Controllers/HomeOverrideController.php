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

        $allocation = $this->buildAssetAllocationTreemap();

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

    private function buildAssetAllocationTreemap(): array
{
    $user = Auth::user();

    // Linked characters
    $char_ids = $user->characters()
        ->select('character_infos.character_id')
        ->distinct()
        ->pluck('character_infos.character_id');

    $now = now('UTC')->toIso8601String();
    if ($char_ids->isEmpty()) {
        return ['leaves' => [], 'updated' => $now];
    }

    // Pull current snapshot of assets
    $assetsTable = (new CA)->getTable();
    $eveConn     = (new CA)->getConnectionName();

    $cols = ['character_id', 'type_id', 'quantity', 'location_id'];
    $has_item_id = Schema::connection($eveConn)->hasColumn($assetsTable, 'item_id');
    if ($has_item_id) $cols[] = 'item_id';

    // NOTE: we do NOT rely on location_type filtering anymore.
    $assets = CA::whereIn('character_id', $char_ids)->get($cols);
    if ($assets->isEmpty()) {
        return ['leaves' => [], 'updated' => $now];
    }

    // ---- Price map ----
    $type_ids = $assets->pluck('type_id')->unique();
    $priceMap = $this->priceMapForTypeIds(
        $type_ids,
        ['adjusted_price','average_price','sell_price','average','buy_price']
    );

    // ---- Category mapping via SDE groups ----
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

    // ---- Build container maps to "walk up" to the top-level place ----
    // itemId -> parentLocationId (string keys)
    $itemParent = [];
    if ($has_item_id) {
        foreach ($assets as $a) {
            if (!is_null($a->item_id)) {
                $itemParent[(string)$a->item_id] = (string)$a->location_id;
            }
        }
    }

    // set of item_ids that are referenced by some asset's location_id => this asset is a container
    $referencedItemIds = [];
    if ($has_item_id) {
        $locIdsAsStr = $assets->pluck('location_id')->map(fn($v)=>(string)$v)->unique()->flip();
        foreach ($assets as $a) {
            if (!is_null($a->item_id) && isset($locIdsAsStr[(string)$a->item_id])) {
                $referencedItemIds[(string)$a->item_id] = true;
            }
        }
    }

    // helper: follow location_id up through containers until we hit a real place id
    $topLocationId = function (string $loc) use ($itemParent): string {
        $guard = 0;
        while (isset($itemParent[$loc]) && $guard++ < 20) {
            $loc = $itemParent[$loc]; // climb: container -> its parent location_id
        }
        return $loc; // station_id / structure_id / solarSystemID / leftover
    };

    // ---- Roll up ISK by (top_location_id, bucket) ----
    $byLocBucket = [];
    $topLocIds = [];

    foreach ($assets as $a) {
        $qty = (int) $a->quantity;
        if ($qty <= 0) continue;

        $px  = (float) ($priceMap[(int) $a->type_id] ?? 0.0);
        if ($px <= 0) continue;

        // Skip container assets themselves (but keep their contents)
        if ($has_item_id && isset($referencedItemIds[(string)($a->item_id ?? '')])) {
            continue;
        }

        // Attribute ALL assets (even nested) to the top-level place (station/structure/system).
        $locTop = $topLocationId((string)$a->location_id);
        $bucket = $bucketOf((int) $a->type_id);
        $isk    = $px * $qty;

        $byLocBucket[$locTop][$bucket] = ($byLocBucket[$locTop][$bucket] ?? 0.0) + $isk;
        $topLocIds[$locTop] = true;
    }

    if (empty($byLocBucket)) {
        return ['leaves' => [], 'updated' => $now];
    }

    // ---- Resolve location names for the *top-level* ids ----
    $locIds   = array_keys($topLocIds);
    $locNames = $this->resolveLocationNames($locIds); // structures → stations → systems → universe_names → ESI

    // ---- Build leaves: [label, parent (location name), isk] ----
    $leaves = [];
    foreach ($byLocBucket as $locId => $buckets) {
        $locName = $locNames[$locId] ?? ('Location ' . $locId);
        foreach ($buckets as $bucket => $isk) {
            $leaves[] = [
                'label' => sprintf('%s (%s)', $bucket, $locName),
                'loc'   => $locName,
                'isk'   => (float) $isk,
            ];
        }
    }

    // Sort largest first (optional)
    usort($leaves, fn($a,$b)=> $b['isk'] <=> $a['isk']);

    return [
        'leaves'  => $leaves,
        'updated' => $now,
    ];
}

/**
 * Resolve raw location IDs to human names.
 * Order: universe_structures → universe_stations → SDE mapSolarSystems → universe_names → live ESI (structures).
 *
 * @param array<int|string> $ids
 * @return array<string,string> [location_id(string) => name]
 */
private function resolveLocationNames(array $ids): array
{
    if (empty($ids)) return [];

    // Stable string keys (bigint-safe) + ints for queries
    $idsStr = array_values(array_unique(array_map('strval', $ids)));
    $idsInt = array_map('intval', $idsStr);

    $eveConn = (new CA)->getConnectionName();
    $names   = [];

    // ---------- 1) Player structures (local cache) ----------
    if (Schema::connection($eveConn)->hasTable('universe_structures')) {
        try {
            $rows = DB::connection($eveConn)->table('universe_structures')
                ->whereIn('structure_id', $idsInt)
                ->pluck('name', 'structure_id');
            foreach ($rows as $k => $v) $names[(string)$k] = $v;
        } catch (\Throwable $e) {}
    }

    // ---------- 2) NPC stations (prefer SeAT’s universe_stations) ----------
    if (Schema::connection($eveConn)->hasTable('universe_stations')) {
        try {
            $rows = DB::connection($eveConn)->table('universe_stations')
                ->whereIn('station_id', $idsInt)
                ->pluck('name', 'station_id');
            foreach ($rows as $k => $v) $names[(string)$k] = $v;
        } catch (\Throwable $e) {}
    }

    // Fallback to SDE station table if present
    if (class_exists(\App\Models\Sde\StaStation::class)) {
        try {
            $missingForStations = array_map('intval', array_values(array_diff($idsStr, array_keys($names))));
            if (!empty($missingForStations)) {
                $rows = \App\Models\Sde\StaStation::whereIn('stationID', $missingForStations)
                    ->pluck('stationName', 'stationID');
                foreach ($rows as $k => $v) $names[(string)$k] = $v;
            }
        } catch (\Throwable $e) {}
    }

    // ---------- 3) Solar systems (SDE) ----------
    if (class_exists(\App\Models\Sde\MapSolarSystem::class)) {
        try {
            $missingForSystems = array_map('intval', array_values(array_diff($idsStr, array_keys($names))));
            if (!empty($missingForSystems)) {
                $rows = \App\Models\Sde\MapSolarSystem::whereIn('solarSystemID', $missingForSystems)
                    ->pluck('solarSystemName', 'solarSystemID');
                foreach ($rows as $k => $v) $names[(string)$k] = $v;
            }
        } catch (\Throwable $e) {}
    }

    // ---------- 4) universe_names (generic fallback cache) ----------
    if (Schema::connection($eveConn)->hasTable('universe_names')) {
        try {
            $missingGeneric = array_map('intval', array_values(array_diff($idsStr, array_keys($names))));
            if (!empty($missingGeneric)) {
                $rows = DB::connection($eveConn)->table('universe_names')
                    ->whereIn('entity_id', $missingGeneric)
                    ->pluck('name', 'entity_id');
                foreach ($rows as $k => $v) $names[(string)$k] = $v;
            }
        } catch (\Throwable $e) {}
    }

    // ---------- 5) Live ESI for any still-missing structures (best-effort) ----------
    $stillMissing = array_values(array_diff($idsStr, array_keys($names)));
    if (!empty($stillMissing)) {
        // Only try ESI for likely structures (>= 1e9). We avoid ranges for stations.
        $maybeStructures = array_filter($stillMissing, fn($x) => (int)$x >= 1000000000);
        if (!empty($maybeStructures)) {
            try {
                // Bind your OSMM ESI wrapper to the current Seat user
                /** @var \CapsuleCmdr\SeatOsmm\Support\Esi\EsiCall $esi */
                $esi = app(\CapsuleCmdr\SeatOsmm\Support\Esi\EsiCall::class)->withSeatUser(Auth::user());

                foreach ($maybeStructures as $sid) {
                    try {
                        $path = "/universe/structures/{$sid}/";
                        $resp = method_exists($esi, 'get')
                            ? $esi->get($path)
                            : (method_exists($esi, 'invoke') ? $esi->invoke('get', $path) : null);

                        if (is_array($resp) && !empty($resp['name'])) {
                            $names[$sid] = $resp['name'];
                        } elseif (is_object($resp) && !empty($resp->name)) {
                            $names[$sid] = $resp->name;
                        } elseif (is_string($resp)) {
                            // Some wrappers may return JSON string
                            $obj = json_decode($resp, false);
                            if ($obj && !empty($obj->name)) $names[$sid] = $obj->name;
                        }
                    } catch (\Throwable $e) {
                        // 403/404/5xx — ignore; fallback label below
                    }
                }
            } catch (\Throwable $e) {
                // ESI wrapper not available — ignore
            }
        }
    }

    // ---------- Final human fallbacks ----------
    foreach ($idsStr as $id) {
        if (!isset($names[$id])) {
            $names[$id] = ((int)$id >= 1000000000)
                ? "Unknown Structure (ID: {$id})"
                : "Location {$id}";
        }
    }

    return $names;
}


    

}
