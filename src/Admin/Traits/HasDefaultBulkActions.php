<?php

namespace MakermakerCore\Admin\Traits;

use MakermakerCore\Attributes\BulkAction;

/**
 * Provides common bulk actions for models
 *
 * Include this trait in your model to enable standard bulk actions:
 * - Activate (sets is_active = 1)
 * - Deactivate (sets is_active = 0)
 * - Archive (soft delete via deleted_at)
 *
 * Usage:
 * ```php
 * class Service extends Model
 * {
 *     use HasDefaultBulkActions;
 * }
 * ```
 *
 * Then in index view:
 * ```php
 * $table->setBulkActions(...mm_bulk_actions(new Service())->getBulkActionsConfig());
 * ```
 *
 * @package MakermakerCore\Admin\Traits
 */
trait HasDefaultBulkActions
{
    /**
     * Bulk activate records
     *
     * Sets is_active = 1 for each record, or calls activate() if method exists.
     *
     * @param array $ids Array of record IDs to activate
     * @return array ['success' => bool, 'count' => int]
     */
    #[BulkAction(label: 'Activate', capability: 'edit', icon: 'check-circle')]
    public function bulkActivate(array $ids): array
    {
        $count = 0;
        foreach ($ids as $id) {
            $record = (new static())->find($id);
            if (!$record) {
                continue;
            }

            if (method_exists($record, 'activate')) {
                $record->activate();
                $count++;
            } elseif (property_exists($record, 'is_active') || in_array('is_active', $record->getFillableFields() ?? [])) {
                $record->is_active = 1;
                $record->save();
                $count++;
            }
        }

        return ['success' => true, 'count' => $count];
    }

    /**
     * Bulk deactivate records
     *
     * Sets is_active = 0 for each record, or calls deactivate() if method exists.
     *
     * @param array $ids Array of record IDs to deactivate
     * @return array ['success' => bool, 'count' => int]
     */
    #[BulkAction(label: 'Deactivate', capability: 'edit', icon: 'x-circle')]
    public function bulkDeactivate(array $ids): array
    {
        $count = 0;
        foreach ($ids as $id) {
            $record = (new static())->find($id);
            if (!$record) {
                continue;
            }

            if (method_exists($record, 'deactivate')) {
                $record->deactivate();
                $count++;
            } elseif (property_exists($record, 'is_active') || in_array('is_active', $record->getFillableFields() ?? [])) {
                $record->is_active = 0;
                $record->save();
                $count++;
            }
        }

        return ['success' => true, 'count' => $count];
    }

    /**
     * Bulk archive (soft delete) records
     *
     * Sets deleted_at timestamp for each record.
     *
     * @param array $ids Array of record IDs to archive
     * @return array ['success' => bool, 'count' => int]
     */
    #[BulkAction(label: 'Archive', capability: 'delete', icon: 'archive', requiresConfirmation: true)]
    public function bulkArchive(array $ids): array
    {
        $count = 0;
        foreach ($ids as $id) {
            $record = (new static())->find($id);
            if (!$record) {
                continue;
            }

            $record->deleted_at = date('Y-m-d H:i:s');
            $record->save();
            $count++;
        }

        return ['success' => true, 'count' => $count];
    }

    /**
     * Bulk restore archived records
     *
     * Clears deleted_at timestamp for each record.
     *
     * @param array $ids Array of record IDs to restore
     * @return array ['success' => bool, 'count' => int]
     */
    #[BulkAction(label: 'Restore', capability: 'edit', icon: 'arrow-counterclockwise')]
    public function bulkRestore(array $ids): array
    {
        $count = 0;
        foreach ($ids as $id) {
            // Find including soft-deleted
            $record = (new static())->findAll()->where('id', $id)->first();
            if (!$record) {
                continue;
            }

            $record->deleted_at = null;
            $record->save();
            $count++;
        }

        return ['success' => true, 'count' => $count];
    }
}
