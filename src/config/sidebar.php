<?php

return [
    'osmm' => [
        'name'       => 'OSMM Config',
        'icon'       => 'fa fa-cogs',
        'route'      => 'seat-osmm.config.branding', // must match your route name
        'permission' => 'osmm.admin',
        // no 'entries' => [...] means this item itself is clickable
        'order'      => 95, // optional: position relative to other top-level items
    ],
];