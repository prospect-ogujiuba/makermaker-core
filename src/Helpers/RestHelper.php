<?php
namespace MakermakerCore\Helpers;

use TypeRocket\Http\Response;

/**
 * Helper for REST API request detection and response formatting
 *
 * Centralizes REST vs web request handling for controllers.
 */
class RestHelper
{
    /**
     * Check if current request is a REST API request
     *
     * Detects based on Accept header containing application/json.
     *
     * @return bool True if REST request
     */
    public static function isRestRequest(): bool
    {
        return strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false;
    }

    /**
     * Format success REST response
     *
     * @param Response $response TypeRocket response object
     * @param mixed $data Data to return (usually model instance)
     * @param string $message Success message
     * @param int $statusCode HTTP status code (200, 201, etc.)
     * @return Response
     */
    public static function successResponse(
        Response $response,
        $data,
        string $message,
        int $statusCode = 200
    ): Response {
        return $response
            ->setData('success', true)
            ->setData('data', $data)
            ->setData('message', $message)
            ->setStatus($statusCode);
    }

    /**
     * Format error REST response
     *
     * @param Response $response TypeRocket response object
     * @param array $errors Error array from model->getErrors()
     * @param string $message Error message
     * @param int $statusCode HTTP status code (400, 404, etc.)
     * @return Response
     */
    public static function errorResponse(
        Response $response,
        array $errors,
        string $message,
        int $statusCode = 400
    ): Response {
        return $response
            ->setData('success', false)
            ->setData('errors', $errors)
            ->setData('message', $message)
            ->setStatus($statusCode);
    }

    /**
     * Format delete success REST response
     *
     * Specialized response for delete operations (no data returned).
     *
     * @param Response $response TypeRocket response object
     * @param string $message Success message
     * @return Response
     */
    public static function deleteResponse(
        Response $response,
        string $message
    ): Response {
        return $response
            ->setData('success', true)
            ->setData('message', $message)
            ->setStatus(200);
    }
}
