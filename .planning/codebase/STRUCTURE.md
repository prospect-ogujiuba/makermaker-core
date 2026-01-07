# Codebase Structure

**Analysis Date:** 2026-01-07

## Directory Layout

```
makermaker/
├── makermaker.php           # Plugin entry point
├── app/                     # Application layer
│   ├── MakermakerTypeRocketPlugin.php  # Main plugin class
│   ├── View.php             # View wrapper
│   ├── Models/              # 23 domain entities
│   ├── Controllers/         # 23 HTTP handlers
│   ├── Http/Fields/         # 21 validation classes
│   ├── Auth/                # 20 authorization policies
│   └── Helpers/             # 2 domain helpers
├── inc/                     # Integration layer
│   ├── resources/           # Admin menu registration
│   └── routes/              # URL routing
├── database/                # Data persistence
│   ├── migrations/          # 50+ SQL files
│   └── docs/                # Database documentation
├── resources/               # Presentation layer
│   ├── views/               # 16+ template subdirectories
│   ├── js/                  # JavaScript source
│   └── sass/                # SCSS source
├── public/                  # Compiled assets
│   ├── admin/               # Admin JS/CSS
│   └── front/               # Frontend JS/CSS
├── vendor/                  # Dependencies
│   └── mxcro/makermaker-core/  # Core package
├── tests/                   # Test suites
│   ├── Pest.php             # Test configuration
│   ├── bootstrap.php        # Test bootstrap
│   ├── Unit/                # Unit tests
│   ├── Integration/         # Integration tests
│   ├── Feature/             # Feature tests
│   ├── Acceptance/          # Acceptance tests
│   └── Factories/           # Test data factories
├── webpack.mix.js           # Build configuration
├── composer.json            # PHP dependencies
├── package.json             # npm dependencies
└── phpunit.xml              # Test runner config
```

## Directory Purposes

**app/**
- Purpose: Application logic (MVC + helpers + policies)
- Contains: PHP classes organized by layer
- Key files:
  - `MakermakerTypeRocketPlugin.php` - Plugin orchestration
  - `View.php` - Custom view wrapper
- Subdirectories:
  - `Models/` - Domain entities (23 files)
  - `Controllers/` - Request handlers (23 files)
  - `Http/Fields/` - Validation rules (21 files)
  - `Auth/` - Authorization policies (20 files)
  - `Helpers/` - Utilities (2 files: ServiceCatalogHelper, TeamHelper)

**inc/**
- Purpose: WordPress integration points
- Contains: Resource registration and routing
- Key files:
  - `resources/service.php` - Service catalog admin menu
  - `resources/team.php` - Team resource registration
  - `resources/contact_submission.php` - Contact submissions
  - `routes/api.php` - API route definitions
  - `routes/public.php` - Public route definitions
- Subdirectories: None (flat structure)

**database/**
- Purpose: Schema and data management
- Contains: SQL migration files
- Key files: Migration naming convention:
  - `1xxx...` - Table creation (e.g., `1758895156.create_services_table.sql`)
  - `2xxx...` - Data inserts (e.g., `2000000015.data_service_delivery.sql`)
  - `3xxx...` - Views (e.g., `3000000001.catalog-active.sql`)
- Subdirectories: `docs/` - Database documentation

**resources/**
- Purpose: Frontend source code (pre-compilation)
- Contains: Views, JavaScript, SCSS
- Key files:
  - `js/admin.js` - Admin JavaScript
  - `js/front.js` - Frontend JavaScript
  - `sass/admin.scss` - Admin styles
  - `sass/front.scss` - Frontend styles
- Subdirectories:
  - `views/{entity}/` - Template files per entity (16+ subdirectories)
  - `views/services/form.php` - Service create/edit form
  - `views/services/index.php` - Service listing

**public/**
- Purpose: Compiled assets (post-build)
- Contains: Production-ready JS/CSS with versioning
- Key files:
  - `admin/admin.js` - Compiled admin JavaScript
  - `admin/admin.css` - Compiled admin styles
  - `admin/mix-manifest.json` - Cache-busting manifest
  - `front/front.js` - Compiled frontend JavaScript
  - `front/front.css` - Compiled frontend styles
- Subdirectories: `admin/`, `front/`

**vendor/mxcro/makermaker-core/**
- Purpose: Shared CRUD scaffolding library
- Contains: Helpers, REST API, Galaxy commands
- Key files:
  - `src/Boot.php` - Initialization
  - `src/Contracts/HasRestActions.php` - REST actions interface
  - `src/helpers.php` - Procedural wrapper functions
- Subdirectories:
  - `src/Helpers/` - 17+ utility classes
  - `src/Rest/` - ReflectiveRestWrapper, QueryBuilder, ActionDispatcher
  - `src/Commands/` - Galaxy CLI commands

**tests/**
- Purpose: Test suites
- Contains: Pest tests organized by type
- Key files:
  - `Pest.php` - Global test configuration, custom expectations
  - `bootstrap.php` - Test environment setup, migrations
- Subdirectories:
  - `Unit/` - Unit tests (fast, isolated)
  - `Integration/` - Integration tests (database, transactions)
  - `Feature/` - Feature tests (user workflows)
  - `Acceptance/` - Acceptance tests (end-to-end)
  - `Factories/` - Test data factories

## Key File Locations

**Entry Points:**
- `makermaker.php` - Plugin bootstrap
- `app/MakermakerTypeRocketPlugin.php` - Main plugin class

**Configuration:**
- `webpack.mix.js` - Asset compilation
- `phpunit.xml` - Test runner
- `composer.json` - PHP dependencies, scripts
- `package.json` - npm dependencies, build scripts

**Core Logic:**
- `app/Models/Service.php` - Core service entity
- `app/Controllers/ServiceController.php` - Service CRUD handler
- `app/Helpers/ServiceCatalogHelper.php` - Service catalog utilities (2,184 lines)
- `vendor/mxcro/makermaker-core/src/Rest/ReflectiveRestWrapper.php` - REST API

**Testing:**
- `tests/Pest.php` - Test configuration
- `tests/bootstrap.php` - Test environment setup
- `tests/Factories/ServiceFactory.php` - Service test factory

**Documentation:**
- `database/docs/Model.md` - Model documentation
- `database/docs/tasks.md` - Database tasks
- `vendor/mxcro/makermaker-core/README.md` - Core package documentation

## Naming Conventions

**Files:**
- Controllers: `{Entity}Controller.php` (e.g., `ServiceController.php`)
- Models: `{Entity}.php` (e.g., `Service.php`)
- Fields: `{Entity}Fields.php` (e.g., `ServiceFields.php`)
- Policies: `{Entity}Policy.php` (e.g., `ServicePolicy.php`)
- Views: `{entity_plural}/form.php` (e.g., `services/form.php`)
- Migrations: `{timestamp}.{description}.sql`

**Directories:**
- PascalCase for class directories: `Models/`, `Controllers/`, `Auth/`
- kebab-case for resource directories: `service-categories/`
- lowercase plural for view directories: `services/`, `teams/`

**Special Patterns:**
- `form.php` - Create/edit form template
- `index.php` - List view template (if exists)
- `*Helper.php` - Static utility class
- `*Policy.php` - Authorization policy
- `*Fields.php` - Validation rules

## Where to Add New Code

**New CRUD Resource:**
- Model: `app/Models/{Entity}.php`
- Controller: `app/Controllers/{Entity}Controller.php`
- Fields: `app/Http/Fields/{Entity}Fields.php`
- Policy: `app/Auth/{Entity}Policy.php`
- View: `resources/views/{entity_plural}/form.php`
- Resource: `inc/resources/{entity}.php`
- Migration: `database/migrations/1{timestamp}.create_{entity_plural}_table.sql`

**New Helper:**
- Plugin-specific: `app/Helpers/{Name}Helper.php`
- Shared (core package): `vendor/mxcro/makermaker-core/src/Helpers/{Name}Helper.php`

**New Route:**
- API: `inc/routes/api.php`
- Public: `inc/routes/public.php`

**New Test:**
- Unit: `tests/Unit/{Name}Test.php`
- Integration: `tests/Integration/{Name}Test.php`
- Factory: `tests/Factories/{Entity}Factory.php`

**Utilities:**
- Domain-specific helpers: `app/Helpers/`
- Test utilities: `tests/Factories/` or inline in test files

## Special Directories

**vendor/**
- Purpose: Composer dependencies
- Source: Managed by Composer (not committed except makermaker-core)
- Committed: No (except `mxcro/makermaker-core` if vendored)

**public/**
- Purpose: Compiled assets
- Source: Generated by Laravel Mix from `resources/`
- Committed: Yes (production builds)

**database/migrations/**
- Purpose: SQL schema and data
- Source: Hand-written SQL files
- Committed: Yes (source of truth for schema)

---

*Structure analysis: 2026-01-07*
*Update when directory structure changes*
