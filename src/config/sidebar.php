<?php 

return [
    'tools' => [
        'osmm-config' => [
            'name'       => 'OSMM Config',
            'icon'       => 'fa fa-cogs',
            'route'      => 'seat-osmm.config.branding', // match your route name
            'permission' => 'osmm.admin',
            'order'      => 999,
        ],
    ],
];