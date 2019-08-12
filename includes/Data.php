<?php


use NlpTools\Classifiers\MultinomialNBClassifier;
use NlpTools\Documents\TokensDocument;
use NlpTools\Documents\TrainingSet;
use NlpTools\FeatureFactories\DataAsFeatures;
use NlpTools\Models\FeatureBasedNB;
use NlpTools\Tokenizers\WhitespaceTokenizer;

class Data
{
    private static $instance = null;

    private $training_data = array(
        array('MuyCaro', 'Caro y malo, el alambre tenía pura cebolla y se tardaron horas en atender.'),
        array('Moderado', 'Tiene buena comida, buena cantina y buen ambiente, con música de salterio. La atención es muy buena. El lugar es pulcro y agradable. Cuenta con terraza hacia la calle de Gante, que es peatonal. El precio no es bajo, pero es razonable.'),
        array('Caro', 'La comida es cara para la ración que sirven. No caigas en la tentación de pedir mojitos al 2x1, no están bien preparados, mejor elige las margaritas'),
        array('Barato', 'El lugar es de un ambiente muy pesado(peligroso) , la música es muy repetida, el lugar es pequeño y el alcohol es barato o poco diverso'),
        array('Moderado', 'Una excelente opción dentro del primer cuadro del centro histórico. Buena relación precio-calidad. Es un poco pequeño el lugar y usualmente hay que hacer fila de espera para tener lugar, pero vale la pena.'),
        array('Caro', 'Muy buen lugar, los precios un poco elevados. También el lugar es pequeño y hay que esperar. El servicio está bien y tiene buen sabor.'),
        array('Moderado', 'Es un lugar pequeño pero muy acojedor, es bastante limpio y los que te atienden en ese lugar son bastante amables, la comida es muy rica y sus precios no son altos a pesar de que esta en la zona centro'),
        array('Moderado', 'Buen lugar,excelente vista,buena comida,precios no tan caros,pero sin preguntar ( cosa q ya no está permitida) te clavan la propina obligatoria! Y solo te venden vinos no muy buenos y en eso sí son muuuy caros! Todo lo demás es bueno,ojo estacionamiento cerca solo en bellas artes!"'),
        array('MuyCaro', 'Cierran a las 9pm pero cocina deja de funcionar a las 7:30pm, parece que te están corriendo antes de tiempo y no dejan disfrutar la vista, además de que los precios son excesivos.'),
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

    private $tset = null;
    private $tok = null;
    private $ff = null;
    private $model = null;
    private $cls = null;

    private function __construct() {
        $this->tset = new TrainingSet();
        $this->tok = new WhitespaceTokenizer();
        $this->ff = new DataAsFeatures();
        $this->model = new FeatureBasedNB();
        $this->training($this->training_data);
        $this->cls = new MultinomialNBClassifier($this->ff, $this->model);
    }

    public static function get() {
        if(self::$instance == null) {
            self::$instance = new Data();
        }
        return self::$instance;
    }

    public function classification($comment) {
        // predice si es caro, muy caro, moderado, barato
        if (isset($comment['comentario'])) {
            $prediction = $this->cls->classify(
                array('Caro', 'MuyCaro', 'Moderado', 'Barato'), // todas las posibles clases
                new TokensDocument(
                    $this->tok->tokenize($comment['comentario']) // el documento
                )
            );
        } else {
            $prediction = 'Barato';
        }
        return $prediction;
    }

    private function training($training) {
        foreach ($training as $d) {
            $this->tset->addDocument(
                $d[0], // clase
                new TokensDocument(
                    $this->tok->tokenize($d[1]) // el documento
                )
            );
        }
        // entrenamiento usando el modelo Naive Bayes
        $this->model->train($this->ff, $this->tset);
    }

    public function add($comment) {
        array_push($this->training_data, $comment);
        // echo '<pre>' . var_export($this->training_data, true) . '</pre>';
        $this->training($this->training_data);
    }
}