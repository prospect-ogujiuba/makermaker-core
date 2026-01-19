# Testing

## Current State

**No test suite present.** This is a core library without tests directory or test configuration.

## Recommended Setup

**Framework:** Pest PHP (Laravel-style syntax, works with WordPress)

**Configuration:**
```xml
<!-- phpunit.xml -->
<phpunit bootstrap="vendor/autoload.php">
    <testsuites>
        <testsuite name="Unit">
            <directory>tests/Unit</directory>
        </testsuite>
    </testsuites>
    <coverage processUncoveredFiles="true">
        <include>
            <directory suffix=".php">src</directory>
        </include>
    </coverage>
</phpunit>
```

## Priority Test Areas

**High Priority:**
1. `StringHelper` - Pure functions, easy to test
2. `FieldTypeDetector` - Type inference logic
3. `ReflectiveFieldIntrospector` - Reflection caching
4. `ValidationHelper` - Validation callbacks

**Medium Priority:**
1. `ReflectiveQueryBuilder` - Query construction
2. `ActionDispatcher` - Action discovery/execution
3. `DatabaseHelper` - Database operations

**Low Priority (integration tests):**
1. `ReflectiveRestWrapper` - Requires WordPress/TypeRocket
2. `ReflectiveTable` - Requires TypeRocket Elements

## Test Patterns

**Unit test example:**
```php
test('toKebab converts PascalCase to snake_case', function () {
    expect(StringHelper::toKebab('ServiceCategory'))
        ->toBe('service_category');
});

test('detectType identifies foreign keys by _id suffix', function () {
    $detector = new FieldTypeDetector($introspector);
    expect($detector->detectType('category_id'))
        ->toBe(FieldTypeDetector::TYPE_FK);
});
```

**Mock example:**
```php
test('introspector caches reflection results', function () {
    $model = Mockery::mock(Model::class);

    $intro1 = new ReflectiveFieldIntrospector($model);
    $intro2 = new ReflectiveFieldIntrospector($model);

    // Same cache entry should be used
    expect($intro1->getFillable())->toBe($intro2->getFillable());
});
```

## Coverage Goals

| Component | Target |
|-----------|--------|
| Helpers | 90% |
| Admin components | 80% |
| REST components | 70% |
| Commands | 50% |

## Running Tests

```bash
# All tests
composer test

# With coverage
composer test:coverage

# Specific suite
./vendor/bin/pest tests/Unit
```
