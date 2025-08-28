<?php

namespace CapsuleCmdr\SeatOsmm\Http\Controllers;

use CapsuleCmdr\SeatOsmm\Models\OsmmAnnouncement as Ann;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;

class OsmmMaintenanceController extends Controller
{
    public function landing()
    {
        // Show latest visible announcement (if any), otherwise generic message
        $announcement = Ann::bannerable()->get()
                          ->tap(fn($c) => $c->each->refreshComputedStatus())
                          ->first(fn($a) => $a->is_visible);

        return view('seat-osmm::maintenance.landing', compact('announcement'));
    }

    public function config()
    {
        
        $announcements = Ann::whereNotIn('status', ['expired'])
            ->orderByDesc('created_at')->paginate(10);

        $settings = [
            'maintenance_enabled'   => (int) (osmm_setting('osmm_maintenance_enabled', 0)),
            'webhook_enabled'       => (int) (osmm_setting('osmm_discord_webhook_enabled', 0)),
            'webhook_url'           => (string) (osmm_setting('osmm_discord_webhook_url', '')),
            'webhook_username'      => (string) (osmm_setting('osmm_discord_webhook_username', '')),
            'webhook_avatar'        => (string) (osmm_setting('osmm_discord_webhook_avatar', '')),
        ];

        return view('seat-osmm::maintenance.config', compact('announcements','settings'));
    }

    public function toggleMaintenance(Request $r)
    {
        $val = (int) $r->boolean('enabled');
        \CapsuleCmdr\SeatOsmm\Models\OsmmSetting::put('osmm_maintenance_enabled', $val, 'text', 1);
        return back()->with('status', 'Maintenance mode '.($val ? 'enabled' : 'disabled').'.');
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
            'id'         => 'nullable|integer|exists:osmm_announcements,id',
            'title'      => 'required|string|max:200',
            'content'    => 'required|string',
            'status'     => 'required|in:new,active,expired',
            'show_banner'=> 'sometimes|boolean',
            'send_to_discord' => 'sometimes|boolean',
            'starts_at'  => 'nullable|date',
            'ends_at'    => 'nullable|date|after_or_equal:starts_at',
        ]);

        $ann = Ann::updateOrCreate(
            ['id' => $data['id'] ?? null],
            [
                'title' => $data['title'],
                'content' => $data['content'],
                'status' => $data['status'],
                'show_banner' => (bool)($data['show_banner'] ?? true),
                'send_to_discord' => (bool)($data['send_to_discord'] ?? false),
                'starts_at' => $data['starts_at'] ?? null,
                'ends_at' => $data['ends_at'] ?? null,
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

    protected function notifyDiscord(Ann $a): void
    {
        try {
            $url = osmm_setting('osmm_discord_webhook_url', '');
            if (!$url) return;

            $payload = [
                'username' => osmm_setting('osmm_discord_webhook_username', 'Maintenance Bot'),
                'avatar_url' => osmm_setting('osmm_discord_webhook_avatar', ''),
                'embeds' => [[
                    'title' => $a->title,
                    'description' => strip_tags($a->content),
                    'color' => 15158332, // red-ish
                    'timestamp' => now()->toIso8601String(),
                    'fields' => array_values(array_filter([
                        $a->starts_at ? ['name'=>'Starts','value'=>$a->starts_at->toIso8601String(),'inline'=>true] : null,
                        $a->ends_at   ? ['name'=>'Ends','value'=>$a->ends_at->toIso8601String(),'inline'=>true] : null,
                        ['name'=>'Status','value'=>ucfirst($a->status),'inline'=>true],
                    ])),
                ]],
            ];

            \Http::withHeaders(['Content-Type'=>'application/json'])
                ->post($url, $payload);
        } catch (\Throwable $e) {
            Log::warning('OSMM Discord webhook failed: '.$e->getMessage());
        }
    }
}
