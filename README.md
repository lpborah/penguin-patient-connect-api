# Slim 4 MySQL CRUD API (with CORS)

This sample project shows a minimal Slim 4-based JSON API with:
- CORS middleware (PSR-15)
- MySQL connection via PDO (dotenv config)
- CRUD endpoints for a `users` table

## Setup

1. Install Composer dependencies:
   ```bash
   composer install
   ```
2. Copy `.env.example` to `.env` and modify DB credentials:
   ```bash
   cp .env.example .env
   ```
3. Create the `users` table in your MySQL database:
   ```sql
   CREATE TABLE users (
     id INT AUTO_INCREMENT PRIMARY KEY,
     name VARCHAR(100),
     email VARCHAR(100),
     created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
   );
   ```
4. Run the built-in PHP server (from project root):
   ```bash
   php -S localhost:8080 -t public
   ```
5. Test endpoints:
   - GET all users: `curl http://localhost:8080/users`
   - POST create: `curl -X POST http://localhost:8080/users -H "Content-Type: application/json" -d '{"name":"John","email":"john@example.com"}'`
   - GET single: `curl http://localhost:8080/users/1`
   - PUT update: `curl -X PUT http://localhost:8080/users/1 -H "Content-Type: application/json" -d '{"name":"John Updated","email":"new@example.com"}'`
   - DELETE: `curl -X DELETE http://localhost:8080/users/1`

## Notes
- This project **does not** include the `vendor/` directory. Run `composer install` to fetch dependencies.
- For production, do not use `php -S`. Instead use a proper webserver (nginx/apache + php-fpm), secure your `.env` and restrict CORS origins.
