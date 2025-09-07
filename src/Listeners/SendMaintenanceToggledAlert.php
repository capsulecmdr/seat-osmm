<?php

namespace CapsuleCmdr\SeatOsmm\Listeners;

use CapsuleCmdr\SeatOsmm\Events\MaintenanceToggled as Event;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Seat\Notifications\Models\NotificationGroup;
use Seat\Notifications\Traits\NotificationDispatchTool;

class SendMaintenanceToggledAlert implements ShouldQueue
{
    use InteractsWithQueue;
    use NotificationDispatchTool;

    public function handle(Event $event): void
    {
        Log::debug('[OSMM] Listener got event', [
            'enabled' => $event->enabled,
            'reason'  => $event->reason,
        ]);

        // 1) find groups that subscribed to your alert key
        $groups = NotificationGroup::whereHas(
            'alerts',
            fn ($q) => $q->where('alert', 'osmm.maintenance_toggled')
        )->get();

        if ($groups->isEmpty()) {
            Log::warning('[OSMM] No notification groups subscribed to osmm.maintenance_toggled');
            return;
        }

        // 2) dispatch via SeAT’s tool: (alert-key, groups, builder)
        $this->dispatchNotifications('osmm.maintenance_toggled', $groups, function (string $handler) use ($event) {
            // $handler will be one of your formatters based on the integration type,
            // e.g. CapsuleCmdr\SeatOsmm\Notifications\Discord\MaintenanceToggled
            return new $handler(
                $event->enabled,
                $event->reason,
                $event->byName,
                $event->at
            );
        });
    }
}
