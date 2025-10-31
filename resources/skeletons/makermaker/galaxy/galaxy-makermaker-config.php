<?php
// galaxy-makermaker-config.php — always located in the WP root

$wp_root     = __DIR__;
$wp_content  = $wp_root . '/wp-content';
$plugins_dir = $wp_content . '/plugins';
$mm_plugin   = $plugins_dir . '/makermaker';

// TypeRocket possible locations (Pro or Free)
$tr_locations = [
    $wp_content . '/mu-plugins/typerocket-pro-v6/typerocket',
    $wp_content . '/mu-plugins/typerocket/typerocket',
    $plugins_dir . '/typerocket-pro-v6/typerocket',
    $plugins_dir . '/typerocket/typerocket',
];

$typerocket = null;
foreach ($tr_locations as $location) {
    if (file_exists($location . '/init.php')) {
        $typerocket = $location;
        break;
    }
}

if (!$typerocket) {
    wp_die('TypeRocket installation not found in mu-plugins or plugins folder.');
}

// Define constants
define('TYPEROCKET_GALAXY_MAKE_NAMESPACE', 'MakerMaker');
define('TYPEROCKET_GALAXY_PATH', $typerocket);

define('TYPEROCKET_CORE_CONFIG_PATH', $typerocket . '/config');
define('TYPEROCKET_ROOT_WP', $wp_root);
define('TYPEROCKET_APP_ROOT_PATH', $mm_plugin);
define('TYPEROCKET_ALT_PATH', $mm_plugin);
