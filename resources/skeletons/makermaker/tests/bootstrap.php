<?php

// Load Composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Load WordPress (for integration tests)
if (file_exists(__DIR__ . '/../../../../wp-config.php')) {
    require_once __DIR__ . '/../../../../wp-config.php';
}

// Load your plugin's main file (adjust the filename if different)
if (file_exists(__DIR__ . '/../plugin.php')) {
    require_once __DIR__ . '/../plugin.php';
}

// Initialize Brain Monkey for unit tests
Brain\Monkey\setUp();

// Define test constants
if (!defined('MAKERMAKER_PLUGIN_TESTS')) {
    define('MAKERMAKER_PLUGIN_TESTS', true);
}