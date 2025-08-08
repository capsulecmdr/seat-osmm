<?php

namespace CapsuleCmdr\SeatOsmm\Http\Controllers;

use Illuminate\Routing\Controller;

class HomeOverrideController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        $war = $this->getWarStatusForUser($user);

        $homeElements = collect(config('osmm.home_elements', []))->sortBy('order');

        return view('seat-osmm::home', compact('homeElements','war'));
    }

    /**
     * Determine if the logged-in user's main or any alts are at war.
     * Returns a compact structure you can use in the view.
     */
    private function getWarStatusForUser($user): array
    {
        $entities = $this->collectUserEntities($user); // corp + alliance IDs
        $activeWars = [];

        foreach ($entities['corp_ids'] as $corpId) {
            $activeWars = array_merge($activeWars, $this->activeWarsForEntity('corporation', $corpId));
        }
        foreach ($entities['alliance_ids'] as $allianceId) {
            $activeWars = array_merge($activeWars, $this->activeWarsForEntity('alliance', $allianceId));
        }

        // De-dupe wars by ID
        $activeWars = collect($activeWars)
            ->unique('id')
            ->values()
            ->all();

        return [
            'at_war'      => !empty($activeWars),
            'active_wars' => $activeWars,          // array of wars with minimal details
            'entities'    => $entities,            // corp_ids / alliance_ids involved
        ];
    }

    /**
     * Get corp/alliance IDs for ALL of the user's linked characters.
     * Uses the public /characters/{character_id}/ endpoint (no scopes).
     */
    private function collectUserEntities($user): array
    {
        /** @var Eseye $esi */
        $esi = app(Eseye::class);

        $corpIds = [];
        $allianceIds = [];

        // SeAT gives you linked characters via $user->characters
        foreach ($user->characters as $char) {
            $charId = $char->character_id ?? $char->id;

            $aff = Cache::remember("char_aff_$charId", 600, function () use ($esi, $charId) {
                // PUBLIC endpoint, no scopes
                $res = $esi->invoke('get', '/characters/{character_id}/', [
                    'character_id' => (int) $charId,
                ]);

                return [
                    'corporation_id' => isset($res->corporation_id) ? (int) $res->corporation_id : null,
                    'alliance_id'    => isset($res->alliance_id) ? (int) $res->alliance_id : null,
                ];
            });

            if (!empty($aff['corporation_id'])) {
                $corpIds[] = (int) $aff['corporation_id'];
            }
            if (!empty($aff['alliance_id'])) {
                $allianceIds[] = (int) $aff['alliance_id'];
            }
        }

        return [
            'corp_ids'     => array_values(array_unique($corpIds)),
            'alliance_ids' => array_values(array_unique($allianceIds)),
        ];
    }

    /**
     * Fetch ACTIVE wars for a single corp or alliance.
     * Caches for 2 minutes to keep the UI snappy and kind to ESI.
     */
    private function activeWarsForEntity(string $type, int $id): array
    {
        /** @var Eseye $esi */
        $esi = app(Eseye::class);

        $cacheKey = "active_wars_{$type}_{$id}";

        return Cache::remember($cacheKey, 120, function () use ($esi, $type, $id) {
            $ids = $this->fetchWarIds($esi, $type, $id);

            if (empty($ids)) {
                return [];
            }

            $wars = [];
            foreach ($ids as $warId) {
                $w = $esi->invoke('get', '/wars/{war_id}/', ['war_id' => (int) $warId]);

                // Active == has started and NOT finished
                $isActive = !isset($w->finished);
                if (!$isActive) {
                    continue;
                }

                $wars[] = [
                    'id'        => (int) $warId,
                    'declared'  => isset($w->declared) ? (string) $w->declared : null,
                    'started'   => isset($w->started) ? (string) $w->started : null,
                    'aggressor' => [
                        'corporation_id' => isset($w->aggressor->corporation_id) ? (int) $w->aggressor->corporation_id : null,
                        'alliance_id'    => isset($w->aggressor->alliance_id) ? (int) $w->aggressor->alliance_id : null,
                    ],
                    'defender'  => [
                        'corporation_id' => isset($w->defender->corporation_id) ? (int) $w->defender->corporation_id : null,
                        'alliance_id'    => isset($w->defender->alliance_id) ? (int) $w->defender->alliance_id : null,
                    ],
                    // allies is optional and often empty; trim down to IDs if present
                    'allies'    => collect($w->allies ?? [])
                        ->map(function ($a) {
                            return [
                                'corporation_id' => isset($a->corporation_id) ? (int) $a->corporation_id : null,
                                'alliance_id'    => isset($a->alliance_id) ? (int) $a->alliance_id : null,
                            ];
                        })->all(),
                ];
            }

            return $wars;
        });
    }

    /**
     * Pull *all pages* of war IDs for a corp or alliance.
     */
    private function fetchWarIds(Eseye $esi, string $type, int $id): array
    {
        $ids = [];
        $page = 1;

        // ESI path + parameter name differ by type
        $path = $type === 'corporation'
            ? '/corporations/{corporation_id}/wars/'
            : '/alliances/{alliance_id}/wars/';

        $param = $type === 'corporation' ? 'corporation_id' : 'alliance_id';

        do {
            $resp = $esi->invoke('get', $path, [$param => $id, 'page' => $page]);
            $batch = collect($resp ?? [])->map(fn($v) => (int) $v)->all();
            $ids = array_merge($ids, $batch);
            $page++;
        } while (!empty($batch));

        return array_values(array_unique($ids));
    }
}
