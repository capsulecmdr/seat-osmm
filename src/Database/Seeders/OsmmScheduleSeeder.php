<?php 
// src/database/seeders/OsmmScheduleSeeder.php
namespace CapsuleCmdr\SeatOsmm\Database\Seeders;

use Seat\Services\Seeding\AbstractScheduleSeeder;

class OsmmScheduleSeeder extends AbstractScheduleSeeder
{
    public function getSchedules(): array
    {
        return [
            [
                'command'           => 'osmm:announcements:refresh-status', // your Artisan signature
                'expression'        => '*/5 * * * *',                        // cron expression
                'allow_overlap'     => false,
                'allow_maintenance' => true,
                'ping_before'       => null,
                'ping_after'        => null,
            ],
        ];
    }

    // Optional: remove/rename old schedules
    public function getDeprecatedSchedules(): array
    {
        return [
            // ['command' => 'old:signature'],
        ];
    }
}
