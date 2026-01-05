<?php

namespace MakermakerCore\Rest;

use MakermakerCore\Contracts\HasRestActions;
use TypeRocket\Models\Model;
use TypeRocket\Models\AuthUser;
use TypeRocket\Http\Request;
use TypeRocket\Http\Response;

/**
 * Action Dispatcher
 *
 * Handles execution of custom REST actions on models implementing HasRestActions
 */
class ActionDispatcher
{
    private Model $model;
    private Request $request;
    private Response $response;
    private ?AuthUser $user;

    public function __construct(Model $model, Request $request, Response $response, ?AuthUser $user = null)
    {
        $this->model = $model;
        $this->request = $request;
        $this->response = $response;
        $this->user = $user;
    }

    /**
     * Dispatch action to model
     *
     * @param string $actionName Action name from URL
     * @return array Response data
     * @throws \Exception
     */
    public function dispatch(string $actionName): array
    {
        // Get available actions (supports both interface and reflective discovery)
        $actions = $this->getActions();

        if (!isset($actions[$actionName])) {
            throw new \Exception("Action not found: {$actionName}", 404);
        }

        $config = $actions[$actionName];

        // Validate action configuration
        $this->validateActionConfig($config, $actionName);

        // Check authorization via policy
        if (isset($config['capability'])) {
            if (!$this->model->can($config['capability'], $this->user)) {
                throw new \Exception("Unauthorized to perform action: {$actionName}", 403);
            }
        }

        // Get action method
        $method = $config['method'];

        if (!method_exists($this->model, $method)) {
            throw new \Exception("Action method not found: {$method}", 500);
        }

        // Get action parameters from request body
        $params = $this->getActionParameters();

        // Execute action
        try {
            $result = $this->model->$method($this->user, $params);

            // Ensure result is array
            if (!is_array($result)) {
                $result = ['success' => true, 'data' => $result];
            }

            // Add default success flag if not present
            if (!isset($result['success'])) {
                $result['success'] = true;
            }

            return $result;

        } catch (\Exception $e) {
            throw new \Exception("Action execution failed: " . $e->getMessage(), 500);
        }
    }

    /**
     * Validate action configuration
     */
    private function validateActionConfig(array $config, string $actionName): void
    {
        if (!isset($config['method'])) {
            throw new \Exception("Action '{$actionName}' missing 'method' configuration", 500);
        }
    }

    /**
     * Get action parameters from request body
     */
    private function getActionParameters(): array
    {
        // Get raw body
        $body = file_get_contents('php://input');

        if (empty($body)) {
            return [];
        }

        // Try to decode JSON
        $params = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception("Invalid JSON in request body", 400);
        }

        return $params ?? [];
    }

    /**
     * Get actions for current model instance
     *
     * Supports both:
     * 1. HasRestActions interface (backward compatibility)
     * 2. Reflective discovery via #[Action] attributes (zero-config)
     *
     * @return array Action configurations
     */
    private function getActions(): array
    {
        // Prefer interface implementation for backward compatibility
        if ($this->model instanceof HasRestActions) {
            return $this->model->getRestActions();
        }

        // Fallback to reflective discovery
        return ReflectiveActionDiscovery::discoverActions($this->model);
    }

    /**
     * Get list of available actions for a model
     *
     * @param Model $model
     * @return array
     */
    public static function getAvailableActions(Model $model): array
    {
        // Use same logic as instance method
        if ($model instanceof HasRestActions) {
            $actions = $model->getRestActions();
        } else {
            $actions = ReflectiveActionDiscovery::discoverActions($model);
        }

        $result = [];

        foreach ($actions as $name => $config) {
            $result[] = [
                'name' => $name,
                'description' => $config['description'] ?? '',
                'capability' => $config['capability'] ?? null,
                'requires_id' => $config['requires_id'] ?? true
            ];
        }

        return $result;
    }
}
