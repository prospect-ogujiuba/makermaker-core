<?php

namespace MakermakerCore\Contracts;

use TypeRocket\Models\AuthUser;

/**
 * Interface for models that support custom REST actions
 *
 * Models implementing this interface can define custom action endpoints
 * that will be automatically available at: POST /tr-api/rest/{resource}/{id}/actions/{action}
 */
interface HasRestActions
{
    /**
     * Get available REST actions for this model
     *
     * @return array Associative array of action configurations
     *
     * Example:
     * [
     *     'duplicate' => [
     *         'method' => 'actionDuplicate',
     *         'description' => 'Duplicate this service',
     *         'capability' => 'create', // Policy method to check
     *         'requires_id' => true      // Whether action needs a specific record (default: true)
     *     ],
     *     'archive' => [
     *         'method' => 'actionArchive',
     *         'description' => 'Archive this service',
     *         'capability' => 'update'
     *     ]
     * ]
     */
    public function getRestActions(): array;
}
