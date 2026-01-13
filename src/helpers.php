<?php

/**
 * Helpers for MakerMaker WordPress/TypeRocket application.
 *
 * This file provides convenient procedural wrappers around OOP helper classes.
 * The actual logic is organized in classes under app/Helpers/ directory.
 *
 * @package makermaker-core
 */

use MakermakerCore\Helpers\StringHelper;
use MakermakerCore\Helpers\HtmlHelper;
use MakermakerCore\Helpers\ValidationHelper;
use MakermakerCore\Helpers\DatabaseHelper;
use MakermakerCore\Helpers\DataTransformer;
use MakermakerCore\Helpers\EntityLookup;
use MakermakerCore\Helpers\ResourceHelper;
use MakermakerCore\Helpers\SmartResourceHelper;
use MakermakerCore\Admin\ReflectiveFieldIntrospector;
use MakermakerCore\Admin\FieldTypeDetector;
use MakermakerCore\Admin\ReflectiveSearchColumns;
use MakermakerCore\Admin\ReflectiveSearchFormFilters;
use MakermakerCore\Admin\ReflectiveSearchModelFilter;
use MakermakerCore\Admin\ReflectiveBulkActions;
use MakermakerCore\Admin\ReflectiveTable;
use TypeRocket\Http\Request;
use TypeRocket\Models\Model;

/**
 * ============================================================================
 * HTML/UI GENERATION HELPERS
 * ============================================================================
 */

/**
 * Output HTML options for select dropdowns
 */
function outputSelectOptions($options, $currentValue, $valueKey = null, $labelKey = null): void
{
    HtmlHelper::outputSelectOptions($options, $currentValue, $valueKey, $labelKey);
}

/**
 * Render search actions for TypeRocket admin pages
 */
function renderAdvancedSearchActions(string $resource): void
{
    HtmlHelper::renderAdvancedSearchActions($resource);
}

/**
 * Output select options from a related model
 *
 * @param string $modelClass Full class name of the model
 * @param mixed $currentValue Currently selected value
 * @param string $labelField Field to use as option label (default: 'name')
 */
function mm_model_select_options(string $modelClass, $currentValue, string $labelField = 'name'): void
{
    HtmlHelper::outputModelSelectOptions($modelClass, $currentValue, 'id', $labelField);
}

/**
 * Output select options for a foreign key field with auto-detection
 *
 * @param Model $model The model containing the FK field
 * @param string $fkField The foreign key field name (e.g., 'category_id')
 * @param mixed $currentValue Currently selected value
 */
function mm_fk_select_options(Model $model, string $fkField, $currentValue): void
{
    HtmlHelper::outputForeignKeyOptions($model, $fkField, $currentValue);
}

/**
 * ============================================================================
 * STRING MANIPULATION HELPERS
 * ============================================================================
 */

/**
 * Convert strings to kebab_case or kebab-case
 */
function mm_kebab(string $s, string $separator = '_'): string
{
    return StringHelper::toKebab($s, $separator);
}

/**
 * Convert string to Title Case
 */
function toTitleCase($string)
{
    return StringHelper::toTitleCase($string);
}

/**
 * Pluralize a word, handling delimiters
 */
function pluralize($word)
{
    return StringHelper::pluralize($word);
}

/**
 * Singularize a word, handling delimiters
 */
function singularize($word)
{
    return StringHelper::singularize($word);
}

/**
 * ============================================================================
 * VALIDATION HELPERS
 * ============================================================================
 */

/**
 * Validate integer range
 */
function checkIntRange($args)
{
    return ValidationHelper::checkIntRange($args);
}

/**
 * Check for self-reference in parent-child relationships
 */
function checkSelfReference($args)
{
    return ValidationHelper::checkSelfReference($args);
}

/**
 * Validates that a field value does not equal another field in the same request
 *
 * Usage in validation rules: 'field' => 'callback:checkNotSameAs:other_field:Custom error message'
 */
function checkNotSameAs($args)
{
    return ValidationHelper::checkNotSameAs($args);
}

/**
 * Validate currency is exactly 3 uppercase letters
 */
function validateCurrency($value, $field_name)
{
    return ValidationHelper::validateCurrency($value, $field_name);
}

/**
 * Validate approval status enum
 */
function validateApprovalStatus($value, $field_name)
{
    return ValidationHelper::validateApprovalStatus($value, $field_name);
}

/**
 * Validate date range - valid_to must be after valid_from
 */
function validateDateRange($value, $field_name)
{
    return ValidationHelper::validateDateRange($value, $field_name);
}

/**
 * Check minimum numeric value
 */
function checkMinValue($value, $field_name, $min_value)
{
    return ValidationHelper::checkMinValue($value, $field_name, $min_value);
}

/**
 * Custom validation callback for enum options
 */
function validateEnumOption($value, $encodedOptions)
{
    return ValidationHelper::validateEnumOption($value, $encodedOptions);
}

/**
 * Validates that a value exists in a predefined list (enum/whitelist validation)
 *
 * Usage in validation rules: 'field' => 'callback:checkInList:value1,value2,value3'
 */
function checkInList($args)
{
    return ValidationHelper::checkInList($args);
}

/**
 * ============================================================================
 * DATABASE HELPERS
 * ============================================================================
 */

/**
 * Simple database operation wrapper for TypeRocket controllers
 */
function tryDatabaseOperation(callable $operation, $response, $successMessage = 'Operation completed successfully')
{
    return DatabaseHelper::tryOperation($operation, $response, $successMessage);
}

/**
 * Clean database error message to hide sensitive information
 */
function cleanDatabaseError($error)
{
    return DatabaseHelper::cleanDatabaseError($error);
}

/**
 * Detect circular references in hierarchical data
 */
function hasCircularReference($parentId, $currentId, $tableName, $parentColumn, $idColumn, $visited = [])
{
    return DatabaseHelper::hasCircularReference($parentId, $currentId, $tableName, $parentColumn, $idColumn, $visited);
}

/**
 * ============================================================================
 * DATA TRANSFORMATION HELPERS
 * ============================================================================
 */

/**
 * Auto-generate or sanitize a code/slug field
 */
function autoGenerateCode(&$fields, $codeField = 'code', $sourceField = 'name', $separator = '-', $addon = null, $placement = 'prefix', $uppercase = false)
{
    DataTransformer::autoGenerateCode($fields, $codeField, $sourceField, $separator, $addon, $placement, $uppercase);
}

/**
 * Convert empty string to NULL
 */
function convertEmptyToNull($value)
{
    return DataTransformer::convertEmptyToNull($value);
}

/**
 * ============================================================================
 * ENTITY LOOKUP HELPERS
 * ============================================================================
 */

/**
 * Get entity name by ID for better descriptions
 */
function getEntityName($modelClass, $id)
{
    return EntityLookup::getEntityName($modelClass, $id);
}

/**
 * Get user display name
 */
function getUserName($userId)
{
    return EntityLookup::getUserName($userId);
}

/**
 * ============================================================================
 * RESOURCE MANAGEMENT HELPERS
 * ============================================================================
 */

/**
 * Create TypeRocket resource page + REST endpoint
 */
function mm_create_custom_resource(
    string $resourceKey,
    string $controller,
    string $title,
    bool $hasAddButton = true,
    ?string $restSlug = null,
    bool $registerRest = true
): \TypeRocket\Register\Page {
    return ResourceHelper::createCustomResource($resourceKey, $controller, $title, $hasAddButton, $restSlug, $registerRest);
}

/**
 * Generate a link to a TypeRocket resource page (legacy method)
 * 
 * @deprecated Use to_resource() or SmartResourceHelper::link() instead
 */
function toResourceUrl($resource, $action = 'index', $text = 'Back', $id = null, $icon = 'box-arrow-up-right')
{
    return HtmlHelper::toResourceUrl($resource, $action, $text, $id, $icon);
}

/**
 * Global helper function for easy resource link generation
 */
if (!function_exists('to_resource')) {
    function to_resource($resource, $action = 'index', $text = null, $id = null, $icon = null, $class = 'button')
    {
        return SmartResourceHelper::link($resource, $action, $text, $id, $icon, $class);
    }
}

/**
 * Global helper function for resource URL generation
 */
if (!function_exists('resource_url')) {
    function resource_url($resource, $action = 'index', $id = null)
    {
        return SmartResourceHelper::url($resource, $action, $id);
    }
}

/**
 * ============================================================================
 * MODEL INTROSPECTION HELPERS
 * ============================================================================
 */

/**
 * Create a ReflectiveFieldIntrospector for a model
 *
 * Provides access to model properties ($fillable, $guard, $with, $cast, $format, $private)
 * via reflection with per-class caching.
 *
 * @param Model $model The model instance to introspect
 * @return ReflectiveFieldIntrospector
 */
function mm_introspect(Model $model): ReflectiveFieldIntrospector
{
    return new ReflectiveFieldIntrospector($model);
}

/**
 * Detect the type of a model field
 *
 * Infers type from $cast, $format, and naming patterns.
 * Returns one of the FieldTypeDetector::TYPE_* constants.
 *
 * @param Model $model The model instance
 * @param string $field The field name
 * @return string The detected type constant
 */
function mm_detect_field_type(Model $model, string $field): string
{
    $introspector = new ReflectiveFieldIntrospector($model);
    $detector = new FieldTypeDetector($introspector);
    return $detector->detectType($field);
}

/**
 * Get auto-discovered search columns for a model
 *
 * Returns array in format expected by tr_table()->setSearchColumns().
 * Auto-excludes non-searchable types (JSON, image).
 *
 * Usage:
 *   $table->setSearchColumns(mm_search_columns(new Service()))
 *
 * For customization, use ReflectiveSearchColumns directly:
 *   ReflectiveSearchColumns::for(new Service())->exclude(['metadata'])->getColumns()
 *
 * @param Model $model The model instance
 * @return array<string, string> ['field_name' => 'Label']
 */
function mm_search_columns(Model $model): array
{
    return ReflectiveSearchColumns::for($model)->getColumns();
}

/**
 * Get auto-generated search form filters for a model
 *
 * Returns a ReflectiveSearchFormFilters instance for fluent configuration.
 * Use ->output() in addSearchFormFilter() callback.
 *
 * Usage:
 *   $table->addSearchFormFilter(function() {
 *       mm_search_form_filters(new Service(), 'service')->output();
 *   });
 *
 * With customization:
 *   $table->addSearchFormFilter(function() {
 *       mm_search_form_filters(new Service(), 'service')
 *           ->exclude(['metadata', 'long_desc'])
 *           ->order(['name', 'category_id', 'is_active'])
 *           ->output();
 *   });
 *
 * @param Model $model The model instance
 * @param string|null $resourceName Resource name for URLs (auto-derived if null)
 * @return ReflectiveSearchFormFilters
 */
function mm_search_form_filters(Model $model, ?string $resourceName = null): ReflectiveSearchFormFilters
{
    return ReflectiveSearchFormFilters::for($model, $resourceName);
}

/**
 * Get auto-applied model query filter for addSearchModelFilter()
 *
 * Returns a ReflectiveSearchModelFilter instance for fluent configuration.
 * Use ->getCallback() to get the closure for addSearchModelFilter().
 *
 * Usage:
 *   $table->addSearchModelFilter(mm_search_model_filter(new Service())->getCallback());
 *
 * With customization:
 *   $table->addSearchModelFilter(
 *       mm_search_model_filter(new Service())
 *           ->exclude(['metadata'])
 *           ->alias('category', 'category_id')
 *           ->relationshipFilter('category_name', 'category', 'name', 'LIKE')
 *           ->getCallback()
 *   );
 *
 * @param Model $model The model instance
 * @return ReflectiveSearchModelFilter
 */
function mm_search_model_filter(Model $model): ReflectiveSearchModelFilter
{
    return ReflectiveSearchModelFilter::for($model);
}

/**
 * Get bulk actions for a model/controller
 *
 * Discovers bulk actions from #[BulkAction] attributed methods.
 * Returns a ReflectiveBulkActions instance for fluent configuration.
 *
 * Usage:
 *   [$form, $actions] = mm_bulk_actions(new Service())->getBulkActionsConfig();
 *   $table->setBulkActions($form, $actions);
 *
 * With customization:
 *   $table->setBulkActions(
 *       ...mm_bulk_actions(new Service())
 *           ->exclude(['archive'])
 *           ->add('export', 'Export to CSV')
 *           ->getBulkActionsConfig()
 *   );
 *
 * @param object $target Model or Controller instance
 * @return ReflectiveBulkActions
 */
function mm_bulk_actions(object $target): ReflectiveBulkActions
{
    return ReflectiveBulkActions::for($target);
}

/**
 * Create a reflective table for a model
 *
 * Zero-config table that combines all reflective components:
 * - Auto-discovered columns from model
 * - Search columns for text search
 * - Form filters for field filtering
 * - Model filters for query application
 * - Bulk actions from #[BulkAction] attributes
 *
 * Usage:
 *   // Zero config - just works
 *   mm_table(Service::class)->render();
 *
 *   // With customization
 *   mm_table(Service::class)
 *       ->excludeColumns(['metadata', 'long_desc'])
 *       ->excludeFilters(['metadata'])
 *       ->sortBy('name', 'ASC')
 *       ->columnCallback('is_active', fn($v) => $v ? '✓' : '✗')
 *       ->render();
 *
 *   // Disable specific features
 *   mm_table(Service::class)
 *       ->withoutBulkActions()
 *       ->withoutFormFilters()
 *       ->render();
 *
 * @param string $modelClass Full model class name
 * @return ReflectiveTable
 */
function mm_table(string $modelClass): ReflectiveTable
{
    return ReflectiveTable::for($modelClass);
}