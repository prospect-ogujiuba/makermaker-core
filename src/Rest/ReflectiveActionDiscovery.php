<?php

namespace MakermakerCore\Rest;

use MakermakerCore\Attributes\Action;
use TypeRocket\Models\Model;
use ReflectionClass;
use ReflectionMethod;

/**
 * Reflective Action Discovery
 *
 * Automatically discovers REST API actions from model methods using PHP 8 attributes.
 * Provides zero-configuration action discovery for models without requiring HasRestActions interface.
 *
 * Discovery Rules:
 * 1. Method has #[Action] attribute → explicit action
 * 2. Capability inferred from attribute or method name patterns
 * 3. Results cached per model class for performance
 *
 * @package MakermakerCore\Rest
 */
class ReflectiveActionDiscovery
{
    /**
     * In-memory cache of discovered actions per model class
     * Persists for request lifetime
     *
     * @var array<string, array>
     */
    private static array $cache = [];

    /**
     * Discover all REST actions for a model via reflection
     *
     * @param Model $model Model instance to inspect
     * @return array Associative array of action configurations
     *
     * Format: [
     *   'actionName' => [
     *     'method' => 'duplicate',
     *     'capability' => 'create',
     *     'description' => 'Duplicate this service',
     *     'requires_params' => false,
     *     'requires_id' => true
     *   ]
     * ]
     */
    public static function discoverActions(Model $model): array
    {
        $class = get_class($model);

        // Return cached result if available
        if (isset(self::$cache[$class])) {
            return self::$cache[$class];
        }

        $actions = self::inspectModelMethods($class);

        // Cache and return
        self::$cache[$class] = $actions;
        return $actions;
    }

    /**
     * Inspect model class methods for Action attributes
     *
     * @param string $class Fully qualified class name
     * @return array Action configurations
     */
    private static function inspectModelMethods(string $class): array
    {
        $reflection = new ReflectionClass($class);
        $actions = [];

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            // Skip inherited methods from Model base class
            if ($method->getDeclaringClass()->getName() === Model::class) {
                continue;
            }

            // Skip magic methods
            if (str_starts_with($method->getName(), '__')) {
                continue;
            }

            // Check for Action attribute
            $attributes = $method->getAttributes(Action::class);

            if (empty($attributes)) {
                continue;
            }

            // Get attribute instance
            $actionAttr = $attributes[0]->newInstance();

            // Validate method signature
            if (!self::validateActionMethod($method)) {
                continue; // Skip invalid action methods
            }

            // Derive action name from method name
            $actionName = self::deriveActionName($method->getName());

            // Build action configuration
            $actions[$actionName] = [
                'method' => $method->getName(),
                'capability' => $actionAttr->capability ?: self::inferCapability($method->getName()),
                'description' => $actionAttr->description,
                'requires_params' => $actionAttr->requiresParams,
                'requires_id' => $actionAttr->requiresId
            ];
        }

        return $actions;
    }

    /**
     * Validate action method signature
     *
     * Required signature: public function action(AuthUser $user, array $params): array
     * Optional: Response parameter
     *
     * @param ReflectionMethod $method Method to validate
     * @return bool True if valid action method
     */
    private static function validateActionMethod(ReflectionMethod $method): bool
    {
        // Must be public
        if (!$method->isPublic()) {
            return false;
        }

        // Must not be static
        if ($method->isStatic()) {
            return false;
        }

        // Check return type (should be array)
        $returnType = $method->getReturnType();
        if ($returnType && $returnType->getName() !== 'array') {
            // Warn in debug mode but still allow
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("Action method {$method->getName()} should return array, returns {$returnType->getName()}");
            }
        }

        // Method signature is valid
        return true;
    }

    /**
     * Derive action name from method name
     *
     * Converts camelCase to kebab-case: duplicate → duplicate, updatePricing → update-pricing
     *
     * @param string $methodName Method name
     * @return string Action name (kebab-case)
     */
    private static function deriveActionName(string $methodName): string
    {
        // Remove 'action' prefix if present
        if (str_starts_with($methodName, 'action')) {
            $methodName = substr($methodName, 6); // Remove 'action'
        }

        // Convert to kebab-case
        $actionName = strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', $methodName));

        return $actionName;
    }

    /**
     * Infer capability from method name patterns
     *
     * Patterns:
     * - create*, duplicate*, copy*, clone* → 'create'
     * - update*, modify*, edit*, change* → 'update'
     * - delete*, remove*, destroy* → 'destroy'
     * - archive*, restore*, activate*, deactivate* → 'update'
     * - default → 'read'
     *
     * @param string $methodName Method name
     * @return string Capability name
     */
    private static function inferCapability(string $methodName): string
    {
        $lower = strtolower($methodName);

        // Creation patterns
        if (preg_match('/^(create|duplicate|copy|clone)/', $lower)) {
            return 'create';
        }

        // Deletion patterns
        if (preg_match('/^(delete|remove|destroy)/', $lower)) {
            return 'destroy';
        }

        // Update patterns
        if (preg_match('/^(update|modify|edit|change|archive|restore|activate|deactivate|toggle)/', $lower)) {
            return 'update';
        }

        // Default to read
        return 'read';
    }

    /**
     * Clear discovery cache (useful for testing)
     *
     * @param string|null $class Optional class name to clear specific cache
     * @return void
     */
    public static function clearCache(?string $class = null): void
    {
        if ($class) {
            unset(self::$cache[$class]);
        } else {
            self::$cache = [];
        }
    }

    /**
     * Check if model has any discovered actions
     *
     * @param Model $model Model to check
     * @return bool True if actions exist
     */
    public static function hasActions(Model $model): bool
    {
        $actions = self::discoverActions($model);
        return !empty($actions);
    }

    /**
     * Get cache statistics (for debugging)
     *
     * @return array Cache statistics
     */
    public static function getCacheStats(): array
    {
        return [
            'cached_models' => count(self::$cache),
            'total_actions' => array_sum(array_map('count', self::$cache)),
            'models' => array_keys(self::$cache)
        ];
    }
}
