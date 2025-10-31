<?php

namespace MakerMaker;

use MakerMaker\Providers\CoreServiceProvider;

class Boot
{
    protected static $initialized = false;
    protected static $paths = [];

    public static function init(array $paths = []): void
    {
        if (self::$initialized) {
            return;
        }

        self::$paths = array_merge([
            'modules_path' => null,
            'config_path' => null,
            'views_path' => null,
            'plugin_dir' => null,
            'plugin_url' => null,
        ], $paths);

        // Register core provider
        CoreServiceProvider::register(self::$paths);

        // Discover and register modules
        if (self::$paths['modules_path'] && is_dir(self::$paths['modules_path'])) {
            ModuleDiscovery::registerAll(self::$paths['modules_path']);
        }

        self::$initialized = true;

        do_action('makermaker/booted', self::$paths);
    }

    public static function getPaths(): array
    {
        return self::$paths;
    }

    public static function getPath(string $key): ?string
    {
        return self::$paths[$key] ?? null;
    }
}
