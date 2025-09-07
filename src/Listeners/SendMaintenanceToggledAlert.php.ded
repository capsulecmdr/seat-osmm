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
    use InteractsWithQueue, NotificationDispatchTool;

    public string $queue = 'notifications';

    public function handle(Event $event): void
    {
        Log::warning('[OSMM] Listener got event', ['enabled' => $event->enabled, 'reason' => $event->reason]);

        // 1) Pick all groups that subscribed to your alert key
        $groups = NotificationGroup::whereHas(
            'alerts',
            fn ($q) => $q->where('alert', 'osmm.maintenance_toggled')
        )->get();

        // 2) Dispatch using the trait’s signature: key, groups, callback(handler) => Notification
        $this->dispatchNotifications('osmm.maintenance_toggled', $groups, function (string $handler) use ($event) {
            // Your formatter’s ctor signature:
            // __construct(bool $enabled, ?string $reason = null, ?string $by = null, ?\Carbon\Carbon $at = null)
            return new $handler(
                $event->enabled,
                $event->reason,
                $event->description,
                $event->byName,
                $event->at ?? now()
            );
        });
    }
}
