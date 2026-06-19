<?php
declare(strict_types=1);

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Database;
use App\ApiResponse;
use App\Validator;
use App\AppLogger;

class ContactController
{
    /* ==============================
       Get All Contacts
    ============================== */
    public function getUsers(Request $request, Response $response): Response
    {
        $queryParams = $request->getQueryParams();
        $user = $queryParams['user'] ?? 'NA';
        AppLogger::info($user, 'Fetching all users');
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->query(
                'SELECT user_id, user_name, email, phone_number, role_id, status, created_at 
                 FROM users 
                 ORDER BY user_id DESC'
            );
            $data = $stmt->fetchAll();

            AppLogger::info($user, 'Users retrieved successfully', ['count' => count($data)]);
            return ApiResponse::success($response, $data, 'Users retrieved successfully');
        } catch (\Exception $e) {
            AppLogger::error('Database error fetching users', ['error' => $e->getMessage()]);
            return ApiResponse::error($response, 'Failed to fetch users', null, 500);
        }
    }

    /* ==============================
       Create User
    ============================== */
    public function saveUser(Request $request, Response $response): Response
    {
        $body = (array) $request->getParsedBody();
        $sanitized = Validator::sanitizeString($body);
        $user = $sanitized['user'];

        AppLogger::info($user, 'New user creation request', ['user_name' => $body['user_name'] ?? null]);

        // Required fields
        $required = ['user_name', 'email', 'phone_number', 'password', 'role_id'];
        $missing = array_filter($required, function ($key) use ($sanitized) {
            return empty($sanitized[$key]);
        });
        if (!empty($missing)) {
            AppLogger::warning($user, 'User creation validation failed', ['missing' => $missing]);
            return ApiResponse::error(
                $response,
                'Missing required fields: ' . implode(', ', $missing),
                null,
                400
            );
        }

        try {
            $pdo = Database::getConnection();

            // Check duplicate username/email
            $stmt = $pdo->prepare('SELECT user_id FROM users WHERE user_name = ? OR email = ? LIMIT 1');
            $stmt->execute([
                $sanitized['user_name'],
                $sanitized['email']
            ]);

            if ($stmt->rowCount() > 0) {
                AppLogger::warning($user, 'User already exists', [
                    'user_name' => $sanitized['user_name']
                ]);

                return ApiResponse::error(
                    $response,
                    'User already exists',
                    null,
                    409
                );
            }

            $passwordHash = password_hash($sanitized['password'], PASSWORD_BCRYPT);

            $stmt = $pdo->prepare(
                'INSERT INTO users 
                (user_name, email, phone_number, password, role_id, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())'
            );

            $stmt->execute([
                $sanitized['user_name'],
                $sanitized['email'],
                $sanitized['phone_number'],
                $passwordHash,
                $sanitized['role_id'],
                1
            ]);

            $user_id = (int) $pdo->lastInsertId();

            AppLogger::info($user, 'User created successfully', [
                'user_id' => $user_id
            ]);
            return ApiResponse::success(
                $response,
                ['user_id' => $user_id],
                'User created successfully'
            );
        } catch (\Exception $e) {
            AppLogger::error($user, 'Database error creating user', ['error' => $e->getMessage()]);
            return ApiResponse::error($response, 'Failed to create user', null, 500);
        }
    }

    /* ==============================
       Update User
    ============================== */
    public function updateUser(Request $request, Response $response): Response
    {
        $body = (array) $request->getParsedBody();
        $sanitized = Validator::sanitizeString($body);
        $user = $sanitized['user'];

        AppLogger::info($user, 'User update requested', [
            'user_id' => $sanitized['user_id'] ?? null
        ]);

        // Validate required fields
        $required = ['user_id', 'user_name', 'email', 'role_id'];
        $missing = array_filter($required, function ($key) use ($sanitized) {
            return empty($sanitized[$key]);
        });

        if (!empty($missing)) {
            return ApiResponse::error(
                $response,
                'Missing required fields: ' . implode(', ', $missing),
                null,
                400
            );
        }

        $user_id = Validator::sanitizeInt($body['user_id']);
        if ($user_id === null) {
            return ApiResponse::error($response, 'Invalid user ID', null, 400);
        }

        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare(
                'UPDATE users SET
                    user_name = ?,
                    email = ?,
                    phone_number = ?,
                    status = ?,
                    role_id = ?
                 WHERE user_id = ?'
            );

            $stmt->execute([
                $sanitized['user_name'],
                $sanitized['email'],
                $sanitized['phone_number'],
                $sanitized['status'],
                $sanitized['role_id'],
                $user_id
            ]);

            $affected = $stmt->rowCount();

            AppLogger::info($user, 'User updated successfully', ['username' => $sanitized['user_name'], 'status' => $sanitized['status'], 'affected' => $affected]);


            return ApiResponse::success(
                $response,
                ['affected' => $stmt->rowCount()],
                'User updated successfully'
            );
        } catch (\Exception $e) {
            AppLogger::error($user, 'Database error updating user', [
                'error' => $e->getMessage()
            ]);
            return ApiResponse::error($response, 'Failed to update user', null, 500);
        }
    }

    /* ==============================
       Soft Delete User
    ============================== */
    public function deleteUser(Request $request, Response $response): Response
    {
        $body = (array) $request->getParsedBody();
        $sanitized = Validator::sanitizeString($body);
        $user = $sanitized['user'];

        AppLogger::info($user, 'User deletion requested', ['user_id' => $sanitized['user_id'] ?? null]);

        // Validate required fields
        if (empty($sanitized['user_id'])) {
            AppLogger::warning($user, 'User deletion validation failed', ['missing' => ['user_id']]);
            return ApiResponse::error(
                $response,
                'Missing required field: user_id',
                null,
                400
            );
        }

        $user_id = Validator::sanitizeInt($sanitized['user_id']);
        if ($user_id === null) {
            AppLogger::error($user, 'Invalid user ID for deletion', ['user_id' => $sanitized['user_id']]);
            return ApiResponse::error($response, 'Invalid user ID', null, 400);
        }

        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("UPDATE users SET status = 'inactive' WHERE user_id = ?");
            $stmt->execute([$user_id]);

            $affected = $stmt->rowCount();
            AppLogger::info($user, 'User soft deleted', ['user_id' => $user_id]);
            return ApiResponse::success(
                $response,
                ['affected' => $affected],
                'User deleted successfully'
            );
        } catch (\Exception $e) {
            AppLogger::error($user, 'Database error deleting user', [
                'error' => $e->getMessage()
            ]);
            return ApiResponse::error($response, 'Failed to delete user', null, 500);
        }
    }

    public function restoreDeletedUser(Request $request, Response $response): Response
    {
        $body = (array) $request->getParsedBody();
        $sanitized = Validator::sanitizeString($body);
        $user = $sanitized['user'];

        AppLogger::info($user, 'User deletion requested', ['user_id' => $sanitized['user_id'] ?? null]);

        // Validate required fields
        if (empty($sanitized['user_id'])) {
            AppLogger::warning($user, 'User deletion validation failed', ['missing' => ['user_id']]);
            return ApiResponse::error(
                $response,
                'Missing required field: user_id',
                null,
                400
            );
        }

        $user_id = Validator::sanitizeInt($sanitized['user_id']);
        if ($user_id === null) {
            AppLogger::error($user, 'Invalid user ID for deletion', ['user_id' => $sanitized['user_id']]);
            return ApiResponse::error($response, 'Invalid user ID', null, 400);
        }

        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("UPDATE users SET status = 'active' WHERE user_id = ?");
            $stmt->execute([$user_id]);

            $affected = $stmt->rowCount();
            AppLogger::info($user, 'User soft deleted', ['user_id' => $user_id]);
            return ApiResponse::success(
                $response,
                ['affected' => $affected],
                'User deleted successfully'
            );
        } catch (\Exception $e) {
            AppLogger::error($user, 'Database error deleting user', [
                'error' => $e->getMessage()
            ]);
            return ApiResponse::error($response, 'Failed to delete user', null, 500);
        }
    }

    public function getRoles(Request $request, Response $response): Response
    {
        $queryParams = $request->getQueryParams();
        $user = $queryParams['user'] ?? 'NA';

        AppLogger::info($user, 'Fetching all roles');

        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->query(
                'SELECT role_id, role_name 
                 FROM roles 
                 ORDER BY role_id DESC'
            );
            $data = $stmt->fetchAll();

            AppLogger::info($user, 'Roles retrieved successfully', ['count' => count($data)]);
            return ApiResponse::success($response, $data, 'Roles retrieved successfully');
        } catch (\Exception $e) {
            AppLogger::error($user, 'Database error fetching roles', ['error' => $e->getMessage()]);
            return ApiResponse::error($response, 'Failed to fetch roles', null, 500);
        }
    }
}
