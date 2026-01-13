<?php

namespace MakermakerCore\Admin;

use TypeRocket\Models\Model;
use MakermakerCore\Helpers\StringHelper;
use ReflectionClass;

/**
 * Zero-config reflective table wrapper
 *
 * Combines all reflective components for admin index views.
 * Reduces index view from 100+ lines to ~10 lines.
 *
 * Usage:
 * ```php
 * // Zero config - just works
 * ReflectiveTable::for(Service::class)->render();
 *
 * // With customization
 * ReflectiveTable::for(Service::class)
 *     ->excludeColumns(['metadata', 'long_desc'])
 *     ->excludeFilters(['metadata'])
 *     ->sortBy('name', 'ASC')
 *     ->columnCallback('is_active', fn($v) => $v ? '✓' : '✗')
 *     ->render();
 *
 * // Disable specific features
 * ReflectiveTable::for(Service::class)
 *     ->withoutBulkActions()
 *     ->withoutFormFilters()
 *     ->render();
 * ```
 *
 * @package MakermakerCore\Admin
 */
class ReflectiveTable
{
    /**
     * Full model class name
     */
    private string $modelClass;

    /**
     * Model instance for introspection
     */
    private ?Model $modelInstance = null;

    /**
     * Resource name for URLs
     */
    private string $resourceName;

    /**
     * TypeRocket table instance
     *
     * @var \TypeRocket\Elements\Tables\Table
     */
    private $table;

    // Component instances (lazy loaded)
    private ?ReflectiveFieldIntrospector $introspector = null;
    private ?FieldTypeDetector $typeDetector = null;
    private ?ReflectiveSearchColumns $searchColumns = null;
    private ?ReflectiveSearchFormFilters $formFilters = null;
    private ?ReflectiveSearchModelFilter $modelFilter = null;
    private ?ReflectiveBulkActions $bulkActions = null;

    // Configuration
    private array $columns = [];
    private array $excludedColumns = [];
    private array $excludedFilters = [];
    private array $columnCallbacks = [];
    private string $defaultSort = 'id';
    private string $defaultOrder = 'DESC';
    private bool $useBulkActions = true;
    private bool $useSearchColumns = true;
    private bool $useFormFilters = true;
    private bool $useModelFilter = true;

    /**
     * Create a new ReflectiveTable
     *
     * @param string $modelClass Full model class name
     */
    public function __construct(string $modelClass)
    {
        $this->modelClass = $modelClass;
        $this->modelInstance = new $modelClass();

        // Derive resource name from class
        $shortName = (new ReflectionClass($modelClass))->getShortName();
        $this->resourceName = StringHelper::toKebab($shortName, '_');

        // Initialize TypeRocket table
        $this->table = tr_table($modelClass);
    }

    /**
     * Static factory method
     *
     * @param string $modelClass Full model class name
     * @return self
     */
    public static function for(string $modelClass): self
    {
        return new self($modelClass);
    }

    // =========================================================================
    // LAZY LOADERS
    // =========================================================================

    /**
     * Get or create introspector
     */
    private function getIntrospector(): ReflectiveFieldIntrospector
    {
        return $this->introspector ??= new ReflectiveFieldIntrospector($this->modelInstance);
    }

    /**
     * Get or create type detector
     */
    private function getTypeDetector(): FieldTypeDetector
    {
        return $this->typeDetector ??= new FieldTypeDetector($this->getIntrospector());
    }

    // =========================================================================
    // COLUMN CONFIGURATION
    // =========================================================================

    /**
     * Set explicit columns (overrides auto-discovery)
     *
     * @param array $columns Column configuration array
     * @return self
     */
    public function columns(array $columns): self
    {
        $this->columns = $columns;
        return $this;
    }

    /**
     * Exclude columns from auto-discovery
     *
     * @param array $columns Column names to exclude
     * @return self
     */
    public function excludeColumns(array $columns): self
    {
        $this->excludedColumns = array_merge($this->excludedColumns, $columns);
        return $this;
    }

    /**
     * Add callback for a column
     *
     * @param string $column Column name
     * @param callable $callback Callback function (receives value, item)
     * @return self
     */
    public function columnCallback(string $column, callable $callback): self
    {
        $this->columnCallbacks[$column] = $callback;
        return $this;
    }

    /**
     * Set default sort field and order
     *
     * @param string $field Field to sort by
     * @param string $order Sort direction (ASC or DESC)
     * @return self
     */
    public function sortBy(string $field, string $order = 'DESC'): self
    {
        $this->defaultSort = $field;
        $this->defaultOrder = $order;
        return $this;
    }

    /**
     * Generate columns config from model
     *
     * @return array Column configuration for TypeRocket table
     */
    private function generateColumns(): array
    {
        if (!empty($this->columns)) {
            return $this->columns;
        }

        $columns = [];
        $displayable = $this->getIntrospector()->getDisplayableFields();
        $first = true;

        foreach ($displayable as $field) {
            if (in_array($field, $this->excludedColumns)) {
                continue;
            }

            $type = $this->getTypeDetector()->detectType($field);
            $label = StringHelper::toTitleCase(str_replace('_id', '', $field));

            $config = [
                'label' => $label,
                'sort' => true,
            ];

            // First column gets actions
            if ($first) {
                $config['actions'] = ['edit', 'view', 'delete'];
                $first = false;
            }

            // Add type-specific callbacks
            if (isset($this->columnCallbacks[$field])) {
                $config['callback'] = $this->columnCallbacks[$field];
            } elseif ($type === FieldTypeDetector::TYPE_DATE || $type === FieldTypeDetector::TYPE_DATETIME) {
                $config['callback'] = function ($value) {
                    return $value ? (new \DateTime($value))->format('M d, Y') : 'N/A';
                };
            } elseif ($type === FieldTypeDetector::TYPE_BOOLEAN) {
                $config['callback'] = function ($value) {
                    return $value
                        ? '<span class="badge badge-success">Yes</span>'
                        : '<span class="badge badge-secondary">No</span>';
                };
            } elseif ($type === FieldTypeDetector::TYPE_FK) {
                $relationship = $this->getTypeDetector()->getForeignKeyRelationship($field);
                if ($relationship) {
                    $config['callback'] = function ($value, $item) use ($relationship) {
                        $related = $item->$relationship ?? null;
                        if (!$related) {
                            return '—';
                        }
                        return $related->name ?? $related->title ?? $related->code ?? "#{$value}";
                    };
                }
            }

            $columns[$field] = $config;
        }

        return $columns;
    }

    // =========================================================================
    // COMPONENT CONFIGURATION
    // =========================================================================

    /**
     * Disable bulk actions
     *
     * @return self
     */
    public function withoutBulkActions(): self
    {
        $this->useBulkActions = false;
        return $this;
    }

    /**
     * Disable search columns
     *
     * @return self
     */
    public function withoutSearchColumns(): self
    {
        $this->useSearchColumns = false;
        return $this;
    }

    /**
     * Disable form filters
     *
     * @return self
     */
    public function withoutFormFilters(): self
    {
        $this->useFormFilters = false;
        return $this;
    }

    /**
     * Disable model filter
     *
     * @return self
     */
    public function withoutModelFilter(): self
    {
        $this->useModelFilter = false;
        return $this;
    }

    /**
     * Exclude fields from filters
     *
     * @param array $fields Field names to exclude
     * @return self
     */
    public function excludeFilters(array $fields): self
    {
        $this->excludedFilters = array_merge($this->excludedFilters, $fields);
        return $this;
    }

    /**
     * Set resource name (for URLs)
     *
     * @param string $name Resource name
     * @return self
     */
    public function resource(string $name): self
    {
        $this->resourceName = $name;
        return $this;
    }

    /**
     * Get underlying TypeRocket table for advanced customization
     *
     * @return \TypeRocket\Elements\Tables\Table
     */
    public function getTable()
    {
        return $this->table;
    }

    // =========================================================================
    // RENDER
    // =========================================================================

    /**
     * Configure and render the table
     *
     * @return void
     */
    public function render(): void
    {
        // Configure bulk actions
        if ($this->useBulkActions) {
            $this->bulkActions = ReflectiveBulkActions::for($this->modelInstance);
            [$form, $actions] = $this->bulkActions->getBulkActionsConfig();
            $this->table->setBulkActions($form, $actions);
        }

        // Configure search columns
        if ($this->useSearchColumns) {
            $this->searchColumns = ReflectiveSearchColumns::for($this->modelInstance);
            if (!empty($this->excludedFilters)) {
                $this->searchColumns->exclude($this->excludedFilters);
            }
            $this->table->setSearchColumns($this->searchColumns->getColumns());
        }

        // Configure form filters
        if ($this->useFormFilters) {
            $this->formFilters = ReflectiveSearchFormFilters::for($this->modelInstance, $this->resourceName);
            if (!empty($this->excludedFilters)) {
                $this->formFilters->exclude($this->excludedFilters);
            }
            $formFilters = $this->formFilters;
            $this->table->addSearchFormFilter(function () use ($formFilters) {
                $formFilters->output();
            });
        }

        // Configure model filter
        if ($this->useModelFilter) {
            $this->modelFilter = ReflectiveSearchModelFilter::for($this->modelInstance);
            if (!empty($this->excludedFilters)) {
                $this->modelFilter->exclude($this->excludedFilters);
            }
            $this->table->addSearchModelFilter($this->modelFilter->getCallback());
        }

        // Configure columns
        $columns = $this->generateColumns();
        $this->table->setColumns($columns, $this->defaultSort);
        $this->table->setOrder($this->defaultSort, $this->defaultOrder);

        // Render
        $this->table->render();
    }

    /**
     * Alias for render
     *
     * @return void
     */
    public function display(): void
    {
        $this->render();
    }
}
