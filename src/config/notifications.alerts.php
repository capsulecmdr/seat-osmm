<?php

return [
    'osmm.maintenance_toggled' => [
        'label' => 'osmm::notifications.maintenance_toggled',
        'handlers' => [
            'mail'    => \CapsuleCmdr\SeatOsmm\Notifications\Mail\MaintenanceToggled::class,
            'slack'   => \CapsuleCmdr\SeatOsmm\Notifications\Slack\MaintenanceToggled::class,
            'discord' => \CapsuleCmdr\SeatOsmm\Notifications\Discord\MaintenanceToggled::class,
        ],
    ],
];
