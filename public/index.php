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
require_once '../includes/TstOperation.php';

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
            $responseData['message'] = 'Registro exitoso';
            $responseData['user'] = $db->getUserByEmail($email);
        } elseif ($result == USER_CREATION_FAILED) {
            $responseData['error'] = true;
            $responseData['message'] = 'Ocurrio un error';
        } elseif ($result == USER_EXIST) {
            $responseData['error'] = true;
            $responseData['message'] = 'Este correo ya existe, por favor inicia sesión';
        }

        $response->getBody()->write(json_encode($responseData));
    }
});

$app->post('/create', function (Request $request, Response $response) {
    if (isTheseParametersAvailable(array('usuario', 'email', 'password', 'nombre', 'apellido_paterno', 'apellido_materno'))) {
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
            // $responseData['user'] = $db->getUserByEmail($email);
        } elseif ($result == USER_CREATION_FAILED) {
            $response_data['error'] = true;
            $response_data['message'] = 'Ocurrio un error';
        } elseif ($result == USER_EXIST) {
            $response_data['error'] = true;
            $response_data['message'] = 'Este correo ya existe, por favor inicia sesión';
        }

        $response->getBody()->write(json_encode($response_data));
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
            $responseData['message'] = 'Email o password inválidos';
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

    // $foaf = new EasyRdf_Graph("http://njh.me/foaf.rdf");
    // $foaf->load();
    // $me = $foaf->primaryTopic();
    // echo "My name is: ".$me->get('foaf:name')."\n";

    // $response->getBody()->write("My name is: " . $me->get('foaf:name')."\n");
});

// obtener todos las triples que tengan como predicado su:email
$app->get('/emailAsPredicate', function (Request $request, Response $response) {
    // $gs = new EasyRdf_GraphStore('http://localhost:3030/susibo/data/');
    EasyRdf_Namespace::set('su', 'http://www.semanticweb.org/vlim1/ontologies/2018/4/susibo#');
    $sparql = new EasyRdf_Sparql_Client('http://localhost:3030/susibo/sparql');
    require_once "html_tag_helpers.php";
    $result = $sparql->query(
        'SELECT ?subject ?predicate ?object ' .
        ' WHERE {'.
        '  ?subject su:email ?object .'.
        '}'
    );
    foreach ($result as $row) {
        echo "<li>".link_to($row->subject, $row->object)."</li>\n";
    }
});


// insertar un usuario directo
$app->post('/insertUser', function (Request $request, Response $response) {
    EasyRdf_Namespace::set('su', 'http://www.semanticweb.org/vlim1/ontologies/2018/4/susibo#');
    $gs = new EasyRdf_GraphStore('http://localhost:3030/susibo/data');
    // Add the current time in a graph
    $graph1 = new EasyRdf_Graph();
    $graph1->add('su:i0434', 'su:nombre', 'Craig');
    $graph1->add('su:i0434', 'su:apellidoPaterno', 'Ellis');
    $graph1->add('su:i0434', 'su:email', 'craigellis@yahoo.com');
    $gs->insertIntoDefault($graph1);
    // Get the graph back out of the graph store and display it
    // $graph2 = $gs->get('time.rdf');
    //print $graph2->dump();

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
            $responseData['message'] = 'Actualización exitosa';
            $responseData['user'] = $db->getUserByEmail($email);
        } else {
            $responseData['error'] = true;
            $responseData['message'] = 'No se actualizó';
        }

        $response->getBody()->write(json_encode($responseData));
    }
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
            $responseData['message'] = 'Actualización exitosa';
            $responseData['user'] = $db->getUserByEmail($email);
        } else {
            $responseData['error'] = true;
            $responseData['message'] = 'No se actualizó';
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
            $responseData['message'] = 'Mensaje enviado';
        } else {
            $responseData['error'] = true;
            $responseData['message'] = 'No se pudo enviar el mensaje';
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
        $response["message"] = 'Los campos ' . substr($error_fields, 0, -2) . ' faltan o están vacíos';
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        return false;
    }
    return true;
}

$app->run();
