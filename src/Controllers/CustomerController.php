<?php
declare(strict_types=1);

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Database;
use App\ApiResponse;
use App\Validator;
use App\AppLogger;

class CustomerController
{
    public function getCustomers(Request $request, Response $response): Response
    {
        $queryParams = $request->getQueryParams();
        $user = $queryParams['user'] ?? 'NA';

        AppLogger::info($user, 'Fetching all customers');

        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->query('SELECT * FROM customer ORDER BY customer_id DESC');
            $data = $stmt->fetchAll();

            AppLogger::info($user, 'Customers retrieved successfully', ['count' => count($data)]);
            return ApiResponse::success($response, $data, 'Customers retrieved successfully');
        } catch (\Exception $e) {
            AppLogger::error($user, 'Database error fetching customers', ['error' => $e->getMessage()]);
            return ApiResponse::error($response, 'Failed to fetch customers', null, 500);
        }
    }

    // Get customers that have AMC required in their subscriptions
    public function getCustomersWithAMCSubscriptions(Request $request, Response $response): Response
    {
        $queryParams = $request->getQueryParams();
        $user = $queryParams['user'] ?? 'NA';

        AppLogger::info($user, 'Fetching customers with AMC subscriptions');

        try {
            $pdo = Database::getConnection();

            $sql = <<<SQL
SELECT c.customer_id, c.customer_name, cs.*
FROM client_subscriptions cs
JOIN customer c ON cs.customer_id = c.customer_id
WHERE cs.amc_required = 1
ORDER BY cs.created_at DESC
SQL;

            $stmt = $pdo->query($sql);
            $rows = $stmt->fetchAll();

            AppLogger::info($user, 'Customers with AMC subscriptions retrieved', ['count' => count($rows)]);
            return ApiResponse::success($response, $rows, 'Customers with AMC subscriptions retrieved successfully');
        } catch (\Exception $e) {
            AppLogger::error($user, 'Database error fetching customers with AMC subscriptions', ['error' => $e->getMessage()]);
            return ApiResponse::error($response, 'Failed to fetch customers with AMC subscriptions', null, 500);
        }
    }

    public function saveCustomer(Request $request, Response $response): Response
    {
        $body = (array) $request->getParsedBody();
        $sanitized = Validator::sanitizeString($body);
        $user = $sanitized['user'];

        AppLogger::info($user, 'New customer submission', ['customer_name' => $sanitized['customer_name'] ?? null]);

        // Validate required fields
        $required = [
            'customer_name',
            'customer_code',
            'aisensy_campaign_name',
            'aisensy_template_name',
            'contact_person',
            'mobile_no',
            'email',
            'address',
            'city',
            'state',
            'country',
            'pin'
        ];
        $missing = array_filter($required, function ($key) use ($sanitized) {
            return empty($sanitized[$key]);
        });

        if (!empty($missing)) {
            AppLogger::warning($user, 'Customer save validation failed', ['missing' => $missing]);
            return ApiResponse::error(
                $response,
                'Missing required fields: ' . implode(', ', $missing),
                null,
                400
            );
        }

        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare('INSERT INTO customer (customer_name, customer_code, aisensy_campaign_name, aisensy_template_name, contact_person, mobile_no, email, address, city, state, country, pin, gst_no, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([
                $sanitized['customer_name'],
                $sanitized['customer_code'],
                $sanitized['aisensy_campaign_name'],
                $sanitized['aisensy_template_name'],
                $sanitized['contact_person'],
                $sanitized['mobile_no'],
                $sanitized['email'],
                $sanitized['address'],
                $sanitized['city'],
                $sanitized['state'],
                $sanitized['country'],
                $sanitized['pin'],
                $sanitized['gst_no'] ?? null,
                $sanitized['status'] ?? 'ACTIVE',
            ]);
            $id = (int) $pdo->lastInsertId();

            AppLogger::info($user, 'Customer created successfully', [
                'customer_id' => $id,
                'customer_name' => $sanitized['customer_name']
            ]);
            return ApiResponse::success($response, ['customer_id' => $id], 'Customer created successfully');
        } catch (\Exception $e) {
            AppLogger::error($user, 'Database error saving customer', ['error' => $e->getMessage()]);
            return ApiResponse::error($response, 'Failed to save customer', null, 500);
        }
    }



    public function updateCustomer(Request $request, Response $response): Response
    {
        $body = (array) $request->getParsedBody();
        $sanitized = Validator::sanitizeString($body);
        $user = $sanitized['user'] ?? 'system';

        AppLogger::info($user, 'Customer update requested', ['customer_id' => $sanitized['customer_id'] ?? null]);

        // Validate required fields
        $required = [
            'customer_id',
            'customer_name',
            'customer_code',
            'aisensy_campaign_name',
            'aisensy_template_name',
            'contact_person',
            'mobile_no',
            'email',
            'address',
            'city',
            'state',
            'country',
            'pin'
        ];
        $missing = array_filter($required, function ($key) use ($sanitized) {
            return empty($sanitized[$key]);
        });

        if (!empty($missing)) {
            AppLogger::warning($user, 'Customer update validation failed', ['missing' => $missing]);
            return ApiResponse::error(
                $response,
                'Missing required fields: ' . implode(', ', $missing),
                null,
                400
            );
        }

        $customerId = Validator::sanitizeInt($sanitized['customer_id']);
        if ($customerId === null) {
            AppLogger::error($user, 'Invalid customer ID for update', ['customer_id' => $sanitized['customer_id']]);
            return ApiResponse::error($response, 'Invalid customer ID', null, 400);
        }

        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare('UPDATE customer SET 
            customer_name = ?, 
            customer_code = ?, 
            aisensy_campaign_name = ?, 
            aisensy_template_name = ?, 
            contact_person = ?, 
            mobile_no = ?, 
            email = ?, 
            pin = ?, 
            address = ?, 
            city = ?, 
            state = ?, 
            country = ?, 
            gst_no = ?, 
            status = ?
            WHERE customer_id = ?'
            );

            $stmt->execute([
                $sanitized['customer_name'],
                $sanitized['customer_code'],
                $sanitized['aisensy_campaign_name'],
                $sanitized['aisensy_template_name'],
                $sanitized['contact_person'],
                $sanitized['mobile_no'],
                $sanitized['email'],
                $sanitized['pin'],
                $sanitized['address'],
                $sanitized['city'],
                $sanitized['state'],
                $sanitized['country'],
                $sanitized['gst_no'] ?? null,
                $sanitized['status'],
                $customerId
            ]);

            $affected = $stmt->rowCount();
            AppLogger::info($user, 'Customer updated successfully', [
                'customer_id' => $customerId,
                'affected' => $affected
            ]);

            return ApiResponse::success($response, ['affected' => $affected], 'Customer updated successfully');
        } catch (\Exception $e) {
            AppLogger::error($user, 'Database error updating customer', [
                'customer_id' => $customerId,
                'error' => $e->getMessage()
            ]);
            return ApiResponse::error($response, 'Failed to update customer', null, 500);
        }
    }

    public function deleteCustomer(Request $request, Response $response): Response
    {
        $body = (array) $request->getParsedBody();
        $sanitized = Validator::sanitizeString($body);
        $user = $sanitized['user'];

        AppLogger::info($user, 'Customer deletion requested', ['customer_id' => $sanitized['customer_id'] ?? null]);

        // Validate required fields
        $required = ['customer_id'];
        $missing = array_filter($required, function ($key) use ($sanitized) {
            return empty($sanitized[$key]);
        });

        if (!empty($missing)) {
            AppLogger::warning($user, 'Customer deletion validation failed', ['missing' => $missing]);
            return ApiResponse::error(
                $response,
                'Missing required fields: ' . implode(', ', $missing),
                null,
                400
            );
        }

        $customerId = Validator::sanitizeInt($sanitized['customer_id']);
        if ($customerId === null) {
            AppLogger::error($user, 'Invalid customer ID for deletion', ['customer_id' => $sanitized['customer_id']]);
            return ApiResponse::error($response, 'Invalid customer ID', null, 400);
        }

        try {
            $pdo = Database::getConnection();
            // $stmt = $pdo->prepare('DELETE FROM customer WHERE customer_id = ?');
            // Do soft delete by setting status to INACTIVE
            $stmt = $pdo->prepare('UPDATE customer SET status = ? WHERE customer_id = ?');
            $stmt->execute(['INACTIVE', $customerId]);
            $affected = $stmt->rowCount();

            AppLogger::info($user, 'Customer soft deleted (status set to INACTIVE)', [
                'customer_id' => $customerId,
                'affected' => $affected
            ]);
            return ApiResponse::success($response, ['affected' => $affected], 'Customer deleted successfully');
        } catch (\Exception $e) {
            AppLogger::error($user, 'Database error deleting customer', [
                'customer_id' => $customerId,
                'error' => $e->getMessage()
            ]);
            return ApiResponse::error($response, 'Failed to delete customer', null, 500);
        }
    }

    // Import customers from CSV
    public function importCustomers(Request $request, Response $response): Response
    {
        $user = 'NA';

        AppLogger::info($user, 'Customer CSV import initiated');

        $uploadedFiles = $request->getUploadedFiles();

        if (!isset($uploadedFiles['file'])) {
            AppLogger::warning($user, 'CSV import failed: no file uploaded');
            return ApiResponse::error($response, 'CSV file is required', null, 400);
        }

        $file = $uploadedFiles['file'];

        if ($file->getError() !== UPLOAD_ERR_OK) {
            AppLogger::error($user, 'CSV file upload error', ['error' => $file->getError()]);
            return ApiResponse::error($response, 'File upload failed', null, 400);
        }

        // Validate extension
        $filename = $file->getClientFilename();
        $extension = pathinfo($filename, PATHINFO_EXTENSION);

        if (strtolower($extension) !== 'csv') {
            AppLogger::warning($user, 'Invalid file type for import', ['filename' => $filename, 'extension' => $extension]);
            return ApiResponse::error($response, 'Only CSV files are allowed', null, 400);
        }

        AppLogger::info($user, 'Processing CSV file', ['filename' => $filename]);

        // Read CSV
        $stream = $file->getStream()->getContents();
        $rows = array_map('str_getcsv', explode(PHP_EOL, trim($stream)));

        if (count($rows) <= 1) {
            AppLogger::warning($user, 'CSV file empty or invalid', ['row_count' => count($rows)]);
            return ApiResponse::error($response, 'CSV file is empty or invalid', null, 400);
        }

        $header = array_map('trim', array_shift($rows));

        // Example expected headers
        $requiredHeaders = [
            'customer_name',
            'customer_code',

            'aisensy_campaign_name',
            'aisensy_template_name',
            // 'aisensy_api_endpoint',
            // 'aisensy_api_key',
            // 'is_active',

            'contact_person',
            'mobile_no',
            'email',
            'address',
            'city',
            'pin',
            'state',
            'country',
            'gst_no',
            'status'
        ];


        foreach ($requiredHeaders as $col) {
            if (!in_array($col, $header)) {
                AppLogger::error($user, 'CSV missing required column', ['column' => $col]);
                return ApiResponse::error(
                    $response,
                    "Missing required column: $col",
                    null,
                    400
                );
            }
        }

        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare(
                'INSERT INTO customer
            (
                customer_name,
                customer_code,
                aisensy_campaign_name,
                aisensy_template_name,
                -- aisensy_api_endpoint,
                -- aisensy_api_key,
                -- is_active,
                contact_person,
                mobile_no,
                email,
                address,
                city,
                pin,
                state,
                country,
                gst_no,
                status
                )
                VALUES
                (
                ?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?
                )'
            );

            $inserted = 0;

            foreach ($rows as $row) {
                if (count($row) !== count($header))
                    continue;

                $data = array_combine($header, $row);

                // Check for existing customer by all columns to avoid duplicates
                $checkStmt = $pdo->prepare(
                    'SELECT customer_id FROM customer WHERE customer_name = ?  
                    AND (
                            (email IS NOT NULL AND email = ?)
                            OR
                            (mobile_no IS NOT NULL AND mobile_no = ?)
                        )
                LIMIT 1'
                );

                $checkStmt->execute([
                    trim($data['customer_name']),
                    trim($data['email'] ?? ''),
                    trim($data['mobile_no'] ?? '')
                ]);

                $existingCustomer = $checkStmt->fetch();

                if (empty($data['customer_code'])) {
                    // Skip rows with empty customer_code
                    continue;
                }

                try {
                    $stmt->execute([
                        trim($data['customer_name']),
                        trim($data['customer_code']),

                        trim($data['aisensy_campaign_name']),
                        trim($data['aisensy_template_name']),
                        // trim($data['aisensy_api_endpoint'] ?? ''),
                        // trim($data['aisensy_api_key'] ?? ''),
                        // trim($data['is_active'] ?? 1),

                        trim($data['contact_person']),
                        trim($data['mobile_no']),
                        trim($data['email']),
                        trim($data['address']),
                        trim($data['city']),
                        trim($data['pin']),
                        trim($data['state']),
                        trim($data['country']),
                        trim($data['gst_no'] ?? ''),
                        trim($data['status'] ?? 'ACTIVE')
                    ]);
                    $inserted++;
                } catch (\PDOException $e) {
                    AppLogger::error($user, 'CSV import row failed', [
                        'customer_code' => $data['customer_code'] ?? null,
                        'error' => $e->getMessage()
                    ]);
                    error_log($e->getMessage());
                    return ApiResponse::error(
                        $response,
                        $e->getMessage()
                    );
                }
            }

            AppLogger::info($user, 'CSV import completed successfully', ['inserted' => $inserted]);
            return ApiResponse::success(
                $response,
                ['inserted' => $inserted],
                'CSV uploaded successfully'
            );
        } catch (\Exception $e) {
            AppLogger::error($user, 'Database error during CSV import', ['error' => $e->getMessage()]);
            return ApiResponse::error($response, 'Failed to import CSV', null, 500);
        }
    }

    public function getCustomerById(Request $request, Response $response): Response
    {
        $queryParams = $request->getQueryParams();
        $user = $queryParams['user'] ?? 'NA';

        AppLogger::info($user, 'Fetching customer by ID', [
            'customer_id' => $queryParams['customer_id'] ?? null
        ]);

        if (empty($queryParams['customer_id'])) {
            AppLogger::warning($user, 'Customer ID missing in request');
            return ApiResponse::error(
                $response,
                'Missing required parameter: customer_id',
                null,
                400
            );
        }

        try {
            $pdo = Database::getConnection();

            $stmt = $pdo->prepare(
                'SELECT * FROM customer WHERE customer_id = ?'
            );

            $stmt->execute([$queryParams['customer_id']]);
            $customer = $stmt->fetch();

            if (!$customer) {
                AppLogger::warning($user, 'Customer not found', [
                    'customer_id' => $queryParams['customer_id']
                ]);

                return ApiResponse::error(
                    $response,
                    'Customer not found',
                    null,
                    404
                );
            }

            AppLogger::info($user, 'Customer retrieved successfully', [
                'customer_id' => $queryParams['customer_id']
            ]);

            return ApiResponse::success(
                $response,
                $customer,
                'Customer retrieved successfully'
            );

        } catch (\Exception $e) {

            AppLogger::error($user, 'Database error fetching customer', [
                'error' => $e->getMessage()
            ]);

            return ApiResponse::error(
                $response,
                'Failed to fetch Customer',
                null,
                500
            );
        }
    }

}