<?php
class RefreshAnnouncementStatuses extends Command
{
    protected $signature = 'osmm:announcements:refresh-status';
    protected $description = 'Recompute announcement statuses in bulk';

    public function handle(): int
    {
        $counts = \CapsuleCmdr\SeatOsmm\Models\OsmmAnnouncement::refreshAllComputedStatus();
        $this->info(sprintf(
            'Expired: %d | Scheduled: %d | Activated: %d',
            $counts['expired'], $counts['scheduled'], $counts['active']
        ));
        return self::SUCCESS;
    }
}