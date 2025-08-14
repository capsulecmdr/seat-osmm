<?php

return [

    // Adds an item under SeAT → Tools
    'tools' => [
        [
            'name'       => 'OSMM Config',
            'icon'       => 'fa fa-cogs',
            'route'      => 'seat-osmm.config.branding', // ← matches your routes.php
            'permission' => 'osmm.admin',
            'order'      => 999, // optional
        ],
    ],

];
