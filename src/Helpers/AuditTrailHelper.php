<?php
namespace MakermakerCore\Helpers;

use TypeRocket\Models\AuthUser;
use TypeRocket\Models\Model;

/**
 * Helper for managing audit trail fields (created_by, updated_by)
 *
 * Follows TypeRocket conventions for tracking user changes.
 */
class AuditTrailHelper
{
    /**
     * Set audit fields on model for create operations
     *
     * Sets both created_by and updated_by to current user ID.
     *
     * @param Model $model Model to update
     * @param AuthUser $user Current authenticated user
     * @return void
     */
    public static function setCreateAuditFields(Model $model, AuthUser $user): void
    {
        $model->created_by = $user->ID;
        $model->updated_by = $user->ID;
    }

    /**
     * Set audit fields on model for update operations
     *
     * Sets only updated_by to current user ID.
     *
     * @param Model $model Model to update
     * @param AuthUser $user Current authenticated user
     * @return void
     */
    public static function setUpdateAuditFields(Model $model, AuthUser $user): void
    {
        $model->updated_by = $user->ID;
    }

    /**
     * Set audit fields on model (auto-detect create vs update)
     *
     * Checks if model has an ID to determine if it's a create or update.
     *
     * @param Model $model Model to update
     * @param AuthUser $user Current authenticated user
     * @return void
     */
    public static function setAuditFields(Model $model, AuthUser $user): void
    {
        if ($model->getID()) {
            self::setUpdateAuditFields($model, $user);
        } else {
            self::setCreateAuditFields($model, $user);
        }
    }
}
