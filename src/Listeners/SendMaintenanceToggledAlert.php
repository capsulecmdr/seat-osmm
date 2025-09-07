<?php

namespace CapsuleCmdr\SeatOsmm\Listeners;

use CapsuleCmdr\SeatOsmm\Events\MaintenanceToggled as Event;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Seat\Notifications\Traits\NotificationDispatchTool;
use Illuminate\Support\Facades\Log;

/**
 * Listener that routes the event into SeAT's built-in notifications system.
 * Uses the "osmm.maintenance_toggled" alert key you registered in config.
 */
class SendMaintenanceToggledAlert implements ShouldQueue
{
    use InteractsWithQueue;
    use NotificationDispatchTool;

    public function handle(Event $event): void
    {
        Log::debug('[OSMM] Listener got event', ['enabled'=>$event->enabled, 'reason'=>$event->reason]);
        // Optional feature gate if you have a toggle in OSMM settings.
        // if (function_exists('osmm_setting') && !osmm_setting('osmm_alerts_enabled')) {
        //     return;
        // }

        $payload = [
            'enabled' => $event->enabled,
            'reason'  => $event->reason,
            'by'      => $event->byName,
            'by_id'   => $event->byUserId,
            'at'      => $event->at ?? now(),
        ];

        // Let SeAT deliver to users/channels that subscribed to this alert key.
        $this->dispatchNotification('osmm.maintenance_toggled', $payload);
    }
}
