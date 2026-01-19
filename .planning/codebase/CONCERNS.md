# Codebase Concerns

## Tech Debt

**Large Files:**
- `src/Commands/Crud.php` (694 lines) - All CRUD generation in single class
- `src/Rest/ReflectiveRestWrapper.php` (539 lines) - All REST operations
- Fix: Extract into strategy classes per operation

**Duplicated Logic:**
- Field type detection in both `ReflectiveFieldIntrospector::inferFieldType()` and `FieldTypeDetector::detectType()`
- TEXT_FIELD_PATTERNS in `ReflectiveQueryBuilder` and `FieldTypeDetector`
- Fix: Centralize in `FieldTypeDetector`

**Hardcoded Namespace:**
- `ReflectiveRestWrapper` defaults to `\MakerMaker\Models\`
- Fix: Auto-detect from controller or Boot config

## Security Considerations

**Direct $_GET Access:**
- Files: `ReflectiveQueryBuilder`, `ReflectiveSearchFormFilters`, `ReflectiveSearchModelFilter`
- Mitigation: Values sanitized with `sanitize_text_field()`
- Recommendation: Use TypeRocket Request consistently

**SQL Column Interpolation:**
- `DatabaseHelper::hasCircularReference()` interpolates column names
- Mitigation: Column names from internal code only
- Recommendation: Validate against whitelist

**REST Error Exposure:**
- Exception messages in responses; traces in WP_DEBUG
- Recommendation: Sanitize messages in production

## Performance

**Reflection Caching:**
- `ReflectiveFieldIntrospector` uses static per-class cache
- Already implemented; consider persistent cache for high-traffic

**FK Options Loading:**
- `loadForeignKeyOptions()` creates multiple model instances
- Recommendation: Cache related model instances

**Pagination:**
- `per_page` defaults to 100, enforced max 100
- Consider lower default (20-50) for large datasets

## Fragile Areas

**Model-Controller Convention:**
- Assumes `ServiceController` → `Service` model naming
- Add explicit model class config per resource

**Field Type Heuristics:**
- Relies on naming patterns (`_id`, `_at`, `is_*`)
- Override via model `$cast` property

**Custom Endpoint Mapping:**
- Hardcoded method mappings (types, priorities, submit)
- No extensibility mechanism

## Missing Features

**High Priority:**
- No test suite
- No structured logging (uses `error_log()`)

**Medium Priority:**
- No centralized input validation layer
- No rate limiting on REST API

## Dependencies at Risk

**TypeRocket Pro v6:**
- Heavy dependency on internals (Registry, Request, Response, Model)
- Version changes may break functionality

**PHP 8 Attributes:**
- Requires PHP 8.0+ minimum
- No backport needed, but document requirement
