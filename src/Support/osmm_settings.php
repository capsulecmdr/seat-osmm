<?php

use CapsuleCmdr\SeatOsmm\Models\OsmmSetting;

if (! function_exists('osmm_setting')) {
    function osmm_setting(string $key, $default = null) {
        try{
            return OsmmSetting::get($key, $default);
        }catch(Exception $e){
            return 0;
        }
    }
}
