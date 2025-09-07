<?php

namespace CapsuleCmdr\SeatOsmm\Events;

use Carbon\Carbon;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired whenever your plugin flips maintenance mode.
 * Pass the actor info at emit time (preferred), since queue workers
 * may not have an auth() context.
 */
class MaintenanceToggled
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public bool $enabled,
        public ?string $reason = null,
        public ?string $byName = null,
        public ?int $byUserId = null,
        public ?Carbon $at = null,
    ) {
        $this->at ??= now();
    }
}
