<?php
declare(strict_types=1);

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Database;
use App\ApiResponse;
use App\Validator;
use App\AppLogger;

class PatientController
{
    // Number of days a consent token stays valid before expiry
    private const CONSENT_TOKEN_VALIDITY_DAYS = 7;

    /**
     * Normalize phone number into E.164-like format used by AiSensy.
     * - If input starts with '+', keep country code and digits.
     * - If input is 10 digits (assumed India), prefix +91.
     * - Strip non-digits and leading zeros where appropriate.
     * Returns null when input is empty or can't be normalized.
     */
    private function normalizePhone(?string $raw): ?string
    {
        if (empty($raw)) {
            return null;
        }
        $raw = trim($raw);
        // Keep only digits
        $digits = preg_replace('/\D+/', '', $raw);
        if ($digits === '') {
            return null;
        }

        // If original had + prefix, preserve it
        if (strpos($raw, '+') === 0) {
            return '+' . $digits;
        }

        // Remove any leading zeros
        $digits = ltrim($digits, '0');

        // If 10 digits -> assume India local number
        if (strlen($digits) === 10) {
            return '+91' . $digits;
        }

        // If already starts with country code 91 (12 digits), prefix +
        if (strlen($digits) >= 11 && substr($digits, 0, 2) === '91') {
            return '+' . $digits;
        }

        // Fallback: prefix + to digits
        return '+' . $digits;
    }

    /**
     * Save patient details, create associated contact and consent token,
     * and trigger a WhatsApp consent message via the AiSensy campaign API.
     *
     * Steps:
     * - Validate input and insert patient and contact records
     * - Generate a consent token and store it in `consent_tokens`
     * - Call AiSensy to send a WhatsApp message containing the consent link
     * - Return created IDs, token and AiSensy response in the API result
     *
     */
    public function savePatient(Request $request, Response $response): Response
    {
        $body = (array) $request->getParsedBody();
        $sanitized = Validator::sanitizeString($body);
        $user = $sanitized['user'] ?? 'NA';

        AppLogger::info($user, 'New patient submission', [
            'patient_name' => $sanitized['patient_name'] ?? null,
            'customer_id' => $sanitized['customer_id'] ?? null
        ]);

        // Validate required fields
        $required = [
            'customer_id',
            'patient_name',
            'mobile'
        ];
        $missing = array_filter($required, function ($key) use ($sanitized) {
            return empty($sanitized[$key]);
        });

        if (!empty($missing)) {
            AppLogger::warning($user, 'Patient save validation failed', ['missing' => $missing]);
            return ApiResponse::error(
                $response,
                'Missing required fields: ' . implode(', ', $missing),
                null,
                400
            );
        }

        $customerId = Validator::sanitizeInt($sanitized['customer_id']);
        if ($customerId === null) {
            AppLogger::error($user, 'Invalid customer ID for patient save', ['customer_id' => $sanitized['customer_id']]);
            return ApiResponse::error($response, 'Invalid customer ID', null, 400);
        }

        try {
            $pdo = Database::getConnection();

            // Pre-insert duplicate checks
            $mobileRaw = trim((string) ($sanitized['mobile'] ?? ''));
            $mobileNorm = $this->normalizePhone($mobileRaw);
            $mobileNormNoPlus = $mobileNorm ? ltrim($mobileNorm, '+') : null;
            $externalPatientId = trim((string) ($sanitized['external_patient_id'] ?? ''));

            // Check duplicate by mobile (raw + normalized variants)
            $stmtMobile = $pdo->prepare(
                'SELECT id FROM patient_master WHERE customer_id = ? AND (mobile = ? OR mobile = ? OR mobile = ?) LIMIT 1'
            );
            $stmtMobile->execute([$customerId, $mobileRaw, $mobileNorm, $mobileNormNoPlus]);
            if ($stmtMobile->fetch()) {
                AppLogger::warning($user, 'Duplicate mobile when saving patient', [
                    'customer_id' => $customerId,
                    'mobile' => $mobileRaw,
                ]);
                return ApiResponse::error($response, 'mobile number already exist', [], 200);
            }

            // Check duplicate by external_patient_id (only when provided)
            if ($externalPatientId !== '') {
                $stmtExt = $pdo->prepare(
                    'SELECT id FROM patient_master WHERE customer_id = ? AND external_patient_id = ? LIMIT 1'
                );
                $stmtExt->execute([$customerId, $externalPatientId]);
                if ($stmtExt->fetch()) {
                    AppLogger::warning($user, 'Duplicate external patient ID when saving patient', [
                        'customer_id' => $customerId,
                        'external_patient_id' => $externalPatientId,
                    ]);
                    return ApiResponse::error($response, 'Patient already registered', [], 200);
                }
            }

            $pdo->beginTransaction();

            // 1. Insert / Save patient details in patient_master table
            $stmt = $pdo->prepare('
                INSERT INTO patient_master (
                    customer_id,
                    external_patient_id,
                    patient_name,
                    first_name,
                    last_name,
                    age,
                    sex,
                    mobile
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ');

            $stmt->execute([
                $customerId,
                $sanitized['external_patient_id'] ?? null,
                $sanitized['patient_name'],
                $sanitized['first_name'] ?? null,
                $sanitized['last_name'] ?? null,
                $sanitized['age'] ?? null,
                $sanitized['sex'] ?? null,
                $sanitized['mobile']
            ]);

            $patientId = (int) $pdo->lastInsertId();

            AppLogger::info($user, 'Patient created successfully', [
                'patient_id' => $patientId,
                'patient_name' => $sanitized['patient_name']
            ]);
            // exit();
            // 2. Insert / Save in contact table
            $stmt = $pdo->prepare('INSERT INTO contact (customer_id, external_patient_id, mobile_no, first_name, last_name, source_type, source_reference, consent_status, consent_source) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([
                $customerId,
                $sanitized['external_patient_id'] ?? (string) $patientId,
                $sanitized['mobile'],
                $sanitized['first_name'] ?? $sanitized['patient_name'],
                $sanitized['last_name'] ?? null,
                $sanitized['source_type'] ?? null,
                $sanitized['source_reference'] ?? null,
                'PENDING',
                $sanitized['consent_source'] ?? null,
            ]);
            $contactId = (int) $pdo->lastInsertId();

            AppLogger::info($user, 'Contact created successfully', [
                'contact_id' => $contactId,
                'patient_id' => $patientId
            ]);

            // 3. Generate token against contact_id and insert in consent_token table
            $rawToken = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $rawToken);
            $tokenLast4 = substr($rawToken, -4);
            $expiresAt = (new \DateTime('+' . self::CONSENT_TOKEN_VALIDITY_DAYS . ' days'))->format('Y-m-d H:i:s');

            $stmt = $pdo->prepare('INSERT INTO consent_tokens (customer_id, contact_id, token_hash, token_last4, purpose, status, expires_at) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([
                $customerId,
                $contactId,
                $tokenHash,
                $tokenLast4,
                $sanitized['purpose'] ?? 'WHATSAPP_CONSENT',
                'ACTIVE',
                $expiresAt,
            ]);
            $tokenId = (int) $pdo->lastInsertId();

            AppLogger::info($user, 'Consent token generated successfully', [
                'token_id' => $tokenId,
                'contact_id' => $contactId
            ]);

            // 4. Call AiSensy Campaign API to send WhatsApp message with the token link
            $aisensyResponse = null;
            try {
                $aisensyEndpoint = 'https://backend.aisensy.com/campaign/t1/api/v2';

                // Ensure destination starts with + and strip non-digits
                // Normalize destination phone number
                $destination = $this->normalizePhone($sanitized['mobile'] ?? null);
                if ($destination === null) {
                    AppLogger::warning($user, 'Invalid mobile provided for AiSensy', ['mobile' => $sanitized['mobile'] ?? null]);
                    $destination = '';
                }

                $buttonUrl = 'https://ppc.penguinhealth.com/consent/accept?t=' . $rawToken;
                // $buttonUrl = 'https://ppc.penguinhealthtech.com/#/login?t=' . $rawToken;

                // Prepare template params ensuring they are strings and fallbacks are provided
                $firstName = trim((string) ($sanitized['first_name'] ?? $sanitized['patient_name'] ?? ''));
                $lastName = trim((string) ($sanitized['last_name'] ?? ''));
                if ($firstName === '') {
                    $firstName = 'User';
                }

                $aisensyPayload = [
                    'campaignName' => 'HM Consent V1',
                    'destination' => $destination,
                    'userName' => $firstName,
                    // AiSensy expects array of template params; provide first and last name (last may be empty string)
                    'templateParams' => [$firstName, $lastName],
                    'buttonUrlParam' => $buttonUrl,
                    'apiKey' => $_ENV['AISENSY_API_KEY'] ?? null
                ];

                $ch = curl_init($aisensyEndpoint);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($aisensyPayload));
                curl_setopt($ch, CURLOPT_TIMEOUT, 15);

                // Set the API key if provided in the request
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $_ENV['AISENSY_API_KEY']
                ]);

                $aisensyResult = curl_exec($ch);
                $aisensyErr = curl_error($ch);
                curl_close($ch);

                if ($aisensyResult === false) {
                    AppLogger::error($user, 'AiSensy API call failed', ['error' => $aisensyErr, 'payload' => $aisensyPayload]);
                    $aisensyResponse = ['error' => $aisensyErr];
                } else {
                    $aisensyData = json_decode($aisensyResult, true);
                    AppLogger::info($user, 'AiSensy API response', ['response' => $aisensyData]);
                    $aisensyResponse = $aisensyData;
                }
            } catch (\Exception $ex) {
                AppLogger::error($user, 'Exception while calling AiSensy API', ['error' => $ex->getMessage()]);
                $aisensyResponse = ['exception' => $ex->getMessage()];
            }

            $pdo->commit();

            return ApiResponse::success($response, [
                'patient_id' => $patientId,
                'contact_id' => $contactId,
                'consent_token_id' => $tokenId,
                'consent_token' => $rawToken,
                'expires_at' => $expiresAt,
                'aisensy_response' => $aisensyResponse,
            ], 'Patient created successfully');
        } catch (\Exception $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            AppLogger::error($user, 'Database error saving patient', ['error' => $e->getMessage()]);
            return ApiResponse::error($response, 'Failed to save patient', null, 500);
        }
    }

    public function consentAgree(Request $request, Response $response): Response
    {
        $queryParams = $request->getQueryParams();
        $user = $queryParams['user'] ?? 'NA';

        AppLogger::info($user, 'Consent agree requested', ['token' => isset($queryParams['t']) ? 'provided' : null]);

        if (empty($queryParams['t'])) {
            AppLogger::warning($user, 'Consent agree validation failed', ['missing' => ['t']]);
            return ApiResponse::error(
                $response,
                'Missing required parameter: token',
                null,
                400
            );
        }

        $tokenHash = hash('sha256', $queryParams['t']);

        try {
            $pdo = Database::getConnection();

            $stmt = $pdo->prepare('SELECT * FROM consent_tokens WHERE token_hash = ? LIMIT 1');
            $stmt->execute([$tokenHash]);
            $tokenRow = $stmt->fetch();

            if (!$tokenRow) {
                AppLogger::warning($user, 'Consent token not found');
                return ApiResponse::error($response, 'Invalid or expired consent link', null, 404);
            }

            if ($tokenRow['status'] !== 'ACTIVE') {
                AppLogger::warning($user, 'Consent token not active', ['status' => $tokenRow['status']]);
                return ApiResponse::error($response, 'This consent link has already been used or is no longer valid', null, 400);
            }

            if (strtotime($tokenRow['expires_at']) < time()) {
                AppLogger::warning($user, 'Consent token expired', ['expires_at' => $tokenRow['expires_at']]);
                return ApiResponse::error($response, 'This consent link has expired', null, 400);
            }

            $pdo->beginTransaction();

            // Mark token as used
            $stmt = $pdo->prepare('UPDATE consent_tokens SET status = ?, used_at = NOW() WHERE id = ?');
            $stmt->execute(['USED', $tokenRow['id']]);

            // Update contact consent status
            $stmt = $pdo->prepare('UPDATE contact SET consent_status = ?, consent_granted_at = NOW() WHERE contact_id = ?');
            $stmt->execute(['CONSENTED', $tokenRow['contact_id']]);

            // Find matching patient via customer_id + mobile and update consent status
            $stmt = $pdo->prepare('SELECT mobile_no FROM contact WHERE contact_id = ?');
            $stmt->execute([$tokenRow['contact_id']]);
            $contact = $stmt->fetch();

            $affectedPatients = 0;
            if ($contact) {
                $stmt = $pdo->prepare('UPDATE patient_master SET consent_status = ?, consent_response_at = NOW() WHERE customer_id = ? AND mobile = ?');
                $stmt->execute(['subscribed', $tokenRow['customer_id'], $contact['mobile_no']]);
                $affectedPatients = $stmt->rowCount();
            }

            $pdo->commit();

            AppLogger::info($user, 'Consent agreed successfully', [
                'contact_id' => $tokenRow['contact_id'],
                'token_id' => $tokenRow['id'],
                'patients_updated' => $affectedPatients
            ]);

            return ApiResponse::success($response, [
                'contact_id' => $tokenRow['contact_id'],
                'patients_updated' => $affectedPatients
            ], 'Consent recorded successfully');
        } catch (\Exception $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            AppLogger::error($user, 'Database error recording consent', ['error' => $e->getMessage()]);
            return ApiResponse::error($response, 'Failed to record consent', null, 500);
        }
    }

    public function sendConsentMessage(Request $request, Response $response): Response
    {
        $body = (array) $request->getParsedBody();
        $sanitized = Validator::sanitizeString($body);
        $user = $sanitized['user'] ?? 'NA';

        AppLogger::info($user, 'Send consent message requested', ['patient_id' => $sanitized['patient_id'] ?? null]);

        $required = ['patient_id'];
        $missing = array_filter($required, function ($key) use ($sanitized) {
            return empty($sanitized[$key]);
        });

        if (!empty($missing)) {
            AppLogger::warning($user, 'Send consent message validation failed', ['missing' => $missing]);
            return ApiResponse::error(
                $response,
                'Missing required fields: ' . implode(', ', $missing),
                null,
                400
            );
        }

        $patientId = Validator::sanitizeInt($sanitized['patient_id']);
        if ($patientId === null) {
            AppLogger::error($user, 'Invalid patient ID for consent message', ['patient_id' => $sanitized['patient_id']]);
            return ApiResponse::error($response, 'Invalid patient ID', null, 400);
        }

        try {
            $pdo = Database::getConnection();

            $stmt = $pdo->prepare('SELECT * FROM patient_master WHERE id = ?');
            $stmt->execute([$patientId]);
            $patient = $stmt->fetch();

            if (!$patient) {
                AppLogger::warning($user, 'Patient not found for consent message', ['patient_id' => $patientId]);
                return ApiResponse::error($response, 'Patient not found', null, 404);
            }

            $stmt = $pdo->prepare('SELECT * FROM customer WHERE customer_id = ?');
            $stmt->execute([$patient['customer_id']]);
            $customer = $stmt->fetch();

            if (!$customer) {
                AppLogger::warning($user, 'Customer not found for consent message', ['customer_id' => $patient['customer_id']]);
                return ApiResponse::error($response, 'Customer not found', null, 404);
            }

            // Find latest active consent token for this patient's contact
            $stmt = $pdo->prepare('SELECT ct.token_hash, ct.id, c.contact_id FROM contact c
                JOIN consent_tokens ct ON ct.contact_id = c.contact_id
                WHERE c.customer_id = ? AND c.mobile_no = ? AND ct.status = ?
                ORDER BY ct.created_at DESC LIMIT 1');
            $stmt->execute([$patient['customer_id'], $patient['mobile'], 'ACTIVE']);
            $tokenRow = $stmt->fetch();

            if (!$tokenRow) {
                AppLogger::warning($user, 'No active consent token found for patient', ['patient_id' => $patientId]);
                return ApiResponse::error($response, 'No active consent token found for this patient', null, 404);
            }

            $endpoint = $customer['aisensy_api_endpoint'] ?? null;
            $apiKey = $customer['aisensy_api_key'] ?? null;

            if (empty($endpoint) || empty($apiKey)) {
                AppLogger::error($user, 'Customer missing aisensy API configuration', ['customer_id' => $patient['customer_id']]);
                return ApiResponse::error($response, 'WhatsApp messaging is not configured for this customer', null, 400);
            }

            $destination = $this->normalizePhone($patient['mobile'] ?? null) ?? $patient['mobile'];

            $payload = [
                'apiKey' => $apiKey,
                'campaignName' => $customer['aisensy_campaign_name'],
                'destination' => $destination,
                'templateParams' => [$patient['patient_name']],
            ];

            $ch = curl_init($endpoint);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            $result = curl_exec($ch);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($result === false) {
                AppLogger::error($user, 'Failed to send consent message via aisensy', ['error' => $curlError]);

                $stmt = $pdo->prepare('UPDATE patients SET last_error = ? WHERE id = ?');
                $stmt->execute([$curlError, $patientId]);

                return ApiResponse::error($response, 'Failed to send consent message', null, 502);
            }

            $resultData = json_decode($result, true);
            $messageId = $resultData['messageId'] ?? $resultData['id'] ?? null;

            $pdo->beginTransaction();

            $stmt = $pdo->prepare('UPDATE patient_master SET consent_status = ?, consent_sent_at = NOW(), last_message_id = ?, last_error = NULL WHERE id = ?');
            $stmt->execute(['sent', $messageId, $patientId]);

            $stmt = $pdo->prepare('UPDATE contact SET consent_status = ?, consent_granted_at = NOW() WHERE contact_id = ?');
            $stmt->execute(['SENT', $tokenRow['contact_id']]);

            $pdo->commit();

            AppLogger::info($user, 'Consent message sent successfully', [
                'patient_id' => $patientId,
                'message_id' => $messageId
            ]);

            return ApiResponse::success($response, [
                'patient_id' => $patientId,
                'message_id' => $messageId
            ], 'Consent message sent successfully');
        } catch (\Exception $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            AppLogger::error($user, 'Database error sending consent message', [
                'patient_id' => $patientId,
                'error' => $e->getMessage()
            ]);
            return ApiResponse::error($response, 'Failed to send consent message', null, 500);
        }
    }

    // Import patients from CSV
    public function bulkImport(Request $request, Response $response): Response
    {
        $user = 'NA';

        AppLogger::info($user, 'Patient CSV import initiated');

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
            'customer_id',
            'patient_name',
            'mobile',
            'source_type',
            'source_reference'
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

            $patientStmt = $pdo->prepare('INSERT INTO patients (customer_id, patient_name, mobile, consent_status) VALUES (?, ?, ?, ?)');
            $contactStmt = $pdo->prepare('INSERT INTO contact (customer_id, external_patient_id, mobile_no, first_name, source_type, source_reference, cpatient_master atus) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $tokenStmt = $pdo->prepare('INSERT INTO consent_tokens (customer_id, contact_id, token_hash, token_last4, purpose, status, expires_at) VALUES (?, ?, ?, ?, ?, ?, ?)');

            $inserted = 0;

            foreach ($rows as $row) {
                if (count($row) !== count($header))
                    continue;

                $data = array_combine($header, $row);

                if (empty($data['customer_id']) || empty($data['mobile'])) {
                    // Skip rows with missing required values
                    continue;
                }

                try {
                    $customerId = (int) trim($data['customer_id']);

                    $pdo->beginTransaction();

                    $patientStmt->execute([
                        $customerId,
                        trim($data['patient_name'] ?? ''),
                        trim($data['mobile']),
                        'pending',
                    ]);
                    $patientId = (int) $pdo->lastInsertId();

                    $contactStmt->execute([
                        $customerId,
                        (string) $patientId,
                        trim($data['mobile']),
                        trim($data['patient_name'] ?? ''),
                        trim($data['source_type'] ?? ''),
                        trim($data['source_reference'] ?? ''),
                        'PENDING',
                    ]);
                    $contactId = (int) $pdo->lastInsertId();

                    $rawToken = bin2hex(random_bytes(32));
                    $tokenHash = hash('sha256', $rawToken);
                    $tokenLast4 = substr($rawToken, -4);
                    $expiresAt = (new \DateTime('+' . self::CONSENT_TOKEN_VALIDITY_DAYS . ' days'))->format('Y-m-d H:i:s');

                    $tokenStmt->execute([
                        $customerId,
                        $contactId,
                        $tokenHash,
                        $tokenLast4,
                        'WHATSAPP_CONSENT',
                        'ACTIVE',
                        $expiresAt,
                    ]);

                    $pdo->commit();
                    $inserted++;
                } catch (\PDOException $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    AppLogger::error($user, 'CSV import row failed', [
                        'mobile' => $data['mobile'] ?? null,
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

}