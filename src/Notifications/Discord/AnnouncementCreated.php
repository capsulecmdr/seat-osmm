<?php

namespace CapsuleCmdr\SeatOsmm\Notifications\Discord;

use Seat\Notifications\Notifications\AbstractDiscordNotification;
use Seat\Notifications\Services\Discord\Messages\DiscordMessage;
use Seat\Notifications\Services\Discord\Messages\DiscordEmbed;
use Seat\Notifications\Services\Discord\Messages\DiscordEmbedField;
class AnnouncementCreated extends AbstractDiscordNotification
{
    public function __construct(
        public ?string $title = null,
        public ?string $content = null,
        public ?string $by = null,
        public ?\Carbon\Carbon $at = null,
    ) {}

    // Tip: methods on DiscordMessage mirror an embed-style builder.
    protected function populateMessage(DiscordMessage $message, mixed $notifiable): DiscordMessage
    {
        return $message
            ->embed(function (DiscordEmbed $embed){
                $embed->timestamp($this->at);
                $embed->author($this->by);
                $embed->color(3329330);
                $embed->title('**Annoucement:** ' . $this->title);
                $embed->description($this->content);                
            })
            
            ->success();
    }
}