<?php
declare(strict_types=1);

use Slim\Factory\AppFactory;
use App\Middleware\CorsMiddleware;

require __DIR__ . '/../vendor/autoload.php';

// Load environment variables
if (file_exists(__DIR__ . '/../.env')) {
    Dotenv\Dotenv::createImmutable(__DIR__ . '/..')->load();
}

$app = AppFactory::create();

// Set base path for subdirectory deployment from environment variable
$basePath = $_ENV['BASE_PATH'] ?? '/penguin-patient-connect-api';
if (!empty($basePath)) {
    $app->setBasePath($basePath);
}

// Middleware
$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();
$app->add(new CorsMiddleware());
$app->addErrorMiddleware(true, true, true);

// Routes
(require __DIR__ . '/../src/Routes.php')($app);

$app->run();
