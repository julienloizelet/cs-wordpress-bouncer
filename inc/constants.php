<?php

function crowdsecDefineConstants(string $crowdsecRandomLogFolder)
{
    if (!defined('CROWDSEC_LOG_PATH')) {
        define('CROWDSEC_LOG_BASE_PATH', __DIR__."/../logs/$crowdsecRandomLogFolder");
        define('CROWDSEC_LOG_PATH', __DIR__."/../logs/$crowdsecRandomLogFolder/prod.log");
        define('CROWDSEC_DEBUG_LOG_PATH', __DIR__."/../logs/$crowdsecRandomLogFolder/debug.log");
        define('CROWDSEC_CACHE_PATH', __DIR__.'/../.cache');
        define('CROWDSEC_CONFIG_PATH', __DIR__.'/standalone-settings.php');
        define('CROWDSEC_BOUNCER_USER_AGENT', 'WordPress CrowdSec Bouncer/v1.6.0');
        define('CROWDSEC_BOUNCER_GEOLOCATION_DIR', __DIR__.'/../geolocation');
    }
}
