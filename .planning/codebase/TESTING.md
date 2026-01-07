# Testing Patterns

**Analysis Date:** 2026-01-07

## Test Framework

**Runner:**
- Pest v2.34 (BDD-style testing built on PHPUnit)
- Config: `phpunit.xml` in project root
- Global config: `tests/Pest.php`

**Assertion Library:**
- Pest built-in `expect()` with fluent API
- Matchers: `toBe()`, `toEqual()`, `toBeInstanceOf()`, `toThrow()`, etc.

**Run Commands:**
```bash
composer test                              # Run all tests
composer test:unit                         # Unit tests only
composer test:quick                        # Unit + smoke tests with documentation
composer test:ci                           # With coverage (minimum 85%)
composer test:all                          # All test suites
composer test:affected                     # Exclude slow/quarantined tests
```

## Test File Organization

**Location:**
- Tests live in `tests/` directory organized by type
- No co-location with source files (separate test tree)

**Naming:**
- Unit tests: `{Name}Test.php` (e.g., `BasicUnitTest.php`)
- Integration: `{Name}Test.php` (e.g., `ReflectiveRestApiTest.php`)
- Factories: `{Entity}Factory.php` (e.g., `ServiceFactory.php`)

**Structure:**
```
tests/
  Pest.php                  # Global configuration
  bootstrap.php             # Environment setup
  Unit/
    BasicUnitTest.php
  Integration/
    BasicIntegrationTest.php
    ReflectiveRestApiTest.php
  Feature/
    BasicFeatureTest.php
  Acceptance/
    (acceptance tests)
  Factories/
    ServiceFactory.php
```

## Test Structure

**Suite Organization:**
```php
it('should do something', function () {
    // arrange
    $service = ServiceFactory::create();

    // act
    $result = $service->someMethod();

    // assert
    expect($result)->toBe('expected');
});
```

**Patterns:**
- Use `it()` function for test definitions (Pest syntax)
- Use `expect()` for assertions
- Arrange/Act/Assert pattern (comments optional)
- Auto-grouping via directory (unit, integration, feature, acceptance)

## Mocking

**Framework:**
- Brain Monkey v2.6 - WordPress function/action/filter mocking
- Mockery v1.6 - Object mocking

**WordPress Mocking:**
```php
// Setup in tests/Pest.php
uses()->beforeEach(function () {
    Brain\Monkey\setUp();
})->afterEach(function () {
    Brain\Monkey\tearDown();
    Mockery::close();
})->in('Unit', 'Feature', 'Acceptance');

// Usage in tests
Brain\Monkey\Functions\expect('wp_insert_post')->once()->andReturn(123);
Brain\Monkey\Actions\expectAdded('init');
Brain\Monkey\Filters\expectApplied('the_content');
```

**Custom Expectations:**
```php
// Defined in tests/Pest.php
expect()->extend('toCallWordPressFunction', function (string $function) {
    Brain\Monkey\Functions\expect($function);
});

expect()->extend('toHaveWordPressAction', function (string $action) {
    Brain\Monkey\Actions\expectAdded($action);
});

expect()->extend('toHaveWordPressFilter', function (string $filter) {
    Brain\Monkey\Filters\expectAdded($filter);
});

// Usage
expect('some_function')->toCallWordPressFunction('wp_insert_post');
```

**What to Mock:**
- WordPress core functions (via Brain Monkey)
- External APIs (none detected in this codebase)
- TypeRocket framework classes (via Mockery)

**What NOT to Mock:**
- Internal business logic
- Models and helpers
- Database queries in integration tests

## Fixtures and Factories

**Test Data:**
```php
// Factory pattern in tests/Factories/ServiceFactory.php
class ServiceFactory
{
    public static function create(array $overrides = []): Service
    {
        // Create prerequisites, merge overrides, save
        return Service::new()->findById($id);
    }

    public static function createScenario(string $scenario, array $overrides = []): Service
    {
        $scenarios = [
            'active' => ['is_active' => 1],
            'featured' => ['is_active' => 1, 'is_featured' => 1],
            'complex' => ['estimated_hours' => 40, 'skill_level' => 'expert'],
        ];
        return self::create(array_merge($scenarios[$scenario] ?? [], $overrides));
    }
}

// Usage in tests
$service = ServiceFactory::create(['name' => 'Custom Name']);
$featured = ServiceFactory::createScenario('featured');
```

**Location:**
- Factories: `tests/Factories/` directory
- Inline test data: within test files as needed

## Coverage

**Requirements:**
- Target: 85% line coverage (enforced in CI via `composer test:ci`)
- Configuration: Via `phpunit.xml`

**Configuration:**
- Tool: PHPUnit/Pest built-in coverage
- Exclusions: Vendor directory, compiled assets

**View Coverage:**
```bash
composer test:ci                           # Run with coverage
# Output shows coverage percentage
```

## Test Types

**Unit Tests:**
- Scope: Test single function/class in isolation
- Mocking: Mock all external dependencies (WordPress functions, TypeRocket)
- Speed: Fast (<100ms per test)
- Location: `tests/Unit/`

**Integration Tests:**
- Scope: Test multiple modules together with real database
- Mocking: Mock only external boundaries (WordPress functions)
- Setup: Database transactions for isolation
- Location: `tests/Integration/`

**Integration Test Isolation:**
```php
// From tests/Pest.php
uses()->beforeEach(function () {
    // Start transaction
    global $wpdb;
    $wpdb->query('START TRANSACTION');

    // Create test user with ID=1 for foreign keys
    $wpdb->insert($wpdb->users, ['ID' => 1, ...]);
})->afterEach(function () {
    // Rollback transaction
    global $wpdb;
    $wpdb->query('ROLLBACK');
})->in('Integration');
```

**Feature Tests:**
- Scope: Test user workflows end-to-end
- Mocking: Minimal mocking, mostly real code
- Location: `tests/Feature/`

**Acceptance Tests:**
- Scope: Full end-to-end tests (if browser automation)
- Location: `tests/Acceptance/`

## Common Patterns

**Async Testing:**
Not applicable (PHP is synchronous)

**Error Testing:**
```php
it('should throw on invalid input', function () {
    expect(fn() => functionCall())->toThrow('error message');
});

// With specific exception type
it('should throw ValidationException', function () {
    expect(fn() => functionCall())->toThrow(ValidationException::class);
});
```

**Snapshot Testing:**
- Not used in this codebase

**Database Testing:**
```php
// Integration tests use real database with transaction rollback
it('should save service to database', function () {
    $service = ServiceFactory::create(['name' => 'Test Service']);

    // Query database directly
    global $wpdb;
    $result = $wpdb->get_row("SELECT * FROM srvc_services WHERE id = {$service->id}");

    expect($result->name)->toBe('Test Service');
});
```

## Test Environment Setup

**Bootstrap:**
- File: `tests/bootstrap.php`
- Responsibilities:
  - Load WordPress test environment
  - Run database migrations (extract UP section from migration files)
  - Initialize test database
  - Create test user

**Migration Loading:**
```php
// From tests/bootstrap.php
// Extracts SQL between >>> Up >>> and >>> Down >>> markers
foreach (glob('database/migrations/*.sql') as $file) {
    $sql = extractUpSection(file_get_contents($file));
    $wpdb->query($sql);
}
```

---

*Testing analysis: 2026-01-07*
*Update when test patterns change*
