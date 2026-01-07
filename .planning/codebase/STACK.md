# Technology Stack

**Analysis Date:** 2026-01-07

## Languages

**Primary:**
- PHP 8.2+ - All application code (`composer.json`)

**Secondary:**
- JavaScript - Frontend assets (`resources/js/admin.js`, `resources/js/front.js`)
- SQL - Database migrations (`database/migrations/*.sql`)
- SCSS - Stylesheets (`resources/sass/admin.scss`, `resources/sass/front.scss`)

## Runtime

**Environment:**
- PHP 8.2+ (strict types, match expressions, named arguments) - `composer.json`
- WordPress environment (requires WordPress installation)
- MySQL/MariaDB database (InnoDB engine)

**Package Manager:**
- Composer for PHP dependencies - `composer.json`, `composer.lock`
- npm for frontend dependencies - `package.json`

## Frameworks

**Core:**
- TypeRocket Pro v6 - WordPress MVC framework (`makermaker.php`, `app/MakermakerTypeRocketPlugin.php`)
  - Eloquent-style ORM via `TypeRocket\Models\Model`
  - Dependency injection in controllers
  - Route collection system
  - Resource registration for admin interface

**Testing:**
- Pest v2.34 - PHP testing framework (BDD-style) - `composer.json`, `tests/Pest.php`
- PHPUnit 10.5 - Unit testing base (via Pest) - `composer.json`
- Brain Monkey v2.6 - WordPress function mocking - `tests/Pest.php`

**Build/Dev:**
- Laravel Mix v4.0.7 - Webpack asset compilation - `webpack.mix.js`
- Sass v1.15.2 - SCSS compilation - `package.json`
- TypeScript v3.6.4 - Type support (configured but minimal usage) - `package.json`

## Key Dependencies

**Critical:**
- `mxcro/makermaker-core` (dev-master) - CRUD scaffolding and helper library - `composer.json`
  - Provides: `RestIndexHelper`, `AuthorizationHelper`, `AuditTrailHelper`, `AutoCodeHelper`, `DeleteHelper`, `RestHelper`, `RedirectHelper`
  - Location: `vendor/mxcro/makermaker-core/`
  - Purpose: Shared abstractions for TypeRocket plugin development

**Testing Infrastructure:**
- `pestphp/pest` ^2.34 - Testing framework
- `brain/monkey` ^2.6 - WordPress mocking
- `mockery/mockery` ^1.6 - Object mocking

**Infrastructure:**
- TypeRocket Pro v6 framework (mu-plugin dependency)
- WordPress core functions and hooks
- MySQL/MariaDB via WordPress `$wpdb` wrapper

## Configuration

**Environment:**
- WordPress configuration (wp-config.php)
- No external environment variables required
- Configuration via TypeRocket resource registration

**Build:**
- `webpack.mix.js` - Asset compilation configuration
- `phpunit.xml` - Test runner configuration
- `tests/Pest.php` - Pest test configuration
- `composer.json` - PHP dependencies and scripts

## Platform Requirements

**Development:**
- PHP 8.2 or higher
- Composer
- npm
- MySQL/MariaDB
- WordPress 5.0+ (compatible with TypeRocket Pro v6)

**Production:**
- PHP 8.2+ environment
- WordPress installation
- MySQL/MariaDB database
- Web server (Apache/Nginx)
- TypeRocket Pro v6 mu-plugin installed

---

*Stack analysis: 2026-01-07*
*Update after major dependency changes*
