<?php

namespace MakermakerCore\Admin;

/**
 * Field Type Detector
 *
 * Infers field types from naming patterns, $cast, and $format properties.
 * Used by search form filters and model filters to render correct HTML inputs
 * and apply correct query operators.
 */
class FieldTypeDetector
{
    // Type constants
    public const TYPE_TEXT = 'text';
    public const TYPE_NUMBER = 'number';
    public const TYPE_DATE = 'date';
    public const TYPE_DATETIME = 'datetime';
    public const TYPE_BOOLEAN = 'boolean';
    public const TYPE_ENUM = 'enum';
    public const TYPE_FK = 'foreign_key';
    public const TYPE_JSON = 'json';
    public const TYPE_IMAGE = 'image';

    /**
     * Text field name patterns (searchable with LIKE)
     */
    private const TEXT_PATTERNS = [
        'name', 'title', 'description', 'desc', 'short_desc', 'long_desc',
        'slug', 'sku', 'code', 'email', 'content', 'body', 'notes',
        'comments', 'address', 'city', 'region', 'street', 'phone',
        'url', 'website', 'summary', 'excerpt', 'label'
    ];

    /**
     * Number field name patterns
     */
    private const NUMBER_PATTERNS = [
        'quantity', 'amount', 'price', 'rate', 'count', 'hours',
        'level', 'order', 'sort', 'sort_order', 'position', 'weight',
        'width', 'height', 'length', 'duration', 'total', 'cost',
        'minimum', 'maximum', 'min', 'max', 'percentage', 'percent'
    ];

    /**
     * Image field name patterns
     */
    private const IMAGE_PATTERNS = [
        'image', 'photo', 'thumbnail', 'avatar', 'logo', 'icon',
        'picture', 'banner', 'cover'
    ];

    /**
     * Known enum fields with their options
     */
    private const ENUM_FIELDS = [
        'status' => ['draft', 'pending', 'active', 'inactive', 'archived'],
        'priority' => ['low', 'medium', 'high', 'critical'],
        'skill_level' => ['beginner', 'intermediate', 'advanced', 'expert'],
        'contact_type' => ['email', 'phone', 'in_person', 'video_call'],
        'gender' => ['male', 'female', 'other', 'prefer_not_to_say'],
        'approval_status' => ['pending', 'approved', 'rejected'],
        'payment_status' => ['pending', 'paid', 'failed', 'refunded'],
        'order_status' => ['pending', 'processing', 'shipped', 'delivered', 'cancelled'],
        'visibility' => ['public', 'private', 'internal'],
        'frequency' => ['once', 'daily', 'weekly', 'monthly', 'yearly'],
    ];

    /**
     * The field introspector instance
     */
    private ReflectiveFieldIntrospector $introspector;

    /**
     * Cached cast definitions
     */
    private array $cast;

    /**
     * Cached format definitions
     */
    private array $format;

    /**
     * Cached relationships
     */
    private array $relationships;

    /**
     * Create a new type detector
     *
     * @param ReflectiveFieldIntrospector $introspector
     */
    public function __construct(ReflectiveFieldIntrospector $introspector)
    {
        $this->introspector = $introspector;
        $this->cast = $introspector->getCast();
        $this->format = $introspector->getFormat();
        $this->relationships = $introspector->getRelationships();
    }

    /**
     * Detect the type of a field
     *
     * Detection priority:
     * 1. $cast definitions
     * 2. $format definitions
     * 3. Naming patterns
     * 4. Known enum fields
     * 5. Default to text
     *
     * @param string $field The field name
     * @return string The detected type constant
     */
    public function detectType(string $field): string
    {
        // 1. Check $cast first
        if (isset($this->cast[$field])) {
            $type = $this->detectFromCast($this->cast[$field]);
            if ($type !== null) {
                return $type;
            }
        }

        // 2. Check $format
        if (isset($this->format[$field])) {
            $type = $this->detectFromFormat($this->format[$field], $field);
            if ($type !== null) {
                return $type;
            }
        }

        // 3. Check naming patterns
        $type = $this->detectFromNaming($field);
        if ($type !== null) {
            return $type;
        }

        // 4. Check known enum fields
        if ($this->isEnumField($field)) {
            return self::TYPE_ENUM;
        }

        // 5. Default to text
        return self::TYPE_TEXT;
    }

    /**
     * Detect type from $cast definition
     *
     * @param string $castType
     * @return string|null
     */
    private function detectFromCast(string $castType): ?string
    {
        $castType = strtolower($castType);

        switch ($castType) {
            case 'array':
            case 'object':
            case 'json':
                return self::TYPE_JSON;

            case 'bool':
            case 'boolean':
                return self::TYPE_BOOLEAN;

            case 'int':
            case 'integer':
            case 'float':
            case 'double':
            case 'decimal':
            case 'real':
                return self::TYPE_NUMBER;

            case 'date':
                return self::TYPE_DATE;

            case 'datetime':
            case 'timestamp':
                return self::TYPE_DATETIME;

            default:
                return null;
        }
    }

    /**
     * Detect type from $format definition
     *
     * @param string $formatType
     * @param string $field
     * @return string|null
     */
    private function detectFromFormat(string $formatType, string $field): ?string
    {
        if ($formatType === 'json_encode') {
            return self::TYPE_JSON;
        }

        // convertEmptyToNull often used with numeric fields
        if ($formatType === 'convertEmptyToNull') {
            // Check if field name suggests numeric
            if ($this->matchesPatterns($field, self::NUMBER_PATTERNS)) {
                return self::TYPE_NUMBER;
            }
        }

        return null;
    }

    /**
     * Detect type from field naming patterns
     *
     * @param string $field
     * @return string|null
     */
    private function detectFromNaming(string $field): ?string
    {
        // Foreign key pattern: ends with _id
        if (str_ends_with($field, '_id')) {
            return self::TYPE_FK;
        }

        // Datetime pattern: ends with _at
        if (str_ends_with($field, '_at')) {
            return self::TYPE_DATETIME;
        }

        // Date pattern: ends with _date
        if (str_ends_with($field, '_date')) {
            return self::TYPE_DATE;
        }

        // Boolean patterns: is_*, has_*, can_*
        if (str_starts_with($field, 'is_') ||
            str_starts_with($field, 'has_') ||
            str_starts_with($field, 'can_')) {
            return self::TYPE_BOOLEAN;
        }

        // Image patterns
        if ($this->matchesPatterns($field, self::IMAGE_PATTERNS)) {
            return self::TYPE_IMAGE;
        }

        // Number patterns
        if ($this->matchesPatterns($field, self::NUMBER_PATTERNS)) {
            return self::TYPE_NUMBER;
        }

        // Text patterns - check last as it's the default fallback anyway
        if ($this->matchesPatterns($field, self::TEXT_PATTERNS)) {
            return self::TYPE_TEXT;
        }

        return null;
    }

    /**
     * Check if field matches any of the given patterns
     *
     * @param string $field
     * @param array $patterns
     * @return bool
     */
    private function matchesPatterns(string $field, array $patterns): bool
    {
        $fieldLower = strtolower($field);

        foreach ($patterns as $pattern) {
            // Exact match
            if ($fieldLower === $pattern) {
                return true;
            }

            // Contains pattern (for compound fields like 'base_price', 'thumbnail_url')
            if (str_contains($fieldLower, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if field is a known enum field
     *
     * @param string $field
     * @return bool
     */
    private function isEnumField(string $field): bool
    {
        return isset(self::ENUM_FIELDS[$field]);
    }

    /**
     * Detect types for all fillable fields
     *
     * @return array ['field_name' => 'type']
     */
    public function detectAllTypes(): array
    {
        $fillable = $this->introspector->getFillable();
        $types = [];

        foreach ($fillable as $field) {
            $types[$field] = $this->detectType($field);
        }

        return $types;
    }

    /**
     * Get the relationship method name for a foreign key field
     *
     * Converts '_id' fields to relationship method names:
     * - 'category_id' → 'category'
     * - 'service_type_id' → 'serviceType' (camelCase)
     *
     * @param string $field
     * @return string|null Relationship name or null if not a FK field
     */
    public function getForeignKeyRelationship(string $field): ?string
    {
        // Must be a FK field
        if (!str_ends_with($field, '_id')) {
            return null;
        }

        // Remove _id suffix
        $relationshipSnake = substr($field, 0, -3);

        // Convert to camelCase
        $relationship = lcfirst(str_replace('_', '', ucwords($relationshipSnake, '_')));

        // Verify relationship exists in $with (optional but useful)
        // Even if not in $with, the relationship method likely exists
        return $relationship;
    }

    /**
     * Get enum options for a field
     *
     * Returns static arrays for known enum fields.
     * Models can override with static getEnumOptionsFor($field) method.
     *
     * @param string $field
     * @return array|null Options array or null if not an enum field
     */
    public function getEnumOptions(string $field): ?array
    {
        // Check if model defines custom options
        $model = $this->introspector->getModel();
        if (method_exists($model, 'getEnumOptionsFor')) {
            $options = $model->getEnumOptionsFor($field);
            if ($options !== null) {
                return $options;
            }
        }

        // Check known enum fields
        if (isset(self::ENUM_FIELDS[$field])) {
            return self::ENUM_FIELDS[$field];
        }

        return null;
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
     * Check if a field type is searchable with LIKE
     *
     * @param string $type
     * @return bool
     */
    public static function isSearchableType(string $type): bool
    {
        return in_array($type, [
            self::TYPE_TEXT,
        ]);
    }

    /**
     * Check if a field type is filterable with exact match
     *
     * @param string $type
     * @return bool
     */
    public static function isFilterableType(string $type): bool
    {
        return in_array($type, [
            self::TYPE_NUMBER,
            self::TYPE_DATE,
            self::TYPE_DATETIME,
            self::TYPE_BOOLEAN,
            self::TYPE_ENUM,
            self::TYPE_FK,
        ]);
    }

    /**
     * Get the HTML input type for a field type
     *
     * @param string $type
     * @return string
     */
    public static function getHtmlInputType(string $type): string
    {
        switch ($type) {
            case self::TYPE_NUMBER:
                return 'number';
            case self::TYPE_DATE:
                return 'date';
            case self::TYPE_DATETIME:
                return 'datetime-local';
            case self::TYPE_BOOLEAN:
                return 'checkbox';
            case self::TYPE_ENUM:
            case self::TYPE_FK:
                return 'select';
            case self::TYPE_IMAGE:
                return 'file';
            case self::TYPE_JSON:
                return 'textarea';
            case self::TYPE_TEXT:
            default:
                return 'text';
        }
    }
}
