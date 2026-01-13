<?php

namespace MakermakerCore\Admin;

use TypeRocket\Models\Model;
use ReflectionClass;

/**
 * Reflective Field Introspector
 *
 * Extracts model properties ($fillable, $guard, $with, $cast, $format, $private, $resource)
 * via reflection. Provides the foundation for all reflective admin components.
 *
 * Uses per-class caching to avoid repeated reflection operations.
 */
class ReflectiveFieldIntrospector
{
    /**
     * Per-class cache for reflected properties
     * @var array<string, array>
     */
    private static array $cache = [];

    /**
     * The model instance being introspected
     */
    private Model $model;

    /**
     * The model's class name (for cache key)
     */
    private string $className;

    /**
     * Create a new introspector for a model
     *
     * @param Model $model The model instance to introspect
     */
    public function __construct(Model $model)
    {
        $this->model = $model;
        $this->className = get_class($model);
        $this->ensureCached();
    }

    /**
     * Ensure model properties are cached
     */
    private function ensureCached(): void
    {
        if (isset(self::$cache[$this->className])) {
            return;
        }

        $reflection = new ReflectionClass($this->model);

        self::$cache[$this->className] = [
            'fillable' => $this->extractProperty($reflection, 'fillable', []),
            'guard' => $this->extractProperty($reflection, 'guard', []),
            'with' => $this->extractProperty($reflection, 'with', []),
            'cast' => $this->extractProperty($reflection, 'cast', []),
            'format' => $this->extractProperty($reflection, 'format', []),
            'private' => $this->extractProperty($reflection, 'private', []),
            'resource' => $this->extractProperty($reflection, 'resource', ''),
        ];
    }

    /**
     * Extract a property value via reflection
     *
     * @param ReflectionClass $reflection The reflection class
     * @param string $property The property name
     * @param mixed $default Default value if property doesn't exist
     * @return mixed The property value
     */
    private function extractProperty(ReflectionClass $reflection, string $property, $default)
    {
        if (!$reflection->hasProperty($property)) {
            return $default;
        }

        $prop = $reflection->getProperty($property);
        $prop->setAccessible(true);

        return $prop->getValue($this->model) ?? $default;
    }

    /**
     * Get the fillable fields
     *
     * @return array
     */
    public function getFillable(): array
    {
        return self::$cache[$this->className]['fillable'];
    }

    /**
     * Get the guarded fields
     *
     * @return array
     */
    public function getGuard(): array
    {
        return self::$cache[$this->className]['guard'];
    }

    /**
     * Get the eager-loaded relationships
     *
     * @return array
     */
    public function getWith(): array
    {
        return self::$cache[$this->className]['with'];
    }

    /**
     * Get the cast definitions
     *
     * @return array
     */
    public function getCast(): array
    {
        return self::$cache[$this->className]['cast'];
    }

    /**
     * Get the format definitions
     *
     * @return array
     */
    public function getFormat(): array
    {
        return self::$cache[$this->className]['format'];
    }

    /**
     * Get the private fields (hidden from REST)
     *
     * @return array
     */
    public function getPrivate(): array
    {
        return self::$cache[$this->className]['private'];
    }

    /**
     * Get the resource/table name
     *
     * @return string
     */
    public function getResource(): string
    {
        return self::$cache[$this->className]['resource'];
    }

    /**
     * Get displayable fields (fillable minus private)
     *
     * @return array
     */
    public function getDisplayableFields(): array
    {
        $fillable = $this->getFillable();
        $private = $this->getPrivate();

        return array_values(array_diff($fillable, $private));
    }

    /**
     * Get relationship names from $with
     * Parses nested paths like 'category.parentCategory' → 'category'
     *
     * @return array Unique top-level relationship names
     */
    public function getRelationships(): array
    {
        $with = $this->getWith();
        $relationships = [];

        foreach ($with as $path) {
            // Extract first segment before any dot
            $parts = explode('.', $path);
            $relationships[] = $parts[0];
        }

        return array_values(array_unique($relationships));
    }

    /**
     * Get metadata for a specific field
     *
     * @param string $field The field name
     * @return array Field metadata
     */
    public function getFieldMetadata(string $field): array
    {
        $fillable = $this->getFillable();
        $guard = $this->getGuard();
        $private = $this->getPrivate();
        $cast = $this->getCast();
        $format = $this->getFormat();
        $relationships = $this->getRelationships();

        return [
            'name' => $field,
            'type' => $this->inferFieldType($field, $cast),
            'is_fillable' => in_array($field, $fillable),
            'is_guarded' => in_array($field, $guard),
            'is_private' => in_array($field, $private),
            'is_relationship' => in_array($field, $relationships),
            'format' => $format[$field] ?? null,
            'cast' => $cast[$field] ?? null,
        ];
    }

    /**
     * Get metadata for all discovered fields
     *
     * @return array Array of field metadata keyed by field name
     */
    public function getAllFieldsMetadata(): array
    {
        $allFields = array_unique(array_merge(
            $this->getFillable(),
            $this->getGuard(),
            $this->getRelationships()
        ));

        $metadata = [];
        foreach ($allFields as $field) {
            $metadata[$field] = $this->getFieldMetadata($field);
        }

        return $metadata;
    }

    /**
     * Infer field type from cast or naming pattern
     * This is preliminary - FieldTypeDetector will enhance this
     *
     * @param string $field The field name
     * @param array $cast The cast definitions
     * @return string The inferred type
     */
    private function inferFieldType(string $field, array $cast): string
    {
        // Check cast first
        if (isset($cast[$field])) {
            $castType = $cast[$field];
            switch ($castType) {
                case 'array':
                case 'object':
                    return 'json';
                case 'int':
                case 'integer':
                    return 'integer';
                case 'float':
                case 'double':
                case 'real':
                    return 'decimal';
                case 'bool':
                case 'boolean':
                    return 'boolean';
                case 'datetime':
                case 'date':
                    return 'date';
                default:
                    return 'string';
            }
        }

        // Infer from naming pattern
        if (str_ends_with($field, '_id')) {
            return 'foreign_key';
        }

        if (str_ends_with($field, '_at')) {
            return 'datetime';
        }

        if (str_starts_with($field, 'is_') || str_starts_with($field, 'has_')) {
            return 'boolean';
        }

        if (in_array($field, ['price', 'amount', 'total', 'cost', 'rate'])) {
            return 'decimal';
        }

        if (in_array($field, ['quantity', 'count', 'position', 'sort_order', 'order'])) {
            return 'integer';
        }

        if (in_array($field, ['description', 'content', 'body', 'notes', 'long_desc'])) {
            return 'text';
        }

        if (in_array($field, ['metadata', 'settings', 'config', 'options', 'data'])) {
            return 'json';
        }

        return 'string';
    }

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
     * Clear the cache (useful for testing)
     */
    public static function clearCache(): void
    {
        self::$cache = [];
    }

    /**
     * Get the detected type for a field
     *
     * Convenience method that creates a FieldTypeDetector and returns the type.
     * Allows chaining: mm_introspect($model)->getFieldType('name')
     *
     * @param string $field The field name
     * @return string The detected type constant
     */
    public function getFieldType(string $field): string
    {
        $detector = new FieldTypeDetector($this);
        return $detector->detectType($field);
    }

    /**
     * Get the type detector for this introspector
     *
     * @return FieldTypeDetector
     */
    public function getTypeDetector(): FieldTypeDetector
    {
        return new FieldTypeDetector($this);
    }
}
