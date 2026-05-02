<?php
// middleware/AuthMiddleware.php

class AuthMiddleware {
    public static function authenticate(): array {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';

        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            Response::unauthorized('Access token is required.');
        }

        $token = substr($authHeader, 7);
        $payload = JWT::verify($token);

        if (!$payload) {
            Response::unauthorized('Invalid or expired token.');
        }

        // Check if token is blacklisted (logged out)
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT id FROM token_blacklist WHERE token = ?");
        $stmt->execute([$token]);
        if ($stmt->fetch()) {
            Response::unauthorized('Token has been revoked. Please login again.');
        }

        // Attach token to payload for logout use
        $payload['_token'] = $token;
        return $payload;
    }

    public static function requireRole(array $user, string ...$roles): void {
        if (!in_array($user['role'], $roles)) {
            Response::forbidden('You do not have permission to perform this action.');
        }
    }
}
