<?php
// app/Http/Controllers/OsmmCalendarController.php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Seat\Eseye\Eseye;
use Seat\Eseye\Containers\EsiAuthentication;
use Seat\Eseye\Exceptions\RequestFailedException;

class OsmmCalendarController extends Controller
{
    public function next(Request $request)
    {
        $user = Auth::user();
        $char = $user->characters()->first();
        if (!$char) return response()->json([], 200);

        // SeAT puts a RefreshToken model on ->refresh_token
        $rt = optional($char->refresh_token)->refresh_token;
        if (!$rt) return response()->json([], 200);

        $esi = new Eseye(new EsiAuthentication([
            'client_id'     => config('services.eveonline.client_id'),
            'secret'        => config('services.eveonline.client_secret'),
            'refresh_token' => $rt,
            'scopes'        => 'esi-calendar.read_calendar_events.v1',
        ]));

        // --- Fetch summaries ---
        try {
            $raw = $esi->invoke('get', '/characters/{character_id}/calendar/', [
                'character_id' => $char->character_id,
                'datasource'   => 'tranquility',
            ]);
        } catch (RequestFailedException $e) {
            // If token/scopes are wrong, just return empty list (frontend handles it)
            return response()->json([], 200);
        }

        $events = $this->toArray($raw);

        // Future only, earliest first, max 5
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $next = collect($events)
            ->map(fn($e) => (array)$e)
            ->filter(fn($e) => isset($e['event_date']) && new \DateTimeImmutable($e['event_date']) >= $now)
            ->sortBy('event_date')
            ->take(5)
            ->values();

        // --- Hydrate details (title/owner/duration) ---
        $detailed = $next->map(function ($e) use ($esi, $char) {
            try {
                $detail = $esi->invoke('get', '/characters/{character_id}/calendar/{event_id}/', [
                    'character_id' => $char->character_id,
                    'event_id'     => $e['event_id'],
                    'datasource'   => 'tranquility',
                ]);
            } catch (\Throwable $ex) {
                $detail = null;
            }
            $d = json_decode(json_encode($detail), true) ?: [];
            return [
                'event_id'  => $e['event_id'],
                'date'      => $e['event_date'],
                'title'     => $d['title'] ?? ($e['event_title'] ?? '(no title)'),
                'owner'     => $d['owner_name'] ?? null,
                'duration'  => $d['duration'] ?? null, // minutes
                'response'  => $e['response'] ?? null,
                'importance'=> $e['importance'] ?? null,
            ];
        });

        return response()->json($detailed->all(), 200);
    }

    private function toArray($raw): array
    {
        if (is_array($raw)) return $raw;
        if (is_string($raw)) return $raw === '' ? [] : (json_decode($raw, true) ?: []);
        return json_decode(json_encode($raw), true) ?: [];
    }
}
