<?php
declare(strict_types=1);

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Database;
use App\ApiResponse;
use App\Validator;
use App\AppLogger;
use App\Helpers\Jwt;

class AuthController
{
    public function login(Request $request, Response $response): Response
    {
        $body = (array) $request->getParsedBody();
        $sanitized = Validator::sanitizeString($body);

        AppLogger::info($sanitized['username'] ?? 'Unknown', 'Login attempt', ['username' => $sanitized['username'] ?? null]);

        // Validate required fields
        $required = ['username', 'password'];
        $missing = array_filter($required, function ($key) use ($sanitized) {
            return empty($sanitized[$key]);
        });

        if (!empty($missing)) {
            AppLogger::warning($sanitized['username'] ?? 'Unknown', 'Login validation failed', ['missing' => $missing]);
            return ApiResponse::error(
                $response,
                'Missing required fields: ' . implode(', ', $missing),
                null,
                400
            );
        }

        try {
            $pdo = Database::getConnection();
            $passwordHash = password_hash($sanitized['password'], PASSWORD_BCRYPT);
            $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
            $stmt->execute([
                $sanitized['username'],
            ]);
            $affected = $stmt->rowCount(); //check if user exists in db rows

            if ($affected == 1) {
                // Verify password
                $userData = $stmt->fetch();
                if (!password_verify($sanitized['password'], $userData['password'])) {
                    AppLogger::warning($sanitized['username'], 'Invalid password', ['username' => $sanitized['username']]);
                    return ApiResponse::error($response, 'Invalid username or password', null, 200);
                }
                // Check if user is active
                if ($userData['status'] !== 'active') {
                    AppLogger::warning($sanitized['username'], 'Inactive user login attempt', ['username' => $sanitized['username']]);
                    return ApiResponse::error($response, 'This account is inactive, please contact administrator.', null, 200);
                }
                // User authenticated successfully
                AppLogger::info($sanitized['username'], 'Login successfull', [
                    'user_id' => $userData['user_id'] ?? null
                ]);
                // Return user data except password and issue JWT
                $user = array_diff_key($userData, ['password' => '']);

                $secret = Jwt::getSecret();
                $token = Jwt::encode([
                    'user_id' => $userData['user_id'] ?? null,
                    'email' => $userData['email'] ?? $sanitized['username'] ?? null,
                ], $secret, 3600);

                return ApiResponse::success(
                    $response,
                    [
                        'user' => $user,
                        'token' => $token,
                        'expires_in' => 3600
                    ],
                    'Login successful'
                );
            } else {
                // Authentication failed
                AppLogger::warning($sanitized['username'], 'Authentication failed', ['username' => $sanitized['username']]);
                return ApiResponse::error($response, 'Invalid username or password', null, 200);
            }
        } catch (\Exception $e) {
            AppLogger::error($sanitized['username'] ?? 'Unknown', 'Database error while logging in', ['error' => $e->getMessage()]);
            return ApiResponse::error($response, 'Login failed', null, 500);
        }
    }

    public function updatePassword(Request $request, Response $response): Response
    {
        $body = (array) $request->getParsedBody();
        $sanitized = Validator::sanitizeString($body);
         $user = $sanitized['user'];

        AppLogger::info($user, 'Update password requested', [
            'username' => $sanitized['username'] ?? null
        ]);

        // Validate required fields
        $required = ['username', 'old_password', 'new_password'];
        $missing = array_filter($required, function ($key) use ($sanitized) {
            return empty($sanitized[$key]);
        });

        if (!empty($missing)) {
            AppLogger::warning($user, 'Update password validation failed', ['missing' => $missing]);
            return ApiResponse::error(
                $response,
                'Missing required fields: ' . implode(', ', $missing),
                null,
                400
            );
        }

        try {
            $pdo = Database::getConnection();

            // Step 1: Verify old password
            $stmt = $pdo->prepare(
                'SELECT user_id FROM users WHERE email = ? AND password = ?'
            );
            $stmt->execute([
                $sanitized['username'],
                $sanitized['old_password']
            ]);

            if ($stmt->rowCount() !== 1) {
                AppLogger::warning($user, 'Old password mismatch', [
                    'username' => $sanitized['username']
                ]);

                return ApiResponse::error(
                    $response,
                    'Old password is incorrect',
                    null,
                    401
                );
            }

            // Step 2: Update password
            $updateStmt = $pdo->prepare(
                'UPDATE users SET password = ?, updated = CURRENT_TIMESTAMP WHERE email = ?'
            );
            $updateStmt->execute([
                $sanitized['new_password'],
                $sanitized['username']
            ]);

            AppLogger::info($user, 'Password updated successfully', [
                'username' => $sanitized['username']
            ]);

            return ApiResponse::success(
                $response,
                [],
                'Password updated successfully'
            );
        } catch (\Exception $e) {
            AppLogger::error($user, 'Database error while updating password', [
                'error' => $e->getMessage()
            ]);

            return ApiResponse::error(
                $response,
                'Failed to update password',
                null,
                500
            );
        }
    }

    public function logout(Request $request, Response $response): Response
    {
        $body = (array) $request->getParsedBody();
        $sanitized = Validator::sanitizeString($body);
        $user = $sanitized['user'] ?? 'Unknown';

        AppLogger::info($user, 'Logout attempt', ['username' => $sanitized['username'] ?? null]);

        try {
            // Perform logout logic here (e.g., invalidate session or token)
            AppLogger::info($user, 'Logout successful', ['username' => $sanitized['username'] ?? null]);
            return ApiResponse::success($response, [], 'Logout successful');
        } catch (\Exception $e) {
            AppLogger::error($user, 'Database error while logging out', ['error' => $e->getMessage()]);
            return ApiResponse::error($response, 'Logout failed', null, 500);
        }
    }
}