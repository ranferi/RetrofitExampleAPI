<?php

class TstOperation
{
    private $gs;
    private $endpoint;

    function __construct()
    {
        require_once dirname(__FILE__) . '/DbConnect.php';
        require '../vendor/autoload.php';

        EasyRdf_Namespace::set('su', 'http://www.semanticweb.org/vlim1/ontologies/2018/4/susibo#');
        EasyRdf_Namespace::set('rdf', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#');

        $this->gs = new EasyRdf_GraphStore('http://localhost:3030/susibo/data');

        $this->endpoint = new EasyRdf_Sparql_Client("http://localhost:3030/susibo/query",
            "http://localhost:3030/susibo/update");

    }

    /***
     * Crea un usuario en la TripleStore
     * @param $usuario
     * @param $email
     * @param $password
     * @param $nombre
     * @param $apellido_paterno
     * @param $apellido_materno
     * @return int una de las constantes definidas
     */
    function createUser($usuario, $email, $password, $nombre, $apellido_paterno, $apellido_materno)
    {
        if (!$this->checkEmailExist($email)) {
            $pass_md5 = md5($password);
            $id = mt_rand(700000, 800000);

            while ($this->checkIDExist($id)) {
                $id = mt_rand(700000, 800000);
            }
            $resource = "su:user_" . $id;

            $graph = new EasyRdf_Graph();
            $graph->add($resource, 'su:usuario', $usuario);
            $graph->add($resource, 'su:email', $email);
            $graph->add($resource, 'su:password', $pass_md5);
            $graph->add($resource, 'su:nombre', $nombre);
            $graph->add($resource, 'su:idUsuario', $id);
            $graph->add($resource, 'su:apellidoPaterno', $apellido_paterno);
            $graph->add($resource, 'su:apellidoMaterno', $apellido_materno);
            $response = $this->gs->insertIntoDefault($graph);

            if ($response->isSuccessful())
                return USER_CREATED;
            return USER_CREATION_FAILED;
        }
        return USER_EXIST;
    }


    /***
     * Método para revisar si el correo ya existe en ontología
     * @param $email
     * @return bool
     */
    function checkEmailExist($email)
    {
        $result = $this->endpoint->query(
            'SELECT * WHERE {' .
            ' ?usuario su:email "' . $email . '" ' .
            '}'
        );

        return $result->numRows() > 0;
    }

    /***
     * Método para revisar si existe ya un ID en la ontología
     * @param $id
     * @return bool
     */
    function checkIDExist($id)
    {
        $result = $this->endpoint->query(
            'SELECT * WHERE {' .
            ' ?usuario su:idUsuario ' . $id . ' .' .
            '}'
        );

        return $result->numRows() > 0;
    }

    /**
     * Método para 'logear' usuario
     * @param $email
     * @param $pass
     * @return bool
     */
    function userLogin($email, $pass)
    {
        $password = md5($pass);
        $result = $this->endpoint->query("
        SELECT ?id
        WHERE {
            ?sujeto su:email \"$email\" .
            ?sujeto su:password \"$password\".
            ?sujeto su:idUsuario ?id .
        }"
        );

        return $result->numRows()  > 0;
    }

    //Method to get messages of a particular user

    function sendMessage($from, $to, $title, $message)
    {
        $id = mt_rand(100000, 200000);

        /* while ($this->idMessageExist($id)) {
            $id = mt_rand(100000, 200000);
        } */
        $resource = "su:message_" . $id;

        $graph = new EasyRdf_Graph();
        $graph->add($resource, 'su:titulo', $title);
        $graph->add($resource, 'su:mensaje', $message );
        $graph->add($resource, 'su:idMensaje', $id );
        $graph->add($resource, 'su:fecha', date("c"));
        $graph->addResource($resource, 'su:deUsuario', 'su:user_' . $from);
        $graph->addResource($resource, 'su:paraUsuario', 'su:user_' . $to);
        $response = $this->gs->insertIntoDefault($graph);


        if ($response->isSuccessful())
            return true;
        return false;

    }

    /***
     * Método para actualizar al usuario
     * @param $id
     * @param $usuario
     * @param $email
     * @param $pass
     * @param $nombre
     * @param $apellido_paterno
     * @param $apellido_materno
     * @return bool
     */
    function updateProfile($id, $usuario, $email, $pass, $nombre, $apellido_paterno, $apellido_materno)
    {
        $pass_md5 = md5($pass);

        $response = $this->endpoint->update("
            DELETE {?s ?p ?o}
            INSERT {
              ?s su:nombre \"$nombre\" ;
                su:email \"$email\" ;
                su:usuario \"$usuario\" ;
                su:apellidoPaterno \"$apellido_paterno\" ;
                su:apellidoMaterno \"$apellido_materno\" ;
                su:password \"$pass_md5\" ;
                su:idUsuario " . $id . " ." .
            "}
            WHERE  {
               ?s su:idUsuario " . $id . " ." .
            "?s ?p ?o . 
                  FILTER(isUri(?p) && STRSTARTS(STR(?p), STR(su:)))
            }"
        );

        if ($response->isSuccessful())
            return true;
        return false;
    }

    //Method to get all users

    function getMessages($userid)
    {

        $result = $this->endpoint->query("
        SELECT ?sujeto ?from_user ?user ?title ?mensaje ?date 
        WHERE {
                ?sujeto su:idUsuario " . $userid . " .
                ?sujeto su:usuario ?user .
                ?mensaje_res su:paraUsuario ?sujeto .
                ?mensaje_res su:deUsuario ?from .
                ?from su:usuario ?from_user .
                ?mensaje_res su:titulo ?title .
                ?mensaje_res su:fecha ?date .
                ?mensaje_res su:mensaje ?mensaje .
        }"
        );

        $messages = array();

        foreach ($result as $message) {
            $temp = array();
            $temp['from'] = $message->from_user->getValue();
            $temp['to'] = $message->user->getValue();
            $temp['title'] = $message->title->getValue();
            $temp['message'] = $message->mensaje->getValue();
            $temp['sent'] = $message->date->getValue();
            array_push($messages, $temp);
        }

        return $messages;
    }

    /**
     * Método para obtener un usuario por su email
     * @param $email
     * @return array
     */
    function getUserByEmail($email)
    {
        $result = $this->endpoint->query("
        SELECT ?sujeto ?id ?nombre ?usuario ?apellidoPaterno ?apellidoMaterno
        WHERE {
            ?sujeto su:email \"$email\" .
            ?sujeto su:idUsuario ?id .
            ?sujeto su:nombre ?nombre .
            ?sujeto su:apellidoPaterno ?apellidoPaterno .
            ?sujeto su:apellidoMaterno ?apellidoMaterno .
            ?sujeto su:usuario ?usuario .
        }"
        );

        $user = array();

        if ($result->numRows() == 1) {
            $user['id'] = $result->current()->id->getValue();
            $user['name'] = $result->current()->nombre->getValue();
            $user['lastName'] = $result->current()->apellidoPaterno->getValue();
            $user['mothersMaidenName'] = $result->current()->apellidoMaterno->getValue();
            $user['email'] = $email;
            $user['user'] = $result->current()->usuario->getValue();
        }
        // print_r($user);

        return $user;
    }

    /**
     * Método para enlistar todos los usuarios en la ontología
     * @return array
     */
    function getAllUsers()
    {

        $result = $this->endpoint->query("
        SELECT ?sujeto ?id ?nombre ?usuario  ?email
        WHERE {
            ?sujeto su:email ?email.
            ?sujeto su:idUsuario ?id .
            ?sujeto su:nombre ?nombre .
            ?sujeto su:usuario ?usuario .
        }"
        );

        //print_r($result);

        $users = array();

        foreach ($result as $user) {
            $temp = array();
            $temp['id'] = $user->id->getValue();
            $temp['name'] = $user->nombre->getValue();
            $temp['usuario'] = $user->usuario->getValue();
            $temp['email'] = $user->email->getValue();
            array_push($users, $temp);
        }
        return $users;
    }
}
