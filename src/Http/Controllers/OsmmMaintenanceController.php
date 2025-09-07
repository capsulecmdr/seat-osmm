<?php

namespace CapsuleCmdr\SeatOsmm\Http\Controllers;

use CapsuleCmdr\SeatOsmm\Models\OsmmAnnouncement as Ann;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use CapsuleCmdr\SeatOsmm\Events\MaintenanceToggled;
use Seat\Notifications\Models\NotificationGroup;
use Seat\Notifications\Traits\NotificationDispatchTool;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;

class OsmmMaintenanceController extends Controller
{
    use NotificationDispatchTool;
    public function landing()
    {
        // Show latest visible announcement (if any), otherwise generic message
        // $announcement = Ann::bannerable()->get()
        //                   ->tap(fn($c) => $c->each->refreshComputedStatus())
        //                   ->first(fn($a) => $a->is_visible);
        
        $reason = (string) osmm_setting('osmm_maintenance_reason', '');
        $desc   = (string) osmm_setting('osmm_maintenance_description', '');
        return response()
        ->view('seat-osmm::maintenance.landing', compact('reason','desc'))
        ->header('Cache-Control','no-store, no-cache, must-revalidate, max-age=0')
        ->header('Pragma','no-cache');
    }

    public function config()
    {
        
        $announcements = Ann::whereNotIn('status', ['expired'])
            ->orderByDesc('created_at')->paginate(10);

        $settings = [
            'maintenance_enabled'   => (int) (osmm_setting('osmm_maintenance_enabled', 0)),
            'maintenance_reason'    => (string) (osmm_setting('osmm_maintenance_reason', 'SeAT Maintenance Advisory')),
            'maintenance_description'=> (string) (osmm_setting('osmm_maintenance_description', 'Server entering reinforced mode. Secure assets and enjoy a Quafe while engineering refits some rigs.')),
            'webhook_enabled'       => (int) (osmm_setting('osmm_discord_webhook_enabled', 0)),
            'webhook_url'           => (string) (osmm_setting('osmm_discord_webhook_url', '')),
            'webhook_username'      => (string) (osmm_setting('osmm_discord_webhook_username', '')),
            'webhook_avatar'        => (string) (osmm_setting('osmm_discord_webhook_avatar', '')),
        ];

        return view('seat-osmm::maintenance.config', compact('announcements','settings'));
    }

    public function toggleMaintenance(Request $r)
    {
        Log::debug('Normal flow message');
        // Previous state before changes
        $wasEnabled = (int) (osmm_setting('osmm_maintenance_enabled', 0)) === 1;

        // Normalize inputs (NOTE: names are 'enabled', 'reason', 'description')
        $data = $r->validate([
            'enabled'     => 'nullable|boolean',
            'reason'      => 'nullable|string|max:200',
            'description' => 'nullable|string|max:4000',
        ]);

        // Browsers omit unchecked checkboxes; default to false when missing
        $nowEnabled  = (bool) ($data['enabled'] ?? false);
        $reason      = (string) ($data['reason'] ?? '');
        $description = (string) ($data['description'] ?? '');

        // Persist
        \CapsuleCmdr\SeatOsmm\Models\OsmmSetting::put('osmm_maintenance_enabled', $nowEnabled ? '1' : '0', 'text', 1);
        \CapsuleCmdr\SeatOsmm\Models\OsmmSetting::put('osmm_maintenance_reason', $reason, 'text', 1);
        \CapsuleCmdr\SeatOsmm\Models\OsmmSetting::put('osmm_maintenance_description', $description, 'text', 1);

        Log::info('OSMM maint toggle', [
            'was' => $wasEnabled,
            'now' => $nowEnabled,
            'webhook_enabled' => (int) osmm_setting('osmm_discord_webhook_enabled', 0),
            'webhook_url_set' => (string) osmm_setting('osmm_discord_webhook_url', '') !== '' ? 1 : 0,
        ]);

        // Only notify on state change
        if ($nowEnabled !== $wasEnabled) {
            // Who flipped the switch?
            $byName = auth()->user()->name ?? 'system';

            // get all the notification groups
            $groups = NotificationGroup::whereHas(
                'alerts',
                fn ($q) => $q->where('alert', 'osmm.maintenance_toggled')
            )->get();

            if ($groups->isEmpty()) return;

            //loop through all notification groups and fire events
            foreach($groups as $group){
                //loop through all integrations within the group
                foreach(($group->integrations) as $integration){
                    $notification = config('notifications.alerts')['osmm.maintenance_toggled']['handlers']['discord'];
                    $setting = (array) $integration->settings;
                    $key = array_key_first($setting);
                    $route = $setting[$key];
                    $anon = (new AnonymousNotifiable)->route($integration->type, $route);

                    $notif = new $notification(
                        true,                                // enabled
                        $reason,            // reason (title)
                        $description,    // description
                        $byName,                             // by
                        now()                                // at (Carbon)
                    );

                    Notification::sendNow($anon, $notification);
                }
            }

            // $this->dispatchNotifications('osmm.maintenance_toggled', $groups, function (string $handler) use ($nowEnabled, $reason, $byName) {
            //     return new $handler($nowEnabled, $reason, $by ?? (auth()->user()->name ?? 'system'), now());
            // });


            Log::warning("State Change for Maintenance Fired...");
            
            //$this->notifyDiscordMaintenance($nowEnabled, $reason, $description);
        } else {
            Log::info('OSMM maint toggle: no state change, skipping webhook');
        }

        return back()->with('status', 'Maintenance mode ' . ($nowEnabled ? 'enabled' : 'disabled') . '.');
    }



    public function saveWebhook(Request $r)
    {
        $data = $r->validate([
            'enabled'   => 'sometimes|boolean',
            'url'       => 'nullable|url',
            'username'  => 'nullable|string|max:80',
            'avatar'    => 'nullable|url'
        ]);

        \CapsuleCmdr\SeatOsmm\Models\OsmmSetting::put('osmm_discord_webhook_enabled', (int)($data['enabled'] ?? 0), 'text', 1);
        \CapsuleCmdr\SeatOsmm\Models\OsmmSetting::put('osmm_discord_webhook_url', $data['url'] ?? '', 'text', 1);
        \CapsuleCmdr\SeatOsmm\Models\OsmmSetting::put('osmm_discord_webhook_username', $data['username'] ?? '', 'text', 1);
        \CapsuleCmdr\SeatOsmm\Models\OsmmSetting::put('osmm_discord_webhook_avatar', $data['avatar'] ?? '', 'text', 1);

        return back()->with('status', 'Discord webhook settings saved.');
    }

    public function upsertAnnouncement(Request $r)
    {
        $data = $r->validate([
            'id'              => 'nullable|integer|exists:osmm_announcements,id',
            'title'           => 'required|string|max:200',
            'content'         => 'required|string',
            'status'          => 'required|in:new,active,expired',
            'show_banner'     => 'required|boolean',
            'send_to_discord' => 'required|boolean',
            'starts_at'       => 'nullable|date',
            'ends_at'         => 'nullable|date|after_or_equal:starts_at',
        ]);

        $ann = Ann::updateOrCreate(
            ['id' => $data['id'] ?? null],
            [
                'title'           => $data['title'],
                'content'         => $data['content'],
                'status'          => $data['status'],
                'show_banner'     => (bool) $data['show_banner'],
                'send_to_discord' => (bool) $data['send_to_discord'],
                'starts_at'       => $data['starts_at'] ?? null,
                'ends_at'         => $data['ends_at'] ?? null,
            ]
        );

        // Auto-sync status to schedule if desired
        $ann->refreshComputedStatus();

        // Optional Discord notify
        if ($ann->send_to_discord && (int) osmm_setting('osmm_discord_webhook_enabled', 0) === 1) {
            $this->notifyDiscord($ann);
        }

        return back()->with('status', 'Announcement saved.');
    }

    public function expireAnnouncement(Ann $announcement)
    {
        $announcement->update(['status' => 'expired']);
        return back()->with('status', 'Announcement expired.');
    }
    protected function notifyDiscord(Ann $a): bool
    {
        $url = (string) osmm_setting('osmm_discord_webhook_url', '');
        if ($url === '') {
            Log::warning('OSMM Discord: missing webhook URL');
            return false;
        }

        $username = osmm_setting('osmm_discord_webhook_username', 'Maintenance Bot') ?: 'Maintenance Bot';
        $avatar   = osmm_setting('osmm_discord_webhook_avatar', '') ?: null;

        // Minimal 'content' + rich embed (Discord accepts either/both)
        $payload = [
            'username'   => $username,
            'avatar_url' => $avatar,
            'content'    => '**' . $a->title . '**',   // so something posts even if embeds are blocked
            'embeds'     => [[
                'title'       => $a->title,
                'description' => strip_tags($a->content),
                'color'       => 15158332,
                'timestamp'   => now('UTC')->toIso8601String(),
                'fields'      => array_values(array_filter([
                    $a->starts_at ? ['name' => 'Starts', 'value' => $a->starts_at->toDayDateTimeString().' UTC', 'inline' => true] : null,
                    $a->ends_at   ? ['name' => 'Ends',   'value' => $a->ends_at->toDayDateTimeString().' UTC', 'inline' => true] : null,
                    ['name' => 'Status', 'value' => ucfirst($a->status), 'inline' => true],
                ])),
            ]],
        ];

        try {
            $resp = Http::timeout(10)
                ->asJson()
                ->post($url, $payload);

            Log::info('OSMM Discord webhook', [
                'status' => $resp->status(),
                // Body can help with 401/404/429 messages
                'body'   => str($resp->body())->limit(300)->toString(),
            ]);

            return $resp->successful();
        } catch (\Throwable $e) {
            Log::warning('OSMM Discord webhook exception: '.$e->getMessage());
            return false;
        }
    }

    protected function notifyDiscordMaintenance(bool $enabled, string $reason = '', string $description = ''): bool
    {
        if ((int) osmm_setting('osmm_discord_webhook_enabled', 0) !== 1) return false;

        $url = (string) osmm_setting('osmm_discord_webhook_url', '');
        if ($url === '') return false;

        $username = osmm_setting('osmm_discord_webhook_username', 'Maintenance Bot') ?: 'Maintenance Bot';
        $avatar   = osmm_setting('osmm_discord_webhook_avatar', '') ?: null;

        // Optional: set these OSMM settings if you want pings/threads
        $mention  = (string) osmm_setting('osmm_discord_mention', '');        // e.g. "<@&ROLE_ID>" or "@everyone"
        $threadId = (string) osmm_setting('osmm_discord_thread_id', '');      // if posting to a thread

        $title = $enabled ? $reason : 'Maintenance Complete';
        $content = trim(($mention ? $mention.' ' : '') . '**'.$title.'**');

        if($title == "Maintenance Complete"){
            $title = "**Complete: " . $reason . "**";
            $content = $title;
            $description = "Server has returned to normal operations";
        }

        $payload = [
            'username'   => $username,
            'avatar_url' => $avatar,
            'content'    => $content,  // ensures something shows even if embeds are hidden
            'allowed_mentions' => [
                'parse' => ['users','roles','everyone'], // safe with explicit mention tokens only
            ],
            'embeds'     => [[
                'title'       => $title,
                'description' => $description !== '' ? $description : '—',
                'color'       => $enabled ? 16747520 : 3329330,
            ]],
        ];

        $req = Http::timeout(10)->asJson();
        if ($threadId !== '') $req = $req->withQueryParameters(['thread_id' => $threadId]);

        $resp = $req->post($url, $payload);
        \Log::info('OSMM Discord maintenance webhook', ['status' => $resp->status(), 'body' => str($resp->body())->limit(200)->toString()]);
        return $resp->successful();
    }

}
