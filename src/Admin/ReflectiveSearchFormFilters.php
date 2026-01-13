<?php

namespace MakermakerCore\Admin;

use MakermakerCore\Helpers\StringHelper;
use TypeRocket\Models\Model;
use ReflectionClass;

/**
 * Reflective Search Form Filters
 *
 * Auto-generates HTML filter inputs for addSearchFormFilter() based on field types.
 * Replaces 100+ lines of manual filter HTML with auto-generated inputs.
 *
 * - Text fields get text inputs
 * - Dates get date range pickers (from/to)
 * - FKs get select dropdowns populated from related models
 * - Booleans get Yes/No/All selects
 * - Enums get select dropdowns with options
 */
class ReflectiveSearchFormFilters
{
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
     * Resource name for URL building
     */
    private string $resourceName;

    /**
     * Fields to exclude from rendering
     * @var array<string>
     */
    private array $excludedFields = [];

    /**
     * Custom field order (if set, only these fields rendered in this order)
     * @var array<string>
     */
    private array $fieldOrder = [];

    /**
     * Field groups for organized display
     * @var array<string, array<string>>
     */
    private array $fieldGroups = [];

    /**
     * Custom renderers for specific fields
     * @var array<string, callable>
     */
    private array $customRenderers = [];

    /**
     * Custom options for FK/enum fields
     * @var array<string, array>
     */
    private array $customOptions = [];

    /**
     * Create a new form filters instance
     *
     * @param Model $model The model instance
     * @param string|null $resourceName Resource name for URLs (auto-derived if null)
     */
    public function __construct(Model $model, ?string $resourceName = null)
    {
        $this->model = $model;
        $this->introspector = new ReflectiveFieldIntrospector($model);
        $this->typeDetector = new FieldTypeDetector($this->introspector);
        $this->resourceName = $resourceName ?? StringHelper::toKebab(
            (new ReflectionClass($model))->getShortName(),
            '_'
        );
    }

    /**
     * Static factory for fluent usage
     *
     * @param Model $model The model instance
     * @param string|null $resourceName Resource name for URLs
     * @return self
     */
    public static function for(Model $model, ?string $resourceName = null): self
    {
        return new self($model, $resourceName);
    }

    /**
     * Render all filter inputs as HTML string
     *
     * @return string Complete HTML for all filters
     */
    public function render(): string
    {
        $html = $this->renderAdvancedSearchActions();
        $html .= '<div class="tr-search-filters">';

        $fields = $this->getFieldsToRender();

        foreach ($fields as $field) {
            $type = $this->typeDetector->detectType($field);
            $label = StringHelper::toTitleCase(str_replace('_id', '', $field));

            // Check for custom renderer
            if (isset($this->customRenderers[$field])) {
                $html .= call_user_func($this->customRenderers[$field], $field, $label, $this);
                continue;
            }

            $html .= match ($type) {
                FieldTypeDetector::TYPE_TEXT => $this->renderTextField($field, $label),
                FieldTypeDetector::TYPE_NUMBER => $this->renderNumberField($field, $label),
                FieldTypeDetector::TYPE_DATE, FieldTypeDetector::TYPE_DATETIME => $this->renderDateField($field, $label),
                FieldTypeDetector::TYPE_BOOLEAN => $this->renderBooleanField($field, $label),
                FieldTypeDetector::TYPE_ENUM => $this->renderEnumField($field, $label, $this->typeDetector->getEnumOptions($field) ?? []),
                FieldTypeDetector::TYPE_FK => $this->renderForeignKeyFieldWithOptions($field, $label),
                default => $this->renderTextField($field, $label),
            };
        }

        $html .= '</div>';
        return $html;
    }

    /**
     * Output rendered HTML directly (for use in callbacks)
     *
     * @return void
     */
    public function output(): void
    {
        echo $this->render();
    }

    /**
     * Render text input field
     *
     * @param string $field Field name
     * @param string $label Display label
     * @return string HTML
     */
    private function renderTextField(string $field, string $label): string
    {
        $value = htmlspecialchars($_GET[$field] ?? '');
        return <<<HTML
        <div class="tr-filter-group">
            <label>{$label}:</label>
            <input type="text" name="{$field}" class="tr-filter" value="{$value}" placeholder="Search {$label}">
        </div>
        HTML;
    }

    /**
     * Render number input field
     *
     * @param string $field Field name
     * @param string $label Display label
     * @return string HTML
     */
    private function renderNumberField(string $field, string $label): string
    {
        $value = htmlspecialchars($_GET[$field] ?? '');
        return <<<HTML
        <div class="tr-filter-group">
            <label>{$label}:</label>
            <input type="number" name="{$field}" class="tr-filter" value="{$value}" placeholder="{$label}">
        </div>
        HTML;
    }

    /**
     * Render date range fields (from/to)
     *
     * @param string $field Field name
     * @param string $label Display label
     * @return string HTML
     */
    private function renderDateField(string $field, string $label): string
    {
        $fromValue = htmlspecialchars($_GET["{$field}_from"] ?? '');
        $toValue = htmlspecialchars($_GET["{$field}_to"] ?? '');
        return <<<HTML
        <div class="tr-filter-group">
            <label>{$label}:</label>
            <div class="tr-date-inputs">
                <input type="date" name="{$field}_from" class="tr-filter" value="{$fromValue}" placeholder="From">
                <input type="date" name="{$field}_to" class="tr-filter" value="{$toValue}" placeholder="To">
            </div>
        </div>
        HTML;
    }

    /**
     * Render boolean select field (Yes/No/All)
     *
     * @param string $field Field name
     * @param string $label Display label
     * @return string HTML
     */
    private function renderBooleanField(string $field, string $label): string
    {
        $value = $_GET[$field] ?? '';
        $yesSelected = $value === '1' ? 'selected' : '';
        $noSelected = $value === '0' ? 'selected' : '';
        return <<<HTML
        <div class="tr-filter-group">
            <label>{$label}:</label>
            <select name="{$field}" class="tr-filter">
                <option value="">All</option>
                <option value="1" {$yesSelected}>Yes</option>
                <option value="0" {$noSelected}>No</option>
            </select>
        </div>
        HTML;
    }

    /**
     * Render enum/select field with options
     *
     * @param string $field Field name
     * @param string $label Display label
     * @param array $options Options array
     * @return string HTML
     */
    private function renderEnumField(string $field, string $label, array $options): string
    {
        $currentValue = $_GET[$field] ?? '';
        $optionsHtml = '<option value="">Select ' . htmlspecialchars($label) . '</option>';

        foreach ($options as $value => $optLabel) {
            $val = is_numeric($value) ? $optLabel : $value;
            $selected = $currentValue === (string) $val ? 'selected' : '';
            $optionsHtml .= '<option value="' . htmlspecialchars($val) . '" ' . $selected . '>' . htmlspecialchars($optLabel) . '</option>';
        }

        return <<<HTML
        <div class="tr-filter-group">
            <label>{$label}:</label>
            <select name="{$field}" class="tr-filter">{$optionsHtml}</select>
        </div>
        HTML;
    }

    /**
     * Render FK field with auto-loaded options
     *
     * @param string $field Field name
     * @param string $label Display label
     * @return string HTML
     */
    private function renderForeignKeyFieldWithOptions(string $field, string $label): string
    {
        // Check for custom options first
        if (isset($this->customOptions[$field])) {
            return $this->renderEnumField($field, $label, $this->customOptions[$field]);
        }

        $relationship = $this->typeDetector->getForeignKeyRelationship($field);
        $options = $relationship ? $this->loadForeignKeyOptions($field, $relationship) : [];
        return $this->renderEnumField($field, $label, $options);
    }

    /**
     * Load options from related model for FK field
     *
     * @param string $field Field name
     * @param string $relationship Relationship method name
     * @return array Options array [id => label]
     */
    private function loadForeignKeyOptions(string $field, string $relationship): array
    {
        $model = $this->model;

        if (!method_exists($model, $relationship)) {
            return [];
        }

        try {
            // Get related model instance via relationship
            $relation = $model->$relationship();
            $relatedModel = $relation->getRelatedModel();
            $relatedClass = get_class($relatedModel);

            // Query related records
            $query = (new $relatedClass())->findAll();

            // Check if model has deleted_at (soft deletes)
            $fillable = (new ReflectiveFieldIntrospector(new $relatedClass()))->getFillable();
            // Note: deleted_at is usually in $guard, not $fillable, so we check property existence
            if (property_exists(new $relatedClass(), 'deleted_at') || method_exists(new $relatedClass(), 'withTrashed')) {
                $query = (new $relatedClass())->where('deleted_at', '=', null)->findAll();
            }

            $records = $query->get();

            $options = [];
            foreach ($records as $record) {
                // Try common display fields
                $recordLabel = $record->name ?? $record->title ?? $record->code ?? "ID: {$record->id}";
                $options[$record->id] = $recordLabel;
            }

            return $options;
        } catch (\Exception $e) {
            // If relationship fails, return empty options
            return [];
        }
    }

    /**
     * Render advanced search actions (reset, search, toggle)
     *
     * @return string HTML
     */
    private function renderAdvancedSearchActions(): string
    {
        $resetUrl = strtok($_SERVER["REQUEST_URI"] ?? '', '?') . '?page=' . $this->resourceName . '_index';
        return <<<HTML
        <div class="tr-search-actions">
            <div>
                <a href="{$resetUrl}" class="button">Reset Filters</a>
                <button type="submit" class="button">Search</button>
            </div>
        </div>
        <input type="checkbox" id="search-toggle" class="search-toggle-input">
        <label for="search-toggle" class="button">Toggle Advanced Search</label>
        HTML;
    }

    /**
     * Get fields to render based on configuration
     *
     * @return array<string> Field names
     */
    private function getFieldsToRender(): array
    {
        if (!empty($this->fieldOrder)) {
            return array_diff($this->fieldOrder, $this->excludedFields);
        }

        // Exclude JSON and image fields by default (not useful for filtering)
        $displayable = $this->introspector->getDisplayableFields();
        $filtered = [];

        foreach ($displayable as $field) {
            if (in_array($field, $this->excludedFields, true)) {
                continue;
            }

            $type = $this->typeDetector->detectType($field);
            if (in_array($type, [FieldTypeDetector::TYPE_JSON, FieldTypeDetector::TYPE_IMAGE], true)) {
                continue;
            }

            $filtered[] = $field;
        }

        return $filtered;
    }

    // =========================================================================
    // FLUENT CONFIGURATION METHODS
    // =========================================================================

    /**
     * Exclude specific fields from rendering
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
     * Set explicit field order (only these fields rendered)
     *
     * @param array<string> $fields Fields in desired order
     * @return self
     */
    public function order(array $fields): self
    {
        $this->fieldOrder = $fields;
        return $this;
    }

    /**
     * Set field groups for organized display
     *
     * @param array<string, array<string>> $groups ['Group Name' => ['field1', 'field2']]
     * @return self
     */
    public function groups(array $groups): self
    {
        $this->fieldGroups = $groups;
        return $this;
    }

    /**
     * Set custom renderer for a specific field
     *
     * @param string $field Field name
     * @param callable $renderer Callback(field, label, $this) returning HTML
     * @return self
     */
    public function customRenderer(string $field, callable $renderer): self
    {
        $this->customRenderers[$field] = $renderer;
        return $this;
    }

    /**
     * Set custom options for a FK/enum field
     *
     * @param string $field Field name
     * @param array $options Options array [value => label]
     * @return self
     */
    public function options(string $field, array $options): self
    {
        $this->customOptions[$field] = $options;
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

    /**
     * Get the resource name
     *
     * @return string
     */
    public function getResourceName(): string
    {
        return $this->resourceName;
    }
}
