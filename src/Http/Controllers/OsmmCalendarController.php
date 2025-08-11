<?php
// app/Http/Controllers/OsmmCalendarController.php
namespace CapsuleCmdr\SeatOsmm\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Seat\Eseye\Eseye;
use Seat\Eseye\Containers\EsiAuthentication;

class OsmmCalendarController extends Controller
{
    public function next(Request $request)
    {
        $user = Auth::user();
        $char = $user->characters()->first(); // pick your primary logic
        if (! $char) return response()->json([]);

        // Build auth from your stored refresh token
        $auth = new EsiAuthentication([
            'client_id'     => config('services.eveonline.client_id'),
            'secret'        => config('services.eveonline.client_secret'),
            'refresh_token' => $char->refresh_token,
            'scopes'        => 'esi-calendar.read_calendar_events.v1',
        ]);

        $esi = new Eseye($auth);

        // 1) Get summaries (next 50 from "now")
        $events = $esi->invoke('get', '/characters/{character_id}/calendar/', [
            'character_id' => $char->character_id,
            'datasource'   => 'tranquility',
        ]);

        // 2) Keep only future events, earliest first, take 5
        $nowUtc = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $nextFive = collect($events ?? [])
            ->filter(fn ($e) => isset($e->event_date) && new \DateTimeImmutable($e->event_date) >= $nowUtc)
            ->sortBy('event_date')
            ->take(5)
            ->values();

        // 3) Hydrate each with details (title/owner/duration/desc)
        $detailed = $nextFive->map(function ($e) use ($esi, $char) {
            $detail = $esi->invoke('get', '/characters/{character_id}/calendar/{event_id}/', [
                'character_id' => $char->character_id,
                'event_id'     => $e->event_id,
                'datasource'   => 'tranquility',
            ]);

            // Normalize fields across summary/detail payloads
            return [
                'event_id'  => $e->event_id,
                'date'      => $e->event_date,
                'title'     => $detail->title ?? $e->event_title ?? '(no title)',
                'owner'     => $detail->owner_name ?? null,
                'duration'  => $detail->duration ?? null, // minutes
                'response'  => $e->response ?? null,      // 'accepted'|'declined'|...
                'importance'=> $e->importance ?? null,
            ];
        });

        return response()->json($detailed);
    }
}
