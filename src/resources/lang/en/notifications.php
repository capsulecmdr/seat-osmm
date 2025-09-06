<?php

return [

    /*
    |--------------------------------------------------------------------------
    | SeAT Alerts (OSMM)
    |--------------------------------------------------------------------------
    |
    | The `maintenance_toggled` key is referenced by your
    | Config/notifications.alerts.php label:
    |   'label' => 'seat-osmm::notifications.maintenance_toggled'
    |
    */

    'maintenance_toggled' => 'OSMM: Maintenance toggled',

    'maintenance' => [
        'title'    => 'OSMM Maintenance',
        'enabled'  => 'ENABLED',
        'disabled' => 'DISABLED',
        'reason'   => 'Reason',
        'by'       => 'By',
        'at'       => 'At',

        'mail' => [
            'subject'  => 'OSMM Maintenance :status',
            'greeting' => 'Heads up!',
            'line'     => 'OSMM maintenance mode was :status.',
        ],

        'slack' => [
            'content'          => 'OSMM maintenance *:status*',
            'attachment_title' => 'Maintenance toggled',
        ],

        'discord' => [
            'embed_title'       => 'OSMM Maintenance',
            'embed_description' => 'Maintenance was **:status**',
        ],
    ],

];
