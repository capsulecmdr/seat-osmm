<?php

use CapsuleCmdr\SeatOsmm\Models\OsmmSetting;

if (! function_exists('osmm_setting')) {
    function osmm_setting(string $key, $default = null) {
        return OsmmSetting::get($key, $default);
    }
}
