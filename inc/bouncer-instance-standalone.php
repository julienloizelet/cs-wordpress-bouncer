<?php

use CrowdSecBouncer\Bouncer;
use CrowdSecBouncer\BouncerException;
use CrowdSecBouncer\Constants;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;
use Symfony\Component\Cache\Adapter\AbstractAdapter;
use Symfony\Component\Cache\Adapter\MemcachedAdapter;
use Symfony\Component\Cache\Adapter\PhpFilesAdapter;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\Cache\Exception\InvalidArgumentException;

/** @var Logger|null */
$crowdSecLogger = null;

function getStandaloneCrowdSecLoggerInstance(string $crowdsecLogPath, bool $debugMode, string $crowdsecDebugLogPath): Logger
{
    // Singleton for this function

    global $crowdSecLogger;
    if ($crowdSecLogger) {
        return $crowdSecLogger;
    }

    // Log more data if debug mode is enabled

    $logger = new Logger('wp_bouncer');

    $fileHandler = new RotatingFileHandler($crowdsecLogPath, 0, Logger::INFO);
    $fileHandler->setFormatter(new LineFormatter("%datetime%|%level%|%context%\n"));
    $logger->pushHandler($fileHandler);

    // Set custom readable logger for debugMode=1
    if ($debugMode) {
        $debugFileHandler = new RotatingFileHandler($crowdsecDebugLogPath, 0, Logger::DEBUG);
		$debugFileHandler->setFormatter(new LineFormatter("%datetime%|%level%|%context%\n"));
		$logger->pushHandler($debugFileHandler);
    }

    return $logger;
}

/** @var AbstractAdapter|null */
$crowdSecCacheAdapterInstance = null;

function getCacheAdapterInstanceStandalone(string $cacheSystem, string $memcachedDsn, string $redisDsn, string
$fsCachePath, bool $forcedReload = false): AbstractAdapter
{
    // Singleton for this function

    global $crowdSecCacheAdapterInstance;
    if (!$forcedReload && $crowdSecCacheAdapterInstance) {
        return $crowdSecCacheAdapterInstance;
    }

    switch ($cacheSystem) {
        case Constants::CACHE_SYSTEM_PHPFS:
            $crowdSecCacheAdapterInstance = new PhpFilesAdapter('', 0, $fsCachePath);
            break;

        case Constants::CACHE_SYSTEM_MEMCACHED:
            if (empty($memcachedDsn)) {
                throw new BouncerException('The selected cache technology is Memcached.'.
                ' Please set a Memcached DSN or select another cache technology.');
            }

            $crowdSecCacheAdapterInstance = new MemcachedAdapter(MemcachedAdapter::createConnection($memcachedDsn));
            break;

        case Constants::CACHE_SYSTEM_REDIS:
            if (empty($redisDsn)) {
                throw new BouncerException('The selected cache technology is Redis.'.
                ' Please set a Redis DSN or select another cache technology.');
            }

            try {
                $crowdSecCacheAdapterInstance = new RedisAdapter(RedisAdapter::createConnection($redisDsn));
            } catch (InvalidArgumentException $e) {
                throw new BouncerException('Error when connecting to Redis.'.
                ' Please fix the Redis DSN or select another cache technology.');
            }

            break;
        default:
            throw new BouncerException('Unknown selected cache technology.');
    }

    return $crowdSecCacheAdapterInstance;
}

$crowdSecBouncer = null;

function getBouncerInstanceStandalone(array $configs, bool $forceReload = false): Bouncer
{
    // Singleton for this function
    global $crowdSecBouncer;
    if (!$forceReload && $crowdSecBouncer) {
        return $crowdSecBouncer;
    }
    $apiUrl = $configs['api_url'];
    // Parse WordPress Options.
    if (empty($apiUrl)) {
        throw new BouncerException('Bouncer enabled but no API URL provided');
    }

    $apiKey = $configs['api_key'];
    if (empty($apiKey)) {
        throw new BouncerException('Bouncer enabled but no API key provided');
    }

    // Init Bouncer instance
    $bouncingLevel = $configs['bouncing_level'];
    switch ($bouncingLevel) {
        case Constants::BOUNCING_LEVEL_DISABLED:
            $maxRemediationLevel = Constants::REMEDIATION_BYPASS;
            break;
        case Constants::BOUNCING_LEVEL_FLEX:
            $maxRemediationLevel = Constants::REMEDIATION_CAPTCHA;
            break;
        case Constants::BOUNCING_LEVEL_NORMAL:
            $maxRemediationLevel = Constants::REMEDIATION_BAN;
            break;
        default:
            throw new BouncerException("Unknown $bouncingLevel");
    }
    $cacheSystem = $configs['cache_system'];
    $memcachedDsn = $configs['memcached_dsn'];
    $redisDsn = $configs['redis_dsn'];
    $fsCachePath = $configs['fs_cache_path'];
    // Instantiate the bouncer
    try {
        $cacheAdapter = getCacheAdapterInstanceStandalone($cacheSystem, $memcachedDsn, $redisDsn, $fsCachePath, $forceReload);
    } catch (Symfony\Component\Cache\Exception\InvalidArgumentException $e) {
        throw new BouncerException($e->getMessage());
    }
    $logger = getStandaloneCrowdSecLoggerInstance(CROWDSEC_LOG_PATH, $configs['debug_mode'], CROWDSEC_DEBUG_LOG_PATH);

    try {
        $bouncer = new Bouncer($cacheAdapter, $logger);
        $finalConfigs = array_merge($configs, ['max_remediation_level' => $maxRemediationLevel]);
        $bouncer->configure($finalConfigs);
    } catch (Exception $e) {
        throw new BouncerException($e->getMessage());
    }
    return $bouncer;
}
