<?php
// controllers/CategoryController.php

class CategoryController {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    private function makeSlug(string $text): string {
        return strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $text), '-'));
    }

    // GET /categories
    public function index(): void {
        $stmt = $this->db->query(
            "SELECT c.*, COUNT(p.id) as post_count
             FROM categories c
             LEFT JOIN posts p ON p.category_id = c.id AND p.status = 'published'
             GROUP BY c.id ORDER BY c.name ASC"
        );
        Response::success($stmt->fetchAll());
    }

    // GET /categories/:id
    public function show(int $id): void {
        $stmt = $this->db->prepare("SELECT * FROM categories WHERE id = ?");
        $stmt->execute([$id]);
        $cat = $stmt->fetch();
        if (!$cat) Response::notFound('Category not found.');
        Response::success($cat);
    }

    // POST /categories  [admin only]
    public function store(): void {
        $user = AuthMiddleware::authenticate();
        AuthMiddleware::requireRole($user, 'admin');

        $data = json_decode(file_get_contents('php://input'), true);
        $name = trim($data['name'] ?? '');
        $desc = trim($data['description'] ?? '');

        if (!$name) Response::error('Category name is required.', 422);

        $slug = $this->makeSlug($name);

        // Ensure unique slug
        $stmt = $this->db->prepare("SELECT id FROM categories WHERE slug = ?");
        $stmt->execute([$slug]);
        if ($stmt->fetch()) Response::error('Category already exists.', 409);

        $stmt = $this->db->prepare(
            "INSERT INTO categories (name, slug, description) VALUES (?, ?, ?)"
        );
        $stmt->execute([$name, $slug, $desc]);

        Response::created(['id' => $this->db->lastInsertId(), 'name' => $name, 'slug' => $slug], 'Category created.');
    }

    // PUT /categories/:id  [admin only]
    public function update(int $id): void {
        $user = AuthMiddleware::authenticate();
        AuthMiddleware::requireRole($user, 'admin');

        $stmt = $this->db->prepare("SELECT * FROM categories WHERE id = ?");
        $stmt->execute([$id]);
        if (!$stmt->fetch()) Response::notFound('Category not found.');

        $data = json_decode(file_get_contents('php://input'), true);
        $name = trim($data['name'] ?? '');
        $desc = trim($data['description'] ?? '');

        if (!$name) Response::error('Category name is required.', 422);

        $slug = $this->makeSlug($name);
        $this->db->prepare(
            "UPDATE categories SET name = ?, slug = ?, description = ? WHERE id = ?"
        )->execute([$name, $slug, $desc, $id]);

        Response::success(null, 'Category updated.');
    }

    // DELETE /categories/:id  [admin only]
    public function delete(int $id): void {
        $user = AuthMiddleware::authenticate();
        AuthMiddleware::requireRole($user, 'admin');

        $stmt = $this->db->prepare("SELECT id FROM categories WHERE id = ?");
        $stmt->execute([$id]);
        if (!$stmt->fetch()) Response::notFound('Category not found.');

        $this->db->prepare("DELETE FROM categories WHERE id = ?")->execute([$id]);
        Response::success(null, 'Category deleted.');
    }
}
