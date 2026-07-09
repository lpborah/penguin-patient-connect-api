<?php
declare(strict_types=1);

use Slim\App;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Database;
use App\ApiResponse;
use App\Validator;

use App\Controllers\CustomerController;
use App\Controllers\PatientController;
use App\Controllers\UserController;
use App\Controllers\AuthController;
use App\Middleware\AuthMiddleware;



return function (App $app): void {

    // Global auth middleware: validates JWT for all endpoints except /login
    //$app->add(new AuthMiddleware());


    $app->get('/', function (Request $request, Response $response) {
        return ApiResponse::success(
            $response,
            null,
            'API is up and running'
        );
    });

    $app->get('/health', function (Request $request, Response $response) {
        return ApiResponse::success(
            $response,
            null,
            'PPC API is up and running'
        );
    });


    // Customer Routes
    $customerController = new CustomerController();
    $app->get('/getCustomers', [$customerController, 'getCustomers']);
    $app->post('/saveCustomer', [$customerController, 'saveCustomer']);
    $app->post('/updateCustomer', [$customerController, 'updateCustomer']);
    $app->post('/deleteCustomer', [$customerController, 'deleteCustomer']);
    $app->post('/importCustomers', [$customerController, 'importCustomers']);
    $app->get('/getCustomerById', [$customerController, 'getCustomerById']);
    $app->get('/getCustomersWithAMCSubscriptions', [$customerController, 'getCustomersWithAMCSubscriptions']);
    
    // Patient Routes
    $patientController = new PatientController();
    $app->post('/savePatient', [$patientController, 'savePatient']);
    $app->post('/sendConsentMessage', [$patientController, 'sendConsentMessage']);
    //$app->get('/consentAgree', [$patientController, 'consentAgree']);
    // Friendly/RESTful alias for consent link handling
    $app->get('/consent/agree', [$patientController, 'consentAgree']);
    $app->post('/bulkImport', [$patientController, 'bulkImport']);
    
    // User Routes
    $userController = new UserController();
    $app->get('/getUsers', [$userController, 'getUsers']);
    $app->post('/saveUser', [$userController, 'saveUser']);
    $app->post('/updateUser', [$userController, 'updateUser']);
    $app->post('/deleteUser', [$userController, 'deleteUser']);
    $app->post('/restoreDeletedUser', [$userController, 'restoreDeletedUser']);
    $app->get('/getRoles', [$userController, 'getRoles']);


    //Auth or login Routes
    $authController = new AuthController();
    $app->post('/login', [$authController, 'login']);
    $app->post('/updatePassword', [$authController, 'updatePassword']);
    $app->post('/logout', [$authController, 'logout']);

};