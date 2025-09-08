<?php
// return [
//     'osmm.maintenance_toggled' => [
//         'label' => 'osmm::notifications.maintenance_toggled',
//         'handlers' => [
//             'mail'    => \CapsuleCmdr\SeatOsmm\Notifications\Mail\MaintenanceToggled::class,
//             'slack'   => \CapsuleCmdr\SeatOsmm\Notifications\Slack\MaintenanceToggled::class,
//             'discord' => \CapsuleCmdr\SeatOsmm\Notifications\Discord\MaintenanceToggled::class,
//         ],
//     ],
// ];

return [
    'osmm.maintenance_toggled' => [
        'label' => 'osmm::notifications.maintenance_toggled',
        'handlers' => [
            'discord' => \CapsuleCmdr\SeatOsmm\Notifications\Discord\MaintenanceToggled::class,
        ],
    ],
    'osmm.announcement_created' => [
        'label' => 'osmm::notifications.announcement_created',
        'handlers' => [
            'discord' => \CapsuleCmdr\SeatOsmm\Notifications\Discord\AnnouncementCreated::class,
        ],
    ],
];
