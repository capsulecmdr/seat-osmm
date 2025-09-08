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
use CapsuleCmdr\SeatOsmm\Models\OsmmMaintenanceTemplate;

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

        $templates = OsmmMaintenanceTemplate::query()
        ->where('is_active', true)
        ->orderBy('name')
        ->get(['id','name','reason','description']);

        return view('seat-osmm::maintenance.config', compact('announcements','settings','templates'));
    }

    public function toggleMaintenance(Request $r)
    {
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
                    Notification::sendNow($anon,new $notification(
                        $nowEnabled,                                // enabled
                        $reason,            // reason (title)
                        $description,    // description
                        $byName,                             // by
                        now()                                // at (Carbon)
                    ));
                }
            }


            
        } else {
            
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

}
