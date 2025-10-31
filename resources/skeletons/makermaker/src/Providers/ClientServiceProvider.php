<?php

namespace {{namespace}}\Providers;

class ClientServiceProvider
{
    public static function register(): void
    {
        // Client-specific hooks and filters
        
        // Example: Override config
        add_filter('makermaker/config', [self::class, 'configOverrides'], 10, 2);
        
        // Load client resources
        self::loadResources();
        
        do_action('{{slug}}/provider_registered');
    }

    public static function configOverrides(array $config, array $paths): array
    {
        // Override config from client's config/makermaker.php if exists
        $clientConfigFile = $paths['config_path'] . '/makermaker.php';
        if (file_exists($clientConfigFile)) {
            $clientConfig = require $clientConfigFile;
            $config = array_merge($config, $clientConfig);
        }
        
        return $config;
    }

    protected static function loadResources(): void
    {
        $paths = \MakerMaker\Boot::getPaths();
        $resources_dir = $paths['plugin_dir'] . 'inc/resources';

        if (!is_dir($resources_dir)) {
            return;
        }

        $resource_files = glob($resources_dir . '/*.php');
        
        if (empty($resource_files)) {
            return;
        }

        foreach ($resource_files as $file) {
            include_once $file;
        }
    }
}
