<?php

namespace CapsuleCmdr\SeatOsmm\Observers;

use Seat\Eveapi\Models\Killmails\KillmailDetail;
use Seat\Notifications\Observers\KillmailNotificationObserver;

/**
 * Fires the same notification logic as the core observer,
 * but on *created* so brand-new killmails are announced.
 *
 * Important: We DO NOT implement updated() here to avoid duplicates.
 */
class CreatedKillmailObserver
{
    public function created(KillmailDetail $killmail): void
    {
        // Reuse the core logic (which queues & applies the 60min window)
        (new KillmailNotificationObserver())->updated($killmail);
    }
}
