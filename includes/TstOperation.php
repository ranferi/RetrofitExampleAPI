<?php

use Geokit\Math;

class TstOperation
{
    private $gs;
    private $endpoint;
    private $math;
    private $price_array = array(0 => 'su:Barato', 1 => 'su:Moderado', 2 => 'su:Caro', 3 => 'su:MuyCaro');
    private $data;

    function __construct($data)
    {
        require '../vendor/autoload.php';

        EasyRdf_Namespace::set('su', 'http://www.semanticweb.org/ranferi/ontologies/2018/9/ssrsi_onto#');
        EasyRdf_Namespace::set('rdf', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#');
        EasyRdf_Namespace::set('rdfs', 'http://www.w3.org/2000/01/rdf-schema#');
        EasyRdf_Namespace::set('owl', 'http://www.w3.org/2002/07/owl#');

        $config = array("timeout" => 500, "maxredirects" => 6);
        EasyRdf_Http::setDefaultHttpClient(
            $this->client = new EasyRdf_Http_Client(null, $config)
        );

        $this->gs = new EasyRdf_GraphStore('http://localhost:3030/repositories/SSRSIP/rdf-graphs/service');

        $this->endpoint = new EasyRdf_Sparql_Client("http://localhost:3030/repositories/SSRSIP",
            "http://localhost:3030/repositories/SSRSIP/statements");

        $this->math = new Math();
        $this->data = $data;
    }



    /****************************
     * Usuario
     ****************************/

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

        return $result->numRows() > 0;
    }

    /***
     * Método para revisar si el usuario existe en ontología
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

        return $response->isSuccessful();
    }

    /**
     * Método para obtener un usuario por su email
     *
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
        }");

        $user = array();
        if ($result->numRows() == 1) {
            $user['id'] = $result->current()->id->getValue();
            $user['name'] = $result->current()->nombre->getValue();
            $user['lastName'] = $result->current()->apellidoPaterno->getValue();
            $user['mothersMaidenName'] = $result->current()->apellidoMaterno->getValue();
            $user['email'] = $email;
            $user['user'] = $result->current()->usuario->getValue();
        }
        return $user;
    }

    /**
     * Método para enlistar todos los usuarios en la ontología
     *
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



    /****************************
     * Sitios
     ****************************/

    /**
     * Método que regresa todos los sitios de la ontología
     * @return array
     */
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
        }"
        );

        $points = array();
        foreach ($result as $place) {
            $sujeto = $place->sujeto->shorten();
            $temp_id = $place->id->getValue();

            $temp = array();
            $temp['id'] = $temp_id;
            $temp['medi'] = $place->medi->localName();
            if (isset($place->latitud))
                $temp['latitud'] = $place->latitud->getValue();
            if (isset($place->longitud))
                $temp['longitud'] = $place->longitud->getValue();
            $temp['direccion'] = $place->dir->getValue();
            $temp['musica'] = $place->musica->getValue();

            $temp_props = $this->getPropertiesJSON($sujeto);
            $resultado = array_merge($temp, $temp_props);

            array_push($points, $resultado);
        }
        return $points;
    }

    /**
     * Método para buscar sitios
     * @param $selected_cat
     * @param $price
     * @param $price_class
     * @param $music
     * @param $lat_user
     * @param $long_user
     * @param $id
     * @param null $distance
     * @return array
     */
    function search($selected_cat, $price, $price_class, $music, $lat_user, $long_user, $id, $distance = null)
    {
         $result = $this->searchPlaces($selected_cat, $price, $lat_user, $long_user, $distance, $music);
         $res_count = count($result);
         $result = $this->similarityPlaces($result, $this->data, $price_class, $res_count, 5);

        if (count($result) < 2) {
            $temp = $this->searchWithExtendedCats($selected_cat, $price, $lat_user, $long_user, $id, $distance, true, $music);
            if (!empty($temp)) {
                $temp = $this->similarityPlaces($temp, $this->data, $price_class, $res_count, 4);
                $diff = array_udiff($temp, $result, "self::compareArraysById");
                if (!empty($diff)) $result = array_merge($result, $diff);
            }
        }

        if (count($result) < 2) {
            $temp = $this->searchWithExtendedCats($selected_cat, $price, $lat_user, $long_user, $id, $distance, false, $music);
            if (!empty($temp1)) {
                $diff = array_udiff($temp, $result, "self::compareArraysById");
                if (!empty($diff)) $result = $this->mergeDiffWithArray($diff, $result, 3);
            }
        }

        foreach ($this->price_array as $new_price) {
            $new_price = $this->findNewPrice($new_price);

            $temp = $this->searchPlaces($selected_cat, $new_price, $lat_user, $long_user, $distance, $music);
            if (!empty($temp))
                $result = $this->mergeDiffWithArray($temp, $result, 2);

            if (count($result) < 2) {
                $temp = $this->searchPlaces($selected_cat, $new_price, $lat_user, $long_user, $distance, !$music);
                if (!empty($temp))
                    $result = $this->mergeDiffWithArray($temp, $result, 2);
            }

            if (count($result) < 2) {
                $temp = $this->searchPlaces($selected_cat, $new_price, $lat_user, $long_user, null, !$music);
                if (!empty($temp))
                    $result = $this->mergeDiffWithArray($temp, $result, 2);
            }

            if (count($result) > 2) break;
        }

        foreach ($this->price_array as $new_price) {
            $temp1 = $this->searchPlaces($selected_cat, $new_price, $lat_user, $long_user, null, null);
            $temp2 = $this->searchWithExtendedCats($selected_cat, $new_price, $lat_user, $long_user, $id, null, false, null);

            if (!empty($temp1) && !empty($temp2)) {
                $diff = array_udiff($temp2, $temp1, "self::compareArraysById");
                if (!empty($diff)) $result = $this->mergeDiffWithArray($temp1, $result, 1);
            } elseif (empty($temp1)) {
                $result = $this->mergeDiffWithArray($temp2, $result, 1);
            } elseif (empty($temp2)) {
                $result = $this->mergeDiffWithArray($temp1, $result, 1);
            }
            if (count($result) > 3) break;
        }

        if (empty($result)) {

            $temp = $this->getVisitedPlacesByUser($id);
            $temp = array_slice($temp, 0, 3);
            if (!empty($temp))
                $result = $this->mergeDiffWithArray($temp, $result, 0);
        }
        usort($result, "self::compareArraysBySimilarity");

        return $result;
    }

    function similarityPlaces($places, $data, $price_class, $res_count, $max_similarity) {
        $similar = 0;
        foreach ($places as &$place) {
            $comments = $place['comentarios'];
            foreach ($comments as $comment) {
                $prediction = $data->classification($comment);
                if ($prediction == $price_class) $similar++;
            }
            $percent = 100 * $similar / $res_count;
            if ($percent > 60) {
                $place['similitud'] = $max_similarity;
            } else {
                $place['similitud'] = $max_similarity - 1;
            }
        }
        return $places;
    }

    /**
     * Metodo auxiliar para busqueda usando categorias hijo y padre de una categoria inicial
     * @param $selected_cat
     * @param $price
     * @param $lat_user
     * @param $long_user
     * @param $id
     * @param null $distance
     * @param bool $preferences
     * @param null $music
     * @return array
     */
    function searchWithExtendedCats($selected_cat, $price, $lat_user, $long_user, $id, $distance = null, $preferences = false, $music = null) {
        $result = array();
        $temp1 = $this->searchWithChildCat($selected_cat, $price, $lat_user, $long_user, $id, $preferences, $distance, $music);
        $temp2 = $this->searchWithParentCat($selected_cat, $price, $lat_user, $long_user, $id, $preferences, $distance, $music);

        if (!empty($temp1) && !empty($temp2)) {
            $diff = array_udiff($temp2, $temp1, "self::compareArraysById");
            if (!empty($diff)) $result = array_merge($temp1, $diff);
        } elseif (empty($temp1)) {
            $result = array_merge($result, $temp2);
        } elseif (empty($temp2)) {
            $result = array_merge($result, $temp1);
        }
        return $result;
    }

    /**
     * Busqueda de sitios con categorias hijo
     * @param $selected_cat
     * @param $price
     * @param $lat_user
     * @param $long_user
     * @param $id
     * @param $preferences
     * @param null $distance
     * @param null $music
     * @param null $visited_cat
     * @return array
     */
    function searchWithChildCat($selected_cat, $price, $lat_user, $long_user, $id, $preferences, $distance = null, $music = null, $visited_cat = null)
    {
        $children_cat = $this->searchChildCat($selected_cat, $id, $preferences);
        if ($visited_cat) {
            $pos = array_search($visited_cat, $children_cat);
            unset($children_cat[$pos]);
        }

        $a = array();
        if (!empty($children_cat)) {
            foreach ($children_cat as $cat) {
                $temp = $this->searchPlaces($cat, $price, $lat_user, $long_user, $distance, $music);
                if (!empty($temp))
                    $a = array_merge($a, $temp);
            }
        }
        return $a;
    }

    /**
     * Busqueda de sitios con categoria padre
     * @param $initial_cat
     * @param $price
     * @param $lat_user
     * @param $long_user
     * @param $id
     * @param $preferences
     * @param null $distance
     * @param null $music
     * @return array
     */
    function searchWithParentCat($initial_cat, $price, $lat_user, $long_user, $id, $preferences, $distance = null, $music = null){
        $parent_cat = $this->searchParentCat($initial_cat, $id, $preferences);
        $b = array();
        if (!empty($parent_cat)) {
            foreach ($parent_cat as $cat) {
                $temp1 = $this->searchPlaces($cat, $price, $lat_user, $long_user, $distance, $music);
                $temp2 = $this->searchWithChildCat($cat, $price, $lat_user, $long_user, $id, $preferences, $distance, $music, $initial_cat);

                if (!empty($temp1) && !empty($temp2)) {
                    $diff = array_udiff($temp2, $temp1, "self::compareArraysById");
                    if (!empty($diff)) $b = array_merge($temp1, $diff);
                } elseif (empty($temp1)) { $b = array_merge($b, $temp2);
                } elseif (empty($temp2)) {$b = array_merge($b, $temp1); }
            }
        }
        return $b;
    }

    /**
     * Método que busca sitios en la triplestore
     * @param $price
     * @param $type
     * @param $music
     * @return object
     */
    function queryPlacesOnTriplestore($price, $type, $music)
    {
        $query = "SELECT ?sujeto ?id ?medi ?latitud ?longitud ?direccion
        WHERE {
            ?sujeto su:idSitio ?id .
            ?sujeto a [
                a owl:Restriction ;
                owl:onProperty su:tieneUnValorMEDI ;
                owl:someValuesFrom ?medi
            ] .
            ?sujeto a [
                a owl:Restriction ;
                owl:onProperty su:tienePrecio ;
                owl:someValuesFrom " . $price . "
            ] .
            ?sujeto su:tieneCategoria " . $type . " .
            ?sujeto su:tienePropiedad/su:direccionSitio ?direccion . \n";

        if (!is_null($music))
            $query .= "?sujeto su:tienePropiedad/su:musica " . ($music ? 'true' : 'false') . " . \n";
        $query .= "?sujeto su:tienePropiedad/su:latitud ?latitud .
                   ?sujeto su:tienePropiedad/su:longitud ?longitud .
                   }";

        $result = $this->endpoint->query($query);
        return $result;
    }

    /**
     * Método para buscar sitios
     * @param $selected_cat
     * @param $price
     * @param $music
     * @param $lat_user
     * @param $long_user
     * @param null $distance
     * @return array
     */
    function searchPlaces($selected_cat, $price, $lat_user, $long_user, $distance = null, $music = null)
    {
        $type_array = is_array($selected_cat) ? $selected_cat : (array)$selected_cat;

        $all_POI = array();
        foreach ($type_array as $cat) {

            $result = $this->queryPlacesOnTriplestore($price, $cat, $music);

            foreach ($result as $place) {
                if ($distance != null) {
                    $distanceFromPlace = $this->compareDistance(
                        $this->calculateDistance($lat_user, $long_user, $place->latitud->getValue(), $place->longitud->getValue())
                    );
                    if ($distance != $distanceFromPlace) continue;
                }


                $sujeto = $place->sujeto->shorten();
                $temp_id = $place->id->getValue();

                $temp = array();
                $temp['id'] = $temp_id;
                $temp['medi'] = $place->medi->localName();
                $temp['latitud'] = $place->latitud->getValue();
                $temp['longitud'] = $place->longitud->getValue();
                $temp['direccion'] = $place->direccion->getValue();
                $temp['musica'] = $music;

                $temp_props = $this->getPropertiesJSON($sujeto);
                $resultado = array_merge($temp, $temp_props);

                array_push($all_POI, $resultado);
            }
        }

        return $all_POI;
    }

    function mergeDiffWithArray($temp, $result, $similitud)
    {
        $diff = array_udiff($temp, $result, "self::compareArraysById");
        if (!empty($diff)) {
            foreach ($diff as &$place) $place['similitud'] = $similitud;
            return array_merge($result, $diff);
        } else {
            return $result;
        }
    }

    /**
     * Método para obtener los sitios visitados por un usuario con los comentarios que haya dejado
     * @param $id
     * @return array
     */
    function getAllVisitedPlacesByUser($id)
    {
        $user = $this->endpoint->query("
        SELECT ?sujeto ?nombre ?usuario ?email
        WHERE {
            ?sujeto su:idUsuario " . $id . " .
            ?sujeto su:nombre ?nombre .
            ?sujeto su:usuario ?usuario .
            ?sujeto su:email ?email .
        }");

        $s = $user->current()->sujeto->shorten();
        $users = array();
        $temp = array();
        $temp['id'] = intval($id);
        $temp['nombre'] = $user->current()->nombre->getValue();
        $temp['usuario'] = $user->current()->usuario->getValue();
        $temp['email'] = $user->current()->email->getValue();
        $temp['visito'] = array();

        $visited = $this->endpoint->query("
        SELECT ?a ?sitio ?precio ?comentario ?c
        WHERE {
            " . $s . " su:visito ?a . 
            ?a su:sitioVisitado ?sitio .
            ?a su:daCalificacionPrecio/su:calificacionDeUsuarioPrecio ?precio .
            ?a su:dejaComentario ?c . 
            ?c su:conComentario ?comentario 
        }");

        $temp_1 = array();
        foreach ($visited as $place) {
            $temp_comentario = array();
            $temp_usuario = array();

            $id_visitado = $this->getIdFromURI($place->a->getUri(), "r_user_place_");
            $id_comentario = $this->getIdFromURI($place->c->getUri(), "r_user_comment_");

            $temp_usuario['id'] = $temp['id'];
            $temp_usuario['usuario'] = $temp['usuario'];
            $temp_usuario['email'] = $temp['email'];

            $temp_comentario['comentario'] = $place->comentario->getValue();
            $temp_comentario['id'] = $id_comentario;
            $temp_comentario['user'] = $temp_usuario;

            $temp_1['id'] = $id_visitado;
            $temp_1['sitio_src'] = $place->sitio->shorten();
            $temp_1['precio'] = $place->precio->shorten();
            $temp_1['gusto'] = $this->checkLikedPlaceByUser($s, $temp_1['sitio_src']);
            $temp_1['comentario'] = $temp_comentario;
            $temp_1['sitio'] = array();

            // Propiedades
            $temp_2 = $this->getBasePropsPlace($temp_1['sitio_src']);

            // Nombres
            $temp_2['nombres'] = $this->getNamesOfPlace($temp_1['sitio_src']);

            // Calificaciones
            $ratings = $this->getRatingsOfPlace($temp_1['sitio_src']);
            $temp_2['calificaciones'] = $ratings;
            $temp_2['total'] = $this->getRatingsTotal($ratings);

            // Categorias
            $temp_2['categorias'] = $this->getCategoriesOfPlace($temp_1['sitio_src']);

            // Imagenes
            $temp_2['imagenes'] = $this->getImagesOfPlace($temp_1['sitio_src']);

            // Comentarios
            $temp_comments1 = $this->getCommentsOfPlace($temp_1['sitio_src']);
            $temp_comments2 = $this->getCommentOfPlaceFromUser($temp_1['sitio_src']);
            $comments = array_merge($temp_comments1, $temp_comments2);

            $temp_2['comentarios'] = $comments;

            array_push($temp_1['sitio'], $temp_2);
            array_push($temp['visito'], $temp_1);

        }
        array_push($users, $temp);
        return $users;
    }

    /**
     * Método que pregunta si existe la relación "leHaGustado" entre un Usuario y un Sitio
     * @param $s
     * @param $p
     * @return mixed
     */
    function checkLikedPlaceByUser($s, $p)
    {
        $liked = $this->endpoint->query("
            ASK { " . $s . " su:leHaGustado " . $p . " }");
        return $liked->getBoolean();
    }

    /**
     * Método para obtener los sitios visitados por un usuario
     * @param $id
     * @return array
     */
    function getVisitedPlacesByUser($id)
    {
        $temp_id = intval($id);
        $visited = $this->endpoint->query("
        SELECT ?sujeto ?sitio
        WHERE {
            ?sujeto su:idUsuario " . $temp_id . " .
            ?sujeto su:haVisitado ?sitio . }");

        $temp = array();
        foreach ($visited as $place) {
            $sitio_src = $place->sitio->shorten();
            $temp_2 = $this->getBasePropsPlace($sitio_src);
            $temp_props = $this->getPropertiesJSON($sitio_src);
            $resultado = array_merge($temp_2, $temp_props);

            array_push($temp, $resultado);
        }

        return $temp;
    }


    /**
     * Método para obtener propiedades del sitio (id, latitud, longitud)
     * @param $place_src
     * @return array
     */
    function getBasePropsPlace($place_src)
    {
        $string = "SELECT ?id ?medi ?latitud ?longitud ?dir ?musica
                WHERE {
                    " . $place_src . " su:idSitio ?id .
                    " . $place_src . " a [
                        a owl:Restriction;
                        owl:onProperty su:tieneUnValorMEDI;
                        owl:someValuesFrom ?medi
                    ] .
                    " . $place_src . " su:tienePropiedad/su:direccionSitio ?dir .
                    " . $place_src . " su:tienePropiedad/su:musica ?musica .
                    OPTIONAL { " . $place_src . " su:tienePropiedad/su:latitud ?latitud . }
                    OPTIONAL { " . $place_src . " su:tienePropiedad/su:longitud ?longitud . }
                }";
        $result = $this->endpoint->query($string);

        $props = array();
        $props['id'] = $result->current()->id->getValue();
        $props['medi'] = $result->current()->medi->localName();
        if (isset($result->current()->latitud))
            $props['latitud'] = $result->current()->latitud->getValue();
        if (isset($result->current()->longitud))
            $props['longitud'] = $result->current()->longitud->getValue();
        $props['direccion'] = $result->current()->dir->getValue();
        $props['musica'] = $result->current()->musica->getValue();
        return $props;
    }

    /**
     * Obtiene las propiedades importantes para el JSON
     * @param $sitio_src
     * @return array
     */
    function getPropertiesJSON($sitio_src)
    {
        $temp_props = array();

        // Nombres
        $temp_props['nombres'] = $this->getNamesOfPlace($sitio_src);

        // Calificaciones
        $ratings = $this->getRatingsOfPlace($sitio_src);
        $temp_props['calificaciones'] = $ratings;
        $temp_props['total'] = $this->getRatingsTotal($ratings);

        // Categorias
        $temp_props['categorias'] = $this->getCategoriesOfPlace($sitio_src);

        // Imagenes
        $temp_props['imagenes'] = $this->getImagesOfPlace($sitio_src);

        // Comentarios
        $temp_comments1 = $this->getCommentsOfPlace($sitio_src);
        $temp_comments2 = $this->getCommentOfPlaceFromUser($sitio_src);
        $comments = array_merge($temp_comments1, $temp_comments2);

        $temp_props['comentarios'] = $comments;

        return $temp_props;
    }

    /**
     * Encuentra un nuevo precio
     * @param $previousPrice
     * @return mixed
     */
    function findNewPrice($previousPrice)
    {
        $pos = array_search($previousPrice, $this->price_array);
        $module = 4;
        $r = ($pos + 1) % $module;
        /*if ($r < 0) {
            $r += abs($module);
        }*/
        return $this->price_array[$r];
    }

    /**
     * Método para buscar la categoría padre de una categoría
     * @param $type
     * @param $id
     * @param bool $preferences
     * @return array
     */
    function searchParentCat($type, $id, $preferences = false)
    {
        $result = $this->endpoint->query("
        SELECT ?sup ?mid ?distance
        WHERE {
            {
                SELECT ?sup (count(?mid) - 1 as ?distance)
                WHERE {
                    " . $type . " rdfs:subClassOf+ ?mid .
                    ?mid rdfs:subClassOf* ?sup .
                    FILTER (?sup != " . $type . ") .
                    FILTER (?sup != owl:Nothing ) .
                }
                GROUP BY ?sup 
                ORDER BY ?sup 
            }
            FILTER (?distance < 2)\n" .
            ($preferences ? "FILTER EXISTS { su:user_" . strval($id) . " su:prefiereCategoria ?sup .}\n" : "\n") . "}");

        $array = array();
        foreach ($result as $cat) {
            if ($cat != null && isset($cat->sup))
                array_push($array, $cat->sup->shorten());
        }
        return $array;
    }

    /**
     * Método para buscar las categorías hijos de un categoría
     * @param $type
     * @param $id
     * @param bool $preferences
     * @return array
     */
    function searchChildCat($type, $id, $preferences = false)
    {
        $result = $this->endpoint->query("
        SELECT ?sub
        WHERE {
            {
            SELECT ?sub (count(?mid) - 1 as ?distance)
            WHERE {
                ?mid rdfs:subClassOf* " . $type . " .
                ?sub rdfs:subClassOf+ ?mid .
                FILTER(?sub != " . $type . " ) .
                FILTER(?sub != owl:Nothing ) .
            }
            GROUP BY ?sub 
        }
        FILTER( ?distance < 2 )\n" .
            ($preferences ? "FILTER EXISTS { su:user_" . strval($id) . " su:prefiereCategoria ?sup .}\n" : "\n") . "}");

        $array = array();
        foreach ($result as $cat) {
            if ($cat != null && isset($cat->sub))
                array_push($array, $cat->sub->shorten());
        }
        return $array;
    }



    /****************************
     * Métodos Auxiliares
     ****************************/

    /**
     * @param $lat_user
     * @param $long_user
     * @param $lat_place
     * @param $long_place
     * @return float
     */
    function calculateDistance($lat_user, $long_user, $lat_place, $long_place)
    {
        return $this->math->distanceVincenty(
            new Geokit\LatLng($lat_user, $long_user),
            new Geokit\LatLng($lat_place, $long_place))->meters();
    }

    /**
     * @param $distanceWithinPointUser
     * @return string
     */
    function compareDistance($distanceWithinPointUser)
    {
        if ($distanceWithinPointUser >= 0.0 || $distanceWithinPointUser < 100.0) $distance = "Cerca";
        else if ($distanceWithinPointUser >= 100.0 || $distanceWithinPointUser < 500.0) $distance = "Mediana";
        else $distance = "Lejos";
        return $distance;
    }

    /**
     * Método que ingresa una calificación de un usuario a un sitio
     * @param $id
     * @param $idPlace
     * @param $liked
     * @param $price
     * @param $comment
     * @return bool|object
     */
    function insertUserRatingSite($id, $idPlace, $liked, $price, $comment)
    {
        $recursos = $this->checkIfSiteVisited($id, $idPlace);
        $id_r1 = $this->createID(200000, 300000, "user_place");
        $id_r2 = $this->createID(300000, 400000, "rating");
        $id_r3 = $this->createID(400000, 500000, "comment");
        if (!empty($recursos)) {
            // existe el vinculo de un usuario con un sitio visitado
            $query = "SELECT DISTINCT ?o 
                      WHERE {" . $this->stringWhereQuery($id, $idPlace) .
                " FILTER(isUri(?o1) && STRSTARTS(STR(?o1), STR(su:))) }";
            $res_rel = $this->endpoint->query($query);

            $r = array();
            foreach ($res_rel as $prop) {
                $r = array_merge($r, $this->typeBlankNode($prop->o->getUri()));
            }
            $recursos = array_merge($recursos, $r);

            $delete_query = "DELETE { ?u su:visito ?r . ?r ?p ?o . ?o ?p1 ?o1 . }";
            $delete_query .= "WHERE {" . $this->stringWhereQuery($id, $idPlace) . " }";
            $response = $this->endpoint->update($delete_query);
            if (!$response->isSuccessful()) {
                return false;
            }
        } else {
            // no existe un sitio visitado
            $recursos["usuario"] = "su:user_" . strval($id);
            $recursos["sitio"] = "su:place_" . strval($idPlace);
            $recursos["usuario_sitio"] = "_:r_user_place_" . strval($id_r1);
            $recursos["usuario_calif"] = "_:r_user_rating_" . strval($id_r2);
            $recursos["usuario_comen"] = "_:r_user_comment_" . strval($id_r3);
        }

        $insert_query = "INSERT DATA {\n" .
            $recursos["usuario"] . " su:visito " . $recursos["usuario_sitio"] . " .\n" .
            $recursos["usuario_sitio"] . " a su:RelacionUsuarioSitio .\n" .
            $recursos["usuario_sitio"] . " su:idUsuarioSitio " . $id_r1 . " .\n" .
            $recursos["usuario_sitio"] . " su:sitioVisitado " . $recursos["sitio"] . " .\n" .
            $recursos["usuario_sitio"] . " su:daCalificacionPrecio " . $recursos["usuario_calif"] . " .\n" .
            $recursos["usuario_sitio"] . " su:dejaComentario " . $recursos["usuario_comen"] . " .\n" .
            ($liked ? $recursos["usuario_sitio"] . " su:leGusto " . $recursos["sitio"] . " .\n" : "\n") .

            $recursos["usuario_calif"] . " a su:RelacionUsuarioCalificacionPrecio .\n" .
            $recursos["usuario_calif"] . " su:idUsuarioCalif " . $id_r2 . " .\n" .
            $recursos["usuario_calif"] . " su:calificacionDeUsuarioPrecio su:" . $price . " .\n" .

            $recursos["usuario_comen"] . " a su:RelacionUsuarioComentario .\n" .
            $recursos["usuario_comen"] . " su:idUsuarioComen " . $id_r3 . " .\n" .
            $recursos["usuario_comen"] . " su:conComentario \"" . $comment . "\" .\n" . "}";

        return $this->endpoint->update($insert_query);
    }

    /**
     * Método que revisa si un sitio ha sido visitado por el usuario
     * @param $id
     * @param $idPlace
     * @return array
     */
    function checkIfSiteVisited($id, $idPlace)
    {
        $query = "SELECT ?s ?p 
        WHERE { 
            ?s su:idUsuario " . strval($id) . " . 
            ?s su:visito ?p .
            ?p su:sitioVisitado su:place_" . strval($idPlace) . " . 
        }";
        $result = $this->endpoint->query($query);
        $relacion = array();
        if ($result->numRows() > 0) {
            $relacion["usuario"] = $result->current()->s->shorten();
            $relacion["sitio"] = "su:place_" . strval($idPlace);
        }
        return $relacion;
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

    /**
     * Método para revisar si existe ya un ID en la ontología
     *
     * @param $id
     * @param $type
     * @return bool
     */
    function checkIDExist($id, $type)
    {
        $query = "";
        switch ($type) {
            case "user":
                $query = "SELECT * WHERE { ?s su:idUsuario " . strval($id) . " . }";
                break;
            case "user_place":
                $query = "SELECT * WHERE { ?s su:idUsuarioSitio " . strval($id) . " . }";
                break;
            case "rating":
                $query = "SELECT * WHERE { ?s su:idUsuarioCalif " . strval($id) . " . }";
                break;
            case "comment":
                $query = "SELECT * WHERE { ?s su:idUsuarioComen " . strval($id) . " . }";
                break;
        }
        $result = $this->endpoint->query($query);
        return $result->numRows() > 0;
    }

    /**
     * Método que obtiene el ID desde un URI de la ontología
     * @param $URI
     * @param $substr
     * @return int
     */
    function getIdFromURI($URI, $substr)
    {
        $sub_uri = substr($URI, strrpos($URI, $substr));
        return intval(substr($sub_uri, strripos($sub_uri, "_") + 1));
    }

    /**
     * Método que regresa el tipo de nodo blanco
     * @param $temp
     * @return array
     */
    function typeBlankNode($temp)
    {
        $blank = substr($temp, strrpos($temp, "r_user_"));
        $type = $this->reverse_strrchr($blank, "_", 1);
        $r = array();
        switch ($type) {
            case "r_user_rating_":
                $r["usuario_calif"] = "_:" . $blank;
                return $r;
            case "r_user_comment_":
                $r["usuario_comen"] = "_:" . $blank;
                return $r;
            case "r_user_place_":
                $r["usuario_sitio"] = "_:" . $blank;
                return $r;
            default:
                $r["error"] = true;
                return $r;
        }
    }

    /**
     * Método que obtiene los nombres de un sitio desde distintas fuentes
     * @param $place_src
     * @return array
     */
    function getNamesOfPlace($place_src)
    {
        $names = array();
        $result = $this->endpoint->query("
                SELECT ?nombreSitio ?base
                WHERE {
                    " . $place_src . " su:tienePropiedad ?prop .
                    ?prop su:nombreSitio ?nombreSitio .
                    ?prop su:provieneDeBD ?base .
                }"
        );
        foreach ($result as $name) {
            $temp_2 = array();
            $temp_2['nombre_sitio'] = $name->nombreSitio->getValue();
            $temp_2['proviene'] = $name->base->localName();
            array_push($names, $temp_2);
        }
        return $names;
    }

    /**
     * Método que obtiene las imagenes de un sitio
     * @param $place_src
     * @return array
     */
    function getImagesOfPlace($place_src)
    {
        $images = array();
        $result = $this->endpoint->query("
                SELECT ?imagen
                WHERE {
                    " . $place_src . " su:tienePropiedad ?prop .
                    ?prop su:imagenSitio ?imagen .
                }"
        );

        foreach ($result as $img) {
            $temp_3 = array();
            $temp_3['imagen'] = $img->imagen->getValue();
            array_push($images, $temp_3);
        }
        return $images;
    }

    /**
     * Método que obtiene las categorías de un sitio desde distintas fuentes
     * @param $place_src
     * @return array
     */
    function getCategoriesOfPlace($place_src)
    {
        $cats = array();
        $result = $this->endpoint->query("
                SELECT ?categoria ?proviene
                WHERE {" . $place_src . " su:tieneCategoria ?categoria .
                ?categoria rdfs:subClassOf [ rdf:rest* [ owl:onProperty su:esParteDeBD ; owl:allValuesFrom ?proviene ] ] }"
        );
        foreach ($result as $cat) {
            $temp_3 = array();
            $temp_3['categoria'] = $cat->categoria->localName();
            $temp_3['proviene'] = $cat->proviene->localName();
            array_push($cats, $temp_3);
        }
        return $cats;
    }

    /**
     * Método que obtiene las calificaciones de un sitio de un sitio desde distintas fuentes
     * @param $place_src
     * @return array
     */
    function getRatingsOfPlace($place_src)
    {
        $ratings = array();
        $result = $this->endpoint->query("
                SELECT ?calificacion ?prop ?base
                WHERE {
                    " . $place_src . " su:tienePropiedad ?prop .
                    ?prop su:calificacion ?calificacion .
                    ?prop su:provieneDeBD ?base .
                }"
        );
        foreach ($result as $rating) {
            $temp_3 = array();
            $temp_3['id'] = $this->getIdFromURI($rating->prop->getUri(), "prop_");
            $temp_3['calificacion'] = $rating->calificacion->getValue();
            $temp_3['proviene'] = $rating->base->localName();
            array_push($ratings, $temp_3);
        }
        return $ratings;
    }

    /**
     * Método que promedia las calificaciones de un sitio
     * @param $ratings
     * @return float|mixed
     */
    function getRatingsTotal($ratings)
    {
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
        return $total;
    }

    /**
     * Método que obtiene los comentarios de un sitio que han dejado usuarios desde las fuentes
     * @param $place_src
     * @return array
     */
    function getCommentsOfPlace($place_src)
    {
        $comments = array();
        $result = $this->endpoint->query("
                SELECT ?comentario ?prop ?base
                WHERE {
                    " . $place_src . " su:tieneComentario ?prop .
                    ?prop su:comentario ?comentario .
                    ?prop su:provieneDeBD ?base .
                }"
        );
        foreach ($result as $comment) {
            $id = $this->getIdFromURI($comment->prop->getUri(), "comm_");
            $temp_3 = array();
            $temp_3['id'] = $id;
            $temp_3['comentario'] = $comment->comentario->getValue();
            $temp_3['proviene'] = $comment->base->localName();
            array_push($comments, $temp_3);
        }

        return $comments;

    }


    /**
     * Método que obtiene los comentarios de un sitio que han dejado los usuarios de SSRSI
     * @param $place_src
     * @return array
     */
    function getCommentOfPlaceFromUser($place_src)
    {
        $comments = array();
        $query = "
            SELECT ?id ?usuario ?correo ?blank_c ?comentario ?u ?idS
            WHERE {
                ?u su:sitioVisitado " . $place_src . " .
                ?user su:visito ?u .
                ?u su:idUsuarioSitio ?idS .
                ?u su:dejaComentario ?blank_c .
                ?blank_c su:conComentario ?comentario .
                ?user su:idUsuario ?id .
                ?user su:email ?correo .
                ?user su:usuario ?usuario . }";

        $result = $this->endpoint->query($query);
        foreach ($result as $comment) {
            $id = $this->getIdFromURI($comment->blank_c->getUri(), "r_user_comment_");
            $temp_4 = array();
            $temp_5 = array();

            $temp_4['id'] = $id;
            $temp_4['comentario'] = $comment->comentario->getValue();

            $temp_5['id'] = $comment->id->getValue();
            $temp_5['usuario'] = $comment->usuario->getValue();
            $temp_5['email'] = $comment->correo->getValue();
            $temp_4['user'] = $temp_5;
            array_push($comments, $temp_4);
        }

        return $comments;
    }

    /**
     * Cadena WHERE de consulta para SPARQL
     * @param $id
     * @param $idPlace
     * @return string
     */
    function stringWhereQuery($id, $idPlace)
    {
        $query = "?u su:idUsuario " . strval($id) . " .
                ?u su:visito ?r .
                ?r su:sitioVisitado ?s .
                ?s su:idSitio " . strval($idPlace) . " .
                ?r ?p ?o .
                OPTIONAL {
                    ?o ?p1 ?o1 .
                    FILTER(isBlank(?o))
                }";
        return $query;
    }

    /**
     * Regresa la última parte de una cadena apartir de la última aparición de un caracter
     * @param $haystack
     * @param $needle
     * @param $trail
     * @return bool|false|string
     */
    function reverse_strrchr($haystack, $needle, $trail)
    {
        return strrpos($haystack, $needle) ? substr($haystack, 0, strrpos($haystack, $needle) + $trail) : false;
    }

    function compareArraysById($obj_a, $obj_b)
    {
        return ($obj_a["id"] - $obj_b["id"]);
    }

    function compareArraysBySimilarity($obj_a, $obj_b)
    {
        return ($obj_a["similitud"] - $obj_b["similitud"]);
    }



}