<?php

namespace MakerMaker\Helpers;

use TypeRocket\Http\Response;

/**
 * Database operation utilities
 * 
 * Provides helpers for database transactions, error handling, and circular reference detection
 */
class DatabaseHelper
{
    /**
     * Simple database operation wrapper for TypeRocket controllers
     * 
     * @param callable $operation The model operation to execute
     * @param \TypeRocket\Http\Response $response The response object
     * @param string $successMessage Message to show on success
     * @return bool True if operation succeeded, false if it failed
     */
    public static function tryOperation(callable $operation, Response $response, string $successMessage = 'Operation completed successfully'): bool
    {
        global $wpdb;

        // Clear any previous errors
        $wpdb->last_error = '';

        try {
            $result = $operation();

            // Check for WordPress database errors first
            if (!empty($wpdb->last_error)) {
                error_log("WordPress database error: " . $wpdb->last_error);

                // Clean up the error message to hide database/table names
                $cleanError = self::cleanDatabaseError($wpdb->last_error);
                $response->flashNext("Database error: " . $cleanError, 'error');
                return false;
            }

            // TypeRocket models return the model instance on success, false/null on failure
            if ($result === false || $result === null) {
                $response->flashNext('Database operation failed - unknown error', 'error');
                return false;
            }

            // Check if the model has errors
            if (is_object($result) && method_exists($result, 'getErrors') && $result->getErrors()) {
                $errors = $result->getErrors();
                $errorMessage = is_array($errors) ? implode('; ', $errors) : $errors;
                $response->flashNext("Error: " . $errorMessage, 'error');
                return false;
            }

            $response->flashNext($successMessage, 'success');
            return true;
        } catch (\TypeRocket\Exceptions\ModelException $e) {
            // TypeRocket model validation or database errors
            error_log("TypeRocket ModelException: " . $e->getMessage());
            $response->flashNext("Model error: " . $e->getMessage(), 'error');
            return false;
        } catch (\Exception $e) {
            // Any other unexpected errors
            error_log("Unexpected error: " . $e->getMessage());
            $response->flashNext("Unexpected error: " . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Clean database error message to hide sensitive information
     * 
     * @param string $error The raw database error
     * @return string Cleaned error message
     */
    public static function cleanDatabaseError(string $error): string
    {
        // Remove database and table name patterns like `database`.`table_name`
        $cleaned = preg_replace('/`[^`]+`\.`[^`]+`/', 'Entity', $error);

        // Remove "for `database`.`table`" patterns entirely
        $cleaned = preg_replace('/\s+for\s+`[^`]+`\.`[^`]+`/', '', $cleaned);

        return $cleaned;
    }

    /**
     * Detect circular references in hierarchical data
     * 
     * @param int $parentId The proposed parent ID
     * @param int $currentId The current record ID
     * @param string $tableName Database table name
     * @param string $parentColumn Parent column name
     * @param string $idColumn Primary key column name
     * @param array $visited Track visited nodes to prevent infinite loops
     * @return bool
     */
    public static function hasCircularReference(
        int $parentId,
        int $currentId,
        string $tableName,
        string $parentColumn,
        string $idColumn,
        array $visited = []
    ): bool {
        global $wpdb;

        // Prevent infinite loops
        if (in_array($parentId, $visited)) {
            return true;
        }

        $visited[] = $parentId;

        // Get the parent's parent
        $query = $wpdb->prepare(
            "SELECT {$parentColumn} FROM {$tableName} WHERE {$idColumn} = %d",
            $parentId
        );

        $grandParentId = $wpdb->get_var($query);

        // If no grandparent, no circular reference
        if (!$grandParentId) {
            return false;
        }

        // If grandparent is our current record, we have a circle
        if ((int) $grandParentId === $currentId) {
            return true;
        }

        // Recursively check up the chain
        return self::hasCircularReference(
            $grandParentId,
            $currentId,
            $tableName,
            $parentColumn,
            $idColumn,
            $visited
        );
    }
}