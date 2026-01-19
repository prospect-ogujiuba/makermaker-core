# Technology Stack

## Languages

**Primary:** PHP ^8.2

## Runtime

- WordPress (via TypeRocket Pro v6)
- PHP 8.2+ required

**Package Manager:** Composer (no lockfile - library package)

## Frameworks

**Core:** TypeRocket Pro v6 - WordPress MVC framework (peer dependency)

## Key Dependencies

**Critical:**
- `typerocket/core` - MVC framework (Model, Controller, Request, Response, Registry)
- WordPress Core - `$wpdb`, hooks, authentication

**Infrastructure:**
- PHP Reflection API - Model introspection
- PHP 8 Attributes - `#[Action]`, `#[BulkAction]`

## Configuration

**Environment:**
- WordPress constants: `MAKERMAKER_PLUGIN_DIR`, `WP_PLUGIN_DIR`, `WP_DEBUG`
- Global WordPress database: `$wpdb`

**Initialization:**
```php
MakermakerCore\Boot::init([
    'modules_path' => PLUGIN_DIR . '/modules',
    'config_path' => PLUGIN_DIR . '/config',
    'views_path' => PLUGIN_DIR . '/resources/views',
    'plugin_dir' => PLUGIN_DIR,
    'plugin_url' => PLUGIN_URL,
]);
```

## Namespace

**Root:** `MakermakerCore\`

**PSR-4:**
```json
{"MakermakerCore\\": "src/"}
```

**Auto-loaded:** `src/helpers.php`

## Component Locations

| Component | Location |
|-----------|----------|
| Admin UI | `src/Admin/` |
| REST API | `src/Rest/` |
| CLI Commands | `src/Commands/` |
| Helpers | `src/Helpers/`, `src/helpers.php` |
| Attributes | `src/Attributes/` |
| Contracts | `src/Contracts/` |
| Templates | `resources/templates/{variant}/` |

Template variants: `simple`, `standard`, `api-ready`
