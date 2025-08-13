<?php

namespace CapsuleCmdr\SeatOsmm\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
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
use Seat\Eveapi\Models\Skills\CharacterSkill as CS;
use Seat\Eseye\Containers\EsiAuthentication;
use Seat\Eseye\Exceptions\RequestFailedException;


class HomeOverrideController extends Controller
{

    public function eseyeDiag()
    {
        return response()->json([
            'psr18_interface'     => interface_exists(\Psr\Http\Client\ClientInterface::class),
            'guzzle7_adapter'     => class_exists(\Http\Adapter\Guzzle7\Client::class),
            'nyholm_psr17'        => class_exists(\Nyholm\Psr7\Factory\Psr17Factory::class),
            'vendor_g7_exists'    => is_dir(base_path('vendor/php-http/guzzle7-adapter')),
            'vendor_nyholm_exists'=> is_dir(base_path('vendor/nyholm/psr7')),
            'config_eseye_loaded' => !is_null(config('eseye')),
            'eseye_client_id'     => (string) config('eseye.esi.auth.client_id') !== '',
        ]);
    }

    public function index()
    {
        $user = Auth::user();

        $atWar = false;

        // Loop through each character
        // foreach ($user->characters as $char) {


        //     $esi = new Eseye();

        //     $info = $esi->invoke('get', '/characters/{character_id}/', [
        //         'character_id' => $char->character_id,
        //     ]);

        //     // Get an authenticated Eseye client from SeAT
        //     $esi = $char->getEsi();

        //     $corpId     = $info->corporation_id;
        //     $allianceId = property_exists($info, 'alliance_id') ? $info->alliance_id : null;

        //     if ($corpId && $this->isAtWar($esi, $corpId, $allianceId)) {
        //         $atWar = true;
        //         break;
        //     }
        // }


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
try{
        $publicInfo = $this->getPublicCharacterInfoData();
        } catch (\Throwable $e) {
    \Log::error('ESI crash', [
        'msg' => $e->getMessage(),
        'trace' => collect($e->getTrace())->take(12), // trim
    ]);
    throw $e; // or return JSON
}

        return view('seat-osmm::home', compact('homeElements','atWar','km','mining','walletBalance30','walletByChar','allocation','skillsChars','publicInfo'));
    }

    public function getPublicCharacterInfoData(): array|\stdClass
    {
        $user = auth()->user();
        $char = $user?->characters()->first();
        if (! $char) {
            return ['error' => 'No linked characters for current user.'];
        }

        try {
            // Try to use the character’s saved token; if none, fall back to unauth client
            $rt = DB::table('refresh_tokens')->where('character_id', $char->character_id)->first();

            if ($rt) {
                $scopes = json_decode($rt->scopes ?? '[]', true) ?: [];
                $auth = new EsiAuthentication ([
                    'access_token'  => $rt->token,               // <-- column is 'token'
                    'refresh_token' => $rt->refresh_token,
                    'token_expires' => $rt->expires_on,
                    'client_id'     => config('eseye.esi.auth.client_id'),
                    'secret'        => config('eseye.esi.auth.client_secret'),
                    'scopes'        => $scopes,                  // array, not string
                ]);
                $esi = app()->make(Eseye::class, ['authentication' => $auth]);
            } else {
                // No token required for the public endpoint
                $esi = app(Eseye::class);
            }

            return $esi->invoke('get', '/characters/{character_id}/', [
                'character_id' => $char->character_id,
            ]);
        } catch (RequestFailedException $e) {
            return ['error' => 'ESI request failed', 'message' => $e->getMessage()];
        } catch (\Throwable $t) {
            return ['error' => 'Unexpected error', 'message' => $t->getMessage()];
        }
    }

    public function getCharacterBlueprintsData(?int $character_id = null): array|\stdClass
    {
        $user = auth()->user();
        if (! $user) return ['error' => 'Not authenticated.'];

        // choose the target character
        $char = $character_id
            ? $user->characters()->where('character_id', $character_id)->first()
            : $user->characters()->first();

        if (! $char) return ['error' => 'Character not found for this user.'];

        // fetch token row (services package)
        $rt = DB::table('refresh_tokens')->where('character_id', $char->character_id)->first();
        if (! $rt) return ['error' => 'No ESI token for character.'];

        try {
            $scopes = json_decode($rt->scopes ?? '[]', true) ?: [];

            $auth = new EsiAuthentication([
                'access_token'  => $rt->token,               // <-- access token column
                'refresh_token' => $rt->refresh_token,
                'token_expires' => $rt->expires_on,
                'client_id'     => config('eseye.esi.auth.client_id'),
                'secret'        => config('eseye.esi.auth.client_secret'),
                'scopes'        => $scopes,
            ]);

            $esi = app()->make(Eseye::class, ['authentication' => $auth]);

            // NOTE: endpoint is paginated; this fetches page 1
            return $esi->invoke('get', '/characters/{character_id}/blueprints/', [
                'character_id' => $char->character_id,
            ]);
        } catch (RequestFailedException $e) {
            return ['error' => 'ESI request failed', 'message' => $e->getMessage()];
        } catch (\Throwable $t) {
            return ['error' => 'Unexpected error', 'message' => $t->getMessage()];
        }
    }

    /**
     * JSON wrappers if you want endpoints for XHR testing.
     */
    public function publicCharacterInfoJson()
    {
        $data = $this->getPublicCharacterInfoData();
        return response()->json($data, isset($data['error']) ? 400 : 200);
    }

    public function characterBlueprintsJson(?int $character_id = null)
    {
        $data = $this->getCharacterBlueprintsData($character_id);
        return response()->json($data, isset($data['error']) ? 400 : 200);
    }

    private function isAtWar(Eseye $esi, int $corpId, ?int $allianceId): bool
    {
        try {
            $warIds = $esi->invoke('get', '/corporations/{corporation_id}/wars/', [
                'corporation_id' => $corpId,
            ]) ?? [];

            if ($allianceId) {
                $allianceWars = $esi->invoke('get', '/alliances/{alliance_id}/wars/', [
                    'alliance_id' => $allianceId,
                ]) ?? [];
                $warIds = array_merge($warIds, $allianceWars);
            }

            foreach (array_unique(array_map('intval', $warIds)) as $warId) {
                $war = $esi->invoke('get', '/wars/{war_id}/', ['war_id' => $warId]);
                if ($war && !isset($war->finished)) {
                    return true;
                }
            }
        } catch (\Throwable $e) {
            \Log::warning("OSMM war check failed for corp {$corpId}: {$e->getMessage()}");
        }

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

        if ($char_ids->isEmpty()) {
            return ['leaves' => [], 'updated' => now('UTC')->toIso8601String()];
        }

        // Pull current snapshot of assets
        // If your table has location_type, we’ll prefer rows that are in stations/structures.
        $cols = ['character_id', 'type_id', 'quantity', 'location_id'];
        $has_location_type = Schema::connection((new CA)->getConnectionName())
            ->hasColumn((new CA)->getTable(), 'location_type');

        if ($has_location_type) $cols[] = 'location_type';

        $assets = CA::whereIn('character_id', $char_ids)->get($cols);

        if ($assets->isEmpty()) {
            return ['leaves' => [], 'updated' => now('UTC')->toIso8601String()];
        }

        // Optionally filter out items whose location_id is another item (ship/container)
        if ($has_location_type) {
            $assets = $assets->filter(function ($a) {
                // keep obvious station/structure/system; drop 'item' containers to avoid double-counting
                return in_array($a->location_type, ['station', 'solar_system', 'structure', 'other'], true);
            })->values();
        }

        if ($assets->isEmpty()) {
            return ['leaves' => [], 'updated' => now('UTC')->toIso8601String()];
        }

        // ---- Price map (reuse your helper order or pass a preference array) ----
        $type_ids = $assets->pluck('type_id')->unique();
        $priceMap = $this->priceMapForTypeIds($type_ids, ['adjusted_price','average_price','sell_price','average','buy_price']);

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
            if (str_contains($name,'planetary') ||
                str_contains($name,'pi'))         return 'PI';
            if (str_contains($name,'salvage'))    return 'Salvage';
            return 'Other';
        };

        // ---- Roll up ISK by (location_id, bucket) ----
        $byLocBucket = [];
        $locationIds = [];

        foreach ($assets as $a) {
            $qty = (int) $a->quantity;
            if ($qty <= 0) continue;

            $px  = (float) ($priceMap[(int) $a->type_id] ?? 0.0);
            if ($px <= 0) continue; // no price data, skip

            $isk = $px * $qty;

            $loc = (string) $a->location_id;
            $bucket = $bucketOf((int) $a->type_id);

            $byLocBucket[$loc][$bucket] = ($byLocBucket[$loc][$bucket] ?? 0.0) + $isk;
            $locationIds[$loc] = true;
        }

        if (empty($byLocBucket)) {
            return ['leaves' => [], 'updated' => now('UTC')->toIso8601String()];
        }

        // ---- Resolve location names ----
        $locIds = array_keys($locationIds);
        $locNames = $this->resolveLocationNames($locIds); // safe, best-effort

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
            'leaves'  => $leaves, // array of {label, loc, isk}
            'updated' => now('UTC')->toIso8601String(),
        ];
    }

    private function resolveTopLevelLocationName(CA $asset): string
    {
        // Eager load if needed (avoids N+1 when you call this in a loop)
        if (! $asset->relationLoaded('container'))      $asset->load('container');
        if (! $asset->relationLoaded('station'))        $asset->load('station');
        if (! $asset->relationLoaded('structure'))      $asset->load('structure');
        if (! $asset->relationLoaded('solar_system'))   $asset->load('solar_system');

        // Walk up through containers/ship holds until we hit a real place
        $guard = 0;
        $node  = $asset;
        while ($node && $node->location_type === 'other' && $guard++ < 10) {
            if (! $node->relationLoaded('container')) $node->load('container');
            $node = $node->container; // parent asset (item_id == this location_id)
        }

        if (! $node) return 'Unknown';

        return match ($node->location_type) {
            'station'      => $node->station->name ?? 'Unknown Station',
            'structure'    => $node->structure->name ?? 'Unknown Structure',
            'solar_system' => $node->solar_system->name ?? 'Unknown System',
            default        => 'Location ' . $node->location_id,
        };
    }

    /**
     * Best-effort name resolver when you ONLY have raw location IDs.
     * Tries:
     *  - Upwell structures  -> universe_structures (eve connection)
     *  - NPC stations       -> SDE sta_stations
     *  - Solar systems      -> SDE mapSolarSystems
     *  - Fallback           -> universe_names (eve connection)
     *
     * @param array<int|string> $ids  list of location_id values
     * @return array<string,string>   map: (string)location_id => name
     */
    private function resolveLocationNames(array $ids): array
    {
        if (empty($ids)) return [];

        $ids    = array_values(array_unique(array_map('strval', $ids)));
        $idInts = array_map('intval', $ids);

        // Same connection as assets/universe_* tables
        $eveConn = (new CA)->getConnectionName();

        $names = [];

        // 1) Upwell structures (if you have esi-universe.read_structures.v1 and they’re hydrated)
        if (Schema::connection($eveConn)->hasTable('universe_structures')) {
            try {
                $rows = DB::connection($eveConn)->table('universe_structures')
                    ->whereIn('structure_id', $idInts)
                    ->pluck('name', 'structure_id');
                foreach ($rows as $k => $v) $names[(string)$k] = $v;
            } catch (\Throwable $e) {}
        }

        // 2) NPC stations via universe_stations (ESI-hydrated)
        if (Schema::connection($eveConn)->hasTable('universe_stations')) {
            try {
                $rows = DB::connection($eveConn)->table('universe_stations')
                    ->whereIn('station_id', $idInts)
                    ->pluck('name', 'station_id');
                foreach ($rows as $k => $v) $names[(string)$k] = $v;
            } catch (\Throwable $e) {}
        }

        // 3) Fallback: universe_names (covers many entity IDs)
        if (Schema::connection($eveConn)->hasTable('universe_names')) {
            $missing = array_values(array_diff($ids, array_keys($names)));
            if (!empty($missing)) {
                try {
                    $rows = DB::connection($eveConn)->table('universe_names')
                        ->whereIn('entity_id', array_map('intval', $missing))
                        ->pluck('name', 'entity_id');
                    foreach ($rows as $k => $v) $names[(string)$k] = $v;
                } catch (\Throwable $e) {}
            }
        }

        // Readable fallback for anything still unresolved
        foreach ($ids as $id) {
            if (!isset($names[$id])) $names[$id] = 'Location ' . $id;
        }

        return $names;
    }

    

}
