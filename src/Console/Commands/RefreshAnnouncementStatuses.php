<?php 
// src/Console/Commands/RefreshAnnouncementStatuses.php
namespace CapsuleCmdr\SeatOsmm\Console\Commands;

use Illuminate\Console\Command;
use CapsuleCmdr\SeatOsmm\Models\OsmmAnnouncement;

class RefreshAnnouncementStatuses extends Command
{
    protected $signature = 'osmm:announcements:refresh-status';
    protected $description = 'Recompute announcement statuses in bulk';

    public function handle(): int
    {
        $counts = OsmmAnnouncement::refreshAllComputedStatus();
        $this->info(sprintf(
            'Expired: %d | Scheduled: %d | Activated: %d',
            $counts['expired'], $counts['scheduled'], $counts['active']
        ));
        return self::SUCCESS;
    }
}
