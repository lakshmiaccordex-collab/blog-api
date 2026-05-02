<?php
// utils/Response.php

class Response {
    public static function success(mixed $data = null, string $message = 'Success', int $code = 200): void {
        http_response_code($code);
        echo json_encode([
            'success' => true,
            'message' => $message,
            'data'    => $data,
        ]);
        exit;
    }

    public static function paginated(array $data, int $total, int $page, int $limit, string $message = 'Success'): void {
        http_response_code(200);
        echo json_encode([
            'success'    => true,
            'message'    => $message,
            'data'       => $data,
            'pagination' => [
                'total'        => $total,
                'per_page'     => $limit,
                'current_page' => $page,
                'total_pages'  => (int) ceil($total / $limit),
                'has_next'     => ($page * $limit) < $total,
                'has_prev'     => $page > 1,
            ],
        ]);
        exit;
    }

    public static function error(string $message = 'Error', int $code = 400, mixed $errors = null): void {
        http_response_code($code);
        $body = ['success' => false, 'message' => $message];
        if ($errors !== null) $body['errors'] = $errors;
        echo json_encode($body);
        exit;
    }

    public static function created(mixed $data = null, string $message = 'Created successfully'): void {
        self::success($data, $message, 201);
    }

    public static function notFound(string $message = 'Resource not found'): void {
        self::error($message, 404);
    }

    public static function unauthorized(string $message = 'Unauthorized'): void {
        self::error($message, 401);
    }

    public static function forbidden(string $message = 'Forbidden'): void {
        self::error($message, 403);
    }

    public static function serverError(string $message = 'Internal server error'): void {
        self::error($message, 500);
    }
}
