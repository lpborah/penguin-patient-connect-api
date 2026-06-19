<?php
declare(strict_types=1);

namespace App;

use Psr\Http\Message\ResponseInterface as Response;

class ApiResponse
{
    /**
     * Send a standardized API response
     * 
     * @param Response $response The response object
     * @param mixed $data The data to return (object, array, or null)
     * @param string $message The message describing the response
     * @param string $status The status: "success" or "error"
     * @param int $httpCode The HTTP status code (default: 200)
     * @return Response
     */
    public static function send(
        Response $response,
        $data = null,
        string $message = '',
        string $status = 'success',
        int $httpCode = 200
    ): Response {
        $payload = [
            'data' => $data,
            'message' => $message,
            'status' => $status
        ];

        $response->getBody()->write(json_encode($payload));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($httpCode);
    }

    /**
     * Send a success response
     */
    public static function success(
        Response $response,
        $data = null,
        string $message = 'Success',
        int $httpCode = 200
    ): Response {
        return self::send($response, $data, $message, 'success', $httpCode);
    }

    /**
     * Send an error response
     */
    public static function error(
        Response $response,
        string $message = 'Error',
        $data = null,
        int $httpCode = 400
    ): Response {
        return self::send($response, $data, $message, 'error', $httpCode);
    }
}
