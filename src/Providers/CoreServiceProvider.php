<?php

namespace MakerMaker\Providers;

use MakerMaker\Commands\Crud;
use MakerMaker\Commands\MakeClient;

class CoreServiceProvider
{
    protected static $paths = [];

    public static function register(array $paths = []): void
    {
        self::$paths = $paths;

        // Register commands
        self::registerCommands();

        // Load config with filters
        self::loadConfig();

        do_action('makermaker/core_provider_registered');
    }

    protected static function registerCommands(): void
    {
        if (!function_exists('tr_console')) {
            return;
        }

        // Register CRUD command
        add_action('typerocket_console', function ($kernel) {
            $kernel->addCommand(Crud::class);
            $kernel->addCommand(MakeClient::class);
        });
    }

    protected static function loadConfig(): void
    {
        $defaultConfig = require __DIR__ . '/../../config/defaults.php';

        $clientConfig = [];
        if (self::$paths['config_path'] && file_exists(self::$paths['config_path'] . '/makermaker.php')) {
            $clientConfig = require self::$paths['config_path'] . '/makermaker.php';
        }

        $config = array_merge($defaultConfig, $clientConfig);
        $config = apply_filters('makermaker/config', $config, self::$paths);

        // Store config globally if needed
        if (!defined('MAKERMAKER_CONFIG')) {
            define('MAKERMAKER_CONFIG', $config);
        }
    }
}
