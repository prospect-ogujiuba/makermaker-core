# MakerMaker Core

Core library for scaffolding TypeRocket CRUD applications in WordPress.

## Installation

```bash
composer require mxcro/makermaker-core
```

## Usage

### Creating a Client Plugin

Use the `maker:client` command to scaffold a new client plugin:

```bash
php galaxy maker:client "Client Name" --slug=client-slug --org=your-org
```

This creates a thin plugin with:
- Composer dependency on makermaker-core
- Bootstrap with `Boot::init()`
- Client service provider
- Config overrides
- Modules and resources directories

### Manual Setup

1. **Require in composer.json:**
```json
{
  "require": {
    "mxcro/makermaker-core": "^1.0"
  }
}
```

2. **Bootstrap in plugin.php:**
```php
require __DIR__ . '/vendor/autoload.php';

add_action('plugins_loaded', function () {
    \MakerMaker\Boot::init([
        'plugin_dir' => __DIR__,
        'plugin_url' => plugin_dir_url(__FILE__),
        'modules_path' => __DIR__ . '/modules',
        'config_path' => __DIR__ . '/config',
        'views_path' => __DIR__ . '/resources/views',
    ]);
    
    \YourNamespace\Providers\ClientServiceProvider::register();
}, 20);
```

3. **Create ClientServiceProvider:**
```php
namespace YourNamespace\Providers;

class ClientServiceProvider
{
    public static function register(): void
    {
        add_filter('makermaker/config', [self::class, 'configOverrides'], 10, 2);
        self::loadResources();
    }
    
    public static function configOverrides(array $config, array $paths): array
    {
        // Override config
        return $config;
    }
    
    protected static function loadResources(): void
    {
        // Load TypeRocket resources
    }
}
```

## Features

### CRUD Generation

Generate complete CRUD with Model, Controller, Policy, Fields, Views, and Resources:

```bash
php galaxy make:crud Product --template=standard
php galaxy make:crud Order --module=shop --template=api-ready
```

### Module Discovery

Automatically loads modules from configured path:

```
modules/
├── shop/
│   ├── module.php
│   ├── module.json
│   ├── Models/
│   ├── Controllers/
│   └── resources/
```

### Template Variants

Three built-in variants:
- `simple`: Basic CRUD without REST endpoints
- `standard`: Full CRUD with admin interface
- `api-ready`: CRUD with REST API endpoints

### Configuration

Override via `config/makermaker.php`:

```php
return [
    'templates' => [
        'default_variant' => 'api-ready',
    ],
    'crud' => [
        'default_module' => null,
    ],
];
```

## Architecture

**Core Library (this package):**
- Commands (`make:crud`, `maker:client`)
- Helpers (String, Database, Validation, etc.)
- Templates (simple, standard, api-ready)
- Module discovery
- Config management
- Boot initialization

**Client Plugins:**
- Models, Controllers, Policies, Fields
- Modules with domain logic
- TypeRocket resources
- Views
- Client-specific config

## Requirements

- PHP ^8.2
- TypeRocket Pro v6
- WordPress 5.9+

## License

GPL-2.0-or-later
