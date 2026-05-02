<?php
// index.php — Main entry point & router

declare(strict_types=1);

// ── Headers ──────────────────────────────────────────────
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ── Autoload ──────────────────────────────────────────────
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/utils/JWT.php';
require_once __DIR__ . '/utils/Response.php';
require_once __DIR__ . '/utils/FileUpload.php';
require_once __DIR__ . '/middleware/AuthMiddleware.php';
require_once __DIR__ . '/controllers/AuthController.php';
require_once __DIR__ . '/controllers/CategoryController.php';
require_once __DIR__ . '/controllers/PostController.php';
require_once __DIR__ . '/controllers/CommentController.php';

// ── Router ────────────────────────────────────────────────
$method = $_SERVER['REQUEST_METHOD'];
$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri    = rtrim(preg_replace('#/blog-api#', '', $uri), '/') ?: '/';
$parts  = explode('/', trim($uri, '/'));

$auth     = new AuthController();
$catCtrl  = new CategoryController();
$postCtrl = new PostController();
$cmtCtrl  = new CommentController();

// ── AUTH ROUTES ───────────────────────────────────────────
// POST   /auth/register
// POST   /auth/login
// POST   /auth/logout
// GET    /auth/me
// PUT    /auth/profile

if ($parts[0] === 'auth') {
    $action = $parts[1] ?? '';
    match (true) {
        $method === 'POST' && $action === 'register' => $auth->register(),
        $method === 'POST' && $action === 'login'    => $auth->login(),
        $method === 'POST' && $action === 'logout'   => $auth->logout(),
        $method === 'GET'  && $action === 'me'       => $auth->me(),
        $method === 'PUT'  && $action === 'profile'  => $auth->updateProfile(),
        default => Response::notFound('Auth route not found.')
    };
    exit;
}

// ── CATEGORY ROUTES ───────────────────────────────────────
// GET    /categories
// GET    /categories/:id
// POST   /categories
// PUT    /categories/:id
// DELETE /categories/:id

if ($parts[0] === 'categories') {
    $id = isset($parts[1]) ? (int) $parts[1] : null;
    match (true) {
        $method === 'GET'    && !$id => $catCtrl->index(),
        $method === 'GET'    && $id  => $catCtrl->show($id),
        $method === 'POST'           => $catCtrl->store(),
        $method === 'PUT'    && $id  => $catCtrl->update($id),
        $method === 'DELETE' && $id  => $catCtrl->delete($id),
        default => Response::notFound('Category route not found.')
    };
    exit;
}

// ── POST ROUTES ───────────────────────────────────────────
// GET    /posts               — list (search, filter, paginate)
// GET    /posts/my            — my posts
// GET    /posts/:slug         — single post with comments
// POST   /posts               — create
// PUT    /posts/:id           — update
// DELETE /posts/:id           — delete
// GET    /posts/:id/comments  — list comments
// POST   /posts/:id/comments  — add comment

if ($parts[0] === 'posts') {
    $segment = $parts[1] ?? null;
    $action  = $parts[2] ?? null;

    // /posts/:id/comments
    if ($segment && $action === 'comments') {
        $postId = (int) $segment;
        match ($method) {
            'GET'  => $cmtCtrl->index($postId),
            'POST' => $cmtCtrl->store($postId),
            default => Response::notFound('Route not found.')
        };
        exit;
    }

    // /posts/my
    if ($method === 'GET' && $segment === 'my') {
        $postCtrl->myPosts();
        exit;
    }

    // /posts  &  /posts/:slug  &  /posts/:id
    $isNumeric = $segment && is_numeric($segment);
    match (true) {
        $method === 'GET'    && !$segment        => $postCtrl->index(),
        $method === 'GET'    && $segment         => $postCtrl->show($segment),
        $method === 'POST'   && !$segment        => $postCtrl->store(),
        $method === 'PUT'    && $isNumeric       => $postCtrl->update((int) $segment),
        $method === 'DELETE' && $isNumeric       => $postCtrl->delete((int) $segment),
        default => Response::notFound('Post route not found.')
    };
    exit;
}

// ── COMMENT ROUTES ────────────────────────────────────────
// PUT    /comments/:id
// DELETE /comments/:id

if ($parts[0] === 'comments') {
    $id = isset($parts[1]) ? (int) $parts[1] : null;
    match (true) {
        $method === 'PUT'    && $id => $cmtCtrl->update($id),
        $method === 'DELETE' && $id => $cmtCtrl->delete($id),
        default => Response::notFound('Comment route not found.')
    };
    exit;
}

// ── HEALTH CHECK ──────────────────────────────────────────
if ($uri === '/' || $uri === '') {
    Response::success([
        'name'    => 'Blog Platform REST API',
        'version' => '1.0.0',
        'status'  => 'running',
    ], 'Welcome to Blog API');
    exit;
}

Response::notFound('Route not found.');
