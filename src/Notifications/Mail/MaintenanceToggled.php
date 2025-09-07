<?php

namespace CapsuleCmdr\SeatOsmm\Notifications\Mail;

use Illuminate\Notifications\Messages\MailMessage;
use Seat\Notifications\Notifications\AbstractMailNotification;

class MaintenanceToggled extends AbstractMailNotification
{
    public function __construct(
        public bool $enabled,
        public ?string $reason = null,
        public ?string $by = null,
        public ?\Carbon\Carbon $at = null,
    ) {}

    protected function populateMessage(MailMessage $message, mixed $notifiable): MailMessage
    {
        $status = $this->enabled ? 'ENABLED' : 'DISABLED';

        return $message
            ->subject("OSMM Maintenance {$status}")
            ->greeting('Heads up!')
            ->line("OSMM maintenance mode was {$status}.")
            ->line('Reason: ' . ($this->reason ?: '—'))
            ->line('By: ' . ($this->by ?: 'system'))
            ->line('At: ' . (($this->at ?? now())->toDateTimeString()));
    }
}
