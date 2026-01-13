<?php

namespace MakermakerCore\Admin;

use MakermakerCore\Helpers\StringHelper;
use TypeRocket\Models\Model;

/**
 * Reflective Search Columns
 *
 * Auto-discovers searchable columns from model for tr_table()->setSearchColumns().
 * Uses model $fillable and type detection to generate column config.
 *
 * Non-searchable types (excluded automatically):
 * - TYPE_JSON: Not useful for text search
 * - TYPE_IMAGE: Binary/path data
 * - TYPE_BOOLEAN: Usually filtered, not searched
 *
 * Searchable types:
 * - TYPE_TEXT, TYPE_NUMBER, TYPE_DATE, TYPE_DATETIME, TYPE_ENUM
 */
class ReflectiveSearchColumns
{
    /**
     * Non-searchable field types
     */
    private const NON_SEARCHABLE_TYPES = [
        FieldTypeDetector::TYPE_JSON,
        FieldTypeDetector::TYPE_IMAGE,
    ];

    /**
     * Field introspector instance
     */
    private ReflectiveFieldIntrospector $introspector;

    /**
     * Field type detector instance
     */
    private FieldTypeDetector $typeDetector;

    /**
     * Fields to exclude from search columns
     * @var array<string>
     */
    private array $excludedFields = [];

    /**
     * If set, only include these fields
     * @var array<string>
     */
    private array $includedFields = [];

    /**
     * Custom labels for specific fields
     * @var array<string, string>
     */
    private array $customLabels = [];

    /**
     * Create a new search columns instance for a model
     *
     * @param Model $model The model instance to introspect
     */
    public function __construct(Model $model)
    {
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
     * Get search columns in format expected by setSearchColumns()
     *
     * @return array<string, string> ['field_name' => 'Label']
     */
    public function getColumns(): array
    {
        $columns = [];
        $displayable = $this->introspector->getDisplayableFields();

        foreach ($displayable as $field) {
            // Skip if explicitly excluded
            if (in_array($field, $this->excludedFields, true)) {
                continue;
            }

            // If includedFields is set, only include those
            if (!empty($this->includedFields) && !in_array($field, $this->includedFields, true)) {
                continue;
            }

            $type = $this->typeDetector->detectType($field);

            // Skip non-searchable types
            if (in_array($type, self::NON_SEARCHABLE_TYPES, true)) {
                continue;
            }

            // Generate label from field name or use custom label
            $label = $this->customLabels[$field] ?? StringHelper::toTitleCase($field);

            $columns[$field] = $label;
        }

        return $columns;
    }

    /**
     * Exclude specific fields from search columns
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
     * Only include specific fields (whitelist mode)
     *
     * @param array<string> $fields Fields to include
     * @return self
     */
    public function only(array $fields): self
    {
        $this->includedFields = $fields;
        return $this;
    }

    /**
     * Set custom label for a single field
     *
     * @param string $field Field name
     * @param string $label Custom label
     * @return self
     */
    public function label(string $field, string $label): self
    {
        $this->customLabels[$field] = $label;
        return $this;
    }

    /**
     * Set custom labels for multiple fields
     *
     * @param array<string, string> $labels ['field' => 'Label']
     * @return self
     */
    public function labels(array $labels): self
    {
        $this->customLabels = array_merge($this->customLabels, $labels);
        return $this;
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
