# External Integrations

## Data Storage

**Database:** MySQL/MariaDB via WordPress `$wpdb`
- Connection: Inherited from WordPress `wp-config.php`
- Table prefix: `GLOBAL_WPDB_PREFIX`

**ORM:** TypeRocket Model
- Extends `TypeRocket\Models\Model`
- Properties: `$fillable`, `$guard`, `$cast`, `$format`, `$with`, `$private`

**Caching:** In-memory per-request (`ReflectiveFieldIntrospector::$cache`)

## REST API

**Endpoint:** `/tr-api/rest/{resource}/{id?}/actions/{action?}`

| Method | Path | Handler |
|--------|------|---------|
| GET | `/{resource}` | `handleList()` - paginated |
| GET | `/{resource}/{id}` | `handleShow()` |
| POST | `/{resource}` | `handleCreate()` |
| PUT | `/{resource}/{id}` | `handleUpdate()` |
| DELETE | `/{resource}/{id}` | `handleDelete()` |
| POST | `/{resource}/{id}/actions/{action}` | `handleAction()` |

**Query params:** `?search=`, `?field=value`, `?orderby=&order=`, `?per_page=&page=`

**Files:**
- `src/Rest/ReflectiveRestWrapper.php`
- `src/Rest/ReflectiveQueryBuilder.php`
- `src/Rest/ActionDispatcher.php`

## Authentication

**Provider:** WordPress user system via TypeRocket `AuthUser`

**Authorization:** Policy-based via `Model::can($action)`

**Files:** `src/Helpers/AuthorizationHelper.php`

## CLI

**Command:** `php galaxy make:crud {name} [--module=] [--template=] [--force] [--only=] [--skip=]`

**Templates:** `simple`, `standard`, `api-ready`

**Generates:** Migration, Model, Controller, Policy, Fields, Views, Resource

## Module System

**Discovery:** `modules/*/module.php` + `modules/*/module.json`

**Hooks:**
- `makermaker/booted`
- `makermaker/module_loaded`

## WordPress Hooks

**Actions used:** `parse_request` (priority 5)

**Functions:** `status_header()`, `sanitize_text_field()`, `do_action()`
