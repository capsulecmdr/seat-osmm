<?php

namespace CapsuleCmdr\SeatOsmm\Listeners;

use CapsuleCmdr\SeatOsmm\Events\MaintenanceToggled as Event;
use Illuminate\Contracts\Queue\ShouldQueue;
use Seat\Notifications\Traits\NotificationDispatchTool;
use Illuminate\Support\Facades\Log;

class SendMaintenanceToggledAlert implements ShouldQueue
{
    use NotificationDispatchTool;

    /** Ensure a worker picks it up with your notifications worker */
    public string $queue = 'notifications';

    public function handle(Event $event): void
    {
        Log::warning('[OSMM] Listener got event', [
            'enabled' => $event->enabled,
            'reason'  => $event->reason,
        ]);

        // Default enabled if not set
        // if (function_exists('osmm_setting') && (int) osmm_setting('osmm_alerts_enabled', 1) !== 1) {
        //     Log::warning('[OSMM] Alerts disabled via osmm_alerts_enabled setting; skipping dispatch.');
        //     return;
        // }

        $this->dispatchNotifications('osmm.maintenance_toggled', [
            'enabled'     => $event->enabled,
            'reason'      => $event->reason,
            'description' => $event->description ?? '',
            'by'          => $event->byName,
            'by_id'       => $event->byUserId,
            'at'          => $event->at ?? now(),
        ]);
    }
}
