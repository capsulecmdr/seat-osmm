<?php

namespace CapsuleCmdr\SeatOsmm\Notifications\Discord;

use Seat\Notifications\Notifications\AbstractDiscordNotification;
use Seat\Notifications\Services\Discord\Messages\DiscordMessage;
use Seat\Notifications\Services\Discord\Messages\DiscordEmbed;
use Seat\Notifications\Services\Discord\Messages\DiscordEmbedField;

class MaintenanceToggled extends AbstractDiscordNotification
{
    public function __construct(
        public bool $enabled,
        public ?string $reason = null,
        public ?string $description = null,
        public ?string $by = null,
        public ?\Carbon\Carbon $at = null,
    ) {}

    // Tip: methods on DiscordMessage mirror an embed-style builder.
    protected function populateMessage(DiscordMessage $message, mixed $notifiable): DiscordMessage
    {
        \Log::warning('[OSMM] Discord populateMessage hit', [
            'enabled' => $this->enabled, 'reason' => $this->reason, 'by' => $this->by
        ]);
        $status = $this->enabled ? 'ENABLED' : 'DISABLED';

        return $message
            ->embed(function (DiscordEmbed $embed){
                $embed->timestamp($this->at);
                $embed->author($this->by);
                if($this->enabled == "ENABLED"){
                    $embed->color(16747520);
                    $embed->title($this->reason);
                    $embed->description($this->description);
                }else{
                    $embed->color(3329330);
                    $embed->title('**Complete:** ' . $this->reason);
                    $embed->description('Return to normal operating proceedures');
                }
                
            })

            ->success();
    }
}
