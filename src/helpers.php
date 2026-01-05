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
use TypeRocket\Http\Request;

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