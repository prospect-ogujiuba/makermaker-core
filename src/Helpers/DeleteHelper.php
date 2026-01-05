<?php
namespace MakermakerCore\Helpers;

use TypeRocket\Http\Response;
use TypeRocket\Models\Model;

class DeleteHelper
{
    public static function checkDependencies(
        Model $model,
        string $relationshipName,
        Response $response,
        ?string $customMessage = null
    ): ?Response {
        $relationship = $model->$relationshipName();

        // Use get()->count() to avoid SQL DISTINCT * errors with joined tables
        $count = $relationship->get()->count();

        if ($count > 0) {
            $entityName = self::getModelName($model);
            $message = $customMessage ?? "Cannot delete: {$count} item(s) still reference this {$entityName}.";

            return $response
                ->error($message)
                ->setStatus(409)
                ->setData(strtolower($entityName), $model);
        }

        return null;
    }

    public static function executeDelete(
        Model $model,
        Response $response,
        ?string $successMessage = null
    ): Response {
        $entityName = self::getModelName($model);
        $deleted = $model->delete();

        if ($deleted === false) {
            $message = 'Delete failed due to a database error.';
            if (RestHelper::isRestRequest()) {
                return RestHelper::errorResponse($response, [], $message, 500);
            }
            return $response->error($message)->setStatus(500);
        }

        $message = $successMessage ?? "{$entityName} deleted successfully";

        if (RestHelper::isRestRequest()) {
            return RestHelper::deleteResponse($response, $message);
        }

        return $response->success($message)->setData(strtolower($entityName), $model);
    }

    private static function getModelName(Model $model): string
    {
        return basename(str_replace('\\', '/', get_class($model)));
    }
}
