<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Homepage Content Elements
    |--------------------------------------------------------------------------
    |
    | Other plugins can push their rendered HTML snippets into this array.
    | These will be displayed on the custom homepage.
    |
    */
    'osmm_home_elements' => [
        // Example:
        // [
        //     'order' => 10,
        //     'html' => view('someplugin::partials.widget')->render(),
        // ],
        [
            'order' => 1,
            'html' => view('seat-osmm::partials.test-widget')->render(),
        ],
    ],
];
