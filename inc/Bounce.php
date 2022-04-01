<?php

require_once __DIR__.'/constants.php';

use CrowdSecBouncer\AbstractBounce;
use CrowdSecBouncer\Bouncer;
use CrowdSecBouncer\Constants;
use CrowdSecBouncer\IBounce;
use CrowdSecBouncer\BouncerException;
use CrowdSecBouncer\Session;

/**
 * The class that apply a bounce.
 *
 * @author    CrowdSec team
 *
 * @see      https://crowdsec.net CrowdSec Official Website
 *
 * @copyright Copyright (c) 2020+ CrowdSec
 * @license   MIT License
 */
class Bounce extends AbstractBounce implements IBounce
{
    public function init(array $crowdSecConfig, array $forcedConfigs = []): Bouncer
    {
        $this->settings = $crowdSecConfig;
        $crowdsecRandomLogFolder = $this->settings['crowdsec_random_log_folder'];
        crowdsecDefineConstants($crowdsecRandomLogFolder);
        $this->setDebug($crowdSecConfig['crowdsec_debug_mode']??false);
        $this->setDisplayErrors($crowdSecConfig['crowdsec_display_errors'] ?? false);
        $this->initLogger();

        return $this->getBouncerInstance($this->settings);
    }

    protected function escape(string $value)
    {
        return htmlspecialchars($value, \ENT_QUOTES, 'UTF-8');
    }

    protected function specialcharsDecodeEntQuotes(string $value)
    {
        return htmlspecialchars_decode($value, \ENT_QUOTES);
    }

    /**
     * @return Bouncer get the bouncer instance
     */
    public function getBouncerInstance(array $settings, bool $forceReload = false): Bouncer
    {
        $crowdSecLogPath = CROWDSEC_LOG_PATH;
        $crowdSecDebugLogPath = CROWDSEC_DEBUG_LOG_PATH;

        $this->logger = getStandaloneCrowdSecLoggerInstance($crowdSecLogPath, $this->debug, $crowdSecDebugLogPath);

        $configs = [
            // LAPI connection
            'api_key' => $this->escape($this->getStringSettings('crowdsec_api_key')),
            'api_url' => $this->escape($this->getStringSettings('crowdsec_api_url')),
            'api_user_agent' => CROWDSEC_BOUNCER_USER_AGENT,
            'api_timeout' => CrowdSecBouncer\Constants::API_TIMEOUT,
            // Debug
            'debug_mode' => $this->getBoolSettings('crowdsec_debug_mode'),
            'log_directory_path' => CROWDSEC_LOG_BASE_PATH,
            'forced_test_ip' => $this->getStringSettings('crowdsec_forced_test_ip'),
            'display_errors' => $this->getBoolSettings('crowdsec_display_errors'),
            // Bouncer
            'bouncing_level' => $this->getStringSettings('crowdsec_bouncing_level'),
            'trust_ip_forward_array' => $this->getArraySettings('crowdsec_trust_ip_forward_array'),
            'fallback_remediation' => $this->getStringSettings('crowdsec_fallback_remediation'),
            // Cache settings
            'stream_mode' => $this->getBoolSettings('crowdsec_stream_mode'),
            'cache_system' => $this->escape($this->getStringSettings('crowdsec_cache_system')),
            'fs_cache_path' => CROWDSEC_CACHE_PATH,
            'redis_dsn' => $this->escape($this->getStringSettings('crowdsec_redis_dsn')),
            'memcached_dsn' => $this->escape($this->getStringSettings('crowdsec_memcached_dsn')),
            'clean_ip_cache_duration' => $this->getIntegerSettings('crowdsec_clean_ip_cache_duration')?:Constants::CACHE_EXPIRATION_FOR_CLEAN_IP,
            'bad_ip_cache_duration' => $this->getIntegerSettings('crowdsec_bad_ip_cache_duration')?:Constants::CACHE_EXPIRATION_FOR_BAD_IP,
            // Geolocation
            'geolocation' => []
        ];


        $this->bouncer = getBouncerInstanceStandalone($configs, $forceReload);

        return $this->bouncer;
    }

    /**
     * @return string Ex: "X-Forwarded-For"
     */
    public function getHttpRequestHeader(string $name): ?string
    {
        $headerName = 'HTTP_'.str_replace('-', '_', strtoupper($name));
        if (!array_key_exists($headerName, $_SERVER)) {
            return null;
        }

        return $_SERVER[$headerName];
    }

    /**
     * @return string The current IP, even if it's the IP of a proxy
     */
    public function getRemoteIp(): string
    {
        return $_SERVER['REMOTE_ADDR'];
    }

    /**
     * @return string The current IP, even if it's the IP of a proxy
     */
    public function getHttpMethod(): string
    {
        return $_SERVER['REQUEST_METHOD'];
    }

    /**
     * @return array ['hide_crowdsec_mentions': bool, color:[text:['primary' : string, 'secondary' : string, 'button' : string, 'error_message : string' ...]]] (returns an array of option required to build the captcha wall template)
     */
    public function getCaptchaWallOptions(): array
    {
        return [
            'hide_crowdsec_mentions' => (bool) $this->getStringSettings('crowdsec_hide_mentions'),
            'color' => [
              'text' => [
                'primary' => $this->specialcharsDecodeEntQuotes($this->getStringSettings('crowdsec_theme_color_text_primary')),
                'secondary' => $this->specialcharsDecodeEntQuotes($this->getStringSettings('crowdsec_theme_color_text_secondary')),
                'button' => $this->specialcharsDecodeEntQuotes($this->getStringSettings('crowdsec_theme_color_text_button')),
                'error_message' => $this->specialcharsDecodeEntQuotes($this->getStringSettings('crowdsec_theme_color_text_error_message')),
              ],
              'background' => [
                'page' => $this->specialcharsDecodeEntQuotes($this->getStringSettings('crowdsec_theme_color_background_page')),
                'container' => $this->specialcharsDecodeEntQuotes($this->getStringSettings('crowdsec_theme_color_background_container')),
                'button' => $this->specialcharsDecodeEntQuotes($this->getStringSettings('crowdsec_theme_color_background_button')),
                'button_hover' => $this->specialcharsDecodeEntQuotes($this->getStringSettings('crowdsec_theme_color_background_button_hover')),
              ],
            ],
            'text' => [
              'captcha_wall' => [
                'tab_title' => $this->specialcharsDecodeEntQuotes($this->getStringSettings('crowdsec_theme_text_captcha_wall_tab_title')),
                'title' => $this->specialcharsDecodeEntQuotes($this->getStringSettings('crowdsec_theme_text_captcha_wall_title')),
                'subtitle' => $this->specialcharsDecodeEntQuotes($this->getStringSettings('crowdsec_theme_text_captcha_wall_subtitle')),
                'refresh_image_link' => $this->specialcharsDecodeEntQuotes($this->getStringSettings('crowdsec_theme_text_captcha_wall_refresh_image_link')),
                'captcha_placeholder' => $this->specialcharsDecodeEntQuotes($this->getStringSettings('crowdsec_theme_text_captcha_wall_captcha_placeholder')),
                'send_button' => $this->specialcharsDecodeEntQuotes($this->getStringSettings('crowdsec_theme_text_captcha_wall_send_button')),
                'error_message' => $this->specialcharsDecodeEntQuotes($this->getStringSettings('crowdsec_theme_text_captcha_wall_error_message')),
                'footer' => $this->specialcharsDecodeEntQuotes($this->getStringSettings('crowdsec_theme_text_captcha_wall_footer')),
              ],
            ],
            'custom_css' => $this->getStringSettings('crowdsec_theme_custom_css'),
          ];
    }

    /**
     * @return array ['hide_crowdsec_mentions': bool, color:[text:['primary' : string, 'secondary' : string, 'error_message : string' ...]]] (returns an array of option required to build the ban wall template)
     */
    public function getBanWallOptions(): array
    {
        return [
            'hide_crowdsec_mentions' => (bool) $this->getStringSettings('crowdsec_hide_mentions'),
            'color' => [
              'text' => [
                'primary' => $this->specialcharsDecodeEntQuotes($this->getStringSettings('crowdsec_theme_color_text_primary')),
                'secondary' => $this->specialcharsDecodeEntQuotes($this->getStringSettings('crowdsec_theme_color_text_secondary')),
                'error_message' => $this->specialcharsDecodeEntQuotes($this->getStringSettings('crowdsec_theme_color_text_error_message')),
              ],
              'background' => [
                'page' => $this->specialcharsDecodeEntQuotes($this->getStringSettings('crowdsec_theme_color_background_page')),
                'container' => $this->specialcharsDecodeEntQuotes($this->getStringSettings('crowdsec_theme_color_background_container')),
                'button' => $this->specialcharsDecodeEntQuotes($this->getStringSettings('crowdsec_theme_color_background_button')),
                'button_hover' => $this->specialcharsDecodeEntQuotes($this->getStringSettings('crowdsec_theme_color_background_button_hover')),
              ],
            ],
            'text' => [
              'ban_wall' => [
                'tab_title' => $this->specialcharsDecodeEntQuotes($this->getStringSettings('crowdsec_theme_text_ban_wall_tab_title')),
                'title' => $this->specialcharsDecodeEntQuotes($this->getStringSettings('crowdsec_theme_text_ban_wall_title')),
                'subtitle' => $this->specialcharsDecodeEntQuotes($this->getStringSettings('crowdsec_theme_text_ban_wall_subtitle')),
                'footer' => $this->specialcharsDecodeEntQuotes($this->getStringSettings('crowdsec_theme_text_ban_wall_footer')),
              ],
            ],
            'custom_css' => $this->specialcharsDecodeEntQuotes($this->getStringSettings('crowdsec_theme_custom_css')),
          ];
    }

    /**
     * @return [[string, string], ...] Returns IP ranges to trust as proxies as an array of comparables ip bounds
     */
    public function getTrustForwardedIpBoundsList(): array
    {
        return $this->getArraySettings('crowdsec_trust_ip_forward_array');
    }

    /**
     * Return a session variable, null if not set.
     */
    public function getSessionVariable(string $name)
    {
        return Session::getSessionVariable($name);
    }

    /**
     * Set a session variable.
     */
    public function setSessionVariable(string $name, $value): void
    {
        Session::setSessionVariable($name, $value);
    }

    /**
     * Unset a session variable, throw an error if this does not exist.
     *
     * @return void;
     */
    public function unsetSessionVariable(string $name): void
    {
        Session::unsetSessionVariable($name);
    }
    /**
     * Get the value of a posted field.
     */
    public function getPostedVariable(string $name): ?string
    {
        if (!isset($_POST[$name])) {
            return null;
        }

        return $_POST[$name];
    }

    /**
     * If the current IP should be bounced or not, matching custom business rules.
     */
    public function shouldBounceCurrentIp(): bool
    {
        // Don't bounce favicon calls.
        if ('/favicon.ico' === $_SERVER['REQUEST_URI']) {
            return false;
        }
		// Don't bounce cli
		if (PHP_SAPI === 'cli') {
			return false;
		}

        $shouldNotBounceWpAdmin = !empty($this->getStringSettings('crowdsec_public_website_only'));
        // when the "crowdsec_public_website_only" is disabled...
        if ($shouldNotBounceWpAdmin) {
            // In standalone context, is_admin() does not work. So we check admin section with another method.
            if (defined('CROWDSEC_STANDALONE_RUNNING_CONTEXT')) {
                // TODO improve the way to detect these pages
                // ...don't bounce back office pages
                if (0 === strpos($_SERVER['PHP_SELF'], '/wp-admin')) {
                    return false;
                }
                // ...don't bounce wp-login and wp-cron pages
                if (0 === strpos($_SERVER['PHP_SELF'], '/wp-login.php')) {
                    return false;
                }
                if (0 === strpos($_SERVER['PHP_SELF'], '/wp-cron.php')) {
                    return false;
                }
            } else {
                // ...don't bounce back office pages
                if (is_admin()) {
                    return false;
                }
                // ...don't bounce wp-login and wp-cron pages
                if (in_array($GLOBALS['pagenow'], ['wp-login.php', 'wp-cron.php'])) {
                    return false;
                }
            }
        }

        if (!$this->isConfigValid()) {
            // We bounce only if plugin config is valid
            return false;
        }

        $bouncingDisabled = (Constants::BOUNCING_LEVEL_DISABLED === $this->escape($this->getStringSettings('crowdsec_bouncing_level')));
        if ($bouncingDisabled) {
            return false;
        }

        return true;
    }

    /**
     * Send HTTP response.
     */
    public function sendResponse(?string $body, int $statusCode = 200): void
    {
        switch ($statusCode) {
            case 200:
                header('HTTP/1.0 200 OK');
                break;
            case 401:
                header('HTTP/1.0 401 Unauthorized');
                header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
                header('Cache-Control: post-check=0, pre-check=0', false);
                header('Pragma: no-cache');
                break;
            case 403:
                header('HTTP/1.0 403 Forbidden');
                header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
                header('Cache-Control: post-check=0, pre-check=0', false);
                header('Pragma: no-cache');
                break;
            default:
                throw new Exception("Unhandled code ${statusCode}");
        }
        if (null !== $body) {
            echo $body;
        }
        die();
    }

    public function safelyBounce(array $configs): bool
    {
        // If there is any technical problem while bouncing, don't block the user. Bypass boucing and log the error.
        set_error_handler(function ($errno, $errstr) {
            throw new BouncerException("$errstr (Error level: $errno)");
        });
        $result = false;
        try {
            if (\PHP_SESSION_NONE === session_status()) {
                session_start();
            }
            // Retro compatibility with crowdsec php lib < 0.14.0
            if($configs['crowdsec_bouncing_level'] === 'normal_boucing'){
                $configs['crowdsec_bouncing_level'] = Constants::BOUNCING_LEVEL_NORMAL;
            }elseif($configs['crowdsec_bouncing_level'] === 'flex_boucing'){
                $configs['crowdsec_bouncing_level'] = Constants::BOUNCING_LEVEL_FLEX;
            }
            $this->init($configs);
            $this->run();
            $result = true;
        } catch (\Exception $e) {
            $this->logger->error('', [
                'type' => 'WP_EXCEPTION_WHILE_BOUNCING',
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            if ($this->displayErrors) {
                throw $e;
            }
        }
        restore_error_handler();

        return $result;
    }

    public function isConfigValid(): bool
    {
        $issues = ['errors' => [], 'warnings' => []];

        $bouncingLevel = $this->escape($this->getStringSettings('crowdsec_bouncing_level'));
        $shouldBounce = (Constants::BOUNCING_LEVEL_DISABLED !== $bouncingLevel);

        if ($shouldBounce) {
            $apiUrl = $this->escape($this->getStringSettings('crowdsec_api_url'));
            if (empty($apiUrl)) {
                $issues['errors'][] = [
                'type' => 'INCORRECT_API_URL',
                'message' => 'Bouncer enabled but no API URL provided',
            ];
            }

            $apiKey = $this->escape($this->getStringSettings('crowdsec_api_key'));
            if (empty($apiKey)) {
                $issues['errors'][] = [
                'type' => 'INCORRECT_API_KEY',
                'message' => 'Bouncer enabled but no API key provided',
            ];
            }

            try {
                $cacheSystem = $this->escape($this->getStringSettings('crowdsec_cache_system'));
                $memcachedDsn = $this->escape($this->getStringSettings('crowdsec_memcached_dsn'));
                $redisDsn = $this->escape($this->getStringSettings('crowdsec_redis_dsn'));
                $fsCachePath = CROWDSEC_CACHE_PATH;
                getCacheAdapterInstanceStandalone($cacheSystem, $memcachedDsn, $redisDsn, $fsCachePath);
            } catch (BouncerException $e) {
                $issues['errors'][] = [
                'type' => 'CACHE_CONFIG_ERROR',
                'message' => $e->getMessage(),
            ];
            }
        }

        return !count($issues['errors']) && !count($issues['warnings']);
    }

    public function initLogger(): void
    {
        $crowdSecLogPath = CROWDSEC_LOG_PATH;
        $crowdSecDebugLogPath = CROWDSEC_DEBUG_LOG_PATH;
        $this->logger = getStandaloneCrowdSecLoggerInstance($crowdSecLogPath, $this->debug, $crowdSecDebugLogPath);
    }
}
