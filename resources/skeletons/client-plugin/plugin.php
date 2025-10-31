<?php
/*
Plugin Name: {{name}}
Version: 1.0.0
Description: {{description}}
Author: {{author}}
License: GPLv2 or later
*/

if (!function_exists('add_action')) {
    echo 'Hi there! I\'m just a plugin, not much I can do when called directly.';
    exit;
}

// Constants
define('{{constant_prefix}}_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('{{constant_prefix}}_PLUGIN_URL', plugin_dir_url(__FILE__));

// Load Composer autoloader
require_once __DIR__ . '/vendor/autoload.php';

// Bootstrap
add_action('plugins_loaded', function () {
    \MakerMaker\Boot::init([
        'plugin_dir' => {{constant_prefix}}_PLUGIN_DIR,
        'plugin_url' => {{constant_prefix}}_PLUGIN_URL,
        'modules_path' => {{constant_prefix}}_PLUGIN_DIR . 'modules',
        'config_path' => {{constant_prefix}}_PLUGIN_DIR . 'config',
        'views_path' => {{constant_prefix}}_PLUGIN_DIR . 'resources/views',
    ]);

    // Register client provider
    \{{namespace}}\Providers\ClientServiceProvider::register();
}, 20);
