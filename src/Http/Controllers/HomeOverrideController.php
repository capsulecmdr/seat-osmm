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

class HomeOverrideController extends Controller
{
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


        $homeElements = collect(config('osmm.home_elements', []))->sortBy('order');

        return view('seat-osmm::home', compact('homeElements','atWar'));
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
}
