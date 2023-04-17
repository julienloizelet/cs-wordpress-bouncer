<?php

require_once __DIR__.'/options-config.php';
require_once __DIR__ . '/Constants.php';


function writeStaticConfigFile($name = null, $newValue = null)
{
    $crowdSecWpPluginOptions = getCrowdSecOptionsConfig();
    $data = [];
    foreach ($crowdSecWpPluginOptions as $option) {
        $data[$option['name']] = get_option($option['name']);
    }
    if ($name) {
        $data[$name] = $newValue;
    }
    $json = json_encode($data);
    file_put_contents(Constants::CONFIG_PATH, "<?php return '$json';");
}

/**
 * The code that runs during plugin activation.
 */
function activate_crowdsec_plugin()
{
    flush_rewrite_rules();

    // Set default options.

    $crowdSecWpPluginOptions = getCrowdSecOptionsConfig();
    foreach ($crowdSecWpPluginOptions as $crowdSecWpPluginOption) {
        if ($crowdSecWpPluginOption['autoInit']) {
            update_option($crowdSecWpPluginOption['name'], $crowdSecWpPluginOption['default']);
        }
    }

    writeStaticConfigFile();
}

/**
 * The code that runs during plugin deactivation.
 */
function deactivate_crowdsec_plugin()
{
    flush_rewrite_rules();

    // Unschedule existing "refresh cache" wp-cron.
    unscheduleBlocklistRefresh();

    $apiUrl = esc_attr(get_option('crowdsec_api_url'));
    $apiKey = esc_attr(get_option('crowdsec_api_key'));
    if (!empty($apiUrl) && !empty($apiKey)) {
        // Clear the bouncer cache.
        clearBouncerCacheInAdminPage();
    }

    // Clean options.

    $crowdSecWpPluginOptions = getCrowdSecOptionsConfig();
    foreach ($crowdSecWpPluginOptions as $crowdSecWpPluginOption) {
        if ($crowdSecWpPluginOption['autoInit']) {
            delete_option($crowdSecWpPluginOption['name']);
        }
    }
}
