# Migration Guide: MakerMaker Monolith to Core + Client

## Overview

This guide explains how to migrate from the original MakerMaker plugin to the new architecture with `makermaker-core` library and thin client plugins.

## Architecture Changes

**Before:**
- Single plugin with all code
- Commands, helpers, templates bundled together
- Client-specific code mixed with reusable code

**After:**
- `mxcro/makermaker-core`: Composer library with reusable logic
- Thin client plugins: Only client-specific code and modules
- Commands available globally via core library

## Migration Steps

### 1. Install Core Library

Add core library to your project:

```bash
composer require mxcro/makermaker-core:^1.0
```

### 2. Create Client Plugin Structure

Use the maker:client command:

```bash
php galaxy maker:client "Client Name" --slug=client-slug --org=your-org
```

Or manually create:
```
client-slug/
├── composer.json (require makermaker-core)
├── plugin.php (bootstrap with Boot::init)
├── config/
│   └── makermaker.php
├── modules/
├── inc/resources/
└── src/Providers/
    └── ClientServiceProvider.php
```

### 3. Move Client-Specific Code

**From original plugin → client plugin:**

- `/app/*` (Models, Controllers, Policies, Fields) → Keep structure
- `/modules/*` → Move to client plugin `/modules`
- `/inc/resources/*.php` → Move to client plugin `/inc/resources`
- `/resources/views/*` → Move to client plugin `/resources/views`

**Delete from client (now in core):**

- `/app/Commands/Crud.php` (in core)
- `/app/Helpers/*` (in core)
- `/inc/templates/*` (in core)

### 4. Update Plugin Bootstrap

Replace original `plugin.php` with:

```php
<?php
/*
Plugin Name: Your Client Plugin
*/

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

### 5. Update Namespaces

**In client plugin:**
- Keep your existing namespace (e.g., `MakerMaker\`)
- Or create new client namespace (e.g., `ClientName\`)

**Update imports:**
- Helpers now imported from `MakerMaker\Helpers\*`
- Commands don't need importing (registered by core)

### 6. Run Composer

```bash
cd client-slug
composer install
```

### 7. Verify

- Activate client plugin in WordPress
- Run `php galaxy` - verify commands are available
- Test CRUD generation: `php galaxy make:crud TestEntity`
- Check modules are loading

## Configuration Overrides

Client plugins can override core config:

**config/makermaker.php:**
```php
return [
    'templates' => [
        'default_variant' => 'api-ready',
    ],
    'crud' => [
        'default_module' => 'core',
    ],
];
```

## CLI Commands

All commands remain available:

```bash
# CRUD generation (unchanged)
php galaxy make:crud Product --module=shop

# New: Create client plugin
php galaxy maker:client "New Client" --slug=new-client
```

## Troubleshooting

**Commands not found:**
- Verify core library is installed: `composer show mxcro/makermaker-core`
- Check `Boot::init()` is called on `plugins_loaded`

**Modules not loading:**
- Verify `modules_path` in `Boot::init()`
- Check `module.php` exists in each module folder

**Templates not found:**
- Core templates are in `vendor/mxcro/makermaker-core/resources/templates`
- Override via config if needed

## Updating Core

To update core library:

```bash
composer update mxcro/makermaker-core
```

Client plugins automatically get new features and bug fixes.
