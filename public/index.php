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
// use \Psr\Http\Message\ResponseInterface as Response;
use Slim\Http\Response;

require '../vendor/autoload.php';
require_once '../includes/TstOperation.php';

// Se configura la app para que muestre los errores
$app = new \Slim\App([
    'settings' => [
        'displayErrorDetails' => true
    ]
]);

/**
 * Registra un nuevo usuario
 */
$app->post('/register', function (Request $request, Response $response) {
    $params = isTheseParametersAvailable(array('usuario', 'email', 'password', 'nombre', 'apellido_paterno', 'apellido_materno'));
    if (!$params["error"]) {
        $request_data = $request->getParsedBody();
        $usuario = $request_data['usuario'];
        $email = $request_data['email'];
        $password = $request_data['password'];
        $nombre = $request_data['nombre'];
        $apellido_paterno = $request_data['apellido_paterno'];
        $apellido_materno = $request_data['apellido_materno'];

        $tst = new TstOperation();

        $response_data = array();

        $result = $tst->createUser($usuario, $email, $password, $nombre,  $apellido_paterno, $apellido_materno);

        if ($result == USER_CREATED) {
            $response_data['error'] = false;
            $response_data['message'] = 'Registro exitoso';
            // $response_data['user'] = $db->getUserByEmail($email);
        } elseif ($result == USER_CREATION_FAILED) {
            $response_data['error'] = true;
            $response_data['message'] = 'Ocurrio un error';
        } elseif ($result == USER_EXIST) {
            $response_data['error'] = true;
            $response_data['message'] = 'Este correo ya existe, por favor inicia sesión';
        }

        return $response->withJson($response_data);
    } else {
        return $response->withJson($params);
    }

});

/***
 * Ruta para 'logear' al usuario
 */
$app->post('/login', function (Request $request, Response $response) {
    $params = isTheseParametersAvailable(array('email', 'password'));
    if (!$params["error"]) {
        $request_data = $request->getParsedBody();
        $email = $request_data['email'];
        $password = $request_data['password'];

        $tst = new TstOperation();

        $response_data = array();

        if ($tst->userLogin($email, $password)) {
            $response_data['error'] = false;
            $response_data['user'] = $tst->getUserByEmail($email);
        } else {
            $response_data['error'] = true;
            $response_data['message'] = 'Email o password inválidos';
        }

        return $response->withJson($response_data);
    } else {
        return $response->withJson($params);
    }
});

/**
 * Se obtienen todos lo usuarios
 */
$app->get('/users', function (Request $request, Response $response) {
    $db = new DbOperation();
    $users = $db->getAllUsers();
    $response->withJson(array("users" => $users));
});

/**
 * Se traen todos los mensajes de un usuario
 */
$app->get('/messages/{id}', function (Request $request, Response $response) {
    $userid = $request->getAttribute('id');
    $tst = new TstOperation();
    $messages = $tst->getMessages($userid);

    return $response->withJson(array("messages" => $messages));

});

/**
 * Se actualiza la información de un usuario
 */
$app->post('/update/{id}', function (Request $request, Response $response) {
    $params = isTheseParametersAvailable(array('usuario', 'email', 'password', 'nombre', 'apellido_paterno', 'apellido_materno'));
    if (!$params["error"]) {
        $id = $request->getAttribute('id');
        $request_data = $request->getParsedBody();
        $usuario = $request_data['usuario'];
        $email = $request_data['email'];
        $password = $request_data['password'];
        $nombre = $request_data['nombre'];
        $apellido_paterno = $request_data['apellido_paterno'];
        $apellido_materno = $request_data['apellido_materno'];

        $tst = new TstOperation();
        $response_data = array();

        if ($tst->updateProfile($id, $usuario, $email, $password, $nombre, $apellido_paterno, $apellido_materno)) {
            $response_data['error'] = false;
            $response_data['message'] = 'Actualización exitosa';
            $response_data['user'] = $tst->getUserByEmail($email);
        } else {
            $response_data['error'] = true;
            $response_data['message'] = 'No se actualizó';
        }

        return $response->withJson($response_data);
    } else {
        return $response->withJson($params);
    }
});

/**
 * Se envia un mensaje a un usuario
 */
$app->post('/sendmessage', function (Request $request, Response $response) {
    $params = isTheseParametersAvailable(array('from', 'to', 'title', 'message'));
    if (!$params["error"]) {
        $request_data = $request->getParsedBody();
        $from = $request_data['from'];
        $to = $request_data['to'];
        $title = $request_data['title'];
        $message = $request_data['message'];

        $tst = new TstOperation();

        $response_data = array();

        if ($tst->sendMessage($from, $to, $title, $message)) {
            $response_data['error'] = false;
            $response_data['message'] = 'Mensaje enviado';
        } else {
            $response_data['error'] = true;
            $response_data['message'] = 'No se pudo enviar el mensaje';
        }

        return $response->withJson($response_data);
    } else {
        return $response->withJson($params);
    }
});

/**
 * Checa que los parametros no esten vacios
 * @param $required_fields
 * @return array
 */
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

    $response = array();
    if ($error) {
        $response["error"] = true;
        $response["message"] = 'Los campos [' . substr($error_fields, 0, -2) . '] faltan o están vacíos';
    } else {
        $response["error"] = false;
    }
    return $response;
}

$app->run();

