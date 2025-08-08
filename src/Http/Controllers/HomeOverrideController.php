<?php

namespace CapsuleCmdr\SeatOsmm\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Seat\Eseye\Eseye;

class HomeOverrideController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        $atWar = false;

        // Loop through each character
        foreach ($user->characters as $char) {


            $esi = new Eseye();

            $info = $esi->invoke('get', '/characters/{character_id}/', [
                'character_id' => $char->character_id,
            ]);

            // Get an authenticated Eseye client from SeAT
            $esi = $char->getEsi();

            $corpId     = $info->corporation_id;
            $allianceId = property_exists($info, 'alliance_id') ? $info->alliance_id : null;

            if ($corpId && $this->isAtWar($esi, $corpId, $allianceId)) {
                $atWar = true;
                break;
            }
        }


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
}
