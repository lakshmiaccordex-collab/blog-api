# 📝 Blog Platform REST API

A production-ready **RESTful API** for a Blog Platform built with **PHP** and **MySQL** — featuring JWT authentication, full CRUD operations, full-text search, pagination, file uploads, and role-based access control.

---

## 🚀 Tech Stack

| Layer | Technology |
|---|---|
| Language | PHP 8.1+ |
| Database | MySQL 8.0+ |
| Auth | JWT (JSON Web Tokens) |
| File Handling | Native PHP file upload |
| Server | Apache with mod_rewrite |

---

## ✨ Features

- 🔐 **JWT Authentication** — Register, Login, Logout with token blacklisting
- 👥 **Role-Based Access Control** — Admin, Author, Reader roles
- 📝 **Posts** — Full CRUD with draft/published/archived status
- 🗂️ **Categories** — Organize posts by category with post counts
- 💬 **Comments** — Nested comments (replies) with approval system
- 🔍 **Full-Text Search** — MySQL FULLTEXT search across title, content, excerpt
- 📄 **Pagination** — All list endpoints support page/limit params
- 🖼️ **File Upload** — Cover images for posts, avatars for users
- 🔎 **Filtering & Sorting** — Filter by category, status; sort by views or date

---

## 📁 Project Structure

```
blog-api/
├── config/
│   └── database.php          # DB connection (PDO singleton)
├── controllers/
│   ├── AuthController.php    # Register, login, logout, profile
│   ├── CategoryController.php
│   ├── PostController.php    # Posts with search, pagination, upload
│   └── CommentController.php # Nested comments
├── middleware/
│   └── AuthMiddleware.php    # JWT verification + role check
├── models/                   # (extend here for model classes)
├── utils/
│   ├── JWT.php               # JWT generate & verify
│   ├── Response.php          # Standardized JSON responses
│   └── FileUpload.php        # Image upload handler
├── uploads/                  # Uploaded images (gitignored)
├── database.sql              # Schema + seed data
├── .env.example              # Environment variable template
├── .htaccess                 # Apache rewrite rules
└── index.php                 # Main router
```

---

## ⚙️ Setup & Installation

### Prerequisites
- PHP >= 8.1
- MySQL >= 8.0
- Apache with `mod_rewrite` enabled

### Steps

```bash
# 1. Clone the repository
git clone https://github.com/yourusername/blog-api.git
cd blog-api

# 2. Create environment file
cp .env.example .env

# 3. Update .env with your database credentials
nano .env

# 4. Import the database schema
mysql -u root -p < database.sql

# 5. Set upload folder permissions
chmod 755 uploads/

# 6. Start Apache and access the API
# Place project in htdocs/www folder or configure virtual host
```

---

## 🔑 Environment Variables

```env
DB_HOST=localhost
DB_NAME=blog_api
DB_USER=root
DB_PASS=your_password

JWT_SECRET=your_secret_key_here
JWT_EXPIRY=3600

UPLOAD_DIR=uploads/
MAX_FILE_SIZE=2097152
ALLOWED_TYPES=image/jpeg,image/png,image/webp
```

---

## 📡 API Endpoints

### Auth
| Method | Endpoint | Description | Auth |
|---|---|---|---|
| POST | `/auth/register` | Register new user | ❌ |
| POST | `/auth/login` | Login & get token | ❌ |
| POST | `/auth/logout` | Logout (blacklist token) | ✅ |
| GET | `/auth/me` | Get logged-in user profile | ✅ |
| PUT | `/auth/profile` | Update profile / avatar | ✅ |

### Categories
| Method | Endpoint | Description | Auth |
|---|---|---|---|
| GET | `/categories` | List all categories | ❌ |
| GET | `/categories/:id` | Get single category | ❌ |
| POST | `/categories` | Create category | Admin |
| PUT | `/categories/:id` | Update category | Admin |
| DELETE | `/categories/:id` | Delete category | Admin |

### Posts
| Method | Endpoint | Description | Auth |
|---|---|---|---|
| GET | `/posts` | List posts (search, filter, paginate) | ❌ |
| GET | `/posts/my` | My posts | ✅ |
| GET | `/posts/:slug` | Single post with comments | ❌ |
| POST | `/posts` | Create post | Author/Admin |
| PUT | `/posts/:id` | Update post | Author/Admin |
| DELETE | `/posts/:id` | Delete post | Author/Admin |

### Comments
| Method | Endpoint | Description | Auth |
|---|---|---|---|
| GET | `/posts/:id/comments` | List comments for a post | ❌ |
| POST | `/posts/:id/comments` | Add comment / reply | ✅ |
| PUT | `/comments/:id` | Edit comment | ✅ |
| DELETE | `/comments/:id` | Delete comment | ✅ |

---

## 📋 Sample API Requests

### Register
```bash
curl -X POST http://localhost/blog-api/auth/register \
  -H "Content-Type: application/json" \
  -d '{"name":"Lakshmi","email":"lakshmi@example.com","password":"Pass@1234","role":"author"}'
```

### Login
```bash
curl -X POST http://localhost/blog-api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"lakshmi@example.com","password":"Pass@1234"}'
```

### Create Post (with cover image)
```bash
curl -X POST http://localhost/blog-api/posts \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -F "title=My First Post" \
  -F "content=Hello world content here..." \
  -F "status=published" \
  -F "category_id=1" \
  -F "cover_image=@/path/to/image.jpg"
```

### Search Posts
```bash
curl "http://localhost/blog-api/posts?search=php&category=technology&page=1&limit=5&sort=views&order=DESC"
```

### Add Comment (with reply)
```bash
curl -X POST http://localhost/blog-api/posts/1/comments \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"content":"Great post!","parent_id":null}'
```

---

## 📦 Sample Response Format

```json
{
  "success": true,
  "message": "Success",
  "data": { ... },
  "pagination": {
    "total": 50,
    "per_page": 10,
    "current_page": 1,
    "total_pages": 5,
    "has_next": true,
    "has_prev": false
  }
}
```

---

## 🔒 Security Features

- Passwords hashed with **bcrypt**
- JWT token **blacklisting** on logout
- **Role-based** endpoint protection
- SQL Injection prevention via **PDO prepared statements**
- File upload **MIME type validation**
- **CORS** headers configured

---

## 👩‍💻 Author

**R.S. Lakshmi** — Senior Full Stack Developer  
📧 lakshmiaccordex@gmail.com  
🔗 [LinkedIn](https://www.linkedin.com/in/lakshmi-r-s-48367238b/)

---

## 📄 License

MIT License — feel free to use this project for learning and portfolio purposes.
