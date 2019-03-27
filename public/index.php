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
use NlpTools\Tokenizers\WhitespaceAndPunctuationTokenizer;
// use \Psr\Http\Message\ResponseInterface as Response;
use Slim\Http\Response;

use \NlpTools\Tokenizers\WhitespaceTokenizer;
use \NlpTools\Similarity\JaccardIndex;
use \NlpTools\Similarity\CosineSimilarity;
use \NlpTools\Similarity\Simhash;

use NlpTools\Models\FeatureBasedNB;
use NlpTools\Documents\TrainingSet;
use NlpTools\Documents\TokensDocument;
use NlpTools\FeatureFactories\DataAsFeatures;
use NlpTools\Classifiers\MultinomialNBClassifier;


require '../vendor/autoload.php';
require_once '../includes/TstOperation.php';

/**
 * Se configura la app para que muestre los errores
 */
$app = new \Slim\App([
    'settings' => [
        'displayErrorDetails' => true
    ]
]);


/**
 * Registra un nuevo usuario
 */
$app->post('/register', function (Request $request, Response $response) {
    $params = isTheseParametersAvailable(array('usuario', 'email', 'password'));
    if (!$params["error"]) {
        $request_data = $request->getParsedBody();
        $usuario = $request_data['usuario'];
        $email = $request_data['email'];
        $password = $request_data['password'];
        $nombre = $request_data['nombre'] ?: '';
        $apellido_paterno = $request_data['apellido_paterno'] ?: '';
        $apellido_materno = $request_data['apellido_materno'] ?: '';

        $tst = new TstOperation();

        $response_data = array();

        $result = $tst->createUser($usuario, $email, $password, $nombre, $apellido_paterno, $apellido_materno);

        if ($result == USER_CREATED) {
            $response_data['error'] = false;
            $response_data['message'] = 'Registro exitoso';
            $response_data['user'] = $tst->getUserByEmail($email);
        } elseif ($result == USER_CREATION_FAILED) {
            $response_data['error'] = true;
            $response_data['message'] = 'Ocurrio un error';
        } elseif ($result == USER_EXIST) {
            $response_data['error'] = true;
            $response_data['message'] = 'Este usuario o correo ya existe, por favor inicia sesión';
        }

        return $response->withJson($response_data);
    } else {
        return $response->withJson($params);
    }

});

$app->get('/pass', function (Request $request, Response $response) {

$str = "incubo";
echo md5($str);
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
    $tst = new TstOperation();
    $users = $tst->getAllUsers();
    return $response->withJson(array("users" => $users));
});


$app->get('/prueba', function (Request $request, Response $response) {
    $tst = new TstOperation();
    $places = $tst->getAllPoints();
    return $response->withJson(array("places" => $places));
});

/**
 * Se obtienen todos lo usuarios
 */
$app->get('/nlp', function (Request $request, Response $response) {
    /*$s1 = "La comida no está tan buena, abusan de la grasa. No es malo el lugar pero por los precios podrías esperar más.
";
    $s2 = "por los precios podrías esperar más";

    $tok = new WhitespaceTokenizer();
    $J = new JaccardIndex();
    $cos = new CosineSimilarity();
    $simhash = new Simhash(16); // 16 bits hash

    $setA = $tok->tokenize($s1);
    $setB = $tok->tokenize($s2);

    printf (
        "
    Jaccard:  %.3f
    Cosine:   %.3f
    Simhash:  %.3f
    SimhashA: %s
    SimhashB: %s
    ",
        $J->similarity(
            $setA,
            $setB
        ),
        $cos->similarity(
            $setA,
            $setB
        ),
        $simhash->similarity(
            $setA,
            $setB
        ),
        $simhash->simhash($setA),
        $simhash->simhash($setB)
    );*/


    // ---------- Data ----------------
// data is taken from http://archive.ics.uci.edu/ml/datasets/SMS+Spam+Collection
// we use a part for training
    $testing = array(
        array('Barato', 'Precios accesibles'),
        array('Barato', 'A muy buen precio'),
        array('Moderado', 'A buen precio'),
        array('Moderado', 'Buena relación precio-costo'),
        array('Moderado', 'Precios no tan caros'),
        array('Moderado', 'Precio muy bueno'),
        array('Moderado', 'Los precios son justos para la calidad'),
        array('Moderado', 'Precio bueno a pesar de'),
        array('Moderado', 'Precio bueno para ser'),
        array('Caro', 'por los precios podrías esperar más'),
        array('Caro', 'Un poco caro'),
        array('Caro', 'Precios un poco elevados'),
        array('Caro', 'En general caro'),
        array('Caro', 'Precios algo caros'),
        array('Caro', 'Comida cara para la ración que sirven'),
        array('Caro', 'Precios excesivos'),
        array('Muy caro', 'Caro y malo')
    );


// and another for evaluating
    $training = array(
        array('Muy caro', 'Caro y malo, el alambre tenía pura cebolla y se tardaron horas en atender.'),
        array('Moderado', 'Tiene buena comida, buena cantina y buen ambiente, con música de salterio. La atención es muy buena. El lugar es pulcro y agradable. Cuenta con terraza hacia la calle de Gante, que es peatonal. El precio no es bajo, pero es razonable.'),
        array('Caro', 'La comida es cara para la ración que sirven. No caigas en la tentación de pedir mojitos al 2x1, no están bien preparados, mejor elige las margaritas'),
        array('Barato', 'El lugar es de un ambiente muy pesado(peligroso) , la música es muy repetida, el lugar es pequeño y el alcohol es barato o poco diverso'),
        array('Moderado', 'Una excelente opción dentro del primer cuadro del centro histórico. Buena relación precio-calidad. Es un poco pequeño el lugar y usualmente hay que hacer fila de espera para tener lugar, pero vale la pena.'),
        array('Caro', 'Muy buen lugar, los precios un poco elevados. También el lugar es pequeño y hay que esperar. El servicio está bien y tiene buen sabor.'),
        array('Moderado', 'Es un lugar pequeño pero muy acojedor, es bastante limpio y los que te atienden en ese lugar son bastante amables, la comida es muy rica y sus precios no son altos a pesar de que esta en la zona centro'),
        array('Moderado', 'Buen lugar,excelente vista,buena comida,precios no tan caros,pero sin preguntar ( cosa q ya no está permitida) te clavan la propina obligatoria! Y solo te venden vinos no muy buenos y en eso sí son muuuy caros! Todo lo demás es bueno,ojo estacionamiento cerca solo en bellas artes!"'),
        array('Muy caro', 'Cierran a las 9pm pero cocina deja de funcionar a las 7:30pm, parece que te están corriendo antes de tiempo y no dejan disfrutar la vista, además de que los precios son excesivos.'),
        array('Barato', 'Recomiendo los chilaquiles en salsa de cacahuate, el servicio fue bueno. Tiene precios accesibles'),
        array('Caro', 'O ya mejore mi paladar o decayo el sabor de este menu, siempre me gusto, desde la primera vez pero ahora si le veo varias fallas, te llenas pero le falta sabor a su comida, donde se fue? si subio el precio'),
        array('Barato', 'Muy buen menú y precios accesibles! Definitivamente volvería!'),
        array('Caro', 'Un lugar típico para comer algunas especialidades, un poco caro'),
        array('Caro', 'Buen lugar para departir un rato en compañía de los amigos o de la familia, el tratado de los meseros es bueno, los precios algo caros, ambiente familiar, lo recomiendo'),
        array('Caro', 'La comida no está tan buena, abusan de la grasa. No es malo el lugar pero por los precios podrías esperar más.'),
        array('Moderado', 'Excelente comida, los postres deliciosos y el café con cardamomo delicioso vale la pena probarlo, el precio es bueno para ser comida \nárabe. El espacio es tranquilo a pesar de estar en medio del centro y la atención es muy buena'),
        array('Moderado', 'Una gran experiencia!\nUna mezcla de gran atención, platillos diversos con opciones vegetarianas, limpieza en alimentos y en el establecimiento en general. Y los precios son justos para la calidad de los mismos. De mis lugares favoritos. Solo hay que perderle el miedo a la zona que no es del todo bonita por el comercio'),
        array('Caro', 'Muy bonito lugar. La comida muy buena pero un poco cara. El ambiente agradable')
    );

    $tset = new TrainingSet(); // will hold the training documents
    $tok = new WhitespaceTokenizer(); // will split into tokens
    $ff = new DataAsFeatures(); // see features in documentation

// ---------- Training ----------------
    foreach ($training as $d) {
        $tset->addDocument(
            $d[0], // class
            new TokensDocument(
                $tok->tokenize($d[1]) // The actual document
            )
        );
    }

    $model = new FeatureBasedNB(); // train a Naive Bayes model
    $model->train($ff, $tset);


// ---------- Classification ----------------
    $cls = new MultinomialNBClassifier($ff, $model);
    $correct = 0;
    foreach ($testing as $d) {
        // predict if it is spam or ham
        $prediction = $cls->classify(
            array('Caro', 'Muy Caro', 'Moderado', 'Barato'), // all possible classes
            new TokensDocument(
                $tok->tokenize($d[1]) // The document
            )
        );
        printf("precio %s\n ", $d[0]);
        printf("comentario %s\n", $d[1]);
        printf("!!!Prediccion %s\n", $prediction);
        if ($prediction == $d[0])
            $correct++;
    }

    printf("Accuracy: %.2f\n", 100 * $correct / count($testing));


    // echo '<pre>' . var_export($tok->tokenize($text), true) . '</pre>';
    return true;
});

/**
 * Se actualiza la información de un usuario
 */
$app->post('/update/{id}', function (Request $request, Response $response) {
    $params = isTheseParametersAvailable(array('usuario', 'email', 'password'));
    if (!$params["error"]) {
        $id = $request->getAttribute('id');
        $request_data = $request->getParsedBody();
        $usuario = $request_data['usuario'];
        $email = $request_data['email'];
        $password = $request_data['password'];
        $nombre = $request_data['nombre'] ?: '';
        $apellido_paterno = $request_data['apellido_paterno'] ?: '';
        $apellido_materno = $request_data['apellido_materno'] ?: '';

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
 * Se actualiza la información de un usuario
 */
$app->get('/visited/{id}', function (Request $request, Response $response) {
    $id = $request->getAttribute('id');

    $tst = new TstOperation();
    $response_data = array();

  /*  if ($tst->getAllVisitedPlacesByUser($id)) {
        $response_data['error'] = false;
        $response_data['message'] = 'Actualización exitosa';
        // $response_data['user'] = $tst->getUserByEmail($email);
    } else {
        $response_data['error'] = true;
        $response_data['message'] = 'No se actualizó';
    }*/

//    return $response->withJson($response_data);
    $places = $tst->getAllVisitedPlacesByUser($id);
    return $response->withJson(array("users" => $places));
});

/**
 * Se envia un mensaje a un usuario
 */
/*$app->post('/sendmessage', function (Request $request, Response $response) {
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
});*/

$app->post('/query/{id}', function (Request $request, Response $response) {
    $params["error"] = false;
    if (!$params["error"]) {
        $id = $request->getAttribute('id');
        $request_data = $request->getParsedBody();
        $id_place = $request_data['id_sitio'];
        $liked = $request_data['gusto'];
        $price = $request_data['precio'];
        $comment = $request_data['commentario'];

        $tst = new TstOperation();
        $response_data = array();

        if ($tst->insertUserRatingSite($id, $id_place, $liked, $price, $comment)) {
            $response_data['error'] = false;
            $response_data['message'] = 'Se envió tu opinión. ¡Gracias!';
            echo '<pre>' . var_export($response_data, true) . '</pre>';
        } else {
            $response_data['error'] = true;
            $response_data['message'] = 'Hubo un error, intenta de nuevo.';
            echo '<pre>' . var_export($response_data, true) . '</pre>';
        }
    }
    return null;
});

/**
 * Checa que los parámetros no estén vacíos
 *
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