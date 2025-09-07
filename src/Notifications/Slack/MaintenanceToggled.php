<?php

namespace CapsuleCmdr\SeatOsmm\Notifications\Slack;

use Illuminate\Notifications\Messages\SlackMessage;
use Seat\Notifications\Notifications\AbstractSlackNotification;

class MaintenanceToggled extends AbstractSlackNotification
{
    public function __construct(
        public bool $enabled,
        public ?string $reason = null,
        public ?string $by = null,
        public ?\Carbon\Carbon $at = null,
    ) {}

    protected function populateMessage(SlackMessage $message, mixed $notifiable): SlackMessage
    {
        $status = $this->enabled ? 'ENABLED' : 'DISABLED';

        return $message
            ->content(":warning: OSMM maintenance *{$status}*")
            ->attachment(function ($attachment) use ($status) {
                $attachment->title('Maintenance toggled')
                    ->fields([
                        'Status' => $status,
                        'Reason' => $this->reason ?: '—',
                        'By'     => $this->by ?: 'system',
                        'At'     => ($this->at ?? now())->toDateTimeString(),
                    ]);
            });
    }
}
