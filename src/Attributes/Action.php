<?php

namespace MakermakerCore\Attributes;

use Attribute;

/**
 * Action Attribute
 *
 * Marks a model method as a REST API action endpoint.
 * Enables zero-configuration action discovery via reflection.
 *
 * Usage:
 * ```php
 * #[Action(capability: 'create', description: 'Duplicate this service')]
 * public function duplicate(AuthUser $user, array $params): array {
 *     // Action logic
 *     return ['success' => true, 'data' => $newRecord];
 * }
 * ```
 *
 * @package MakermakerCore\Attributes
 */
#[Attribute(Attribute::TARGET_METHOD)]
class Action
{
    /**
     * @param string $capability Policy method to check for authorization (e.g., 'create', 'update', 'destroy')
     * @param string $description Human-readable description of what this action does
     * @param bool $requiresParams Whether this action requires parameters in request body
     * @param bool $requiresId Whether this action needs a specific record (default: true for instance methods)
     */
    public function __construct(
        public string $capability = 'read',
        public string $description = '',
        public bool $requiresParams = false,
        public bool $requiresId = true
    ) {}
}
