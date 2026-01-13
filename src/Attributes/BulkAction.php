<?php

namespace MakermakerCore\Attributes;

use Attribute;

/**
 * Marks a method as a bulk action for admin index views
 *
 * Usage:
 * ```php
 * #[BulkAction(label: 'Send Email', capability: 'edit')]
 * public function bulkSendEmail(array $ids): array
 * {
 *     // Process bulk action
 *     return ['success' => true, 'count' => count($ids)];
 * }
 * ```
 *
 * @package MakermakerCore\Attributes
 */
#[Attribute(Attribute::TARGET_METHOD)]
class BulkAction
{
    /**
     * @param string $label Display label for the action in dropdown
     * @param string $capability Policy capability to check (e.g., 'edit', 'delete')
     * @param string $description Human-readable description
     * @param bool $requiresConfirmation Whether to show confirmation dialog
     * @param string|null $icon Bootstrap icon class (e.g., 'check-circle')
     */
    public function __construct(
        public string $label,
        public string $capability = 'edit',
        public string $description = '',
        public bool $requiresConfirmation = true,
        public ?string $icon = null
    ) {}
}
