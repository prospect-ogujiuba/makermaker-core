<?php

namespace MakerMaker\Helpers;

use TypeRocket\Models\Model;
use TypeRocket\Models\WPUser;

/**
 * Entity lookup utilities
 * 
 * Provides methods for looking up entities and users by ID
 */
class EntityLookup
{
    /**
     * Get entity name by ID for better descriptions
     * 
     * @param string $modelClass Model class name
     * @param int|null $id Entity ID
     * @return string Entity name or ID
     */
    public static function getEntityName(string $modelClass, ?int $id): string
    {
        if (!$id) {
            return 'N/A';
        }

        try {
            $entity = $modelClass::new()->findById($id);
            if ($entity) {
                // Try common name fields
                return $entity->name ?? $entity->title ?? "ID: $id";
            }
        } catch (\Exception $e) {
            // If lookup fails, just return the ID
        }

        return "ID: $id";
    }

    /**
     * Get user display name
     * 
     * @param int|null $userId User ID
     * @return string User name or ID
     */
    public static function getUserName(?int $userId): string
    {
        if (!$userId) {
            return 'N/A';
        }

        try {
            $user = WPUser::new()->findById($userId);
            if ($user) {
                return $user->display_name ?? $user->user_login ?? "User #$userId";
            }
        } catch (\Exception $e) {
            // If lookup fails, just return the ID
        }

        return "User #$userId";
    }

    /**
     * Get entity with multiple possible name fields
     * 
     * @param Model|null $entity Entity model instance
     * @param array $nameFields Fields to check for name (in priority order)
     * @param string $fallback Fallback text if no name found
     * @return string Entity name
     */
    public static function getEntityNameFromModel(?Model $entity, array $nameFields = ['name', 'title'], string $fallback = 'N/A'): string
    {
        if (!$entity) {
            return $fallback;
        }

        foreach ($nameFields as $field) {
            if (isset($entity->$field) && !empty($entity->$field)) {
                return $entity->$field;
            }
        }

        // If we have an ID, return that
        if (method_exists($entity, 'getID')) {
            $id = $entity->getID();
            if ($id) {
                return "ID: $id";
            }
        }

        return $fallback;
    }
}