# Coding Conventions

**Analysis Date:** 2026-01-07

## Naming Patterns

**Files:**
- PascalCase for classes matching class name: `Service.php`, `ServiceController.php`, `ServicePolicy.php`
- Test files match class + suffix: `BasicUnitTest.php`, `ReflectiveRestApiTest.php`
- Views: `{entity_plural}/form.php` (lowercase with underscores)

**Functions:**
- camelCase for all functions: `setCreateAuditFields()`, `isRestRequest()`, `generateSkuAndSlug()`
- No special prefix for async functions
- Boolean methods start with `is`, `has`, `can`: `isRestRequest()`, `hasSku()`

**Variables:**
- camelCase for variables: `$categoryId`, `$overrides`, `$serviceName`
- No underscore prefix for private members (PHP 8.2+ doesn't use private markers)

**Types:**
- PascalCase for classes, interfaces, traits: `Service`, `HasRestActions`, `ServicePolicy`
- No `I` prefix for interfaces (just `HasRestActions`, not `IHasRestActions`)
- Enum naming not detected (no enums in codebase)

**Database:**
- Tables: snake_case with prefix: `srvc_services`, `srvc_service_equipment`
- Columns: snake_case: `created_at`, `updated_by`, `is_active`, `service_type_id`
- Constants: SCREAMING_SNAKE_CASE: `GLOBAL_WPDB_PREFIX`, `MAKERMAKER_PLUGIN_DIR`

## Code Style

**Formatting:**
- Tool: No explicit .editorconfig or .prettierrc detected
- Indentation: 4 spaces (consistent across all PHP files)
- Quotes: Single quotes `''` for static strings, double quotes `""` for interpolation
- Semicolons: Required on all statements
- Line length: No hard limit (wraps at logical boundaries)
- Opening braces: Same line (PSR-2 style)

**Linting:**
- Tool: No explicit phpcs.xml or phpstan.neon detected
- Type hints: Full type hints on all parameters and return types (PHP 8.2+ strict)
- Example: `public static function setCreateAuditFields(Model $model, AuthUser $user): void`

## Import Organization

**Order:**
1. External packages (`use TypeRocket\Models\Model`)
2. Internal modules within namespace (`use MakerMaker\Models\Service`)
3. No specific sorting within groups detected

**Grouping:**
- No explicit blank lines between import groups
- Alphabetical sorting not enforced

**Path Aliases:**
- None detected (uses full namespaces)
- Namespace: `MakerMaker\{Layer}\{ClassName}` (e.g., `MakerMaker\Models\Service`)

## Error Handling

**Patterns:**
- Controllers catch model errors via `$model->getErrors()`
- REST requests: `RestHelper::errorResponse($response, $errors, 'Message', 400)`
- Admin requests: `tr_redirect()->back()->withErrors($model->getErrors())`
- No try/catch blocks detected in controllers (relies on TypeRocket error handling)

**Error Types:**
- Validation errors returned via TypeRocket field validation
- Model save errors via `$model->getErrors()`
- Authorization failures via `AuthorizationHelper::authorize()` (abort on fail)

## Logging

**Framework:**
- WordPress debug.log for errors
- No structured logging framework (pino, winston, monolog)

**Patterns:**
- Example: `error_log("Message: " . $error);` (standard PHP)
- No logging detected in normal flow (only for errors)

## Comments

**When to Comment:**
- PHPDoc for public methods (required)
- Inline comments explain "why" not "what"
- Section comments use visual separators: `// ============================================================================`

**PHPDoc/TSDoc:**
- Required for public API methods
- Format:
  ```php
  /**
   * Brief description
   *
   * Longer explanation if needed.
   *
   * @param Type $name Description
   * @return Type Description
   */
  ```
- Example from `AutoCodeHelper.php`:
  ```php
  /**
   * Generate both SKU (uppercase) and slug (lowercase) from name
   *
   * Used for entities with inventory/catalog codes (Service, Equipment).
   *
   * @param array $fields Field array reference (modified in-place)
   * @param string $sourceField Source field name (default: 'name')
   * @param string $separator Separator character (default: '-')
   * @return void
   */
  ```

**TODO Comments:**
- No TODO/FIXME/HACK comments detected in codebase
- Use issue tracking instead of code comments

## Function Design

**Size:**
- No hard limit enforced
- ServiceCatalogHelper has very large methods (file is 2,184 lines)
- Standard controller methods: 30-80 lines

**Parameters:**
- Use dependency injection for framework objects
- Example: `public function create(ServiceFields $fields, Service $service, Response $response, AuthUser $user)`
- Pass-by-reference for field modification: `function generateCode(&$fields, ...)`

**Return Values:**
- Explicit return types required (PHP 8.2+ strict)
- Example: `public static function isRestRequest(): bool`
- Early returns for guard clauses

## Module Design

**Exports:**
- No explicit export pattern (PHP uses `use` statements)
- Public methods are accessible via class name

**Barrel Files:**
- Not applicable (PHP autoloading via Composer PSR-4)

**Namespaces:**
- Plugin: `MakerMaker\{Layer}\{ClassName}`
- Core package: `MakermakerCore\{Component}\{ClassName}`

## Patterns Observed

**Helper Pattern:**
- Static utility classes with public static methods
- Example: `AuditTrailHelper::setCreateAuditFields($model, $user)`
- Located in `app/Helpers/` (plugin) and `vendor/mxcro/makermaker-core/src/Helpers/` (core)

**Model Pattern:**
```php
protected $resource = 'srvc_services';     // Table name
protected $fillable = [...];               // Mass-assignable fields
protected $guard = [...];                  // Protected fields
protected $format = [...];                 // Serialization on save
protected $cast = [...];                   // Deserialization on load
protected $with = [...];                   // Eager load relationships
protected $private = [...];                // Hidden from REST API
```

**Controller Pattern:**
```php
public function create(Fields $fields, Model $model, Response $response, AuthUser $user)
{
    // 1. Authorize
    AuthorizationHelper::authorize($model, 'create', $response);

    // 2. Auto-generate codes
    AutoCodeHelper::generateSkuAndSlug($fields);

    // 3. Set audit fields
    AuditTrailHelper::setCreateAuditFields($model, $user);

    // 4. Save with validation
    $model->save($fields);

    // 5. Return response
    if (RestHelper::isRestRequest()) {
        return RestHelper::successResponse($response, $model, 'Created', 201);
    }
    return tr_redirect()->toPage('resource', 'index');
}
```

**Procedural Wrapper Pattern:**
- Core package provides procedural wrappers for helper methods
- Example: `helpers.php` wraps `AutoCodeHelper` methods
- Format: `function autoGenerateCode(...) { return AutoCodeHelper::generateCode(...); }`

---

*Convention analysis: 2026-01-07*
*Update when patterns change*
