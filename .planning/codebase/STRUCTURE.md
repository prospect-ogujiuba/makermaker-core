# Directory Structure

```
src/
├── Admin/                    # Reflective admin components
│   ├── FieldTypeDetector.php       # Type inference from $cast/naming
│   ├── ReflectiveBulkActions.php   # #[BulkAction] discovery
│   ├── ReflectiveFieldIntrospector.php  # Model property extraction
│   ├── ReflectiveSearchColumns.php      # Table column config
│   ├── ReflectiveSearchFormFilters.php  # Filter form builder
│   ├── ReflectiveSearchModelFilter.php  # Query filter callback
│   └── ReflectiveTable.php              # Table wrapper facade
├── Attributes/               # PHP 8 attributes
│   ├── Action.php                  # REST action declaration
│   └── BulkAction.php              # Bulk action declaration
├── Commands/                 # Galaxy CLI
│   └── Crud.php                    # CRUD scaffolding generator
├── Contracts/                # Interfaces
│   └── HasRestActions.php          # Custom REST actions interface
├── Helpers/                  # Utility classes
│   ├── AuthorizationHelper.php     # Policy authorization
│   ├── DatabaseHelper.php          # DB operations, transactions
│   ├── HtmlHelper.php              # HTML generation
│   ├── StringHelper.php            # String manipulation
│   └── ValidationHelper.php        # Form validation callbacks
├── Rest/                     # REST API layer
│   ├── ActionDispatcher.php        # Custom action execution
│   ├── ReflectiveActionDiscovery.php   # #[Action] attribute scanning
│   ├── ReflectiveQueryBuilder.php      # Search/filter/paginate
│   └── ReflectiveRestWrapper.php       # Route interception
├── Traits/                   # Reusable traits
│   └── HasDefaultBulkActions.php   # Activate/deactivate/delete
├── Boot.php                  # Library initialization
├── ModuleDiscovery.php       # Module loader
└── helpers.php               # Global mm_* functions

resources/
└── templates/                # CRUD scaffolding templates
    ├── simple/                     # Minimal template set
    ├── standard/                   # Full template set
    └── api-ready/                  # API-focused templates
        ├── Controller.txt
        ├── Fields.txt
        ├── Model.txt
        ├── Policy.txt
        ├── index.php.txt
        ├── form.php.txt
        ├── migration.txt
        └── resource.txt

prompts/                      # AI prompts for development
```

## Key File Locations

| Purpose | Path |
|---------|------|
| Table rendering | `src/Admin/ReflectiveTable.php` |
| REST entry | `src/Rest/ReflectiveRestWrapper.php` |
| Query building | `src/Rest/ReflectiveQueryBuilder.php` |
| Type detection | `src/Admin/FieldTypeDetector.php` |
| Model introspection | `src/Admin/ReflectiveFieldIntrospector.php` |
| CRUD generator | `src/Commands/Crud.php` |
| Global helpers | `src/helpers.php` |
| Bootstrap | `src/Boot.php` |

## Naming Conventions

| Type | Pattern | Example |
|------|---------|---------|
| Helper class | `{Domain}Helper` | `StringHelper`, `DatabaseHelper` |
| Reflective component | `Reflective{Purpose}` | `ReflectiveTable`, `ReflectiveQueryBuilder` |
| Attribute | Singular noun | `Action`, `BulkAction` |
| Trait | `Has{Capability}` | `HasDefaultBulkActions` |
| Template | `{Component}.txt` | `Controller.txt`, `Model.txt` |
