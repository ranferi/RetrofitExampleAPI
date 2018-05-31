<?php

class DbConnect
{
    // Variable para guardar la conexión a la BD
    private $con;

    function __construct()
    {

    }

    // Conecta a la BD
    function connect()
    {
        include_once dirname(__FILE__) . '/Constants.php';

        $this->con = new mysqli(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME);


        if (mysqli_connect_errno()) {
            echo "Error de conexión a (MySQL): " . mysqli_connect_error();
            return null;
        }

        return $this->con;
    }

}