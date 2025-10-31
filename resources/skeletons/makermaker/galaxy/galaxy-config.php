<?php
// galaxy-config.php

$typerocket = __DIR__ . '/wp-content/mu-plugins/typerocket-pro-v6/typerocket';
define('TYPEROCKET_GALAXY_PATH', $typerocket);
define('TYPEROCKET_CORE_CONFIG_PATH', $typerocket . '/config');
define('TYPEROCKET_ROOT_WP', __DIR__);

// The folder that contains your app folder
// not the app folder itself
define('TYPEROCKET_APP_ROOT_PATH', __DIR__ . '/wp-content/themes/makerstarter');
define('TYPEROCKET_ALT_PATH', __DIR__ . '/wp-content/themes/makerstarter');

// Here we include the app folder
define('TYPEROCKET_AUTOLOAD_APP', [
    'prefix' => 'App',
    'folder' => __DIR__ . '/wp-content/themes/makerstarter/app',
]);
