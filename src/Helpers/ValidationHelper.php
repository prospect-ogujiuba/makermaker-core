<?php

namespace MakerMaker\Helpers;

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
}