<?php

namespace CapsuleCmdr\SeatOsmm\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Seat\Eseye\Eseye;
use Carbon\Carbon;
use Seat\Eveapi\Models\Killmails\KillmailDetail as KD;
use Seat\Eveapi\Models\Killmails\KillmailAttacker as KA;
use Seat\Eveapi\Models\Killmails\KillmailVictim as KV;
use Seat\Eveapi\Models\Killmails\KillmailItem as KMI;
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
use Illuminate\Support\Collection;


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

        $rows = $this->buildAssetTableRows($user);

        $allocation = [
            'nodes' => $this->buildTreemapNodes($rows),
            'updated' => $this->computeAllocationUpdated($user),
        ];

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

    // Linked character IDs
    $char_ids = $user->characters()
        ->select('character_infos.character_id')
        ->distinct()
        ->pluck('character_infos.character_id');

    // Month window (UTC)
    $now   = \Carbon\Carbon::now('UTC');
    $start = $now->copy()->startOfMonth();
    $end   = $now->copy()->endOfMonth();
    $days_in_month = $end->day;

    // Empty/default payload
    $empty = fn() => [
        'days'                => range(1, $days_in_month),
        'cum_wins'            => array_fill(0, $days_in_month, 0),
        'cum_total'           => array_fill(0, $days_in_month, 0),
        'total_wins'          => 0,
        'total_losses'        => 0,
        'total_killmails'     => 0,
        // ISK (computed)
        'daily_isk'           => array_fill(0, $days_in_month, 0.0),
        'cum_isk'             => array_fill(0, $days_in_month, 0.0),
        'daily_isk_destroyed' => array_fill(0, $days_in_month, 0.0),
        'daily_isk_lost'      => array_fill(0, $days_in_month, 0.0),
        'cum_isk_destroyed'   => array_fill(0, $days_in_month, 0.0),
        'cum_isk_lost'        => array_fill(0, $days_in_month, 0.0),
        'month'               => $start->format('Y-m'),
    ];

    if ($char_ids->isEmpty()) {
        return $empty();
    }

    // Losses (victim is ours) -> killmail_ids in month
    $loss_mail_ids = \Seat\Eveapi\Models\Killmails\KillmailVictim::whereIn('character_id', $char_ids)
        ->distinct()->pluck('killmail_id');

    $loss_details = $loss_mail_ids->isNotEmpty()
        ? \Seat\Eveapi\Models\Killmails\KillmailDetail::whereIn('killmail_id', $loss_mail_ids)
            ->whereBetween('killmail_time', [$start, $end])
            ->get(['killmail_id', 'killmail_time'])
        : collect();

    // Wins (any of ours is an attacker) -> killmail_ids in month
    $win_mail_ids = \Seat\Eveapi\Models\Killmails\KillmailAttacker::whereIn('character_id', $char_ids)
        ->distinct()->pluck('killmail_id');

    $win_details = $win_mail_ids->isNotEmpty()
        ? \Seat\Eveapi\Models\Killmails\KillmailDetail::whereIn('killmail_id', $win_mail_ids)
            ->whereBetween('killmail_time', [$start, $end])
            ->get(['killmail_id', 'killmail_time'])
        : collect();

    // Per-day counts (1..EOM)
    $wins_per_day  = array_fill(1, $days_in_month, 0);
    $total_per_day = array_fill(1, $days_in_month, 0);

    foreach ($win_details as $row) {
        $d = \Carbon\Carbon::parse($row->killmail_time, 'UTC')->day;
        $wins_per_day[$d]  += 1;
        $total_per_day[$d] += 1;
    }
    foreach ($loss_details as $row) {
        $d = \Carbon\Carbon::parse($row->killmail_time, 'UTC')->day;
        $total_per_day[$d] += 1;
    }

    // Cumulative counts
    $cum_wins = $cum_total = [];
    $acc_w = 0; $acc_t = 0;
    for ($d = 1; $d <= $days_in_month; $d++) {
        $acc_w += $wins_per_day[$d];
        $acc_t += $total_per_day[$d];
        $cum_wins[]  = $acc_w;
        $cum_total[] = $acc_t;
    }

    $total_wins   = $win_details->count();
    $total_losses = $loss_details->count();

    // ==============================
    // ISK: compute from victim ship + victim items
    // ==============================

    $isk_destroyed_per_day = array_fill(1, $days_in_month, 0.0); // our wins → enemy ISK destroyed
    $isk_lost_per_day      = array_fill(1, $days_in_month, 0.0); // our losses → our ISK lost

    // All killmail_ids in the month we care about
    $all_ids = $win_details->pluck('killmail_id')
        ->merge($loss_details->pluck('killmail_id'))
        ->unique()
        ->values()
        ->all();

    if (!empty($all_ids)) {
        // Victim hulls: [killmail_id => ship_type_id]
        $victims = \DB::table('killmail_victims')
            ->whereIn('killmail_id', $all_ids)
            ->pluck('ship_type_id', 'killmail_id');

        // Victim items (destroyed + dropped)
        $items = \DB::table('killmail_victim_items')
            ->whereIn('killmail_id', $all_ids)
            ->get(['killmail_id', 'item_type_id', 'quantity_destroyed', 'quantity_dropped']);

        // Build price map for all involved type_ids
        $type_ids = collect($victims->values()->all())
            ->merge($items->pluck('item_type_id'))
            ->filter()
            ->unique()
            ->values();

        // Uses your existing helper — returns type_id => price (float)
        $priceMap = $this->priceMapForTypeIds($type_ids,["adjusted_price"]);

        // Per-KM total value: victim ship + all items
        $kmValue = [];

        foreach ($victims as $kmid => $shipType) {
            $kmValue[$kmid] = (float) ($priceMap[(int) $shipType] ?? 0.0);
        }

        foreach ($items as $it) {
            $qty = (int) ($it->quantity_destroyed ?? 0) + (int) ($it->quantity_dropped ?? 0);
            if ($qty <= 0) continue;
            $px  = (float) ($priceMap[(int) $it->item_type_id] ?? 0.0);
            $kmValue[$it->killmail_id] = ($kmValue[$it->killmail_id] ?? 0.0) + ($px * $qty);
        }

        // Bucket by UTC day
        foreach ($win_details as $row) {
            $d = \Carbon\Carbon::parse($row->killmail_time, 'UTC')->day;
            $isk_destroyed_per_day[$d] += (float) ($kmValue[$row->killmail_id] ?? 0.0);
        }
        foreach ($loss_details as $row) {
            $d = \Carbon\Carbon::parse($row->killmail_time, 'UTC')->day;
            $isk_lost_per_day[$d] += (float) ($kmValue[$row->killmail_id] ?? 0.0);
        }
    }

    // Cumulative ISK
    $cum_isk_destroyed = $cum_isk_lost = [];
    $accD = 0.0; $accL = 0.0;
    for ($d = 1; $d <= $days_in_month; $d++) {
        $accD += $isk_destroyed_per_day[$d];
        $accL += $isk_lost_per_day[$d];
        $cum_isk_destroyed[] = $accD;
        $cum_isk_lost[]      = $accL;
    }

    // Alias "destroyed" series to the generic names your JS reads
    $daily_isk = array_values($isk_destroyed_per_day); // 0-based for JS
    $cum_isk   = $cum_isk_destroyed;

    return [
        'days'                => range(1, $days_in_month),
        'cum_wins'            => $cum_wins,
        'cum_total'           => $cum_total,
        'total_wins'          => $total_wins,
        'total_losses'        => $total_losses,
        'total_killmails'     => $total_wins + $total_losses,
        'month'               => $start->format('Y-m'),

        // ISK outputs (destroyed-focused; lost also included if you want to show later)
        'daily_isk'           => $daily_isk,
        'cum_isk'             => $cum_isk,
        'daily_isk_destroyed' => array_values($isk_destroyed_per_day),
        'daily_isk_lost'      => array_values($isk_lost_per_day),
        'cum_isk_destroyed'   => $cum_isk_destroyed,
        'cum_isk_lost'        => $cum_isk_lost,
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
    private function priceMapForTypeIds($type_ids, array $preference = [
    'average_price', 'adjusted_price', 'average', 'sell_price', 'buy_price'
])
    {
        $ids = collect($type_ids)
            ->filter(fn($v) => $v !== null)
            ->map(fn($v) => (int) $v)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return collect(); // => empty mapping
        }

        // Only fetch the columns we might use
        $cols = array_values(array_unique(array_merge(['type_id'], $preference)));

        // Default connection on purpose
        $rows = \DB::table('market_prices')
            ->whereIn('type_id', $ids)
            ->get($cols)
            ->keyBy('type_id');

        return $ids->mapWithKeys(function ($id) use ($rows, $preference) {
            $r = $rows->get($id);
            $price = 0.0;

            if ($r) {
                foreach ($preference as $col) {
                    if (isset($r->{$col}) && $r->{$col} !== null) {
                        $cand = (float) $r->{$col};
                        if ($cand > 0) { $price = $cand; break; }
                    }
                }
            }

            return [(int) $id => $price];
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

    protected function buildAssetTableRows($user): Collection
    {
        // Get user character ids
        $charIds = $user->characters()
            ->select('character_infos.character_id')
            ->distinct()
            ->pluck('character_infos.character_id');

        if ($charIds->isEmpty()) {
            return collect();
        }

        // Notes:
        // - We detect STATION vs STRUCTURE by whichever join matches the location_id
        // - system_id is derived from structure.solar_system_id OR station.system_id
        // - region_id from solar_systems.region_id
        // - price from market_prices.average_price (fallback 0)
        //
        // IMPORTANT: All table names below assume they live in your *default* connection.
        // If your actual names differ, just swap them.
        $q = DB::table('character_assets as a')
            ->whereIn('a.character_id', $charIds)
            // Type name
            ->leftJoin('invTypes as t', 't.typeID', '=', 'a.type_id')
            // Avg price (nullable)
            ->leftJoin('market_prices as mp', 'mp.type_id', '=', 'a.type_id')
            // Try structure first
            ->leftJoin('universe_structures as us', 'us.structure_id', '=', 'a.location_id')
            // Also try station (one of these will match; occasionally neither if asset safety or odd edge)
            ->leftJoin('universe_stations as st', 'st.station_id', '=', 'a.location_id')
            // System is from structure.solar_system_id OR station.system_id
            ->leftJoin('solar_systems as sys', function ($join) {
                $join->on('sys.system_id', '=', DB::raw('COALESCE(us.solar_system_id, st.system_id)'));
            })
            // Region from system.region_id
            ->leftJoin('regions as r', 'r.region_id', '=', 'sys.region_id')
            ->select([
                'a.item_id',
                'a.type_id',
                DB::raw('COALESCE(t.typeName, CONCAT("type:", a.type_id)) as type_name'),
                // Price * qty; fall back to 0 if no price
                DB::raw('COALESCE(mp.average_price, 0) * a.quantity as type_value'),
                // Station
                'st.station_id',
                DB::raw('COALESCE(st.name, NULL) as station_name'),
                // Structure
                'us.structure_id',
                DB::raw('COALESCE(us.name, NULL) as structure_name'),
                // System + region
                DB::raw('COALESCE(sys.name, NULL) as solar_system_name'),
                DB::raw('COALESCE(r.name, NULL) as region_name'),
                // Helpful extras (not in your table but sometimes useful)
                'a.quantity',
                'a.location_flag',
                'a.location_id',
            ]);

        // You had a .limit(5) in your prototype; remove or keep as you like:
        // $q->limit(5);

        $rows = collect($q->get())->map(function ($r) {
            // Ensure *either* station or structure column is null when not applicable (cosmetic)
            // (Left joins already handle this; keep as-is unless you want explicit casting.)
            return (object) [
                'item_id'           => $r->item_id,
                'type_id'           => $r->type_id,
                'type_name'         => $r->type_name,
                'type_value'        => (float) $r->type_value,
                'station_id'        => $r->station_id,
                'station_name'      => $r->station_name,
                'structure_id'      => $r->structure_id,
                'structure_name'    => $r->structure_name,
                'solar_system_name' => $r->solar_system_name,
                'region_name'       => $r->region_name,
            ];
        });

        return $rows;
    }

    /**
     * Build hierarchical nodes for your Google TreeMap:
     * Level 0: Root         -> "Assets"
     * Level 1: Region       -> region_name (or "Unknown Region")
     * Level 2: Solar System -> solar_system_name (or "Unknown System")
     * Level 3: Location     -> structure_name || station_name || "Unknown Location"
     * Value: sum of type_value for all assets under that node
     */
    protected function buildTreemapNodes(Collection $rows): array
    {
        $rootId = 'assets_root';
        $nodes = [];

        // Root
        $nodes[] = [
            'id'     => $rootId,
            'parent' => null,
            'label'  => 'Assets',
            'value'  => 0,   // non-leaf
        ];

        // Group by region → system → location
        // Define helpers that stabilize IDs and labels even when nulls happen
        $regKey = fn($name) => 'region:' . ($name ?: 'Unknown Region');
        $sysKey = fn($rName, $sName) => $regKey($rName) . '|system:' . ($sName ?: 'Unknown System');
        $locKey = function ($rName, $sName, $stName, $usName) use ($sysKey) {
            $loc = $usName ?: $stName ?: 'Unknown Location';
            return $sysKey($rName, $sName) . '|loc:' . $loc;
        };

        // 1) Region nodes
        $byRegion = $rows->groupBy(fn($r) => $regKey($r->region_name));
        foreach ($byRegion as $rk => $regionRows) {
            $regionName = $regionRows->first()->region_name ?? 'Unknown Region';
            $nodes[] = [
                'id'     => $rk,
                'parent' => $rootId,
                'label'  => $regionName ?: 'Unknown Region',
                'value'  => 0,
            ];

            // 2) System nodes under each region
            $bySystem = $regionRows->groupBy(fn($r) => $sysKey($r->region_name, $r->solar_system_name));
            foreach ($bySystem as $sk => $systemRows) {
                $systemName = $systemRows->first()->solar_system_name ?? 'Unknown System';
                $nodes[] = [
                    'id'     => $sk,
                    'parent' => $rk,
                    'label'  => $systemName ?: 'Unknown System',
                    'value'  => 0,
                ];

                // 3) Location nodes (station/structure) under each system
                $byLocation = $systemRows->groupBy(fn($r) => $locKey(
                    $r->region_name,
                    $r->solar_system_name,
                    $r->station_name,
                    $r->structure_name
                ));
                foreach ($byLocation as $lk => $locRows) {
                    $label = $locRows->first()->structure_name
                        ?? $locRows->first()->station_name
                        ?? 'Unknown Location';

                    // Sum the leaf values at the location level
                    $value = (float) $locRows->sum('type_value');

                    $nodes[] = [
                        'id'     => $lk,
                        'parent' => $sk,
                        'label'  => $label,
                        'value'  => $value, // leaves carry value in your treemap
                    ];
                }
            }
        }

        return $nodes;
    }

    private function computeAllocationUpdated($user): ?string
{
    // Collect char IDs once
    $charIds = $user->characters()
        ->select('character_infos.character_id')
        ->distinct()
        ->pluck('character_infos.character_id');

    // Latest asset update across the user’s characters
    $assetsUpdatedAt = $charIds->isNotEmpty()
        ? DB::table('character_assets')->whereIn('character_id', $charIds)->max('updated_at')
        : null;

    // Optional: include price table freshness if it has updated_at
    $pricesUpdatedAt = \Schema::hasTable('market_prices') && \Schema::hasColumn('market_prices','updated_at')
        ? DB::table('market_prices')->max('updated_at')
        : null;

    $freshest = collect([$assetsUpdatedAt, $pricesUpdatedAt])->filter()->max();

    return $freshest ? \Carbon\Carbon::parse($freshest, 'UTC')->toIso8601String() : null;
}





    

}
