<?php

namespace MakermakerCore\Admin;

use MakermakerCore\Helpers\StringHelper;
use TypeRocket\Models\Model;

/**
 * Reflective Search Model Filter
 *
 * Auto-applies $_GET filters to model query for addSearchModelFilter().
 * Uses field types to apply correct operators (LIKE for text, = for exact, range for dates).
 *
 * Replaces 50+ lines of manual query building with auto-applied filters.
 */
class ReflectiveSearchModelFilter
{
    /**
     * Reserved query params that should not be treated as filters
     */
    private const RESERVED_PARAMS = [
        'page', 'paged', 'orderby', 'order', 'per_page',
        's', 'search', 'action', 'route_args', '_wpnonce'
    ];

    /**
     * The model instance
     */
    private Model $model;

    /**
     * Field introspector instance
     */
    private ReflectiveFieldIntrospector $introspector;

    /**
     * Field type detector instance
     */
    private FieldTypeDetector $typeDetector;

    /**
     * Fields to exclude from filtering
     * @var array<string>
     */
    private array $excludedFields = [];

    /**
     * Custom filter callables
     * @var array<string, callable>
     */
    private array $customFilters = [];

    /**
     * Query param to field aliases
     * @var array<string, string>
     */
    private array $fieldAliases = [];

    /**
     * Create a new model filter instance
     *
     * @param Model $model The model instance
     */
    public function __construct(Model $model)
    {
        $this->model = $model;
        $this->introspector = new ReflectiveFieldIntrospector($model);
        $this->typeDetector = new FieldTypeDetector($this->introspector);
    }

    /**
     * Static factory for fluent usage
     *
     * @param Model $model The model instance
     * @return self
     */
    public static function for(Model $model): self
    {
        return new self($model);
    }

    /**
     * Apply filters to model query
     *
     * @param array $args The args passed to the callback
     * @param Model $model The model/query to filter
     * @param mixed $table The table instance
     * @return array The args (required by TypeRocket)
     */
    public function apply(array $args, $model, $table): array
    {
        $fillable = $this->introspector->getFillable();
        $processedDateFields = [];

        foreach ($_GET as $param => $value) {
            // Skip reserved params
            if (in_array($param, self::RESERVED_PARAMS, true)) {
                continue;
            }

            // Handle date range suffixes (_from, _to)
            if (str_ends_with($param, '_from') || str_ends_with($param, '_to')) {
                $baseField = preg_replace('/_(from|to)$/', '', $param);

                if (in_array($baseField, $fillable, true) && !in_array($baseField, $this->excludedFields, true)) {
                    // Only process once per base field
                    if (!in_array($baseField, $processedDateFields, true)) {
                        $this->applyDateRangeFilter($model, $baseField);
                        $processedDateFields[] = $baseField;
                    }
                }
                continue;
            }

            // Check field alias
            $field = $this->fieldAliases[$param] ?? $param;

            // Skip if not a fillable field or explicitly excluded
            if (!in_array($field, $fillable, true)) {
                continue;
            }
            if (in_array($field, $this->excludedFields, true)) {
                continue;
            }

            // Check for custom filter
            if (isset($this->customFilters[$param])) {
                call_user_func($this->customFilters[$param], $model, $field, $value, $this);
                continue;
            }
            if (isset($this->customFilters[$field])) {
                call_user_func($this->customFilters[$field], $model, $field, $value, $this);
                continue;
            }

            // Apply filter based on type
            $type = $this->typeDetector->detectType($field);

            match ($type) {
                FieldTypeDetector::TYPE_TEXT => $this->applyTextFilter($model, $field, $value),
                FieldTypeDetector::TYPE_NUMBER => $this->applyNumberFilter($model, $field, $value),
                FieldTypeDetector::TYPE_BOOLEAN => $this->applyBooleanFilter($model, $field, $value),
                FieldTypeDetector::TYPE_DATE, FieldTypeDetector::TYPE_DATETIME => null, // Handled by range
                FieldTypeDetector::TYPE_ENUM => $this->applyExactFilter($model, $field, $value),
                FieldTypeDetector::TYPE_FK => $this->applyForeignKeyFilter($model, $field, $value),
                default => $this->applyTextFilter($model, $field, $value),
            };
        }

        return $args;
    }

    /**
     * Get a closure for use with addSearchModelFilter()
     *
     * @return \Closure
     */
    public function getCallback(): \Closure
    {
        return fn($args, $model, $table) => $this->apply($args, $model, $table);
    }

    // =========================================================================
    // FILTER APPLICATION METHODS
    // =========================================================================

    /**
     * Apply text filter using LIKE operator
     *
     * @param Model $model Query model
     * @param string $field Field name
     * @param mixed $value Filter value
     */
    private function applyTextFilter($model, string $field, $value): void
    {
        if (empty($value)) {
            return;
        }
        $model->where($field, 'LIKE', '%' . $this->sanitize($value) . '%');
    }

    /**
     * Apply exact match filter
     *
     * @param Model $model Query model
     * @param string $field Field name
     * @param mixed $value Filter value
     */
    private function applyExactFilter($model, string $field, $value): void
    {
        if ($value === '' || $value === null) {
            return;
        }
        $model->where($field, '=', $this->sanitize($value));
    }

    /**
     * Apply number filter
     *
     * @param Model $model Query model
     * @param string $field Field name
     * @param mixed $value Filter value
     */
    private function applyNumberFilter($model, string $field, $value): void
    {
        if ($value === '' || $value === null) {
            return;
        }
        $model->where($field, '=', (int) $value);
    }

    /**
     * Apply boolean filter
     *
     * @param Model $model Query model
     * @param string $field Field name
     * @param mixed $value Filter value
     */
    private function applyBooleanFilter($model, string $field, $value): void
    {
        if ($value === '' || $value === null) {
            return;
        }
        $model->where($field, '=', $value === '1' ? 1 : 0);
    }

    /**
     * Apply date range filter from _from and _to suffixed params
     *
     * @param Model $model Query model
     * @param string $field Base field name (without suffix)
     */
    private function applyDateRangeFilter($model, string $field): void
    {
        $from = $_GET["{$field}_from"] ?? null;
        $to = $_GET["{$field}_to"] ?? null;

        if (!empty($from)) {
            $model->where($field, '>=', $this->sanitize($from) . ' 00:00:00');
        }
        if (!empty($to)) {
            $model->where($field, '<=', $this->sanitize($to) . ' 23:59:59');
        }
    }

    /**
     * Apply foreign key filter
     *
     * @param Model $model Query model
     * @param string $field Field name
     * @param mixed $value Filter value
     */
    private function applyForeignKeyFilter($model, string $field, $value): void
    {
        if (empty($value)) {
            return;
        }
        $model->where($field, '=', (int) $value);
    }

    /**
     * Sanitize filter value
     *
     * @param mixed $value Value to sanitize
     * @return string Sanitized value
     */
    private function sanitize($value): string
    {
        return function_exists('sanitize_text_field')
            ? sanitize_text_field($value)
            : htmlspecialchars(strip_tags($value), ENT_QUOTES, 'UTF-8');
    }

    // =========================================================================
    // FLUENT CONFIGURATION METHODS
    // =========================================================================

    /**
     * Exclude specific fields from filtering
     *
     * @param array<string> $fields Fields to exclude
     * @return self
     */
    public function exclude(array $fields): self
    {
        $this->excludedFields = array_merge($this->excludedFields, $fields);
        return $this;
    }

    /**
     * Set query param to field alias
     *
     * @param string $queryParam The GET parameter name
     * @param string $actualField The actual model field name
     * @return self
     */
    public function alias(string $queryParam, string $actualField): self
    {
        $this->fieldAliases[$queryParam] = $actualField;
        return $this;
    }

    /**
     * Set multiple aliases at once
     *
     * @param array<string, string> $aliases ['param' => 'field']
     * @return self
     */
    public function aliases(array $aliases): self
    {
        $this->fieldAliases = array_merge($this->fieldAliases, $aliases);
        return $this;
    }

    /**
     * Set custom filter for a field
     *
     * @param string $field Field name or query param
     * @param callable $filter Callback($model, $field, $value, $this)
     * @return self
     */
    public function customFilter(string $field, callable $filter): self
    {
        $this->customFilters[$field] = $filter;
        return $this;
    }

    /**
     * Add relationship filter (join + where)
     *
     * @param string $queryParam The GET parameter name
     * @param string $relationship The relationship method name
     * @param string $relatedField The field to filter on in related table
     * @param string $operator The comparison operator (=, LIKE, etc.)
     * @return self
     */
    public function relationshipFilter(string $queryParam, string $relationship, string $relatedField, string $operator = '='): self
    {
        $this->customFilters[$queryParam] = function ($model, $field, $value) use ($relationship, $relatedField, $operator) {
            if (empty($value)) {
                return;
            }

            // Get table names
            $baseTable = $this->model->getResource();

            if (!method_exists($this->model, $relationship)) {
                return;
            }

            $relatedModel = $this->model->$relationship()->getRelatedModel();
            $relatedTable = $relatedModel->getResource();

            // Build FK field name from relationship
            $fkField = StringHelper::toKebab($relationship, '_') . '_id';

            // Apply join
            $model->join($relatedTable, "{$relatedTable}.id", '=', "{$baseTable}.{$fkField}");

            // Apply where
            if ($operator === 'LIKE') {
                $model->where("{$relatedTable}.{$relatedField}", 'LIKE', '%' . $this->sanitize($value) . '%');
            } else {
                $model->where("{$relatedTable}.{$relatedField}", $operator, $this->sanitize($value));
            }
        };

        return $this;
    }

    // =========================================================================
    // ACCESSORS
    // =========================================================================

    /**
     * Get the model instance
     *
     * @return Model
     */
    public function getModel(): Model
    {
        return $this->model;
    }

    /**
     * Get the introspector instance
     *
     * @return ReflectiveFieldIntrospector
     */
    public function getIntrospector(): ReflectiveFieldIntrospector
    {
        return $this->introspector;
    }

    /**
     * Get the type detector instance
     *
     * @return FieldTypeDetector
     */
    public function getTypeDetector(): FieldTypeDetector
    {
        return $this->typeDetector;
    }
}
