<?php

namespace MakerMaker\Helpers;

use TypeRocket\Http\Fields;

/**
 * Data transformation utilities
 * 
 * Provides methods for transforming and sanitizing data
 */
class DataTransformer
{
    /**
     * Auto-generate or sanitize a code/slug field
     * - If code field is empty: generates from source field
     * - If code field has user input: sanitizes it to proper format
     * 
     * @param array|Fields &$fields Reference to fields array or Fields object
     * @param string $codeField The field name for the code (default: 'code')
     * @param string $sourceField The field name to generate from (default: 'name') 
     * @param string $separator Separator for kebab case and between addon and code (default: '-')
     * @param string|null $addon Optional text to add to the generated code
     * @param string $placement Where to place the addon: 'prefix', 'suffix' (default: 'prefix')
     * @param bool $uppercase Whether to uppercase the result (default: false)
     * @return void
     */
    public static function autoGenerateCode(
        &$fields,
        string $codeField = 'code',
        string $sourceField = 'name',
        string $separator = '-',
        ?string $addon = null,
        string $placement = 'prefix',
        bool $uppercase = false
    ): void {
        // Handle TypeRocket Fields objects
        if (is_object($fields) && method_exists($fields, 'getArrayCopy')) {
            self::processFieldsObject($fields, $codeField, $sourceField, $separator, $addon, $placement, $uppercase);
        } else {
            // Handle regular arrays
            self::processFieldsArray($fields, $codeField, $sourceField, $separator, $addon, $placement, $uppercase);
        }
    }

    /**
     * Process Fields object
     * 
     * @param Fields &$fields Fields object
     * @param string $codeField Code field name
     * @param string $sourceField Source field name
     * @param string $separator Separator character
     * @param string|null $addon Optional addon text
     * @param string $placement Addon placement
     * @param bool $uppercase Uppercase result
     * @return void
     */
    protected static function processFieldsObject(
        &$fields,
        string $codeField,
        string $sourceField,
        string $separator,
        ?string $addon,
        string $placement,
        bool $uppercase
    ): void {
        $fieldsArray = $fields->getArrayCopy();

        $sourceValue = '';
        $needsProcessing = false;

        // Determine source value for code generation
        if (!isset($fieldsArray[$codeField]) || empty($fieldsArray[$codeField])) {
            // Code field is empty - generate from source field
            if (isset($fieldsArray[$sourceField]) && !empty($fieldsArray[$sourceField])) {
                $sourceValue = $fieldsArray[$sourceField];
                $needsProcessing = true;
            }
        } else {
            // Code field has user input - sanitize it
            $sourceValue = $fieldsArray[$codeField];
            $needsProcessing = true;
        }

        if ($needsProcessing && $sourceValue !== '') {
            $generatedCode = self::generateCode($sourceValue, $separator, $uppercase);

            // Add addon if provided (only when generating from source field, not when sanitizing user input)
            if ($addon !== null && $addon !== '' && (!isset($fieldsArray[$codeField]) || empty($fieldsArray[$codeField]))) {
                $generatedCode = self::addAddon($generatedCode, $addon, $separator, $placement, $uppercase);
            }

            $fieldsArray[$codeField] = $generatedCode;
            $fields->exchangeArray($fieldsArray);
        }
    }

    /**
     * Process regular array
     * 
     * @param array &$fields Fields array
     * @param string $codeField Code field name
     * @param string $sourceField Source field name
     * @param string $separator Separator character
     * @param string|null $addon Optional addon text
     * @param string $placement Addon placement
     * @param bool $uppercase Uppercase result
     * @return void
     */
    protected static function processFieldsArray(
        &$fields,
        string $codeField,
        string $sourceField,
        string $separator,
        ?string $addon,
        string $placement,
        bool $uppercase
    ): void {
        $sourceValue = '';
        $needsProcessing = false;

        // Determine source value for code generation
        if (!isset($fields[$codeField]) || empty($fields[$codeField])) {
            // Code field is empty - generate from source field
            if (isset($fields[$sourceField]) && !empty($fields[$sourceField])) {
                $sourceValue = $fields[$sourceField];
                $needsProcessing = true;
            }
        } else {
            // Code field has user input - sanitize it
            $sourceValue = $fields[$codeField];
            $needsProcessing = true;
        }

        if ($needsProcessing && $sourceValue !== '') {
            $generatedCode = self::generateCode($sourceValue, $separator, $uppercase);

            // Add addon if provided (only when generating from source field, not when sanitizing user input)
            if ($addon !== null && $addon !== '' && (!isset($fields[$codeField]) || empty($fields[$codeField]))) {
                $generatedCode = self::addAddon($generatedCode, $addon, $separator, $placement, $uppercase);
            }

            $fields[$codeField] = $generatedCode;
        }
    }

    /**
     * Generate code from source value
     * 
     * @param string $sourceValue Source value
     * @param string $separator Separator character
     * @param bool $uppercase Uppercase result
     * @return string Generated code
     */
    protected static function generateCode(string $sourceValue, string $separator, bool $uppercase): string
    {
        $code = StringHelper::toKebab($sourceValue, $separator);
        return $uppercase ? strtoupper($code) : $code;
    }

    /**
     * Add addon to code
     * 
     * @param string $code Base code
     * @param string $addon Addon text
     * @param string $separator Separator character
     * @param string $placement Placement (prefix/suffix)
     * @param bool $uppercase Uppercase result
     * @return string Code with addon
     */
    protected static function addAddon(
        string $code,
        string $addon,
        string $separator,
        string $placement,
        bool $uppercase
    ): string {
        $processedAddon = StringHelper::toKebab($addon, $separator);
        $processedAddon = $uppercase ? strtoupper($processedAddon) : $processedAddon;

        return $placement === 'suffix'
            ? $code . $separator . $processedAddon
            : $processedAddon . $separator . $code;
    }

    /**
     * Convert empty string to NULL
     * 
     * @param mixed $value Input value
     * @return mixed Value or null
     */
    public static function convertEmptyToNull($value)
    {
        return ($value === '' || $value === null) ? null : $value;
    }
}