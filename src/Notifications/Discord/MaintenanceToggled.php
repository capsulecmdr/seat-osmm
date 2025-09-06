<?php

namespace Capsulecmdr\SeatOsmm\Notifications\Discord;

use Seat\Notifications\Notifications\AbstractDiscordNotification;
use Seat\Notifications\Services\Discord\Messages\DiscordMessage;

class MaintenanceToggled extends AbstractDiscordNotification
{
    public function __construct(
        public bool $enabled,
        public ?string $reason = null,
        public ?string $by = null,
        public ?\Carbon\Carbon $at = null,
    ) {}

    // Tip: methods on DiscordMessage mirror an embed-style builder.
    protected function populateMessage(DiscordMessage $message, mixed $notifiable): DiscordMessage
    {
        $status = $this->enabled ? 'ENABLED' : 'DISABLED';

        return $message
            ->title('OSMM Maintenance')
            ->description("Maintenance was **{$status}**")
            ->field('Reason', $this->reason ?: '—', true)
            ->field('By', $this->by ?: 'system', true)
            ->field('At', ($this->at ?? now())->toDateTimeString(), true);
    }
}
