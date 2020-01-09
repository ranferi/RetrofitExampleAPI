<?php use NlpTools\Documents\TokensDocument; ?>

<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="//netdna.bootstrapcdn.com/bootstrap/3.1.1/css/bootstrap.min.css">
    <title>Alta de usuarios en la aplicación</title>
</head>
<body>
<div class="container">
        <?php if (isset($flash['error'])): ?>
            <span class="label label-danger"><?php echo $flash['error'] ?></span>
        <?php endif; ?>

        <?php

        $correct = 0; ?>
        <table class="table table-striped table-bordered">
            <thead align="left" style="display: table-header-group">
            <tr>
                <th scope="col">#</th>
                <th scope="col">Comentario</th>
                <th scope="col">Clase</th>
                <th scope="col">Predicción</th>
            </tr>
            </thead>
            <tbody>
            <?php
            $i = 0;
            foreach ($testing as $d) :
            $i++; ?>
            <tr class="item_row">
                <?php
                $prediction = $cls->classify(
                array('Caro', 'MuyCaro', 'Moderado', 'Barato'), // todas las posibles clases
                new TokensDocument(
                $tok->tokenize($d[1]) // el documento
                )
                ); ?>
                <th scope="row"><?php echo strval($i) ?></th>
                <td><?php echo $d[1] ?></td>
                <td><?php echo $d[0] ?></td>
                <td><?php echo $prediction ?></td>
            </tr>
            <?php if ($prediction == $d[0])
            $correct++;
            endforeach; ?>

            </tbody>
            </table>

    <h1><?php printf("Precisión: %.2f\n", 100 * $correct / count($testing)); ?></h1>

</div>
</body>
</html>
