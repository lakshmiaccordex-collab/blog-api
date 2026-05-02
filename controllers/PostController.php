<?php
// controllers/PostController.php

class PostController {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    private function makeSlug(string $text): string {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $text), '-'));
        return $slug . '-' . time();
    }

    // GET /posts  (search + filter + pagination)
    public function index(): void {
        $page     = max(1, (int) ($_GET['page'] ?? 1));
        $limit    = min((int) ($_GET['limit'] ?? 10), 50);
        $offset   = ($page - 1) * $limit;
        $search   = trim($_GET['search'] ?? '');
        $category = $_GET['category'] ?? '';
        $status   = $_GET['status'] ?? 'published';
        $sortBy   = in_array($_GET['sort'] ?? '', ['views', 'created_at']) ? $_GET['sort'] : 'created_at';
        $order    = strtoupper($_GET['order'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';

        $where  = ["p.status = ?"];
        $params = [$status];

        if ($search) {
            $where[]  = "MATCH(p.title, p.content, p.excerpt) AGAINST(? IN BOOLEAN MODE)";
            $params[] = $search . '*';
        }

        if ($category) {
            $where[]  = "c.slug = ?";
            $params[] = $category;
        }

        $whereSQL = implode(' AND ', $where);

        // Total count
        $countSQL = "SELECT COUNT(*) FROM posts p
                     LEFT JOIN categories c ON c.id = p.category_id
                     WHERE $whereSQL";
        $countStmt = $this->db->prepare($countSQL);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        // Fetch posts
        $sql = "SELECT p.id, p.title, p.slug, p.excerpt, p.cover_image, p.status, p.views,
                       p.created_at, p.updated_at,
                       u.id as author_id, u.name as author_name, u.avatar as author_avatar,
                       c.id as category_id, c.name as category_name, c.slug as category_slug,
                       (SELECT COUNT(*) FROM comments cm WHERE cm.post_id = p.id AND cm.is_approved = 1) as comment_count
                FROM posts p
                LEFT JOIN users u ON u.id = p.user_id
                LEFT JOIN categories c ON c.id = p.category_id
                WHERE $whereSQL
                ORDER BY p.$sortBy $order
                LIMIT ? OFFSET ?";

        $params[] = $limit;
        $params[] = $offset;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $posts = $stmt->fetchAll();

        Response::paginated($posts, $total, $page, $limit);
    }

    // GET /posts/:slug
    public function show(string $slug): void {
        $stmt = $this->db->prepare(
            "SELECT p.*, u.name as author_name, u.avatar as author_avatar,
                    c.name as category_name, c.slug as category_slug
             FROM posts p
             LEFT JOIN users u ON u.id = p.user_id
             LEFT JOIN categories c ON c.id = p.category_id
             WHERE p.slug = ?"
        );
        $stmt->execute([$slug]);
        $post = $stmt->fetch();

        if (!$post) Response::notFound('Post not found.');

        // Increment views
        $this->db->prepare("UPDATE posts SET views = views + 1 WHERE id = ?")->execute([$post['id']]);
        $post['views']++;

        // Fetch top-level comments with replies
        $cStmt = $this->db->prepare(
            "SELECT cm.*, u.name as author_name, u.avatar as author_avatar
             FROM comments cm
             LEFT JOIN users u ON u.id = cm.user_id
             WHERE cm.post_id = ? AND cm.parent_id IS NULL AND cm.is_approved = 1
             ORDER BY cm.created_at ASC"
        );
        $cStmt->execute([$post['id']]);
        $comments = $cStmt->fetchAll();

        foreach ($comments as &$comment) {
            $rStmt = $this->db->prepare(
                "SELECT cm.*, u.name as author_name FROM comments cm
                 LEFT JOIN users u ON u.id = cm.user_id
                 WHERE cm.parent_id = ? AND cm.is_approved = 1"
            );
            $rStmt->execute([$comment['id']]);
            $comment['replies'] = $rStmt->fetchAll();
        }

        $post['comments'] = $comments;
        Response::success($post);
    }

    // POST /posts  [author, admin]
    public function store(): void {
        $user = AuthMiddleware::authenticate();
        AuthMiddleware::requireRole($user, 'admin', 'author');

        $data    = json_decode(file_get_contents('php://input'), true);
        $title   = trim($data['title'] ?? '');
        $content = trim($data['content'] ?? '');
        $excerpt = trim($data['excerpt'] ?? '');
        $catId   = $data['category_id'] ?? null;
        $status  = in_array($data['status'] ?? '', ['draft', 'published']) ? $data['status'] : 'draft';

        $errors = [];
        if (!$title)   $errors['title']   = 'Title is required.';
        if (!$content) $errors['content'] = 'Content is required.';
        if ($errors)   Response::error('Validation failed.', 422, $errors);

        $slug        = $this->makeSlug($title);
        $coverImage  = null;

        // Handle cover image upload
        if (isset($_FILES['cover_image'])) {
            $uploader = new FileUpload();
            try {
                $coverImage = $uploader->upload($_FILES['cover_image'], 'covers');
            } catch (RuntimeException $e) {
                Response::error($e->getMessage(), 422);
            }
        }

        $stmt = $this->db->prepare(
            "INSERT INTO posts (title, slug, content, excerpt, cover_image, category_id, user_id, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([$title, $slug, $content, $excerpt, $coverImage, $catId, $user['id'], $status]);

        Response::created(['id' => $this->db->lastInsertId(), 'slug' => $slug], 'Post created successfully.');
    }

    // PUT /posts/:id
    public function update(int $id): void {
        $user = AuthMiddleware::authenticate();

        $stmt = $this->db->prepare("SELECT * FROM posts WHERE id = ?");
        $stmt->execute([$id]);
        $post = $stmt->fetch();

        if (!$post) Response::notFound('Post not found.');

        // Only author or admin can edit
        if ($post['user_id'] !== $user['id'] && $user['role'] !== 'admin') {
            Response::forbidden('You can only edit your own posts.');
        }

        $data    = json_decode(file_get_contents('php://input'), true);
        $title   = trim($data['title']   ?? $post['title']);
        $content = trim($data['content'] ?? $post['content']);
        $excerpt = trim($data['excerpt'] ?? $post['excerpt']);
        $catId   = $data['category_id']  ?? $post['category_id'];
        $status  = in_array($data['status'] ?? '', ['draft', 'published', 'archived'])
                   ? $data['status'] : $post['status'];

        $coverImage = $post['cover_image'];
        if (isset($_FILES['cover_image'])) {
            $uploader = new FileUpload();
            try {
                if ($coverImage) $uploader->delete($coverImage);
                $coverImage = $uploader->upload($_FILES['cover_image'], 'covers');
            } catch (RuntimeException $e) {
                Response::error($e->getMessage(), 422);
            }
        }

        $this->db->prepare(
            "UPDATE posts SET title=?, content=?, excerpt=?, cover_image=?, category_id=?, status=? WHERE id=?"
        )->execute([$title, $content, $excerpt, $coverImage, $catId, $status, $id]);

        Response::success(null, 'Post updated successfully.');
    }

    // DELETE /posts/:id
    public function delete(int $id): void {
        $user = AuthMiddleware::authenticate();

        $stmt = $this->db->prepare("SELECT * FROM posts WHERE id = ?");
        $stmt->execute([$id]);
        $post = $stmt->fetch();

        if (!$post) Response::notFound('Post not found.');

        if ($post['user_id'] !== $user['id'] && $user['role'] !== 'admin') {
            Response::forbidden('You can only delete your own posts.');
        }

        // Delete cover image
        if ($post['cover_image']) {
            (new FileUpload())->delete($post['cover_image']);
        }

        $this->db->prepare("DELETE FROM posts WHERE id = ?")->execute([$id]);
        Response::success(null, 'Post deleted successfully.');
    }

    // GET /posts/my  — get logged-in user's posts
    public function myPosts(): void {
        $user   = AuthMiddleware::authenticate();
        $page   = max(1, (int) ($_GET['page'] ?? 1));
        $limit  = min((int) ($_GET['limit'] ?? 10), 50);
        $offset = ($page - 1) * $limit;

        $countStmt = $this->db->prepare("SELECT COUNT(*) FROM posts WHERE user_id = ?");
        $countStmt->execute([$user['id']]);
        $total = (int) $countStmt->fetchColumn();

        $stmt = $this->db->prepare(
            "SELECT p.id, p.title, p.slug, p.status, p.views, p.created_at,
                    c.name as category_name
             FROM posts p
             LEFT JOIN categories c ON c.id = p.category_id
             WHERE p.user_id = ?
             ORDER BY p.created_at DESC LIMIT ? OFFSET ?"
        );
        $stmt->execute([$user['id'], $limit, $offset]);

        Response::paginated($stmt->fetchAll(), $total, $page, $limit);
    }
}
