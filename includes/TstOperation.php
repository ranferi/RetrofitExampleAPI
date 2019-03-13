<?php

class TstOperation
{
    private $gs;
    private $endpoint;

    function __construct()
    {
        require_once dirname(__FILE__) . '/Constants.php';
        require '../vendor/autoload.php';

        EasyRdf_Namespace::set('su', 'http://www.semanticweb.org/ranferi/ontologies/2018/9/ssrsi_onto#');
        EasyRdf_Namespace::set('rdf', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#');
        EasyRdf_Namespace::set('rdfs', 'http://www.w3.org/2000/01/rdf-schema#');
        EasyRdf_Namespace::set('owl', 'http://www.w3.org/2002/07/owl#');

        $this->gs = new  EasyRdf_GraphStore('http://localhost:3030/repositories/prueba3/rdf-graphs/service');

        $this->endpoint = new EasyRdf_Sparql_Client("http://localhost:3030/repositories/prueba3",
            "http://localhost:3030/repositories/prueba3/statements");
    }

    /***
     * Crea un usuario en la TripleStore
     *
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
        if (!$this->checkUserExist($usuario) && !$this->checkEmailExist($email)) {
            $pass_md5 = md5($password);
            $id = mt_rand(700000, 800000);

            while ($this->checkIDExist($id, "user")) {
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
     *
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
     * Método para revisar si el correo ya existe en ontología
     *
     * @param $user
     * @return bool
     */
    function checkUserExist($user)
    {
        $result = $this->endpoint->query(
            'SELECT * WHERE {' .
            ' ?usuario su:usuario "' . $user . '" ' .
            '}'
        );

        return $result->numRows() > 0;
    }


    /**
     * Método para 'logear' usuario
     *
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

    /***
     * Método para actualizar al usuario
     * 
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

    /**
     * Método para obtener un usuario por su email
     * @param $user
     * @return array
     */
    function getUserByUsername($user)
    {
        $result = $this->endpoint->query("
        SELECT ?sujeto ?id ?nombre ?usuario ?apellidoPaterno ?apellidoMaterno
        WHERE {
            ?sujeto su:usuario \"$user\" .
            ?sujeto su:idUsuario ?id .
            ?sujeto su:nombre ?nombre .
            ?sujeto su:apellidoPaterno ?apellidoPaterno .
            ?sujeto su:apellidoMaterno ?apellidoMaterno .
            ?sujeto su:email ?email.
        }"
        );
        $user = array();

        if ($result->numRows() == 1) {
            $user['id'] = $result->current()->id->getValue();
            $user['name'] = $result->current()->nombre->getValue();
            $user['lastName'] = $result->current()->apellidoPaterno->getValue();
            $user['mothersMaidenName'] = $result->current()->apellidoMaterno->getValue();
            $user['user'] = $user;
            $user['email'] = $result->current()->email->getValue();
        }

        return $user;
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

    function getAllPoints()
    {
        $result = $this->endpoint->query("
        SELECT ?sujeto ?id ?medi ?latitud ?longitud ?dir ?musica
        WHERE {
            ?sujeto su:idSitio ?id .
            ?sujeto a [
                a owl:Restriction;
                owl:onProperty su:tieneUnValorMEDI;
                owl:someValuesFrom ?medi
            ] .
            ?sujeto su:tienePropiedad/su:direccionSitio ?dir .
            ?sujeto su:tienePropiedad/su:musica ?musica .
            OPTIONAL { ?sujeto su:tienePropiedad/su:latitud ?latitud . }
            OPTIONAL { ?sujeto su:tienePropiedad/su:longitud ?longitud . }
        }
        LIMIT 5"
        );

        $points = array();

        foreach ($result as $place) {
            $temp = array();
            $temp_id = $place->id->getValue();
            $temp['id'] = $temp_id;
            $temp['medi'] = $place->medi->localName();
            if (isset($place->latitud))
                $temp['latitud'] = $place->latitud->getValue();
            if (isset($place->longitud))
                $temp['longitud'] = $place->longitud->getValue();
            $temp['direccion'] = $place->dir->getValue();
            $temp['musica'] = $place->musica->getValue();

            $second = $this->endpoint->query("
                SELECT ?sujeto ?nombreSitio ?base
                WHERE {
                    ?sujeto su:idSitio " . $temp_id . " .
                    ?sujeto su:tienePropiedad ?prop .
                    ?prop su:nombreSitio ?nombreSitio .
                    ?prop su:provieneDeBD ?base .
                }"
            );

            $names = array();
            foreach ($second as $name) {
                $temp_2 = array();
                $temp_2['nombre_sitio'] = $name->nombreSitio->getValue();
                $temp_2['proviene'] = $name->base->localName();
                array_push($names, $temp_2);
            }
            $temp['nombres'] = $names;

            $third = $this->endpoint->query("
                SELECT ?sujeto ?calificacion ?base
                WHERE {
                    ?sujeto su:idSitio " . $temp_id . " .
                    ?sujeto su:tienePropiedad ?prop .
                    ?prop su:calificacion ?calificacion .
                    ?prop su:provieneDeBD ?base .
                }"
            );

            $ratings = array();
            foreach ($third as $rating) {
                $temp_3 = array();
                $temp_3['calificacion'] = $rating->calificacion->getValue();
                $temp_3['proviene'] = $rating->base->localName();
                array_push($ratings, $temp_3);
            }
            $total = 0.0;
            if ($ratings) {
                foreach ($ratings as $rating) {
                    if ($rating['proviene'] == "GooglePlaces")
                        $total += $rating['calificacion'] * 2.0;
                    else
                        $total += $rating['calificacion'];
                }
                $total = $total / 2.0;
            }
            $temp['calificaciones'] = $ratings;
            $temp['total'] = $total;


            // Categorias
            $fourth = $this->endpoint->query("
                SELECT ?categoria ?proviene
                WHERE {
                    ?s su:idSitio " . $temp_id . " .
                    ?s su:tienePropiedad ?prop .
                    ?prop su:categoria ?prop1 .
                    ?prop1 rdf:type ?categoria .
                    ?categoria rdfs:subClassOf [ rdf:rest* [ owl:onProperty su:esParteDeBD ; owl:allValuesFrom ?proviene ] ]
                    FILTER NOT EXISTS { 
                        ?prop1 a ?otra .
                        ?otra rdfs:subClassOf ?categoria .
                        FILTER(?otra != ?categoria)
                    }
                }"
            );

            $cats = array();
            foreach ($fourth as $cat) {
                $temp_3 = array();
                $temp_3['categoria'] = $cat->categoria->localName();
                $temp_3['proviene'] = $cat->proviene->localName();
                array_push($cats, $temp_3);
            }
            $temp['categorias'] = $cats;


            // Imagenes
            $fifth = $this->endpoint->query("
                SELECT ?sujeto ?imagen
                WHERE {
                    ?sujeto su:idSitio " . $temp_id . " .
                    ?sujeto su:tienePropiedad ?prop .
                    ?prop su:imagenSitio ?imagen .
                }"
            );

            $images = array();
            foreach ($fifth as $img) {
                $temp_3 = array();
                $temp_3['imagen'] = $img->imagen->getValue();
                array_push($images, $temp_3);
            }
            $temp['imagenes'] = $images;


            // Comentarios
            $sixth = $this->endpoint->query("
                SELECT ?sujeto ?comentario ?base
                WHERE {
                    ?sujeto su:idSitio " . $temp_id . " .
                    ?sujeto su:tieneComentario ?prop .
                    ?prop su:comentario ?comentario .
                    ?prop su:provieneDeBD ?base .
                }"
            );

            $comments = array();
            foreach ($sixth as $comment) {
                $temp_3 = array();
                $temp_3['comentario'] = $comment->comentario->getValue();
                $temp_3['proviene'] = $comment->base->localName();
                array_push($comments, $temp_3);
            }
            $temp['comentarios'] = $comments;

            array_push($points, $temp);
        }
        return $points;
    }

    function siteVisited() {}

    function siteLiked() {}

    function insertUserRatingSite($id, $idSitio, $liked, $price, $comment) {
        $id_r1 = $this->createID(200000, 300000, "user_place");
        $id_r2 = $this->createID(300000, 400000, "rating");
        $id_r3 = $this->createID(400000, 500000, "comment");

        $resource1 = "su:r_user_place_" . $id_r1;
        $resource2 = "su:r_user_rating_" . $id_r2;
        $resource3 = "su:r_user_comment_" . $id_r3;
        $resource4 = "su:place_" . $idSitio;

        $result = $this->endpoint->query("SELECT ?s WHERE {
          ?s su:idUsuario \"$id \" . }"
        );
        $user = $result->user->getValue();

        $this->endpoint->update("INSERT DATA {
            \"$user\" su:visito \"$resource1\" .
            \"$resource1\" a su:RelacionUsuarioSitio .
            \"$resource1\" su:sitioVisitado \"$resource4\" .
            \"$resource1\" su:daCalificacionPrecio \"$resource2\"  .
            \"$resource1\" su:dejaComentario \"$resource3\"  .
            \"$resource1\" su:leGusto \"$liked\"^^xsd:boolean .
            
            \"$resource2\" a su:RelacionUsuarioCalificacionPrecio .
            \"$resource2\" su:calificacionDeUsuarioPrecio \"$price\" .
            
            \"$resource3\" a su:RelacionUsuarioComentario .
            \"$resource3\" su:conComentario \"$comment\" .
                    
            }"
        );
    }

    /**
     * Método para crear un ID en la ontología
     *
     * @param $lower
     * @param $upper
     * @param $type
     * @return int
     */
    function createID($lower, $upper, $type)
    {
        $id = mt_rand($lower, $upper);
        while ($this->checkIDExist($id, $type)) {
            $id = mt_rand($lower, $upper);
        }
        return $id;
    }

    /***
     * Método para revisar si existe ya un ID en la ontología
     *
     * @param $id
     * @return bool
     */
    function checkIDExist($id, $type)
    {
        $query = "";
        switch ($type) {
            case "user":
                $query = "SELECT * WHERE { ?s su:idUsuario \"$id\" . }";
                break;
            case "user_place":
                $query = "SELECT * WHERE { ?s su:visito su:r_user_place_\"$id\" . }";
                break;
            case "rating":
                $query = "SELECT * WHERE { ?s su:daCalificacionPrecio su:r_user_rating_\"$id\" . }";
                break;
            case "comment":
                $query = "SELECT * WHERE { ?s su:dejaComentario su:r_user_comment_\"$id\" . }";
                break;
        }
        $result = $this->endpoint->query($query);
        return $result->numRows() > 0;
    }

}
