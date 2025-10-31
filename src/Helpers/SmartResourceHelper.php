<?php

namespace MakerMaker\Helpers;

use TypeRocket\Models\Model;

class SmartResourceHelper
{
    // No manual mappings needed - TypeRocket uses lowercase resource names

    /**
     * Actions that require an ID
     */
    const ID_REQUIRED_ACTIONS = ['edit', 'show', 'delete', 'update', 'destroy'];

    /**
     * Action mappings (TypeRocket uses different action names internally)
     */
    const ACTION_MAPPINGS = [
        'create' => 'add',
        'update' => 'edit',
        'destroy' => 'delete',
    ];

    /**
     * Generate TypeRocket resource URL
     *
     * @param string|Model $resource Resource name or Model instance
     * @param string $action Action name
     * @param int|null $id Resource ID (auto-detected from Model if not provided)
     * @return string
     */
    public static function url($resource, $action = 'index', $id = null)
    {
        // Handle Model instances
        if ($resource instanceof Model) {
            $id = $id ?: $resource->getID();
            $resource = self::getResourceNameFromModel($resource);
        }

        // Convert resource name to slug (PriceRecord -> PriceRecord)
        $resource_slug = self::resourceToSlug($resource);

        // Build page parameter - ALWAYS include action with underscore
        $page = $resource_slug . '_' . $action;

        // Build URL parameters
        $params = ['page' => $page];

        // Add ID for actions that need it
        if (in_array($action, self::ID_REQUIRED_ACTIONS) && $id) {
            $params['route_args'] = [$id];
        }

        return admin_url('admin.php?' . http_build_query($params));
    }

    /**
     * Generate HTML link to TypeRocket resource
     *
     * @param string|Model $resource Resource name or Model instance  
     * @param string $action Action name
     * @param string $text Link text
     * @param int|null $id Resource ID
     * @param string $icon Bootstrap icon name (without 'bi bi-' prefix)
     * @param string $class Additional CSS classes
     * @return string
     */
    public static function link($resource, $action = 'index', $text = null, $id = null, $icon = null, $class = 'button')
    {
        $url = self::url($resource, $action, $id);

        // Auto-generate text if not provided
        if (!$text) {
            $text = self::generateLinkText($resource, $action);
        }

        // Auto-select icon if not provided
        if (!$icon) {
            $icon = self::getDefaultIcon($action);
        }

        // Build icon HTML
        $icon_html = $icon ? "<i class='bi bi-{$icon}'></i> " : '';

        // Build CSS classes
        $css_classes = self::getActionClasses($action, $class);

        return "<a href='{$url}' class='{$css_classes}'>{$icon_html}{$text}</a>";
    }

    /**
     * Generate back link to resource index
     *
     * @param string|Model $resource Resource name or Model instance
     * @param string $text Link text
     * @return string
     */
    public static function backToIndex($resource, $text = null)
    {
        $resource_name = $resource instanceof Model ? self::getResourceNameFromModel($resource) : $resource;
        $text = $text ?: "Back to " . pluralize($resource_name);

        return self::link($resource, 'index', $text, null, 'arrow-left', 'button button-secondary');
    }

    /**
     * Generate edit link
     *
     * @param string|Model $resource Resource name or Model instance
     * @param int|null $id Resource ID
     * @param string $text Link text
     * @return string
     */
    public static function editLink($resource, $id = null, $text = 'Edit')
    {
        return self::link($resource, 'edit', $text, $id, 'pencil', 'button button-primary');
    }

    /**
     * Generate view link
     *
     * @param string|Model $resource Resource name or Model instance
     * @param int|null $id Resource ID
     * @param string $text Link text
     * @return string
     */
    public static function viewLink($resource, $id = null, $text = 'View')
    {
        return self::link($resource, 'show', $text, $id, 'eye', 'button button-secondary');
    }

    /**
     * Generate delete link
     *
     * @param string|Model $resource Resource name or Model instance
     * @param int|null $id Resource ID
     * @param string $text Link text
     * @return string
     */
    public static function deleteLink($resource, $id = null, $text = 'Delete')
    {
        return self::link($resource, 'delete', $text, $id, 'trash', 'button button-danger');
    }

    /**
     * Convert resource name to TypeRocket format
     * PriceRecord -> PriceRecord, ServicePrice -> serviceprice
     *
     * @param string $resource Resource name
     * @return string
     */
    protected static function resourceToSlug($resource)
    {
        // TypeRocket uses simple lowercase - no underscores
        return strtolower($resource);
    }

    /**
     * Get resource name from Model instance
     *
     * @param Model $model Model instance
     * @return string
     */
    protected static function getResourceNameFromModel($model)
    {
        $class = get_class($model);
        return basename(str_replace('\\', '/', $class));
    }

    /**
     * Generate default link text based on resource and action
     *
     * @param string|Model $resource Resource name
     * @param string $action Action name
     * @return string
     */
    protected static function generateLinkText($resource, $action)
    {
        $resource_name = $resource instanceof Model ? self::getResourceNameFromModel($resource) : $resource;

        switch ($action) {
            case 'index':
                return pluralize($resource_name);
            case 'add':
            case 'create':
                return "Add " . $resource_name;
            case 'edit':
            case 'update':
                return "Edit " . $resource_name;
            case 'show':
                return "View " . $resource_name;
            case 'delete':
            case 'destroy':
                return "Delete " . $resource_name;
            default:
                return ucfirst($action) . " " . $resource_name;
        }
    }

    /**
     * Get default icon for action
     *
     * @param string $action Action name
     * @return string
     */
    protected static function getDefaultIcon($action)
    {
        $icons = [
            'index' => 'list',
            'add' => 'plus',
            'create' => 'plus',
            'edit' => 'pencil',
            'update' => 'pencil',
            'show' => 'eye',
            'delete' => 'trash',
            'destroy' => 'trash',
        ];

        return $icons[$action] ?? 'arrow-right';
    }

    /**
     * Get CSS classes for action
     *
     * @param string $action Action name
     * @param string $base_class Base CSS class
     * @return string
     */
    protected static function getActionClasses($action, $base_class)
    {
        $action_classes = [
            'add' => 'button-primary',
            'create' => 'button-primary',
            'edit' => 'button-primary',
            'update' => 'button-primary',
            'show' => 'button-secondary',
            'delete' => 'button-danger',
            'destroy' => 'button-danger',
            'index' => 'button-secondary',
        ];

        $action_class = $action_classes[$action] ?? 'button-secondary';

        return trim($base_class . ' ' . $action_class);
    }
}
