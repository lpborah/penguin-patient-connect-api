<?php
declare(strict_types=1);

use Slim\App;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use App\Database;
use App\ApiResponse;
use App\Validator;
use App\Utility;

return function (App $app): void {

    $app->get('/', function (Request $request, Response $response) {
        return ApiResponse::success(
            $response,
            null,
            'API is up and running'
        );
    });

    // Get All Messages
    $app->get('/getMessages', function (Request $request, Response $response) {
        $pdo = Database::getConnection();
        $stmt = $pdo->query('SELECT * FROM contact_messages ORDER BY created_at DESC');
        $data = $stmt->fetchAll();

        return ApiResponse::success($response, $data, 'Messages retrieved successfully');
    });

    // Update Message Status
    $app->post('/updateMessage', function (Request $request, Response $response) {
        // Read payload or params
        $body = (array) $request->getParsedBody();

        // Sanitize input
        $sanitized = Validator::sanitizeString($body);

        // Validate required fields
        $required = ['id', 'status'];
        $missing = array_filter($required, function($key) use ($sanitized) { return empty($sanitized[$key]); });
        if (!empty($missing)) {
            return ApiResponse::error(
                $response,
                'Missing required fields: ' . implode(', ', $missing),
                null,
                400
            );
        }

        $id = Validator::sanitizeInt($sanitized['id']);
        if ($id === null) {
            return ApiResponse::error($response, 'Invalid message ID', null, 400);
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('UPDATE contact_messages SET status = ? WHERE id = ?');
        $stmt->execute([
            $sanitized['status'],
            $id
        ]);

        $affected = $stmt->rowCount();

        return ApiResponse::success($response, ['affected' => $affected], 'Message status updated successfully');
    });

    // Save Message
    $app->post('/saveMessage', function (Request $request, Response $response) {
        // Read payload or params
        $body = (array) $request->getParsedBody();

        // Sanitize input
        $sanitized = Validator::sanitizeString($body);

        // Validate required fields
        $required = ['name', 'email', 'phone', 'subject', 'message'];
        $missing = array_filter($required, function($key) use ($sanitized) { return empty($sanitized[$key]); });
        if (!empty($missing)) {
            return ApiResponse::error(
                $response,
                'Missing required fields: ' . implode(', ', $missing),
                null,
                400
            );
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('INSERT INTO contact_messages (name, email, phone, subject, message, ip_address) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $sanitized['name'],
            $sanitized['email'],
            $sanitized['phone'],
            $sanitized['subject'],
            $sanitized['message'],
            'Not Viewed',
            Utility::getClientIp()
        ]);
        $id = (int) $pdo->lastInsertId();

        return ApiResponse::success($response, ['id' => $id], 'Resource created successfully');
    });

    // Save Message and Send Mail
    $app->post('/contactUs', function (Request $request, Response $response) {
        // Read payload or params
        $body = (array) $request->getParsedBody();

        $sanitized = Validator::sanitizeString($body);

        // Validate required fields
        $required = ['name', 'email', 'phone', 'subject', 'message'];
        $missing = array_filter($required, function($key) use ($sanitized) { return empty($sanitized[$key]); });
        if (!empty($missing)) {
            return ApiResponse::error(
                $response,
                'Missing required fields: ' . implode(', ', $missing),
                null,
                400
            );
        }

        // Save Data
        $dataSaved = false;
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare('INSERT INTO contact_messages (name, email, phone, subject, message, status, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([
                $sanitized['name'],
                $sanitized['email'],
                $sanitized['phone'],
                $sanitized['subject'],
                $sanitized['message'],
                'Not Viewed',
                Utility::getClientIp()
            ]);

            $id = (int) $pdo->lastInsertId();

            if ($id > 0) {
                $dataSaved = true;
            }

        } catch (PDOException $e) {
            return ApiResponse::error($response, "Could not save data. Mysql Database Error: {$e->getMessage()}");
        }

        if (!$dataSaved) {
            exit(); // Stop, don't execute code below.
        }

        // Send an email
        //$to = 'info@penguinhealthtech.com';
        $to = 'lok.b.chetri@gmail.com';
        $cc = 'borah.lakshmi@gmail.com';
        $from = 'info@penguinhealthtech.com';
        $fromName = 'Penguin HealthTech';
        $emailSubject = 'New Contact Message From Penguin HealthTech | Sent By ' . $sanitized['name'];

        // Mail content
        $mailContent = '
				<p>Following message has been received from Penguin HealthTech website:</p>
				<p><b>Name: </b>' . $sanitized['name'] . '</p>
				<p><b>Email: </b>' . $sanitized['email'] . '</p>
				<p><b>Phone: </b>' . $sanitized['phone'] . '</p>
				<p><b>Subject: </b>' . $sanitized['subject'] . '</p>
				<p><b>Message: </b> </p> <p>' . $sanitized['message'] . '</p>
				<p><b>IP Address: </b> </p> <p>' . Utility::getClientIp() . '</p>
		';
        $mail = new PHPMailer(true); // Passing `true` enables exceptions

        try {
            //Server settings
            $mail->isSMTP();
            $mail->Host = 'smtpout.secureserver.net'; // Your SMTP host
            $mail->SMTPAuth = true;
            $mail->Username = 'info@penguinhealthtech.com'; // Your SMTP username
            $mail->Password = 'PHT@2026#Pen99'; // Your SMTP password
            $mail->SMTPSecure = 'ssl'; // Use TLS or SSL
            $mail->Port = 465; // Port for TLS/SSL

            //Recipients
            $mail->setFrom($from, $fromName); // Sender's email and name
            $mail->addAddress($to); // Recipient email
            $mail->addCC($cc); // Add CC recipient
            $mail->addReplyTo($sanitized['email'], $sanitized['name']); // Set Reply-To to user's email


            //Content
            $mail->isHTML(true);
            $mail->Subject = $emailSubject;
            $mail->Body = $mailContent;

            $mail->send();
            return ApiResponse::success($response, null, 'Email sent successfully');
        } catch (Exception $e) {
            return ApiResponse::error($response, "Email could not be sent. Mailer Error: {$mail->ErrorInfo}");
        }

    });

    //==================================================================================
    // Get All Resources
    $app->get('/getResources', function (Request $request, Response $response) {
        $pdo = Database::getConnection();
        $stmt = $pdo->query('SELECT * FROM resources ORDER BY id DESC');
        $data = $stmt->fetchAll();

        return ApiResponse::success($response, $data, 'Resources retrieved successfully');
    });

    // Save Resource
    $app->post('/saveResource', function (Request $request, Response $response) {
        // Read payload or params
        $body = (array) $request->getParsedBody();

        // Sanitize input
        $sanitized = Validator::sanitizeString($body);

        // Validate required fields
        $required = ['category', 'resourceName', 'url'];
        $missing = array_filter($required, function($key) use ($sanitized) { return empty($sanitized[$key]); });
        if (!empty($missing)) {
            return ApiResponse::error(
                $response,
                'Missing required fields: ' . implode(', ', $missing),
                null,
                400
            );
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('INSERT INTO resources (category, resourceName, url) VALUES (?, ?, ?)');
        $stmt->execute([
            $sanitized['category'],
            $sanitized['resourceName'],
            $sanitized['url']
        ]);
        $id = (int) $pdo->lastInsertId();

        return ApiResponse::success($response, ['id' => $id], 'Resource created successfully');
    });

    // Get Resource By ID
    $app->get('/resources/{id}', function (Request $request, Response $response, $args) {
        $id = Validator::sanitizeInt($args['id']);

        if ($id === null) {
            return ApiResponse::error($response, 'Invalid resource ID', null, 400);
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT * FROM resources WHERE id = ?');
        $stmt->execute([$id]);
        $data = $stmt->fetch();

        if (!$data) {
            return ApiResponse::error($response, 'Resource not found', null, 404);
        }

        return ApiResponse::success($response, $data, 'Resource retrieved successfully');
    });

    // Delete Resource By ID
    $app->post('/deleteResource', function (Request $request, Response $response) {
        // Read payload or params
        $body = (array) $request->getParsedBody();

        // Sanitize input
        $sanitized = Validator::sanitizeString($body);

        // Validate required fields
        $required = ['id'];
        $missing = array_filter($required, function($key) use ($sanitized) { return empty($sanitized[$key]); });
        if (!empty($missing)) {
            return ApiResponse::error(
                $response,
                'Missing required fields: ' . implode(', ', $missing),
                null,
                400
            );
        }
        $id = Validator::sanitizeInt($sanitized['id']);

        if ($id === null) {
            return ApiResponse::error($response, 'Invalid resource ID', null, 400);
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('DELETE FROM resources WHERE id = ?');
        $stmt->execute([$id]);

        return ApiResponse::success($response, null, 'Resource deleted successfully');
    });

    //Update Resource By ID
    $app->post('/updateResource', function (Request $request, Response $response) {
        // Read payload or params
        $body = (array) $request->getParsedBody();

        // Sanitize input
        $sanitized = Validator::sanitizeString($body);

        // Validate required fields
        $required = ['category', 'resourceName', 'url', 'id'];
        $missing = array_filter($required, function($key) use ($sanitized) { return empty($sanitized[$key]); });
        if (!empty($missing)) {
            return ApiResponse::error(
                $response,
                'Missing required fields: ' . implode(', ', $missing),
                null,
                400
            );
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('UPDATE resources SET category = ?, resourceName = ?, url = ? WHERE id = ?');

        $stmt->execute([
            $sanitized['category'],
            $sanitized['resourceName'],
            $sanitized['url'],
            $sanitized['id']   // Make sure you have this in your request!
        ]);

        // Optional: Check if update succeeded
        $affected = $stmt->rowCount();

        return ApiResponse::success($response, ['affected' => $affected], 'Resource created successfully');
    });

    //===================================================================================
    // Get all Clients
    $app->get('/getClients', function (Request $request, Response $response) {
        $pdo = Database::getConnection();
        $stmt = $pdo->query('SELECT * FROM clients ORDER BY client_id DESC');
        $data = $stmt->fetchAll();

        return ApiResponse::success($response, $data, 'Clients retrieved successfully');
    });
    // Get clients by Id


    // Save client
    $app->post('/saveClient', function (Request $request, Response $response) {
        // Read payload or params
        $body = (array) $request->getParsedBody();

        // Sanitize input
        $sanitized = Validator::sanitizeString($body);

        // Validate required fields
        $required = ['company_name', 'contact_person', 'phone', 'email', 'address','city','state','country', 'gst_number'];
        $missing = array_filter($required, function($key) use ($sanitized) { return empty($sanitized[$key]); });
        if (!empty($missing)) {
            return ApiResponse::error(
                $response,
                'Missing required fields: ' . implode(', ', $missing),
                null,
                400
            );
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('INSERT INTO clients (company_name, contact_person, phone, email, address, city, state, country, gst_number) VALUES (?, ?, ?, ?,?,?,?,?,?)');
        $stmt->execute([
            $sanitized['company_name'],
            $sanitized['contact_person'],
            $sanitized['phone'],
            $sanitized['email'],
            $sanitized['address'],
            $sanitized['city'],
            $sanitized['state'],
            $sanitized['country'],
            $sanitized['gst_number']

        ]);
        $id = (int) $pdo->lastInsertId();

        return ApiResponse::success($response, ['client_id' => $id], 'Resource created successfully');
    });

    // Update client
    // Delete client

};