<?php

namespace MakermakerCore\Helpers;

use TypeRocket\Http\Request;

/**
 * Validation utilities for TypeRocket forms
 * 
 * Provides custom validation methods for common scenarios
 */
class ValidationHelper
{
    /**
     * Validate integer range
     * 
     * @param array $args Validator arguments
     * @return bool|string True if valid, error message if invalid
     */
    public static function checkIntRange(array $args)
    {
        /**
         * @var $option3
         * @var $option
         * @var $option2
         * @var $name
         * @var $field_name
         * @var $value
         * @var $type
         * @var \TypeRocket\Utility\Validator $validator
         */
        extract($args);

        // Check if value is numeric first
        if (!is_numeric($value)) {
            return ' must be a valid number';
        }

        $numValue = (int) $value;

        // For callback validators:
        // $option = function name (checkIntRange)
        // $option2 = first parameter (min value)  
        // $option3 = second parameter (max value)
        $min = isset($option2) ? (int) $option2 : 0;
        $max = isset($option3) ? (int) $option3 : 255;

        // Ensure min is not greater than max
        if ($min > $max) {
            $temp = $min;
            $min = $max;
            $max = $temp;
        }

        // Check for valid range
        if ($numValue < $min || $numValue > $max) {
            return " must be between {$min} and {$max}";
        }

        return true;
    }

    /**
     * Check for self-reference in parent-child relationships
     * 
     * This function prevents entities from referencing themselves as parents
     * and can detect circular references in hierarchical data structures.
     * 
     * @param array $args Standard TypeRocket validator args
     * @return true|string Returns true if valid, error message if invalid
     */
    public static function checkSelfReference(array $args)
    {
        /**
         * @var $option - table name (required)
         * @var $option2 - parent column name (default: 'parent_id')
         * @var $option3 - primary key column name (default: 'id')
         * @var $value - the parent_id value being validated
         * @var $validator - TypeRocket Validator instance
         * @var $weak - whether this is an optional field
         */
        extract($args);

        // Check if this is an optional field and the value is considered "empty" by TypeRocket standards
        if (isset($weak) && $weak && \TypeRocket\Utility\Data::emptyOrBlankRecursive($value)) {
            return true;
        }

        // If no value provided, it's valid (nullable parent)
        // Handle all possible "empty" states from select dropdowns
        if (
            $value === null || $value === '' || $value === 0 || $value === '0' ||
            (is_string($value) && trim($value) === '') ||
            (is_array($value) && empty($value))
        ) {
            return true;
        }

        // Get current record ID from route args
        $request = Request::new();
        $route_args = $request->getDataGet('route_args');
        $currentId = $route_args[0] ?? null;

        // Convert to integer for comparison
        $parentId = (int) $value;

        // If no current ID (new record), no self-reference possible
        if (!$currentId) {
            return true;
        }

        // Convert current ID to integer for comparison
        $currentId = (int) $currentId;

        // Direct self-reference check
        if ($currentId === $parentId) {
            return ' cannot reference itself as parent';
        }

        // Check for circular reference by traversing up the hierarchy
        $tableName = $option; // Required: table name
        $parentColumn = $option2 ?? 'parent_id';
        $idColumn = $option3 ?? 'id';

        if (DatabaseHelper::hasCircularReference($parentId, $currentId, $tableName, $parentColumn, $idColumn)) {
            return ' would create a circular reference';
        }

        return true;
    }

    /**
     * Validate currency is exactly 3 uppercase letters
     * 
     * @param mixed $value The value to validate
     * @param string $field_name Field name
     * @return bool|string True if valid, error message if invalid
     */
    public static function validateCurrency($value, string $field_name)
    {
        if (!preg_match('/^[A-Z]{3}$/', $value)) {
            return 'Currency must be exactly 3 uppercase letters (e.g., CAD, USD)';
        }
        return true;
    }

    /**
     * Validate approval status enum
     * 
     * @param mixed $value The value to validate
     * @param string $field_name Field name
     * @return bool|string True if valid, error message if invalid
     */
    public static function validateApprovalStatus($value, string $field_name)
    {
        $valid_statuses = ['draft', 'pending', 'approved', 'rejected'];
        if (!in_array($value, $valid_statuses)) {
            return 'Approval status must be one of: ' . implode(', ', $valid_statuses);
        }
        return true;
    }

    /**
     * Validate date range - valid_to must be after valid_from
     * 
     * @param mixed $value The value to validate
     * @param string $field_name Field name
     * @return bool|string True if valid, error message if invalid
     */
    public static function validateDateRange($value, string $field_name)
    {
        $request = Request::new();
        $fields = $request->getFields();
        $valid_from = $fields['valid_from'] ?? null;

        if ($value && $valid_from) {
            $from_time = strtotime($valid_from);
            $to_time = strtotime($value);

            if ($to_time <= $from_time) {
                return 'Valid to date must be after valid from date';
            }
        }
        return true;
    }

    /**
     * Check minimum numeric value
     * 
     * @param mixed $value The value to validate
     * @param string $field_name Field name
     * @param float $min_value Minimum allowed value
     * @return bool|string True if valid, error message if invalid
     */
    public static function checkMinValue($value, string $field_name, float $min_value)
    {
        if ($value !== null && $value !== '' && (float)$value < $min_value) {
            return "must be greater than or equal to {$min_value}";
        }
        return true;
    }

    /**
     * Custom validation callback for enum options
     *
     * @param mixed $value The value being validated
     * @param string $encodedOptions Base64 encoded JSON array of valid options
     * @return bool
     */
    public static function validateEnumOption($value, string $encodedOptions): bool
    {
        $validOptions = json_decode(base64_decode($encodedOptions), true);

        if (!is_array($validOptions)) {
            return false;
        }

        return in_array((string)$value, $validOptions, true);
    }

    /**
     * Cache for reflected ENUM values to prevent repeated database queries
     * Key format: "ModelClass::fieldName"
     *
     * @var array
     */
    protected static $enumCache = [];

    /**
     * Validates that a value exists in a predefined list (enum/whitelist validation)
     *
     * This validator provides enum validation for TypeRocket form fields with two modes:
     *
     * MODE 1 - Explicit list (backward compatible):
     *   'field' => 'callback:checkInList:value1,value2,value3'
     *   Validates against manually specified comma-separated values.
     *
     * MODE 2 - Explicit model reflection:
     *   'field' => 'callback:checkInList:ModelName'
     *   Reflects the model's ENUM column for this field name, extracts allowed values.
     *   Example: 'approval_status' => 'callback:checkInList:ServicePrice'
     *
     * @param array $args Standard TypeRocket validator args array
     * @return bool|string True if valid, error message string if invalid
     */
    public static function checkInList(array $args)
    {
        /**
         * @var $option - The function name (checkInList)
         * @var $option2 - Mode-dependent: comma-list, ModelName, or empty
         * @var $option3 - Additional parameters (unused currently)
         * @var $full_name - Raw field name (snake_case, actual database column name)
         * @var $field_name - Formatted field label for error messages (Title Case with HTML)
         * @var $value - The value being validated
         * @var $weak - Whether this is an optional field
         * @var \TypeRocket\Utility\Validator $validator
         */
        extract($args);

        // Use raw field name as column name - database columns are snake_case
        // TypeRocket passes both $full_name (raw: 'region_type') and $field_name (formatted: '<strong>"Region Type"</strong>')
        $columnName = $full_name ?? $field_name;

        // Handle optional fields with empty values
        if (isset($weak) && $weak && \TypeRocket\Utility\Data::emptyOrBlankRecursive($value)) {
            return true;
        }

        if ($value === null || $value === '' || (is_string($value) && trim($value) === '')) {
            return true;
        }

        // Determine mode and get allowed values
        $allowedValues = [];
        $mode = null;

        if (isset($option2) && is_string($option2) && $option2 !== '') {
            // Check if option2 contains comma (Mode 1) or is a class name (Mode 2)
            if (strpos($option2, ',') !== false) {
                // MODE 1: Explicit comma-separated list
                $mode = 'explicit_list';
                $allowedValues = array_map('trim', explode(',', $option2));
            } else {
                // MODE 2: Explicit model class name
                $mode = 'explicit_model';
                $modelClass = $option2;

                // Resolve full class name if short name provided
                if (strpos($modelClass, '\\') === false) {
                    $modelClass = "MakerMaker\\Models\\{$modelClass}";
                }

                if (!class_exists($modelClass)) {
                    return " validation error: Model class '{$option2}' not found";
                }

                // Use raw column name for database reflection
                $allowedValues = self::getEnumValuesFromModel($modelClass, $columnName);
            }
        } else {
            // No parameter provided - requires explicit list or model name
            return ' validation error: checkInList requires explicit list or model name parameter';
        }

        // Handle reflection errors
        if (is_string($allowedValues)) {
            // Error message returned from getEnumValuesFromModel
            return $allowedValues;
        }

        if (empty($allowedValues)) {
            return ' has no valid options defined';
        }

        $valueStr = (string) $value;

        // Check both strict and loose comparison to handle '1' vs 1 type mismatches
        if (in_array($valueStr, $allowedValues, true)) {
            return true;
        }

        // Try loose comparison for numeric strings
        if (in_array($valueStr, $allowedValues, false)) {
            return true;
        }

        // Value not found in list - return error message
        $allowedList = implode(', ', $allowedValues);
        return " must be one of: {$allowedList}";
    }

    /**
     * Get ENUM values from database schema by reflecting model
     *
     * Queries INFORMATION_SCHEMA to extract ENUM column definition and parse allowed values.
     * Results are cached per model+field to prevent repeated database queries.
     *
     * @param string $modelClass Fully qualified model class name
     * @param string $fieldName Database column name
     * @return array|string Array of allowed values, or error message string on failure
     */
    protected static function getEnumValuesFromModel($modelClass, $fieldName)
    {
        // Check cache first
        $cacheKey = "{$modelClass}::{$fieldName}";
        if (isset(self::$enumCache[$cacheKey])) {
            return self::$enumCache[$cacheKey];
        }

        try {
            // Instantiate model to get table name
            $model = new $modelClass();

            if (!method_exists($model, 'getTable')) {
                self::$enumCache[$cacheKey] = " validation error: Model does not support getTable()";
                return self::$enumCache[$cacheKey];
            }

            $tableName = $model->getTable();

            // Query INFORMATION_SCHEMA for column definition
            global $wpdb;

            $sql = $wpdb->prepare(
                "SELECT COLUMN_TYPE
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = %s
                  AND COLUMN_NAME = %s",
                $tableName,
                $fieldName
            );

            $columnType = $wpdb->get_var($sql);

            if (!$columnType) {
                self::$enumCache[$cacheKey] = " validation error: Column '{$fieldName}' not found in table '{$tableName}'";
                return self::$enumCache[$cacheKey];
            }

            // Check if column is ENUM type
            if (stripos($columnType, 'enum(') !== 0) {
                self::$enumCache[$cacheKey] = " validation error: Column '{$fieldName}' is not an ENUM type (found: {$columnType})";
                return self::$enumCache[$cacheKey];
            }

            // Parse ENUM values from definition: enum('value1','value2','value3')
            // Remove "enum(" prefix and ")" suffix
            $enumValues = substr($columnType, 5, -1);

            // Split on comma and remove quotes
            $values = array_map(function($value) {
                return trim($value, "'\"");
            }, explode(',', $enumValues));

            // Cache and return
            self::$enumCache[$cacheKey] = $values;
            return $values;

        } catch (\Exception $e) {
            $errorMsg = " validation error: Failed to reflect ENUM values - " . $e->getMessage();
            self::$enumCache[$cacheKey] = $errorMsg;
            return $errorMsg;
        }
    }

    /**
     * Clear ENUM cache (useful for testing/debugging)
     *
     * @param string|null $cacheKey Specific cache key to clear, or null to clear all
     * @return void
     */
    public static function clearEnumCache($cacheKey = null)
    {
        if ($cacheKey === null) {
            self::$enumCache = [];
        } elseif (isset(self::$enumCache[$cacheKey])) {
            unset(self::$enumCache[$cacheKey]);
        }
    }

    /**
     * Validates that a field value does not equal another field in the same request
     *
     * Prevents self-referential records where two fields point to the same entity.
     * Common use cases: preventing service from being addon to itself,
     * preventing self-relationships in many-to-many junction tables.
     *
     * Usage: 'addon_service_id' => 'callback:checkNotSameAs:service_id:Service cannot be an addon to itself'
     *
     * @param array $args Standard TypeRocket validator args
     * @return true|string Returns true if valid, error message if invalid
     */
    public static function checkNotSameAs(array $args)
    {
        /**
         * @var $option - function name (checkNotSameAs)
         * @var $option2 - field name to compare against (required)
         * @var $option3 - custom error message (optional)
         * @var $value - the value being validated
         * @var $weak - whether this is an optional field
         */
        extract($args);

        // $option2 = field name to compare against
        // $option3 = custom error message (optional)
        $compareField = $option2 ?? null;
        $errorMessage = $option3 ?? ' cannot be the same as ' . $compareField;

        if (!$compareField) {
            return ' validation error: checkNotSameAs requires a field name parameter';
        }

        // Handle optional fields with empty values
        if (isset($weak) && $weak && \TypeRocket\Utility\Data::emptyOrBlankRecursive($value)) {
            return true;
        }

        // Get the other field's value from the request
        $request = Request::new();
        $fields = $request->getFields();
        $compareValue = $fields[$compareField] ?? null;

        // Allow if either value is empty
        if ($value === null || $value === '' || $compareValue === null || $compareValue === '') {
            return true;
        }

        // Compare as integers for ID fields
        if ((int)$value === (int)$compareValue) {
            return $errorMessage;
        }

        return true;
    }
}