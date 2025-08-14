<?php

if (!function_exists('plugin_version')) {
    function plugin_version($packageName)
    {
        $lockFile = base_path('composer.lock');

        if (!file_exists($lockFile)) {
            return 'unknown';
        }

        $lockData = json_decode(file_get_contents($lockFile), true);

        foreach ($lockData['packages'] as $package) {
            if ($package['name'] === $packageName) {
                return $package['version'];
            }
        }

        return 'unknown';
    }
}
