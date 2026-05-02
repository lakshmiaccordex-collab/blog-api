<?php
// controllers/CommentController.php

class CommentController {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    // POST /posts/:postId/comments
    public function store(int $postId): void {
        $user = AuthMiddleware::authenticate();

        $stmt = $this->db->prepare("SELECT id FROM posts WHERE id = ? AND status = 'published'");
        $stmt->execute([$postId]);
        if (!$stmt->fetch()) Response::notFound('Post not found.');

        $data     = json_decode(file_get_contents('php://input'), true);
        $content  = trim($data['content'] ?? '');
        $parentId = $data['parent_id'] ?? null;

        if (!$content) Response::error('Comment content is required.', 422);

        // Validate parent comment belongs to same post
        if ($parentId) {
            $pStmt = $this->db->prepare("SELECT id FROM comments WHERE id = ? AND post_id = ?");
            $pStmt->execute([$parentId, $postId]);
            if (!$pStmt->fetch()) Response::error('Invalid parent comment.', 422);
        }

        $stmt = $this->db->prepare(
            "INSERT INTO comments (post_id, user_id, parent_id, content) VALUES (?, ?, ?, ?)"
        );
        $stmt->execute([$postId, $user['id'], $parentId, $content]);

        Response::created(['id' => $this->db->lastInsertId()], 'Comment added successfully.');
    }

    // PUT /comments/:id
    public function update(int $id): void {
        $user = AuthMiddleware::authenticate();

        $stmt = $this->db->prepare("SELECT * FROM comments WHERE id = ?");
        $stmt->execute([$id]);
        $comment = $stmt->fetch();

        if (!$comment) Response::notFound('Comment not found.');
        if ($comment['user_id'] !== $user['id']) Response::forbidden('You can only edit your own comments.');

        $data    = json_decode(file_get_contents('php://input'), true);
        $content = trim($data['content'] ?? '');

        if (!$content) Response::error('Comment content is required.', 422);

        $this->db->prepare("UPDATE comments SET content = ? WHERE id = ?")->execute([$content, $id]);
        Response::success(null, 'Comment updated.');
    }

    // DELETE /comments/:id
    public function delete(int $id): void {
        $user = AuthMiddleware::authenticate();

        $stmt = $this->db->prepare("SELECT * FROM comments WHERE id = ?");
        $stmt->execute([$id]);
        $comment = $stmt->fetch();

        if (!$comment) Response::notFound('Comment not found.');

        if ($comment['user_id'] !== $user['id'] && $user['role'] !== 'admin') {
            Response::forbidden('You can only delete your own comments.');
        }

        $this->db->prepare("DELETE FROM comments WHERE id = ?")->execute([$id]);
        Response::success(null, 'Comment deleted.');
    }

    // GET /posts/:postId/comments
    public function index(int $postId): void {
        $page   = max(1, (int) ($_GET['page'] ?? 1));
        $limit  = min((int) ($_GET['limit'] ?? 10), 50);
        $offset = ($page - 1) * $limit;

        $countStmt = $this->db->prepare(
            "SELECT COUNT(*) FROM comments WHERE post_id = ? AND parent_id IS NULL AND is_approved = 1"
        );
        $countStmt->execute([$postId]);
        $total = (int) $countStmt->fetchColumn();

        $stmt = $this->db->prepare(
            "SELECT cm.*, u.name as author_name, u.avatar as author_avatar
             FROM comments cm
             LEFT JOIN users u ON u.id = cm.user_id
             WHERE cm.post_id = ? AND cm.parent_id IS NULL AND cm.is_approved = 1
             ORDER BY cm.created_at DESC LIMIT ? OFFSET ?"
        );
        $stmt->execute([$postId, $limit, $offset]);
        $comments = $stmt->fetchAll();

        foreach ($comments as &$c) {
            $rStmt = $this->db->prepare(
                "SELECT cm.*, u.name as author_name FROM comments cm
                 LEFT JOIN users u ON u.id = cm.user_id
                 WHERE cm.parent_id = ? AND cm.is_approved = 1 ORDER BY cm.created_at ASC"
            );
            $rStmt->execute([$c['id']]);
            $c['replies'] = $rStmt->fetchAll();
        }

        Response::paginated($comments, $total, $page, $limit);
    }
}
