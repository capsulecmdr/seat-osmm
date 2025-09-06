<?php

return [
    'osmm.maintenance_toggled' => [
        'label' => 'osmm::notifications.maintenance_toggled',
        'handlers' => [
            'mail'    => \Capsulecmdr\SeatOsmm\Notifications\Mail\MaintenanceToggled::class,
            'slack'   => \Capsulecmdr\SeatOsmm\Notifications\Slack\MaintenanceToggled::class,
            'discord' => \Capsulecmdr\SeatOsmm\Notifications\Discord\MaintenanceToggled::class,
        ],
    ],
];
