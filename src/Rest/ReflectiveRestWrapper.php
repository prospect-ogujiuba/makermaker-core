<?php

namespace MakermakerCore\Rest;

use TypeRocket\Register\Registry;
use TypeRocket\Http\Request;
use TypeRocket\Http\Response;
use TypeRocket\Models\AuthUser;
use TypeRocket\Models\Model;
use TypeRocket\Core\Container;

/**
 * Reflective REST API Wrapper
 *
 * Zero-configuration wrapper that automatically adds search, filtering,
 * sorting, pagination, and action capabilities to all TypeRocket custom resources.
 *
 * Usage: Call ReflectiveRestWrapper::init() once in plugin initialization.
 */
class ReflectiveRestWrapper
{
    private static $initialized = false;
    private static string $modelNamespace = '\\MakerMaker\\Models\\';
    private static ?callable $listQueryModifier = null;

    /**
     * Set the model namespace for the plugin
     *
     * @param string $namespace Model namespace (e.g., '\\MakerMaker\\Models\\')
     */
    public static function setModelNamespace(string $namespace): void
    {
        self::$modelNamespace = rtrim($namespace, '\\') . '\\';
    }

    /**
     * Set a callback to modify list queries (for user-based filtering, etc.)
     *
     * Callback signature: function(Model $model, string $resource, AuthUser $user): Model
     *
     * @param callable|null $callback Query modifier callback
     */
    public static function setListQueryModifier(?callable $callback): void
    {
        self::$listQueryModifier = $callback;
    }

    /**
     * Initialize the wrapper (call once during plugin init)
     */
    public static function init(): void
    {
        if (self::$initialized) {
            return;
        }

        self::$initialized = true;

        // Hook into WordPress request parsing (before TypeRocket routing)
        add_action('parse_request', [self::class, 'handleRequest'], 5);
    }

    /**
     * Handle incoming request - intercept REST API calls
     *
     * @param \WP $wp WordPress environment object
     */
    public static function handleRequest($wp): void
    {
        // Check if this is a REST API request
        if (!isset($wp->request) || !preg_match(
            '#^tr-api/rest/([^/]+)(?:/([^/]+))?(?:/actions/([^/]+))?$#',
            $wp->request,
            $matches
        )) {
            return; // Not a REST request we care about
        }

        [$full, $resource, $id, $action] = array_pad($matches, 4, null);

        // Handle custom non-numeric endpoints (like /types, /priorities, /submit)
        if ($id && !$action && !is_numeric($id)) {
            self::handleCustomEndpoint($resource, $id);
            exit;
        }

        // Handle all REST operations with consistent authentication
        // Previously only indexRest was enhanced, causing 401 errors on showRest/createRest/updateRest/deleteRest
        try {
            if ($action) {
                // POST /tr-api/rest/{resource}/{id}/actions/{action}
                self::handleAction($resource, $id, $action);
            } elseif ($id && $_SERVER['REQUEST_METHOD'] === 'GET') {
                // GET /tr-api/rest/{resource}/{id} - showRest
                self::handleShow($resource, $id);
            } elseif ($id && $_SERVER['REQUEST_METHOD'] === 'PUT') {
                // PUT /tr-api/rest/{resource}/{id} - updateRest
                self::handleUpdate($resource, $id);
            } elseif ($id && $_SERVER['REQUEST_METHOD'] === 'DELETE') {
                // DELETE /tr-api/rest/{resource}/{id} - deleteRest
                self::handleDelete($resource, $id);
            } elseif (!$id && $_SERVER['REQUEST_METHOD'] === 'POST') {
                // POST /tr-api/rest/{resource} - createRest
                self::handleCreate($resource);
            } else {
                // GET /tr-api/rest/{resource} - indexRest
                self::handleList($resource);
            }
        } catch (\Exception $e) {
            self::sendErrorResponse($e);
        }

        exit; // Prevent WordPress/TypeRocket from further processing
    }

    /**
     * Handle enhanced list endpoint with search, filters, pagination
     */
    private static function handleList(string $resource): void
    {
        // Get resource configuration from Registry
        $config = Registry::getCustomResource($resource);

        if (!$config) {
            throw new \Exception("Resource not found: {$resource}", 404);
        }

        // Get model from controller
        $model = self::getModelFromResource($config);

        // Check authorization (model internally resolves current user)
        if (!$model->can('read')) {
            throw new \Exception("Unauthorized access to resource: {$resource}", 403);
        }

        // Apply custom query modifier if configured (for user-based filtering, etc.)
        if (self::$listQueryModifier !== null) {
            $user = Container::resolveAlias(AuthUser::ALIAS);
            $model = call_user_func(self::$listQueryModifier, $model, $resource, $user);
        }

        // Build request
        $request = new Request();

        // Build query with reflective query builder
        $builder = new ReflectiveQueryBuilder($model, $request);
        $result = $builder->execute();

        // Send successful response
        self::sendSuccessResponse($result['data'], $result['meta']);
    }

    /**
     * Handle show endpoint - GET /tr-api/rest/{resource}/{id}
     * Applies same authorization pattern as handleList
     */
    private static function handleShow(string $resource, string $id): void
    {
        // Get resource configuration from Registry
        $config = Registry::getCustomResource($resource);

        if (!$config) {
            throw new \Exception("Resource not found: {$resource}", 404);
        }

        // Get model from controller
        $model = self::getModelFromResource($config);

        // Find the specific record
        $record = $model->findById((int) $id);

        if (!$record) {
            throw new \Exception("Record not found: {$resource}/{$id}", 404);
        }

        // Check authorization - model internally resolves current user
        if (!$record->can('read')) {
            throw new \Exception("Unauthorized access to resource: {$resource}/{$id}", 403);
        }

        // Send successful response
        self::sendSuccessResponse($record);
    }

    /**
     * Handle create endpoint - POST /tr-api/rest/{resource}
     * Applies same authorization pattern as handleList
     */
    private static function handleCreate(string $resource): void
    {
        // Get resource configuration from Registry
        $config = Registry::getCustomResource($resource);

        if (!$config) {
            throw new \Exception("Resource not found: {$resource}", 404);
        }

        // Get model from controller
        $model = self::getModelFromResource($config);

        // Check authorization for creating new records
        if (!$model->can('create')) {
            throw new \Exception("Unauthorized to create resource: {$resource}", 403);
        }

        // Get request data
        $request = new Request();
        $data = $request->getFields();
        if (empty($data)) {
            $data = $request->getDataJson();
        }

        // Save the model with request data
        $model->save($data);

        // Check for validation errors
        if ($model->getErrors()) {
            throw new \Exception("Validation failed: " . json_encode($model->getErrors()), 400);
        }

        // Send successful response with created record
        self::sendSuccessResponse($model, null, 'Resource created successfully');
    }

    /**
     * Handle update endpoint - PUT /tr-api/rest/{resource}/{id}
     * Applies same authorization pattern as handleList
     */
    private static function handleUpdate(string $resource, string $id): void
    {
        // Get resource configuration from Registry
        $config = Registry::getCustomResource($resource);

        if (!$config) {
            throw new \Exception("Resource not found: {$resource}", 404);
        }

        // Get model from controller
        $model = self::getModelFromResource($config);

        // Find the specific record
        $record = $model->findById((int) $id);

        if (!$record) {
            throw new \Exception("Record not found: {$resource}/{$id}", 404);
        }

        // Check authorization for updating this record
        if (!$record->can('update')) {
            throw new \Exception("Unauthorized to update resource: {$resource}/{$id}", 403);
        }

        // Get request data
        $request = new Request();
        $data = $request->getFields();
        if (empty($data)) {
            $data = $request->getDataJson();
        }

        // Update the record
        $record->save($data);

        // Check for validation errors
        if ($record->getErrors()) {
            throw new \Exception("Validation failed: " . json_encode($record->getErrors()), 400);
        }

        // Send successful response with updated record
        self::sendSuccessResponse($record, null, 'Resource updated successfully');
    }

    /**
     * Handle delete endpoint - DELETE /tr-api/rest/{resource}/{id}
     * Applies same authorization pattern as handleList
     */
    private static function handleDelete(string $resource, string $id): void
    {
        // Get resource configuration from Registry
        $config = Registry::getCustomResource($resource);

        if (!$config) {
            throw new \Exception("Resource not found: {$resource}", 404);
        }

        // Get model from controller
        $model = self::getModelFromResource($config);

        // Find the specific record
        $record = $model->findById((int) $id);

        if (!$record) {
            throw new \Exception("Record not found: {$resource}/{$id}", 404);
        }

        // Check authorization for deleting this record
        if (!$record->can('destroy')) {
            throw new \Exception("Unauthorized to delete resource: {$resource}/{$id}", 403);
        }

        // Delete the record
        $record->delete();

        // Send successful response
        self::sendSuccessResponse(null, null, 'Resource deleted successfully');
    }

    /**
     * Handle custom action endpoint
     */
    private static function handleAction(string $resource, string $id, string $actionName): void
    {
        // Only allow POST for actions
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            throw new \Exception("Actions only support POST method", 405);
        }

        // Get resource configuration
        $config = Registry::getCustomResource($resource);

        if (!$config) {
            throw new \Exception("Resource not found: {$resource}", 404);
        }

        // Get model instance
        $model = self::getModelFromResource($config);

        // Load specific record
        $record = $model->findById((int) $id);

        if (!$record) {
            throw new \Exception("Record not found: {$resource}/{$id}", 404);
        }

        // Get current user
        $user = Container::resolveAlias(AuthUser::ALIAS);

        // Dispatch action
        $request = new Request();
        $response = Response::getFromContainer();

        $dispatcher = new ActionDispatcher($record, $request, $response, $user);
        $result = $dispatcher->dispatch($actionName);

        // Send response
        self::sendSuccessResponse($result['data'] ?? null, null, $result['message'] ?? 'Action executed successfully');
    }

    /**
     * Handle custom non-numeric endpoint (e.g., /types, /priorities, /submit)
     */
    private static function handleCustomEndpoint(string $resource, string $endpoint): void
    {
        // Get resource configuration
        $config = Registry::getCustomResource($resource);

        if (!$config) {
            throw new \Exception("Resource not found: {$resource}", 404);
        }

        // Get controller class
        $controllerClass = $config['controller'] ?? null;

        if (!$controllerClass) {
            throw new \Exception("Resource has no controller configured", 500);
        }

        // Map endpoint to method name
        // e.g., "types" -> "getContactTypes" or "getTypes"
        // e.g., "priorities" -> "getPriorities"
        // e.g., "submit" -> "submit"
        $methodMappings = [
            'types' => ['getContactTypes', 'getTypes'],
            'priorities' => ['getPriorities'],
            'submit' => ['submit'],
        ];

        $possibleMethods = $methodMappings[$endpoint] ?? [
            'get' . str_replace('-', '', ucwords($endpoint, '-'))
        ];

        // Find which method exists
        $methodName = null;
        foreach ($possibleMethods as $method) {
            if (method_exists($controllerClass, $method)) {
                $methodName = $method;
                break;
            }
        }

        if (!$methodName) {
            throw new \Exception("Custom endpoint not found: {$resource}/{$endpoint}", 404);
        }

        // Create controller instance
        $controller = new $controllerClass();

        // Call the method without response parameter (controller will return array)
        try {
            $result = $controller->$methodName();

            // If result is an array, send it as JSON
            if (is_array($result)) {
                $status = 200;

                // Check if error status was set
                if (isset($result['success']) && $result['success'] === false) {
                    $status = 500;
                }

                status_header($status);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                return;
            }

            // If result is a Response object (shouldn't happen but handle it)
            if ($result instanceof Response) {
                $data = $result->getData();
                $status = $result->getStatus() ?: 200;

                status_header($status);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                return;
            }

            // Unknown result type
            throw new \Exception("Unexpected return type from {$controllerClass}::{$methodName}", 500);

        } catch (\Exception $e) {
            throw new \Exception("Error calling {$controllerClass}::{$methodName}: " . $e->getMessage(), 500);
        }
    }

    /**
     * Get model instance from resource configuration
     */
    private static function getModelFromResource(array $config): Model
    {
        $controller = $config['controller'] ?? null;

        if (!$controller) {
            throw new \Exception("Resource has no controller configured", 500);
        }

        // Extract model name from controller name
        // E.g., \MakerMaker\Controllers\ServiceController -> Service
        $controllerClass = basename(str_replace('\\', '/', $controller));
        $modelName = str_replace('Controller', '', $controllerClass);

        // Build model class name using configurable namespace
        $modelClass = self::$modelNamespace . $modelName;

        if (!class_exists($modelClass)) {
            throw new \Exception("Model not found: {$modelClass}", 500);
        }

        // Create new instance
        return new $modelClass();
    }

    /**
     * Send successful JSON response
     */
    private static function sendSuccessResponse($data, ?array $meta = null, ?string $message = null): void
    {
        $response = [
            'success' => true,
        ];

        if ($data !== null) {
            $response['data'] = $data;
        }

        if ($meta !== null) {
            $response['meta'] = $meta;
        }

        if ($message !== null) {
            $response['message'] = $message;
        }

        self::sendJsonResponse($response, 200);
    }

    /**
     * Send error JSON response
     */
    private static function sendErrorResponse(\Exception $e): void
    {
        $code = $e->getCode() ?: 500;

        // Ensure valid HTTP status code
        if ($code < 100 || $code > 599) {
            $code = 500;
        }

        $response = [
            'success' => false,
            'message' => $e->getMessage(),
            'code' => self::getErrorCode($e)
        ];

        // In debug mode, include trace
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $response['trace'] = $e->getTraceAsString();
        }

        self::sendJsonResponse($response, $code);
    }

    /**
     * Get error code from exception
     */
    private static function getErrorCode(\Exception $e): string
    {
        $code = $e->getCode();

        $codes = [
            400 => 'BAD_REQUEST',
            403 => 'FORBIDDEN',
            404 => 'NOT_FOUND',
            405 => 'METHOD_NOT_ALLOWED',
            500 => 'INTERNAL_ERROR'
        ];

        return $codes[$code] ?? 'UNKNOWN_ERROR';
    }

    /**
     * Send JSON response with proper headers
     */
    private static function sendJsonResponse(array $data, int $status = 200): void
    {
        status_header($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}
