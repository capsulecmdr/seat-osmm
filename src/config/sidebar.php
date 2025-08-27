<?php

return [
    "osmm"=>[
        "name"=>"Server Manager",
        "icon"=>"fas fa-server",
        "route_segment"=>"osmm",
        "permission"=>"osmm.admin",
        "entries"=>[
            [
                "name"=>"Branding",
                "icon"=>"fas fa-server",
                "route"=>"osmm.config.branding",
                "permission"=>"osmm.admin",
            ],
            [
                "name"=>"Menu Manager",
                "icon"=>"fas fa-bars",
                "route"=>"osmm.menu.index",
                "permission"=>"osmm.admin",
            ],
        ],
    ]
];