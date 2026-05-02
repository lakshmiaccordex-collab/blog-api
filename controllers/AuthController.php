<?php
// controllers/AuthController.php

class AuthController {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    // POST /auth/register
    public function register(): void {
        $data = json_decode(file_get_contents('php://input'), true);

        $name     = trim($data['name'] ?? '');
        $email    = trim($data['email'] ?? '');
        $password = $data['password'] ?? '';
        $role     = $data['role'] ?? 'reader';

        // Validate
        $errors = [];
        if (empty($name))                          $errors['name']     = 'Name is required.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Valid email is required.';
        if (strlen($password) < 8)                 $errors['password'] = 'Password must be at least 8 characters.';
        if (!in_array($role, ['admin', 'author', 'reader'])) $role = 'reader';

        if ($errors) Response::error('Validation failed.', 422, $errors);

        // Check duplicate email
        $stmt = $this->db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) Response::error('Email is already registered.', 409);

        // Save user
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $this->db->prepare(
            "INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)"
        );
        $stmt->execute([$name, $email, $hash, $role]);
        $userId = $this->db->lastInsertId();

        $token = JWT::generate(['id' => $userId, 'email' => $email, 'role' => $role]);

        Response::created([
            'user'  => ['id' => $userId, 'name' => $name, 'email' => $email, 'role' => $role],
            'token' => $token,
        ], 'Registration successful.');
    }

    // POST /auth/login
    public function login(): void {
        $data     = json_decode(file_get_contents('php://input'), true);
        $email    = trim($data['email'] ?? '');
        $password = $data['password'] ?? '';

        if (!$email || !$password) Response::error('Email and password are required.', 422);

        $stmt = $this->db->prepare("SELECT * FROM users WHERE email = ? AND is_active = 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password'])) {
            Response::unauthorized('Invalid email or password.');
        }

        $token = JWT::generate(['id' => $user['id'], 'email' => $user['email'], 'role' => $user['role']]);

        Response::success([
            'user'  => [
                'id'     => $user['id'],
                'name'   => $user['name'],
                'email'  => $user['email'],
                'role'   => $user['role'],
                'avatar' => $user['avatar'],
            ],
            'token' => $token,
        ], 'Login successful.');
    }

    // POST /auth/logout
    public function logout(): void {
        $user  = AuthMiddleware::authenticate();
        $token = $user['_token'];
        $exp   = date('Y-m-d H:i:s', $user['exp']);

        $stmt = $this->db->prepare("INSERT INTO token_blacklist (token, expires_at) VALUES (?, ?)");
        $stmt->execute([$token, $exp]);

        Response::success(null, 'Logged out successfully.');
    }

    // GET /auth/me
    public function me(): void {
        $user = AuthMiddleware::authenticate();

        $stmt = $this->db->prepare(
            "SELECT id, name, email, role, avatar, created_at FROM users WHERE id = ?"
        );
        $stmt->execute([$user['id']]);
        $profile = $stmt->fetch();

        if (!$profile) Response::notFound('User not found.');

        Response::success($profile);
    }

    // PUT /auth/profile
    public function updateProfile(): void {
        $user = AuthMiddleware::authenticate();
        $data = json_decode(file_get_contents('php://input'), true);

        $name   = trim($data['name'] ?? '');
        $fields = [];
        $params = [];

        if ($name) { $fields[] = 'name = ?'; $params[] = $name; }

        if (isset($data['password'])) {
            if (strlen($data['password']) < 8)
                Response::error('Password must be at least 8 characters.', 422);
            $fields[] = 'password = ?';
            $params[] = password_hash($data['password'], PASSWORD_BCRYPT);
        }

        // Handle avatar upload
        if (isset($_FILES['avatar'])) {
            $uploader = new FileUpload();
            try {
                $path     = $uploader->upload($_FILES['avatar'], 'avatars');
                $fields[] = 'avatar = ?';
                $params[] = $path;
            } catch (RuntimeException $e) {
                Response::error($e->getMessage(), 422);
            }
        }

        if (empty($fields)) Response::error('Nothing to update.', 422);

        $params[] = $user['id'];
        $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?";
        $this->db->prepare($sql)->execute($params);

        Response::success(null, 'Profile updated successfully.');
    }
}
