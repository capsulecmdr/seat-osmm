<?php

return [
    'tools' => [
        'entries' => [
            [
                'name'       => 'OSMM Config',
                'icon'       => 'fa fa-cogs',
                'route'      => 'seat-osmm.config.branding', // must match your routes.php
                'permission' => 'osmm.admin',
                'order'      => 999,
            ],
        ],
    ],
];
