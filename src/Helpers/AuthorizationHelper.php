<?php
namespace MakermakerCore\Helpers;

use TypeRocket\Http\Response;
use TypeRocket\Models\Model;

/**
 * Helper for authorization checks in controllers
 *
 * Centralizes permission checking and unauthorized responses.
 */
class AuthorizationHelper
{
    /**
     * Check if user can perform action, abort if unauthorized
     *
     * @param Model $model Model to check permissions on
     * @param string $action Action name (create, update, delete, etc.)
     * @param Response $response Response object for abort
     * @param string|null $message Custom unauthorized message
     * @return void
     * @throws \Exception Aborts with 403 if unauthorized
     */
    public static function authorize(
        Model $model,
        string $action,
        Response $response,
        ?string $message = null
    ): void {
        if (!$model->can($action)) {
            $entityName = (new \ReflectionClass($model))->getShortName();
            $defaultMessage = sprintf('Unauthorized: %s not %s', $entityName, self::getPastTense($action));
            $response->unauthorized($message ?? $defaultMessage)->abort();
        }
    }

    /**
     * Get past tense verb for action
     *
     * @param string $action Action name (create, update, delete)
     * @return string Past tense (created, updated, deleted)
     */
    private static function getPastTense(string $action): string
    {
        $pastTenses = [
            'create' => 'created',
            'update' => 'updated',
            'delete' => 'deleted',
            'destroy' => 'deleted',
            'read' => 'read',
            'view' => 'viewed',
        ];

        return $pastTenses[$action] ?? $action . 'd';
    }
}
