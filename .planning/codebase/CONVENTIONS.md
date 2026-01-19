# Coding Conventions

## Naming

**Classes:**
- Helpers: `{Domain}Helper` (`StringHelper`, `DatabaseHelper`)
- Admin: `Reflective{Purpose}` (`ReflectiveTable`, `ReflectiveFieldIntrospector`)
- Attributes: Singular (`Action`, `BulkAction`)
- Traits: `Has{Capability}` (`HasDefaultBulkActions`)

**Methods:**
- Public/private: camelCase (`detectType()`, `getColumns()`)
- Static factories: `for()` pattern (`ReflectiveTable::for(Model::class)`)
- Fluent: verb-based (`exclude()`, `sortBy()`, `withoutBulkActions()`)

**Constants:** SCREAMING_SNAKE_CASE (`TYPE_TEXT`, `TYPE_FK`)

## Static Factory Pattern

```php
class ReflectiveTable
{
    public static function for(string $modelClass): self
    {
        return new self($modelClass);
    }
}

// Usage
ReflectiveTable::for(Service::class)
    ->excludeColumns(['metadata'])
    ->render();
```

## Fluent Interface

```php
public function excludeColumns(array $columns): self
{
    $this->excludedColumns = array_merge($this->excludedColumns, $columns);
    return $this;
}
```

Terminal methods: `render()`, `display()`, `getCallback()`, `getColumns()`

## PHP 8 Attributes

```php
#[Attribute(Attribute::TARGET_METHOD)]
class BulkAction
{
    public function __construct(
        public string $label,
        public string $capability = 'edit',
        public bool $requiresConfirmation = true
    ) {}
}

// Usage
#[BulkAction(label: 'Activate', capability: 'edit')]
public function bulkActivate(array $ids): array
```

## Error Handling

**Validation callbacks:** Return `true` or error message string

**Database operations:**
```php
tryDatabaseOperation(
    fn() => $model->save($fields),
    $response,
    'Record created successfully'
);
```

**REST exceptions:**
```php
throw new \Exception("Resource not found", 404);
```

## Caching Pattern

```php
class ReflectiveFieldIntrospector
{
    private static array $cache = [];

    private function ensureCached(): void
    {
        if (isset(self::$cache[$this->className])) return;
        self::$cache[$this->className] = [...];
    }

    public static function clearCache(): void
    {
        self::$cache = [];
    }
}
```

## Helper Wrappers

```php
// src/helpers.php
function mm_table(string $modelClass): ReflectiveTable
{
    return ReflectiveTable::for($modelClass);
}

function mm_introspect(Model $model): ReflectiveFieldIntrospector
{
    return new ReflectiveFieldIntrospector($model);
}
```

## Template Placeholders

```
{{namespace}}      - Full namespace
{{class}}          - PascalCase class name
{{variable}}       - camelCase variable
{{table_name}}     - snake_case table
{{view_path}}      - View directory
{{app_namespace}}  - Application namespace
```
