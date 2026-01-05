<?php

namespace MakermakerCore;

class ModuleDiscovery
{
    protected static $registered = [];

    public static function registerAll(string $modulesPath): void
    {
        if (!is_dir($modulesPath)) {
            return;
        }

        $modules = glob($modulesPath . '/*/module.php');

        foreach ($modules as $moduleFile) {
            $moduleName = basename(dirname($moduleFile));

            if (isset(self::$registered[$moduleName])) {
                continue;
            }

            // Check for module.json metadata
            $metadataFile = dirname($moduleFile) . '/module.json';
            $metadata = [];
            if (file_exists($metadataFile)) {
                $metadata = json_decode(file_get_contents($metadataFile), true) ?? [];
            }

            // Skip if module is explicitly disabled
            if (isset($metadata['active']) && $metadata['active'] === false) {
                continue;
            }

            require_once $moduleFile;
            self::$registered[$moduleName] = $moduleFile;

            do_action('makermaker/module_loaded', $moduleName, $metadata);
        }
    }

    public static function getRegistered(): array
    {
        return self::$registered;
    }
}
