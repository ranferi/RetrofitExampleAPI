<?php
/*if (PHP_SAPI == 'cli-server') {
    // To help the built-in PHP dev server, check if the request was actually for
    // something which should probably be served as a static file
    $url  = parse_url($_SERVER['REQUEST_URI']);
    $file = __DIR__ . $url['path'];
    if (is_file($file)) {
        return false;
    }
}

require __DIR__ . '/../vendor/autoload.php';

session_start();

// Instantiate the app
$settings = require __DIR__ . '/../src/settings.php';
$app = new \Slim\App($settings);

// Set up dependencies
require __DIR__ . '/../src/dependencies.php';

// Register middleware
require __DIR__ . '/../src/middleware.php';

// Register routes
require __DIR__ . '/../src/routes.php';

// Run app
$app->run();*/

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

require '../vendor/autoload.php';
require_once '../includes/DbOperation.php';

//Creating a new app with the config to show errors
$app = new \Slim\App([
    'settings' => [
        'displayErrorDetails' => true
    ]
]);

//registering a new user
$app->post('/register', function (Request $request, Response $response) {
    if (isTheseParametersAvailable(array('name', 'email', 'password', 'gender'))) {
        $requestData = $request->getParsedBody();
        $name = $requestData['name'];
        $email = $requestData['email'];
        $password = $requestData['password'];
        $gender = $requestData['gender'];
        $db = new DbOperation();
        $responseData = array();

        $result = $db->registerUser($name, $email, $password, $gender);

        if ($result == USER_CREATED) {
            $responseData['error'] = false;
            $responseData['message'] = 'Registered successfully';
            $responseData['user'] = $db->getUserByEmail($email);
        } elseif ($result == USER_CREATION_FAILED) {
            $responseData['error'] = true;
            $responseData['message'] = 'Some error occurred';
        } elseif ($result == USER_EXIST) {
            $responseData['error'] = true;
            $responseData['message'] = 'This email already exist, please login';
        }

        $response->getBody()->write(json_encode($responseData));
    }
});

//user login route
$app->post('/login', function (Request $request, Response $response) {
    if (isTheseParametersAvailable(array('email', 'password'))) {
        $requestData = $request->getParsedBody();
        $email = $requestData['email'];
        $password = $requestData['password'];

        $db = new DbOperation();

        $responseData = array();

        if ($db->userLogin($email, $password)) {
            $responseData['error'] = false;
            $responseData['user'] = $db->getUserByEmail($email);
        } else {
            $responseData['error'] = true;
            $responseData['message'] = 'Invalid email or password';
        }

        $response->getBody()->write(json_encode($responseData));
    }
});

//getting all users
$app->get('/users', function (Request $request, Response $response) {
    $db = new DbOperation();
    $users = $db->getAllUsers();
    $response->getBody()->write(json_encode(array("users" => $users)));
});

//getting messages for a user
$app->get('/messages/{id}', function (Request $request, Response $response) {
    $userid = $request->getAttribute('id');
    $db = new DbOperation();
    $messages = $db->getMessages($userid);
    $response->getBody()->write(json_encode(array("messages" => $messages)));
});

//updating a user
$app->post('/update/{id}', function (Request $request, Response $response) {
    if (isTheseParametersAvailable(array('name', 'email', 'password', 'gender'))) {
        $id = $request->getAttribute('id');

        $requestData = $request->getParsedBody();

        $name = $requestData['name'];
        $email = $requestData['email'];
        $password = $requestData['password'];
        $gender = $requestData['gender'];


        $db = new DbOperation();

        $responseData = array();

        if ($db->updateProfile($id, $name, $email, $password, $gender)) {
            $responseData['error'] = false;
            $responseData['message'] = 'Updated successfully';
            $responseData['user'] = $db->getUserByEmail($email);
        } else {
            $responseData['error'] = true;
            $responseData['message'] = 'Not updated';
        }

        $response->getBody()->write(json_encode($responseData));
    }
});


//sending message to user
$app->post('/sendmessage', function (Request $request, Response $response) {
    if (isTheseParametersAvailable(array('from', 'to', 'title', 'message'))) {
        $requestData = $request->getParsedBody();
        $from = $requestData['from'];
        $to = $requestData['to'];
        $title = $requestData['title'];
        $message = $requestData['message'];

        $db = new DbOperation();

        $responseData = array();

        if ($db->sendMessage($from, $to, $title, $message)) {
            $responseData['error'] = false;
            $responseData['message'] = 'Message sent successfully';
        } else {
            $responseData['error'] = true;
            $responseData['message'] = 'Could not send message';
        }

        $response->getBody()->write(json_encode($responseData));
    }
});

//function to check parameters
function isTheseParametersAvailable($required_fields)
{
    $error = false;
    $error_fields = "";
    $request_params = $_REQUEST;

    foreach ($required_fields as $field) {
        if (!isset($request_params[$field]) || strlen(trim($request_params[$field])) <= 0) {
            $error = true;
            $error_fields .= $field . ', ';
        }
    }

    if ($error) {
        $response = array();
        $response["error"] = true;
        $response["message"] = 'Required field(s) ' . substr($error_fields, 0, -2) . ' is missing or empty';
        echo json_encode($response);
        return false;
    }
    return true;
}

$app->run();
