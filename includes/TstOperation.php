<?php

class TstOperation
{
    private $con;

    private $gs;
    private $sparql;
    public $endpoint;

    function __construct()
    {
        EasyRdf_Namespace::set('su', 'http://www.semanticweb.org/vlim1/ontologies/2018/4/susibo#');
        EasyRdf_Namespace::set('rdf', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#');
        require_once dirname(__FILE__) . '/DbConnect.php';
        require '../vendor/autoload.php';
        $db = new DbConnect();
        $this->con = $db->connect();
        $this->gs = new EasyRdf_GraphStore('http://localhost:3030/susibo/data');
        $this->sparql = new EasyRdf_Sparql_Client('http://localhost:3030/susibo/sparql');

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
        // $gs = new EasyRdf_GraphStore('http://localhost:3030/susibo/data');

        if (!$this->userExist($email)) {
            $pass_md5 = md5($password);
            $id = mt_rand(700000, 800000);

            while ($this->idExist($id)) {
                $id = mt_rand(700000, 800000);
            }
            $resource = "su:user_" . $id;

            $graph1 = new EasyRdf_Graph();
            $graph1->add($resource, 'su:usuario', $usuario);
            $graph1->add($resource, 'su:email', $email);
            $graph1->add($resource, 'su:password', $pass_md5);
            $graph1->add($resource, 'su:nombre', $nombre);
            $graph1->add($resource, 'su:identificador', $id);
            $graph1->add($resource, 'su:apellidoPaterno', $apellido_paterno);
            $graph1->add($resource, 'su:apellidoMaterno', $apellido_materno);
            $response = $this->gs->insertIntoDefault($graph1);

            if ($response->isSuccessful())
                return USER_CREATED;
            return USER_CREATION_FAILED;
        }
        return USER_EXIST;
    }

    //Method for user login

    /***
     * Método para revisar si el correo ya existe en ontología
     * @param $email
     * @return bool
     */
    function userExist($email)
    {
        $result = $this->sparql->query(
            'SELECT * WHERE {' .
            ' ?usuario su:email "' . $email . '" ' .
            '}'
        );

        return $result->numRows() > 0;
    }

    //Method to send a message to another user

    /***
     * Método para revisar si existe ya un ID en la ontología
     * @param $id
     * @return bool
     */
    function idExist($id)
    {
        $result = $this->sparql->query(
            'SELECT * WHERE {' .
            ' ?usuario su:identificador ' . $id . ' .' .
            '}'
        );

        return $result->numRows() > 0;
    }

    function userLogin($email, $pass)
    {
        $password = md5($pass);
        $stmt = $this->con->prepare("SELECT id FROM users WHERE email = ? AND password = ?");
        $stmt->bind_param("ss", $email, $password);
        $stmt->execute();
        $stmt->store_result();
        return $stmt->num_rows > 0;
    }

    //Method to get messages of a particular user

    function sendMessage($from, $to, $title, $message)
    {
        $stmt = $this->con->prepare("INSERT INTO messages (from_users_id, to_users_id, title, message) VALUES (?, ?, ?, ?);");
        $stmt->bind_param("iiss", $from, $to, $title, $message);
        if ($stmt->execute())
            return true;
        return false;
    }

    //Method to get user by email

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
                su:identificador " . $id . " ." .
            "}
            WHERE  {
               ?s su:identificador " . $id . " ." .
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
        $stmt = $this->con->prepare("SELECT messages.id, (SELECT users.name FROM users WHERE users.id = messages.from_users_id) as `from`, (SELECT users.name FROM users WHERE users.id = messages.to_users_id) as `to`, messages.title, messages.message, messages.sentat FROM messages WHERE messages.to_users_id = ?;");
        $stmt->bind_param("i", $userid);
        $stmt->execute();
        $stmt->bind_result($id, $from, $to, $title, $message, $sent);

        $messages = array();

        while ($stmt->fetch()) {
            $temp = array();

            $temp['id'] = $id;
            $temp['from'] = $from;
            $temp['to'] = $to;
            $temp['title'] = $title;
            $temp['message'] = $message;
            $temp['sent'] = $sent;

            array_push($messages, $temp);
        }

        return $messages;
    }

    function getUserByEmail($email)
    {
        $result = $this->endpoint->query("
        SELECT ?subject ?id ?nombre ?usuario
        WHERE {
            ?subject su:email \"$email\" .
            ?subject su:identificador ?id .
            ?subject su:nombre ?nombre .
            ?subject su:usuario ?usuario .
        }"
        );

        $user = array();
      
        if ($result->numRows() == 1) {
            $user['id'] = $copy->current()->id;
            $user['name'] = $copy->current()->nombre;
            $user['email'] = $email;
            $user['user'] = $copy->current()->usuario;
        }

        /* foreach ($result as $row) {
            $user['id'] = $row->id;
            $user['name'] = $row->nombre;
            $user['email'] = $email;
            $user['user'] = $row->usuario;
        } */

        // return print_r($result);
        // return print_r($copy->current()->id);
        return $user;
    }

    function getAllUsers()
    {
        $stmt = $this->con->prepare("SELECT id, name, email, gender FROM users");
        $stmt->execute();
        $stmt->bind_result($id, $name, $email, $gender);
        $users = array();
        while ($stmt->fetch()) {
            $temp = array();
            $temp['id'] = $id;
            $temp['name'] = $name;
            $temp['email'] = $email;
            $temp['gender'] = $gender;
            array_push($users, $temp);
        }
        return $users;
    }
}
