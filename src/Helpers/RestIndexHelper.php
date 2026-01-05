<?php
namespace MakermakerCore\Helpers;

use TypeRocket\Http\Response;
use TypeRocket\Models\Model;

class RestIndexHelper
{
    public static function handleIndex(
        Response $response,
        string $modelClass,
        string $pluralName,
        ?callable $queryModifier = null
    ): Response {
        try {
            $query = $modelClass::new();

            if ($queryModifier !== null) {
                $query = $queryModifier($query);
            }

            $items = $query->get();
            $displayName = ucfirst(str_replace('_', ' ', $pluralName));

            if (empty($items)) {
                return $response
                    ->setData($pluralName, [])
                    ->setMessage("No {$displayName} found", 'info')
                    ->setStatus(200);
            }

            return $response
                ->setData($pluralName, $items)
                ->setMessage("{$displayName} retrieved successfully", 'success')
                ->setStatus(200);

        } catch (\Exception $e) {
            error_log("{$pluralName} indexRest error: " . $e->getMessage());
            return $response
                ->error("Failed to retrieve {$pluralName}: " . $e->getMessage())
                ->setStatus(500);
        }
    }

    public static function handleShow(
        Response $response,
        Model $model,
        string $modelClass,
        string $singularName,
        ?array $with = null
    ): Response {
        try {
            $query = $modelClass::new();

            if ($with !== null) {
                $query = $query->with($with);
            }

            $item = $query->find($model->getID());
            $displayName = ucfirst(str_replace('_', ' ', $singularName));

            if (empty($item)) {
                return $response
                    ->setData($singularName, null)
                    ->setMessage("{$displayName} not found", 'info')
                    ->setStatus(404);
            }

            return $response
                ->setData($singularName, $item)
                ->setMessage("{$displayName} retrieved successfully", 'success')
                ->setStatus(200);

        } catch (\Exception $e) {
            error_log("{$singularName} showRest error: " . $e->getMessage());
            return $response
                ->setMessage("An error occurred while retrieving {$displayName}", 'error')
                ->setStatus(500);
        }
    }
}
