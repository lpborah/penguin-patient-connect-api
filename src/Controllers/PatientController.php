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
     * Insert a record into patient_messages after every outbound provider (AiSensy) API call.
     * Derives status/error from the provider response array.
     * Failures here are caught and logged without affecting the main flow.
     */
    private function insertPatientMessage(
        \PDO    $pdo,
        int     $customerId,
        int     $patientId,
        ?int    $consentId,
        string  $messageType,
        string  $templateName,
        string  $mobileNo,
        string  $provider,
        array   $requestPayload,
        ?array  $providerResponse,
        ?int    $visitId = null
    ): void {
        try {
            // Derive status and error from provider response
            $status            = 'SENT';
            $errorMessage      = null;
            $providerMessageId = null;

            if (is_array($providerResponse)) {
                if (isset($providerResponse['error']) || isset($providerResponse['exception'])) {
                    $status       = 'FAILED';
                    $errorMessage = $providerResponse['error'] ?? $providerResponse['exception'] ?? null;
                } else {
                    $providerMessageId = $providerResponse['submitted_message_id']
                        ?? $providerResponse['messageId']
                        ?? $providerResponse['id']
                        ?? null;
                }
            }

            $stmt = $pdo->prepare(
                'INSERT INTO patient_messages
                    (customer_id, patient_id, visit_id, consent_id, message_type, template_name,
                     mobile_no, provider, provider_message_id, status, error_message,
                     request_payload, provider_response)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $customerId,
                $patientId,
                $visitId,
                $consentId,
                $messageType,
                $templateName,
                $mobileNo,
                $provider,
                $providerMessageId,
                $status,
                $errorMessage,
                json_encode($requestPayload),
                $providerResponse !== null ? json_encode($providerResponse) : null,
            ]);
        } catch (\Exception $e) {
            AppLogger::error('system', 'Failed to insert patient message log', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Call the AiSensy Campaign API to send a WhatsApp message.
     * Returns the decoded response array, or an error/exception array on failure.
     * @Param string $campaignName Name of the AiSensy campaign to triggerś
     * @param string $destination  Normalized E.164 phone number
     * @param array  $templateParams Template parameters for the message
     * @return array Decoded provider response
     */

    // Generic method to call generic AiSensy campaign APIs
    private function callAiSensyCampaignApi(
        string $campaignName,
        string $destination,
        array $templateParams,
        string $campaignEndpoint
    
    ): array {
        $endpoint = $campaignEndpoint;
        $apiKey   = $_ENV['AISENSY_API_KEY'] ?? '';
        $payload = [
            'campaignName'   => $campaignName,
            'destination'    => $destination,
            'templateParams' => $templateParams,
            'apiKey'         => $apiKey,
        ];
        
        // Log the payload for debugging
        AppLogger::info('system', 'AiSensy API request payload', [
            'campaignName' => $campaignName,
            'destination' => $destination,
            'templateParams' => $templateParams,
        ]);

        try {
            $ch = curl_init($endpoint);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ]);

            $result   = curl_exec($ch);
            $curlErr  = curl_error($ch);
            curl_close($ch);

            if ($result === false) {
                AppLogger::error('system', 'AiSensy cURL error', ['error' => $curlErr, 'destination' => $destination]);
                return ['_payload' => $payload, 'error' => $curlErr];
            }

            $decoded = json_decode($result, true) ?? [];
            $decoded['_payload'] = $payload;   // carry payload for message logging
            AppLogger::info('system', 'AiSensy API response', ['response' => $decoded]);
            return $decoded;

        } catch (\Exception $ex) {
            AppLogger::error('system', 'AiSensy exception', ['error' => $ex->getMessage()]);
            return ['_payload' => $payload, 'exception' => $ex->getMessage()];
        }
    }

    private function callAiSensyConsentCampaignApi(
        string $campaignName,
        string $destination,
        string $userName,
        array $templateParams
    
    ): array {
        $endpoint = 'https://backend.aisensy.com/campaign/t1/api/v2';
        $apiKey   = $_ENV['AISENSY_API_KEY'] ?? '';
        $payload = [
            'campaignName'   => $campaignName,
            'destination'    => $destination,
            'userName'       => $userName,
            'templateParams' => $templateParams,
            'apiKey'         => $apiKey,
        ];
        
        // Log the payload for debugging
        AppLogger::info('system', 'AiSensy API request payload', [
            'campaignName' => $campaignName,
            'destination' => $destination,
            'templateParams' => $templateParams,
        ]);

        try {
            $ch = curl_init($endpoint);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ]);

            $result   = curl_exec($ch);
            $curlErr  = curl_error($ch);
            curl_close($ch);

            if ($result === false) {
                AppLogger::error('system', 'AiSensy cURL error', ['error' => $curlErr, 'destination' => $destination]);
                return ['_payload' => $payload, 'error' => $curlErr];
            }

            $decoded = json_decode($result, true) ?? [];
            $decoded['_payload'] = $payload;   // carry payload for message logging
            AppLogger::info('system', 'AiSensy API response', ['response' => $decoded]);
            return $decoded;

        } catch (\Exception $ex) {
            AppLogger::error('system', 'AiSensy exception', ['error' => $ex->getMessage()]);
            return ['_payload' => $payload, 'exception' => $ex->getMessage()];
        }
    }

    /**
     * Insert a patient_visits row and return the new visit_id.
     * visit_date defaults to today when not supplied.
     * Returns null and logs on failure (non-fatal).
     */
    private function insertVisitRecord(
        \PDO  $pdo,
        int   $customerId,
        int   $patientId,
        array $data
    ): ?int {
        try {
            $externalVisitId = trim((string) ($data['external_visit_id'] ?? ''));
            $billAmount      = isset($data['bill_amount']) && $data['bill_amount'] !== ''
                ? (float) $data['bill_amount']
                : null;
            $visitDate = !empty($data['visit_date'])
                ? $data['visit_date']
                : (new \DateTime())->format('Y-m-d');

            $stmt = $pdo->prepare('
                INSERT INTO patient_visits (
                    customer_id, patient_id, external_visit_id, source_type, source_reference,
                    department, doctor_name, visit_date, laboratory_id,
                    bill_number, bill_amount, admission_number, ward, bed
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ');
            $stmt->execute([
                $customerId,
                $patientId,
                $externalVisitId !== '' ? $externalVisitId : null,
                $data['source_type']      ?? null,
                $data['source_reference'] ?? null,
                $data['department']       ?? null,
                $data['doctor_name']      ?? null,
                $visitDate,
                $data['laboratory_id']    ?? null,
                $data['bill_number']      ?? null,
                $billAmount,
                $data['admission_number'] ?? null,
                $data['ward']             ?? null,
                $data['bed']              ?? null,
            ]);

            return (int) $pdo->lastInsertId();
        } catch (\Exception $e) {
            AppLogger::error('system', 'Failed to insert visit record', ['error' => $e->getMessage()]);
            return null;
        }
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
        $campaignName = $sanitized['campaign_name'] ?? 'HM Consent V2';
        // $campaignUserName = $sanitized['campaign_user_name'] ?? 'User';
        // $campaignEndpoint = $sanitized['campaign_endpoint'] ?? 'https://backend.aisensy.com/campaign/t1/api/v2';
        // $campaignTemplateParams = $sanitized['campaign_template_params'] ?? [];
        AppLogger::info($user, 'New patient submission', [
            'first_name' => $sanitized['first_name'] ?? null,
            'last_name' => $sanitized['last_name'] ?? null,
            'customer_id' => $sanitized['customer_id'] ?? null,
            'campaign_name' => $campaignName
        ]);

        // Validate required fields
        $required = [
            'customer_id',
            'first_name',
            'last_name',
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
        $firstName = trim((string) ($sanitized['first_name'] ?? 'User'));
        $lastName  = trim((string) ($sanitized['last_name'] ?? 'User'));
        $fullName = $firstName . ' ' . $lastName;

        if ($customerId === null) {
            AppLogger::error($user, 'Invalid customer ID for patient save', ['customer_id' => $sanitized['customer_id']]);
            return ApiResponse::error($response, 'Invalid customer ID', null, 400);
        }

        try {
            $pdo = Database::getConnection();

            // ── Duplicate / existing-patient checks ──────────────────────────────
            $mobileRaw = trim((string) ($sanitized['mobile'] ?? ''));
            $mobileNorm = $this->normalizePhone($mobileRaw);
            $mobileNormNoPlus = $mobileNorm ? ltrim($mobileNorm, '+') : null;
            $externalPatientId = trim((string) ($sanitized['external_patient_id'] ?? ''));

            // Try to find an existing patient by mobile (all variants)
            $existingPatient = null;
            $stmtMobile = $pdo->prepare(
                'SELECT * FROM patient_master WHERE customer_id = ? AND (mobile = ? OR mobile = ? OR mobile = ?) LIMIT 1'
            );
            $stmtMobile->execute([$customerId, $mobileRaw, $mobileNorm, $mobileNormNoPlus]);
            $existingPatient = $stmtMobile->fetch() ?: null;

            // Fall back to external_patient_id lookup if mobile didn't match
            if (!$existingPatient && $externalPatientId !== '') {
                $stmtExt = $pdo->prepare(
                    'SELECT * FROM patient_master WHERE customer_id = ? AND external_patient_id = ? LIMIT 1'
                );
                $stmtExt->execute([$customerId, $externalPatientId]);
                $existingPatient = $stmtExt->fetch() ?: null;
            }

            if ($existingPatient) {
                // Patient exists — check whether consent is still PENDING
                $stmtConsent = $pdo->prepare(
                    'SELECT * FROM patient_consents WHERE patient_id = ? AND consent_status = ? ORDER BY consent_id DESC LIMIT 1'
                );
                $stmtConsent->execute([$existingPatient['id'], 'PENDING']);
                $pendingConsent = $stmtConsent->fetch();

                if (!$pendingConsent) {
                    // Consent already completed or not pending — nothing to resend
                    AppLogger::warning($user, 'Patient exists with no pending consent', [
                        'patient_id' => $existingPatient['id'],
                    ]);
                    return ApiResponse::error($response, 'mobile number already exist', [], 400);
                }

                // Consent is PENDING — generate a fresh token and resend AiSensy message
                $rawToken  = bin2hex(random_bytes(32));
                $tokenHash = hash('sha256', $rawToken);
                $tokenLast4 = substr($rawToken, -4);
                $expiresAt = (new \DateTime('+' . self::CONSENT_TOKEN_VALIDITY_DAYS . ' days'))->format('Y-m-d H:i:s');

                $pdo->beginTransaction();

                // Supersede any previously active tokens for this consent
                $stmt = $pdo->prepare('UPDATE consent_tokens SET status = ? WHERE consent_id = ? AND status = ?');
                $stmt->execute(['SUPERSEDED', $pendingConsent['consent_id'], 'ACTIVE']);

                // Insert the new token
                $stmt = $pdo->prepare('INSERT INTO consent_tokens (customer_id, consent_id, token_hash, token_last4, status, expires_at) VALUES (?, ?, ?, ?, ?, ?)');
                $stmt->execute([$customerId, $pendingConsent['consent_id'], $tokenHash, $tokenLast4, 'ACTIVE', $expiresAt]);
                $tokenId = (int) $pdo->lastInsertId();

                $pdo->commit();

                AppLogger::info($user, 'Resending consent message to existing patient', [
                    'patient_id'  => $existingPatient['id'],
                    'consent_id'  => $pendingConsent['consent_id'],
                    'token_id'    => $tokenId,
                ]);

                // Call AiSensy for existing patient
                $aisensyResponse = $this->callAiSensyConsentCampaignApi(
                    $campaignName,
                    $this->normalizePhone($existingPatient['mobile'] ?? null) ?? '',
                    $fullName,
                    [$fullName, $rawToken],
                );
                $aisensyPayload  = $aisensyResponse['_payload'] ?? [];
                unset($aisensyResponse['_payload']);

                // Save visit record and capture visit_id for message log
                $visitId = $this->insertVisitRecord($pdo, $customerId, $existingPatient['id'], $sanitized);

                $this->insertPatientMessage(
                    $pdo,
                    $customerId,
                    $existingPatient['id'],
                    $pendingConsent['consent_id'],
                    'CONSENT',
                    $campaignName,
                    $existingPatient['mobile'],
                    'AISENSY',
                    $aisensyPayload,
                    $aisensyResponse,
                    $visitId
                );

                return ApiResponse::success($response, [
                    'patient_id'       => $existingPatient['id'],
                    'consent_id'       => $pendingConsent['consent_id'],
                    'consent_token_id' => $tokenId,
                    'consent_token'    => $rawToken,
                    'expires_at'       => $expiresAt,
                    'visit_id'         => $visitId,
                    'aisensy_response' => $aisensyResponse,
                ], 'Consent message resent to existing patient');
            }
            // ── End existing-patient branch — fall through to new patient creation ──

            $pdo->beginTransaction();

            // 1. Insert / Save patient details in patient_master table
            $stmt = $pdo->prepare('
                INSERT INTO patient_master (
                    customer_id,
                    external_patient_id,
                    first_name,
                    last_name,
                    age,
                    sex,
                    mobile
                ) VALUES (?, ?, ?, ?, ?, ?, ?)
            ');

            $stmt->execute([
                $customerId,
                $sanitized['external_patient_id'] ?? null,
                $sanitized['first_name'] ?? null,
                $sanitized['last_name'] ?? null,
                $sanitized['age'] ?? null,
                $sanitized['sex'] ?? null,
                $sanitized['mobile']
            ]);

            $patientId = (int) $pdo->lastInsertId();

            AppLogger::info($user, 'Patient created successfully', [
                'patient_id' => $patientId,
                'first_name' => $sanitized['first_name'] ?? null,
                'last_name' => $sanitized['last_name'] ?? null
            ]);
            // exit();
            // 2. Insert / Save in patient_consents table
            $stmt = $pdo->prepare('INSERT INTO patient_consents (customer_id, patient_id, consent_status, consent_source, purpose) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([
                $customerId,
                $patientId,
                'PENDING',
                $sanitized['consent_source'] ?? null,
                $sanitized['purpose'] ?? 'WHATSAPP_CONSENT',
            ]);
            $consentId = (int) $pdo->lastInsertId();

            AppLogger::info($user, 'Patient consent record created', [
                'consent_id' => $consentId,
                'patient_id' => $patientId
            ]);

            // 3. Generate token against consent_id and insert in consent_tokens table
            $rawToken = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $rawToken);
            $tokenLast4 = substr($rawToken, -4);
            $expiresAt = (new \DateTime('+' . self::CONSENT_TOKEN_VALIDITY_DAYS . ' days'))->format('Y-m-d H:i:s');

            $stmt = $pdo->prepare('INSERT INTO consent_tokens (customer_id, consent_id, token_hash, token_last4, status, expires_at) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->execute([
                $customerId,
                $consentId,
                $tokenHash,
                $tokenLast4,
                'ACTIVE',
                $expiresAt,
            ]);
            $tokenId = (int) $pdo->lastInsertId();

            AppLogger::info($user, 'Consent token generated successfully', [
                'token_id' => $tokenId,
                'consent_id' => $consentId
            ]);

            // 4. Call AiSensy Campaign API to send WhatsApp message with the token link
            $destination = $this->normalizePhone($sanitized['mobile'] ?? null) ?? '';
            if ($destination === '') {
                AppLogger::warning($user, 'Invalid mobile provided for AiSensy', ['mobile' => $sanitized['mobile'] ?? null]);
            }

            $aisensyResponse = $this->callAiSensyConsentCampaignApi(
                $campaignName,
                $destination,
                $firstName,
                [$fullName, $rawToken]
            );
            $aisensyPayload  = $aisensyResponse['_payload'] ?? [];
            unset($aisensyResponse['_payload']);

            $pdo->commit();

            // Save visit record and capture visit_id for message log
            $visitId = $this->insertVisitRecord($pdo, $customerId, $patientId, $sanitized);

            $this->insertPatientMessage(
                $pdo,
                $customerId,
                $patientId,
                $consentId,
                'CONSENT',
                $campaignName,
                $sanitized['mobile'],
                'AISENSY',
                $aisensyPayload,
                $aisensyResponse,
                $visitId
            );

            return ApiResponse::success($response, [
                'patient_id'       => $patientId,
                'consent_id'       => $consentId,
                'consent_token_id' => $tokenId,
                'consent_token'    => $rawToken,
                'expires_at'       => $expiresAt,
                'visit_id'         => $visitId,
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

            // Update patient consent status
            $stmt = $pdo->prepare('UPDATE patient_consents SET consent_status = ?, consent_granted_at = NOW() WHERE consent_id = ?');
            $stmt->execute(['CONSENTED', $tokenRow['contact_id']]);

            // Find matching patient via consent record and update consent status
            $stmt = $pdo->prepare(
                'SELECT pm.mobile FROM patient_consents pc
                JOIN patient_master pm ON pm.id = pc.patient_id
                WHERE pc.consent_id = ?'
            );
            $stmt->execute([$tokenRow['contact_id']]);
            $contact = $stmt->fetch();

            $affectedPatients = 0;
            if ($contact) {
                $stmt = $pdo->prepare('UPDATE patient_master SET consent_status = ?, consent_response_at = NOW() WHERE customer_id = ? AND mobile = ?');
                $stmt->execute(['subscribed', $tokenRow['customer_id'], $contact['mobile']]);
                $affectedPatients = $stmt->rowCount();
            }

            $pdo->commit();

            AppLogger::info($user, 'Consent agreed successfully', [
                'contact_id' => $tokenRow['contact_id'],
                'token_id' => $tokenRow['id'],
                'patients_updated' => $affectedPatients
            ]);

            return ApiResponse::success($response, [
                'consent_id' => $tokenRow['contact_id'],
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

    public function sendWhatsappMessage(Request $request, Response $response): Response
    {
        $body = (array) $request->getParsedBody();
        $sanitized = Validator::sanitizeString($body);
        $user = $sanitized['user'] ?? 'NA';

        AppLogger::info($user, 'Send WhatsApp message requested');

        $required = ['campaign_name', 'mobile', 'campaign_template_params', 'campaign_endpoint'];
        $missing = array_filter($required, function ($key) use ($sanitized) {
            return empty($sanitized[$key]); 
        });

        if (!empty($missing)) {
            return ApiResponse::error(
                $response,
                'Missing required fields: ' . implode(', ', $missing),
                null,
                200
            );
        }

        $campaignName = trim((string) $sanitized['campaign_name']);
        $destination = $this->normalizePhone($sanitized['mobile'] ?? null) ?? '';
        $campaignEndpoint = $sanitized['campaign_endpoint'] ?? '';
        $templateParams = $sanitized['campaign_template_params'] ?? [];

        if ($destination === '') {
            return ApiResponse::error($response, 'Invalid mobile number', null, 400);
        }

        $aisensyResponse = $this->callAiSensyCampaignApi(
            $campaignName,
            $destination,
            $templateParams,
            $campaignEndpoint
        );

        $aisensyPayload = $aisensyResponse['_payload'] ?? [];
        unset($aisensyResponse['_payload']);

        AppLogger::info($user, 'Send WhatsApp message response', [
            'campaign_name' => $campaignName,
            'destination' => $destination,
            'response' => $aisensyResponse,
        ]);

        return ApiResponse::success($response, [
            'campaign_name' => $campaignName,
            'destination' => $destination,
            'aisensy_response' => $aisensyResponse,
        ], 'WhatsApp message sent');
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
            'first_name',
            'last_name',
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

            $patientStmt = $pdo->prepare('INSERT INTO patient_master (customer_id, first_name, last_name, mobile, consent_status) VALUES (?, ?, ?, ?, ?)');
            $contactStmt = $pdo->prepare('INSERT INTO patient_consents (customer_id, patient_id, consent_status, purpose) VALUES (?, ?, ?, ?)');
            $tokenStmt = $pdo->prepare('INSERT INTO consent_tokens (customer_id, consent_id, token_hash, token_last4, status, expires_at) VALUES (?, ?, ?, ?, ?, ?)');

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
                        trim($data['first_name'] ?? ''),
                        trim($data['last_name'] ?? ''),
                        trim($data['mobile']),
                        'pending',
                    ]);
                    $patientId = (int) $pdo->lastInsertId();

                    $contactStmt->execute([
                        $customerId,
                        $patientId,
                        'PENDING',
                        'WHATSAPP_CONSENT',
                    ]);
                    $consentId = (int) $pdo->lastInsertId();

                    $rawToken = bin2hex(random_bytes(32));
                    $tokenHash = hash('sha256', $rawToken);
                    $tokenLast4 = substr($rawToken, -4);
                    $expiresAt = (new \DateTime('+' . self::CONSENT_TOKEN_VALIDITY_DAYS . ' days'))->format('Y-m-d H:i:s');

                    $tokenStmt->execute([
                        $customerId,
                        $consentId,
                        $tokenHash,
                        $tokenLast4,
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

    /**
     * Save a patient visit record.
     *
     * Required: customer_id, patient_id, visit_date
     * Optional: external_visit_id, source_type, source_reference, department,
     *           doctor_name, laboratory_id, bill_number, bill_amount,
     *           admission_number, ward, bed
     */
    public function saveVisit(Request $request, Response $response): Response
    {
        $body      = (array) $request->getParsedBody();
        $sanitized = Validator::sanitizeString($body);
        $user      = $sanitized['user'] ?? 'NA';

        AppLogger::info($user, 'New visit submission', [
            'patient_id'  => $sanitized['patient_id']  ?? null,
            'customer_id' => $sanitized['customer_id'] ?? null,
        ]);

        // Validate required fields
        $required = ['customer_id', 'patient_id', 'visit_date'];
        $missing  = array_filter($required, function ($k) use ($sanitized) { return empty($sanitized[$k]); });

        if (!empty($missing)) {
            AppLogger::warning($user, 'Visit save validation failed', ['missing' => $missing]);
            return ApiResponse::error(
                $response,
                'Missing required fields: ' . implode(', ', $missing),
                null,
                400
            );
        }

        $customerId = Validator::sanitizeInt($sanitized['customer_id']);
        $patientId  = Validator::sanitizeInt($sanitized['patient_id']);

        if ($customerId === null || $patientId === null) {
            return ApiResponse::error($response, 'Invalid customer_id or patient_id', null, 400);
        }

        try {
            $pdo = Database::getConnection();

            // Verify patient belongs to customer
            $stmt = $pdo->prepare('SELECT id FROM patient_master WHERE id = ? AND customer_id = ? LIMIT 1');
            $stmt->execute([$patientId, $customerId]);
            if (!$stmt->fetch()) {
                AppLogger::warning($user, 'Patient not found for visit save', [
                    'patient_id'  => $patientId,
                    'customer_id' => $customerId,
                ]);
                return ApiResponse::error($response, 'Patient not found', null, 404);
            }

            // Duplicate check by external_visit_id (when provided)
            $externalVisitId = trim((string) ($sanitized['external_visit_id'] ?? ''));
            if ($externalVisitId !== '') {
                $stmt = $pdo->prepare(
                    'SELECT visit_id FROM patient_visits WHERE customer_id = ? AND external_visit_id = ? LIMIT 1'
                );
                $stmt->execute([$customerId, $externalVisitId]);
                if ($stmt->fetch()) {
                    AppLogger::warning($user, 'Duplicate external_visit_id', [
                        'customer_id'       => $customerId,
                        'external_visit_id' => $externalVisitId,
                    ]);
                    return ApiResponse::error($response, 'Visit already exists', [], 200);
                }
            }

            $visitId = $this->insertVisitRecord($pdo, $customerId, $patientId, $sanitized);

            if ($visitId === null) {
                return ApiResponse::error($response, 'Failed to save visit', null, 500);
            }

            AppLogger::info($user, 'Visit saved successfully', [
                'visit_id'   => $visitId,
                'patient_id' => $patientId,
            ]);

            return ApiResponse::success($response, [
                'visit_id'    => $visitId,
                'patient_id'  => $patientId,
                'customer_id' => $customerId,
            ], 'Visit saved successfully');

        } catch (\Exception $e) {
            AppLogger::error($user, 'Database error saving visit', ['error' => $e->getMessage()]);
            return ApiResponse::error($response, 'Failed to save visit', null, 500);
        }
    }

}