# Architecture

**Analysis Date:** 2026-01-07

## Pattern Overview

**Overall:** Thin Client WordPress Plugin with Domain-Driven Design

**Key Characteristics:**
- TypeRocket Pro v6 MVC framework foundation
- Domain logic in plugin, shared utilities in Composer package (`mxcro/makermaker-core`)
- Policy-based authorization with auto-discovery
- Zero-configuration REST API via ReflectiveRestWrapper
- Service catalog domain model (23 entities)

## Layers

**Model Layer:**
- Purpose: Domain entities representing service catalog system
- Contains: 23 model classes extending `TypeRocket\Models\Model`
- Location: `app/Models/*.php`
- Depends on: TypeRocket ORM
- Used by: Controllers, Helpers, Views

**Controller Layer:**
- Purpose: HTTP request handling and business workflow orchestration
- Contains: 23 controller classes (one per model)
- Location: `app/Controllers/*.php`
- Depends on: Models, Fields, Helpers, Policies (via DI)
- Used by: TypeRocket router (`inc/routes/*.php`)

**Validation Layer:**
- Purpose: Define validation rules per entity
- Contains: 21 field classes extending `TypeRocket\Http\Fields`
- Location: `app/Http/Fields/*.php`
- Depends on: TypeRocket validation system
- Used by: Controllers (auto-injected)

**Authorization Layer:**
- Purpose: Access control policies per model
- Contains: 20 policy classes extending `TypeRocket\Auth\Policy`
- Location: `app/Auth/*Policy.php`
- Depends on: TypeRocket policy system
- Used by: Controllers via `AuthorizationHelper`

**Helper Layer:**
- Purpose: Cross-cutting utilities and business logic
- Contains: Plugin helpers (2) + Core package helpers (17+)
- Location: `app/Helpers/*.php` (plugin), `vendor/mxcro/makermaker-core/src/Helpers/*.php` (core)
- Depends on: Models, TypeRocket framework
- Used by: Controllers, Models

**Presentation Layer:**
- Purpose: Admin interface forms and views
- Contains: Template files using TypeRocket form builders
- Location: `resources/views/{entity}/*.php`
- Depends on: TypeRocket view/form helpers
- Used by: Controllers

## Data Flow

**Admin CRUD Request:**

1. Browser submits form → WordPress
2. TypeRocket router matches route → `inc/routes/*.php`
3. Controller method invoked → e.g., `ServiceController::create()`
4. Authorization check → `AuthorizationHelper::authorize($model, 'create')`
5. Validation runs → Injected `ServiceFields` validates input
6. Auto-code generation → `AutoCodeHelper::generateSkuAndSlug($fields)`
7. Audit trail → `AuditTrailHelper::setCreateAuditFields($model, $user)`
8. Model save → `$service->save($fields)`
9. Response → Redirect to index or return REST JSON

**REST API Request:**

1. HTTP request → `/tr-api/rest/services`
2. ReflectiveRestWrapper intercepts → `ReflectiveRestWrapper::handleRequest()`
3. Query builder constructs query → search, filter, sort, paginate
4. Execute query → `RestIndexHelper::handleIndex()`
5. JSON response → data + pagination metadata

**State Management:**
- Database-driven (no in-memory state)
- Soft deletes via `deleted_at` column
- Optimistic locking via `version` field
- Audit trail: `created_by`, `updated_by`, `created_at`, `updated_at`

## Key Abstractions

**Resource:**
- Purpose: Register WordPress admin menu structure
- Examples: Service, ServiceCategory, ServicePrice
- Pattern: Created via `mm_create_custom_resource()` helper
- Location: `inc/resources/*.php`

**Policy:**
- Purpose: Authorization logic per model
- Examples: `app/Auth/ServicePolicy.php`, `app/Auth/ServiceCategoryPolicy.php`
- Pattern: Auto-discovered by naming convention (`{Entity}Policy` → `Models\{Entity}`)
- Location: `app/Auth/*Policy.php`

**Helper:**
- Purpose: Static utility methods for cross-cutting concerns
- Examples: `AuditTrailHelper`, `RestHelper`, `AuthorizationHelper`, `AutoCodeHelper`
- Pattern: Static methods, no instantiation
- Location: `app/Helpers/` (plugin-specific), `vendor/mxcro/makermaker-core/src/Helpers/` (shared)

**Model:**
- Purpose: Domain entity with relationships and persistence
- Examples: `app/Models/Service.php`, `app/Models/Equipment.php`
- Pattern: Extends `TypeRocket\Models\Model`, defines `$resource`, `$fillable`, `$with`, relationships
- Location: `app/Models/*.php`

## Entry Points

**Plugin Bootstrap:**
- Location: `makermaker.php`
- Triggers: WordPress plugin load
- Responsibilities: Define constants, load autoloader, hook into `typerocket_loaded`

**Main Plugin Class:**
- Location: `app/MakermakerTypeRocketPlugin.php`
- Triggers: `typerocket_loaded` action (priority 9)
- Responsibilities:
  - Discover and register policies
  - Load resources from `inc/resources/*.php`
  - Register routes from `inc/routes/*.php`
  - Initialize ReflectiveRestApi
  - Enqueue compiled assets

**REST API Entry:**
- Location: `vendor/mxcro/makermaker-core/src/Rest/ReflectiveRestWrapper.php`
- Triggers: TypeRocket REST route match
- Responsibilities: Query building, search/filter/sort/paginate, JSON response

## Error Handling

**Strategy:** Exception bubbling to controller level, then REST or redirect response

**Patterns:**
- Controllers catch model errors via `$model->getErrors()`
- REST requests: `RestHelper::errorResponse($response, $errors, 'Message', 400)`
- Admin requests: `tr_redirect()->back()->withErrors($model->getErrors())`
- Validation errors handled by TypeRocket field injection

## Cross-Cutting Concerns

**Logging:**
- WordPress debug.log for errors
- No structured logging framework detected

**Validation:**
- TypeRocket field classes define rules
- Validation runs on controller method injection
- Example: `app/Http/Fields/ServiceFields.php`

**Authentication:**
- WordPress `current_user_can()` capability checks
- TypeRocket `AuthUser` injected into controllers

**Authorization:**
- Policy-based via `app/Auth/*Policy.php`
- Enforced via `AuthorizationHelper::authorize($model, 'action', $response)`

**Audit Trail:**
- `created_by`, `updated_by` fields on all models
- Set via `AuditTrailHelper::setCreateAuditFields()` and `setUpdateAuditFields()`

**Auto-Code Generation:**
- SKU/slug generation for services, equipment
- Code generation for config entities
- Via `AutoCodeHelper` static methods

---

*Architecture analysis: 2026-01-07*
*Update when major patterns change*
