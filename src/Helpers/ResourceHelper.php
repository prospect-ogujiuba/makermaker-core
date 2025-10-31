<?php

namespace MakerMaker\Helpers;

use TypeRocket\Register\Page;
use TypeRocket\Register\Registry;

/**
 * TypeRocket resource management utilities
 * 
 * Provides methods for creating and managing TypeRocket resources
 */
class ResourceHelper
{
    /**
     * Create TypeRocket resource page + REST endpoint
     * 
     * @param string $resourceKey Resource identifier
     * @param string $controller Controller class name
     * @param string $title Display title
     * @param bool $hasAddButton Show add new button
     * @param string|null $restSlug Custom REST slug
     * @param bool $registerRest Register REST endpoint
     * @return Page TypeRocket page instance
     */
    public static function createCustomResource(
        string $resourceKey,
        string $controller,
        string $title,
        bool $hasAddButton = true,
        ?string $restSlug = null,
        bool $registerRest = true
    ): Page {
        $fqcn = '\\MakerMaker\\Controllers\\' . $controller;

        // Create admin page
        $resourcePage = tr_resource_pages("{$resourceKey}@{$fqcn}", $title);

        if ($hasAddButton) {
            $adminPageSlug = strtolower($resourceKey) . '_add';
            $resourcePage->addNewButton(admin_url('admin.php?page=' . $adminPageSlug));
        }

        // Register REST endpoint
        if ($registerRest) {
            $slug = $restSlug ?: StringHelper::pluralize(StringHelper::toKebab($resourceKey, '-'));
            Registry::addCustomResource($slug, ['controller' => $fqcn]);
        }

        return $resourcePage;
    }

    /**
     * Generate resource URL
     * 
     * @param string $resourceKey Resource identifier
     * @param string $action Action name
     * @param int|null $id Resource ID
     * @return string Admin URL
     */
    public static function generateResourceUrl(string $resourceKey, string $action = 'index', ?int $id = null): string
    {
        $resourceSlug = strtolower($resourceKey);
        $page = $resourceSlug . '_' . $action;

        $params = ['page' => $page];

        if (in_array($action, ['edit', 'show', 'delete']) && $id) {
            $params['route_args'] = [$id];
        }

        return admin_url('admin.php?' . http_build_query($params));
    }
}