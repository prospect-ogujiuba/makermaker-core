<?php
namespace MakermakerCore\Helpers;

use TypeRocket\Http\Fields;

/**
 * Helper for auto-generating SKU/slug codes
 *
 * Wraps makermaker-core's autoGenerateCode() with entity-specific conventions.
 */
class AutoCodeHelper
{
    /**
     * Generate both SKU (uppercase) and slug (lowercase) from name
     *
     * Used for entities with inventory/catalog codes (Service, Equipment).
     *
     * @param array $fields Field array reference (modified in-place)
     * @param string $sourceField Source field name (default: 'name')
     * @param string $separator Separator character (default: '-')
     * @return void
     */
    public static function generateSkuAndSlug(
        Fields &$fields,
        string $sourceField = 'name',
        string $separator = '-'
    ): void {
        autoGenerateCode($fields, 'sku', $sourceField, $separator, null, 'prefix', true);
        autoGenerateCode($fields, 'slug', $sourceField, $separator);
    }

    /**
     * Generate slug (lowercase) from name
     *
     * Used for most entities (categories, types, config entities).
     *
     * @param array $fields Field array reference (modified in-place)
     * @param string $sourceField Source field name (default: 'name')
     * @param string $separator Separator character (default: '-')
     * @return void
     */
    public static function generateSlug(
        Fields &$fields,
        string $sourceField = 'name',
        string $separator = '-'
    ): void {
        autoGenerateCode($fields, 'slug', $sourceField, $separator);
    }

    /**
     * Generate code field (lowercase) from name
     *
     * Used for entities that use 'code' field (types, models, tiers, etc.).
     *
     * @param array $fields Field array reference (modified in-place)
     * @param string $sourceField Source field name (default: 'name')
     * @param string $separator Separator character (default: '-')
     * @return void
     */
    public static function generateCode(
        Fields &$fields,
        string $sourceField = 'name',
        string $separator = '-'
    ): void {
        autoGenerateCode($fields, 'code', $sourceField, $separator);
    }

    /**
     * Check if entity has SKU field (vs just slug)
     *
     * @param string $entityName Entity name (Service, Equipment, etc.)
     * @return bool True if entity uses SKU
     */
    public static function hasSku(string $entityName): bool
    {
        return in_array($entityName, ['Service', 'Equipment']);
    }
}
