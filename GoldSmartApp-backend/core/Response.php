<?php declare(strict_types=1);

/**
 * Response Helper Class
 */
class Response
{
    /**
     * Send JSON response (optimized)
     */
    public static function json($data, int $statusCode = 200): void
    {
        // Clean all output buffers
        while (ob_get_level()) {
            ob_end_clean();
        }

        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');

        // Optional cache control for specific responses
        if (isset($data['_cache_ttl'])) {
            $ttl = (int) $data['_cache_ttl'];
            header("Cache-Control: public, max-age={$ttl}");
            unset($data['_cache_ttl']);
        } else {
            // Prevent caching by default for dynamic API responses
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');
        }

        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * Success response
     */
    public static function success($data = null, string $message = 'Success', int $statusCode = 200, int $cacheTtl = 0)
    {
        $response = [
            'success' => true,
            'message' => $message
        ];

        if ($data !== null) {
            $response['data'] = $data;
        }

        if ($cacheTtl > 0) {
            $response['_cache_ttl'] = $cacheTtl;
        }

        self::json($response, $statusCode);
    }

    /**
     * Error response
     */
    public static function error(string $message = 'Error', int $statusCode = 400, $errors = null)
    {
        $response = [
            'success' => false,
            'message' => $message
        ];

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        self::json($response, $statusCode);
    }

    /**
     * Validation error response
     */
    public static function validationError($errors)
    {
        self::json([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $errors
        ], 422);
    }

    /**
     * Unauthorized response
     */
    public static function unauthorized(string $message = 'Unauthorized')
    {
        self::json([
            'success' => false,
            'message' => $message
        ], 401);
    }

    /**
     * Forbidden response
     */
    public static function forbidden(string $message = 'Forbidden')
    {
        self::json([
            'success' => false,
            'message' => $message
        ], 403);
    }

    /**
     * Not found response
     */
    public static function notFound(string $message = 'Not found')
    {
        self::json([
            'success' => false,
            'message' => $message
        ], 404);
    }
}
