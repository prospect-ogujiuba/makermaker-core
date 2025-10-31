<?php

namespace MakerMaker\Helpers;

use TypeRocket\Utility\Inflect;

/**
 * String manipulation utilities
 * 
 * Provides methods for case conversion, pluralization, and string formatting
 */
class StringHelper
{
    /**
     * Convert string to kebab-case or kebab_case
     * 
     * @param string $string Input string
     * @param string $separator Separator to use ('-' or '_')
     * @return string Kebab-cased string
     */
    public static function toKebab(string $string, string $separator = '_'): string
    {
        // Normalize separator: only allow '-' or '_'
        $sep = ($separator === '-') ? '-' : '_';

        // Split camelCase / PascalCase with chosen separator
        $kebab = preg_replace('/([a-z])([A-Z])/', '$1' . $sep . '$2', $string);
        $kebab = strtolower($kebab);

        // Replace non-alphanumeric/separator characters with chosen separator
        $kebab = preg_replace('/[^a-z0-9' . preg_quote($sep, '/') . ']+/', $sep, $kebab);

        // Collapse multiple separators
        $pattern = '/' . preg_quote($sep, '/') . '+/';
        $kebab = preg_replace($pattern, $sep, $kebab);

        return trim($kebab, $sep);
    }

    /**
     * Convert string to Title Case
     * Handles PascalCase, snake_case, and regular strings
     * 
     * @param string $string Input string
     * @return string Title cased string
     */
    public static function toTitleCase(string $string): string
    {
        // Convert PascalCase to Title Case with spaces
        // TicketStatus becomes "Ticket Status"
        // Only process if string is in PascalCase format (no underscores, no spaces)
        if (strpos($string, '_') !== false || strpos($string, ' ') !== false) {
            // Already has separators, just return ucwords
            return ucwords(str_replace('_', ' ', $string));
        }
        
        $result = preg_replace('/(?<!^)([A-Z])/', ' $1', $string);
        return trim($result);
    }

    /**
     * Pluralize a word, handling delimiters (kebab-case, snake_case, etc.)
     * Only pluralizes the last segment when delimiters are present.
     * 
     * Examples:
     * - "equipment" -> "equipment"
     * - "service-equipment" -> "service-equipment" (equipment is uncountable)
     * - "user-profile" -> "user-profiles"
     * - "api_endpoint" -> "api_endpoints"
     * - "ServiceEquipment" -> "ServiceEquipments"
     * 
     * @param string $word The word to pluralize
     * @return string The pluralized word
     */
    public static function pluralize(string $word): string
    {
        $inflector = Inflect::class;

        // Detect delimiter
        $delimiter = self::detectDelimiter($word);

        // Check for PascalCase/camelCase
        if ($delimiter === null && preg_match('/[a-z][A-Z]/', $word)) {
            return self::pluralizeCamelCase($word, $inflector);
        }

        // No delimiter found, pluralize the whole word
        if ($delimiter === null) {
            return $inflector::pluralize($word);
        }

        // Split by delimiter, pluralize last part only
        return self::pluralizeDelimited($word, $delimiter, $inflector);
    }

    /**
     * Singularize a word, handling delimiters
     * Only singularizes the last segment when delimiters are present.
     * 
     * @param string $word The word to singularize
     * @return string The singularized word
     */
    public static function singularize(string $word): string
    {
        $inflector = Inflect::class;

        // Detect delimiter
        $delimiter = self::detectDelimiter($word);

        // Check for PascalCase/camelCase
        if ($delimiter === null && preg_match('/[a-z][A-Z]/', $word)) {
            return self::singularizeCamelCase($word, $inflector);
        }

        // No delimiter found, singularize the whole word
        if ($delimiter === null) {
            return $inflector::singularize($word);
        }

        // Split by delimiter, singularize last part only
        return self::singularizeDelimited($word, $delimiter, $inflector);
    }

    /**
     * Detect delimiter in string
     * 
     * @param string $string Input string
     * @return string|null Delimiter or null if none found
     */
    protected static function detectDelimiter(string $string): ?string
    {
        $delimiters = ['-', '_', '.', ' '];

        foreach ($delimiters as $delimiter) {
            if (strpos($string, $delimiter) !== false) {
                return $delimiter;
            }
        }

        return null;
    }

    /**
     * Pluralize camelCase/PascalCase word
     * 
     * @param string $word Input word
     * @param string $inflector Inflector class
     * @return string Pluralized word
     */
    protected static function pluralizeCamelCase(string $word, string $inflector): string
    {
        // Split on capital letters
        $parts = preg_split('/(?=[A-Z])/', $word, -1, PREG_SPLIT_NO_EMPTY);

        if (count($parts) > 1) {
            // Pluralize the last part
            $lastIndex = count($parts) - 1;
            $parts[$lastIndex] = $inflector::pluralize($parts[$lastIndex]);
            return implode('', $parts);
        }

        return $word;
    }

    /**
     * Singularize camelCase/PascalCase word
     * 
     * @param string $word Input word
     * @param string $inflector Inflector class
     * @return string Singularized word
     */
    protected static function singularizeCamelCase(string $word, string $inflector): string
    {
        // Split on capital letters
        $parts = preg_split('/(?=[A-Z])/', $word, -1, PREG_SPLIT_NO_EMPTY);

        if (count($parts) > 1) {
            // Singularize the last part
            $lastIndex = count($parts) - 1;
            $parts[$lastIndex] = $inflector::singularize($parts[$lastIndex]);
            return implode('', $parts);
        }

        return $word;
    }

    /**
     * Pluralize delimited word (kebab-case, snake_case, etc.)
     * 
     * @param string $word Input word
     * @param string $delimiter Delimiter character
     * @param string $inflector Inflector class
     * @return string Pluralized word
     */
    protected static function pluralizeDelimited(string $word, string $delimiter, string $inflector): string
    {
        $parts = explode($delimiter, $word);
        $lastIndex = count($parts) - 1;
        $parts[$lastIndex] = $inflector::pluralize($parts[$lastIndex]);
        return implode($delimiter, $parts);
    }

    /**
     * Singularize delimited word (kebab-case, snake_case, etc.)
     * 
     * @param string $word Input word
     * @param string $delimiter Delimiter character
     * @param string $inflector Inflector class
     * @return string Singularized word
     */
    protected static function singularizeDelimited(string $word, string $delimiter, string $inflector): string
    {
        $parts = explode($delimiter, $word);
        $lastIndex = count($parts) - 1;
        $parts[$lastIndex] = $inflector::singularize($parts[$lastIndex]);
        return implode($delimiter, $parts);
    }
}