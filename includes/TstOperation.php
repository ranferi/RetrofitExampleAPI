<?php

class TstOperation
{
    private $con;

    private $gs;
    private $sparql;

    function __construct()
    {
        EasyRdf_Namespace::set('su', 'http://www.semanticweb.org/vlim1/ontologies/2018/4/susibo#');
        require_once dirname(__FILE__) . '/DbConnect.php';
        require '../vendor/autoload.php';
        $db = new DbConnect();
        $this->con = $db->connect();
        $this->gs = new EasyRdf_GraphStore('http://localhost:3030/susibo/data');
        $this->sparql = new EasyRdf_Sparql_Client('http://localhost:3030/susibo/sparql');
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
            // $stmt = $this->con->prepare("INSERT INTO users (name, email, password, gender) VALUES (?, ?, ?, ?)");
            // $stmt->bind_param("ssss", $name, $email, $password, $gender);


            $graph1 = new EasyRdf_Graph();
            $graph1->add('su:i0436', 'su:usuario', $usuario);
            $graph1->add('su:i0436', 'su:email', $email);
            $graph1->add('su:i0436', 'su:password', $pass_md5);
            $graph1->add('su:i0436', 'su:nombre', $nombre);
            $graph1->add('su:i0436', 'su:apellidoPaterno', $apellido_paterno);
            $graph1->add('su:i0436', 'su:apellidoMaterno', $apellido_materno);
            $response = $this->gs->insertIntoDefault($graph1);

            if ($response->isSuccessful())
                return USER_CREATED;
            return USER_CREATION_FAILED;
        }
        return USER_EXIST;
    }

    //Method for user login
    function userLogin($email, $pass)
    {
        $password = md5($pass);
        $stmt = $this->con->prepare("SELECT id FROM users WHERE email = ? AND password = ?");
        $stmt->bind_param("ss", $email, $password);
        $stmt->execute();
        $stmt->store_result();
        return $stmt->num_rows > 0;
    }

    //Method to send a message to another user
    function sendMessage($from, $to, $title, $message)
    {
        $stmt = $this->con->prepare("INSERT INTO messages (from_users_id, to_users_id, title, message) VALUES (?, ?, ?, ?);");
        $stmt->bind_param("iiss", $from, $to, $title, $message);
        if ($stmt->execute())
            return true;
        return false;
    }

    //Method to update profile of user
    function updateProfile($id, $name, $email, $pass, $gender)
    {
        $password = md5($pass);
        $stmt = $this->con->prepare("UPDATE users SET name = ?, email = ?, password = ?, gender = ? WHERE id = ?");
        $stmt->bind_param("ssssi", $name, $email, $password, $gender, $id);
        if ($stmt->execute())
            return true;
        return false;
    }

    //Method to get messages of a particular user
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

    //Method to get user by email
    function getUserByEmail($email)
    {
        $stmt = $this->con->prepare("SELECT id, name, email, gender FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->bind_result($id, $name, $email, $gender);
        $stmt->fetch();
        $user = array();
        $user['id'] = $id;
        $user['name'] = $name;
        $user['email'] = $email;
        $user['gender'] = $gender;
        return $user;
    }

    //Method to get all users
    function getAllUsers(){
        $stmt = $this->con->prepare("SELECT id, name, email, gender FROM users");
        $stmt->execute();
        $stmt->bind_result($id, $name, $email, $gender);
        $users = array();
        while($stmt->fetch()){
            $temp = array();
            $temp['id'] = $id;
            $temp['name'] = $name;
            $temp['email'] = $email;
            $temp['gender'] = $gender;
            array_push($users, $temp);
        }
        return $users;
    }

    /***
     * Metodo para revisar si el correo ya existe en onto
     * @param $email
     * @return bool
     */
    function userExist($email)
    {
        $result = $this->sparql->query(
            'SELECT * WHERE {'.
            ' ?usuario su:email "' . $email . '" ' .
            '}'
        );

        return $result->numRows() > 0;
    }
}
