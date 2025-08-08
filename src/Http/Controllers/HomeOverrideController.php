<?php

namespace CapsuleCmdr\SeatOsmm\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Seat\Eseye\Eseye;
use Seat\Services\Repositories\Character;

class HomeOverrideController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        // Loop through main + alts
        foreach ($user->characters as $character) {

            /** @var Eseye $esi */
            $esi = $character->getEsi(); // built-in helper

            // corp ID and alliance ID come from the character’s record
            $corpId     = $character->corporation_id;
            $allianceId = $character->alliance_id ?? null;

            if ($this->isAtWar($esi, $corpId, $allianceId)) {
                $atWar = true;
                break;
            }
        }

        $homeElements = collect(config('osmm.home_elements', []))->sortBy('order');

        return view('seat-osmm::home', compact('homeElements','atWar'));
    }
    private function isAtWar(Eseye $esi, int $corpId, ?int $allianceId): bool
    {
        $warIds = $esi->invoke('get', '/corporations/{corporation_id}/wars/', [
            'corporation_id' => $corpId,
        ]);

        if ($allianceId) {
            $allianceWars = $esi->invoke('get', '/alliances/{alliance_id}/wars/', [
                'alliance_id' => $allianceId,
            ]);
            $warIds = array_merge($warIds, $allianceWars);
        }

        foreach (array_unique($warIds) as $warId) {
            $war = $esi->invoke('get', '/wars/{war_id}/', ['war_id' => $warId]);
            if (!isset($war->finished)) {
                return true; // active war found
            }
        }

        return false;
    }
}
