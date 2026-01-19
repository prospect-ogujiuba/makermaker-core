# Architecture

## Pattern

Reflection-Based CRUD Scaffolding Library
- Zero-configuration via PHP 8 attributes and model introspection
- Extends TypeRocket Pro v6 ORM
- Per-class static caching

## Layers

| Layer | Location | Purpose |
|-------|----------|---------|
| Bootstrap | `src/Boot.php`, `src/ModuleDiscovery.php` | Init, paths, modules |
| REST API | `src/Rest/` | Zero-config endpoints with search/filter/pagination |
| Admin UI | `src/Admin/` | Reflective table/form components |
| Helpers | `src/Helpers/`, `src/helpers.php` | Utilities, validation |
| Commands | `src/Commands/` | Galaxy CLI scaffolding |
| Contracts | `src/Contracts/` | Extension interfaces |
| Attributes | `src/Attributes/` | `#[Action]`, `#[BulkAction]` |

## Data Flow

**REST Request:**
1. `parse_request` hook → `ReflectiveRestWrapper::handleRequest()`
2. Resource config from `TypeRocket\Register\Registry`
3. Model resolved: `{Resource}Controller` → `{Resource}` model
4. Authorization: `$model->can()`
5. Query: `ReflectiveQueryBuilder` with filters/search/pagination
6. JSON response, WordPress halted

**Admin Table:**
1. `mm_table(Model::class)->render()`
2. `ReflectiveFieldIntrospector` extracts `$fillable`, `$cast`, etc.
3. `FieldTypeDetector` infers column types
4. TypeRocket `tr_table()` rendered

## Key Abstractions

**ReflectiveFieldIntrospector** (`src/Admin/ReflectiveFieldIntrospector.php`)
- Extracts model properties via reflection
- Per-class caching

**FieldTypeDetector** (`src/Admin/FieldTypeDetector.php`)
- Infers types from `$cast`, `$format`, naming patterns
- Types: `TYPE_TEXT`, `TYPE_NUMBER`, `TYPE_DATE`, `TYPE_FK`, `TYPE_BOOLEAN`, `TYPE_ENUM`, `TYPE_JSON`, `TYPE_IMAGE`

**ReflectiveQueryBuilder** (`src/Rest/ReflectiveQueryBuilder.php`)
- Builds filtered, searchable, paginated queries
- Auto-detects searchable fields

## Entry Points

| Entry | Location | Trigger |
|-------|----------|---------|
| `Boot::init()` | `src/Boot.php` | `typerocket_loaded` hook |
| `ReflectiveRestWrapper::init()` | `src/Rest/ReflectiveRestWrapper.php` | Plugin init |
| `helpers.php` | `src/helpers.php` | Composer autoload |
| `Crud` command | `src/Commands/Crud.php` | `php galaxy make:crud` |

## Error Handling

- REST: Exceptions → JSON with HTTP status codes
- Validation: 400 with error details
- Auth failures: 403
- Debug mode: Stack traces in response
