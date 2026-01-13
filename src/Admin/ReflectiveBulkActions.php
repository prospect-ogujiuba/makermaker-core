<?php

namespace MakermakerCore\Admin;

use MakermakerCore\Attributes\BulkAction;
use ReflectionClass;
use ReflectionMethod;

/**
 * Discovers bulk actions from model or controller via #[BulkAction] attributes
 *
 * Provides zero-configuration bulk action discovery for admin index views.
 * Actions are discovered by scanning for #[BulkAction] attributed methods.
 *
 * Usage:
 * ```php
 * // Auto-discover from model
 * [$form, $actions] = ReflectiveBulkActions::for(new Service())->getBulkActionsConfig();
 * $table->setBulkActions($form, $actions);
 *
 * // With customization
 * $table->setBulkActions(
 *     ...ReflectiveBulkActions::for(new Service())
 *         ->exclude(['archive'])
 *         ->add('export', 'Export to CSV')
 *         ->getBulkActionsConfig()
 * );
 * ```
 *
 * @package MakermakerCore\Admin
 */
class ReflectiveBulkActions
{
    /**
     * In-memory cache of discovered actions per class
     *
     * @var array<string, array>
     */
    private static array $cache = [];

    /**
     * The target model or controller instance
     */
    private object $target;

    /**
     * Actions to exclude from results
     *
     * @var array<string>
     */
    private array $excludedActions = [];

    /**
     * Additional actions to add manually
     *
     * @var array<string, array>
     */
    private array $additionalActions = [];

    /**
     * Whether to include the default delete action
     */
    private bool $includeDelete = true;

    /**
     * Create a new ReflectiveBulkActions instance
     *
     * @param object $target Model or Controller instance
     */
    public function __construct(object $target)
    {
        $this->target = $target;
    }

    /**
     * Static factory method
     *
     * @param object $target Model or Controller instance
     * @return self
     */
    public static function for(object $target): self
    {
        return new self($target);
    }

    /**
     * Discover bulk actions from target class
     *
     * @return array<string, array> ['action_key' => ['label' => '...', 'capability' => '...']]
     */
    public function discover(): array
    {
        $class = get_class($this->target);

        if (!isset(self::$cache[$class])) {
            self::$cache[$class] = $this->discoverFromClass();
        }

        $actions = self::$cache[$class];

        // Apply exclusions
        foreach ($this->excludedActions as $excluded) {
            unset($actions[$excluded]);
        }

        // Add additional actions
        return array_merge($actions, $this->additionalActions);
    }

    /**
     * Discover actions by inspecting class methods for #[BulkAction] attribute
     *
     * @return array Action configurations
     */
    private function discoverFromClass(): array
    {
        $reflection = new ReflectionClass($this->target);
        $actions = [];

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            // Skip static, constructor, magic methods
            if ($method->isStatic() || $method->isConstructor()) {
                continue;
            }
            if (str_starts_with($method->getName(), '__')) {
                continue;
            }

            // Check for BulkAction attribute
            $attributes = $method->getAttributes(BulkAction::class);
            if (empty($attributes)) {
                continue;
            }

            $attr = $attributes[0]->newInstance();
            $methodName = $method->getName();

            // Convert method name to action key (bulkSendEmail → send_email)
            $actionKey = $this->methodToActionKey($methodName);

            $actions[$actionKey] = [
                'label' => $attr->label,
                'capability' => $attr->capability,
                'description' => $attr->description,
                'method' => $methodName,
                'requiresConfirmation' => $attr->requiresConfirmation,
                'icon' => $attr->icon,
            ];
        }

        return $actions;
    }

    /**
     * Convert method name to action key
     *
     * bulkSendEmail → send_email
     * bulkActivate → activate
     *
     * @param string $method Method name
     * @return string Action key in snake_case
     */
    private function methodToActionKey(string $method): string
    {
        // Remove 'bulk' prefix if present
        if (str_starts_with($method, 'bulk')) {
            $method = lcfirst(substr($method, 4));
        }

        // Convert camelCase to snake_case
        return strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $method));
    }

    /**
     * Get actions formatted for TypeRocket's setBulkActions()
     *
     * @return array ['Label' => 'action_key', ...]
     */
    public function getActionsForTable(): array
    {
        $actions = $this->discover();
        $formatted = [];

        foreach ($actions as $key => $config) {
            $formatted[$config['label']] = $key;
        }

        // Always include delete unless excluded
        if ($this->includeDelete && !in_array('delete', $this->excludedActions)) {
            $formatted['Delete'] = 'delete';
        }

        return $formatted;
    }

    /**
     * Get form instance for bulk actions (with confirmation if needed)
     *
     * @return \TypeRocket\Elements\Form|mixed Form instance (may be App override)
     */
    public function getForm()
    {
        return tr_form()->useConfirm();
    }

    /**
     * Convenience method that returns both form and actions
     *
     * @return array [Form, actions array]
     */
    public function getBulkActionsConfig(): array
    {
        return [
            $this->getForm(),
            $this->getActionsForTable()
        ];
    }

    /**
     * Get detailed action info by key
     *
     * @param string $actionKey Action key
     * @return array|null Action config or null if not found
     */
    public function getAction(string $actionKey): ?array
    {
        $actions = $this->discover();
        return $actions[$actionKey] ?? null;
    }

    /**
     * Check if target has any bulk actions defined
     *
     * @return bool
     */
    public function hasActions(): bool
    {
        return !empty($this->discover());
    }

    /**
     * Exclude actions from results
     *
     * @param array<string> $actions Action keys to exclude
     * @return self
     */
    public function exclude(array $actions): self
    {
        $this->excludedActions = array_merge($this->excludedActions, $actions);
        return $this;
    }

    /**
     * Exclude the default delete action
     *
     * @return self
     */
    public function excludeDelete(): self
    {
        $this->includeDelete = false;
        return $this;
    }

    /**
     * Add a custom action manually
     *
     * @param string $key Action key
     * @param string $label Display label
     * @param string $capability Required capability (default: 'edit')
     * @return self
     */
    public function add(string $key, string $label, string $capability = 'edit'): self
    {
        $this->additionalActions[$key] = [
            'label' => $label,
            'capability' => $capability,
            'description' => '',
            'method' => null,
            'requiresConfirmation' => true,
            'icon' => null,
        ];
        return $this;
    }

    /**
     * Clear discovery cache
     *
     * @param string|null $class Clear specific class or all if null
     */
    public static function clearCache(?string $class = null): void
    {
        if ($class) {
            unset(self::$cache[$class]);
        } else {
            self::$cache = [];
        }
    }
}
