<?php
declare(strict_types=1); ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="/SIGED/public/css/elegirRol.css">
    <title>SIGED - Bienvenido</title>
    <style>
        /* (Aquí se puede poner estilos rápidos si no quieres un CSS) */
    </style>
</head>
<body>
    <section class="Contenedor">
        <article class="TITULO_LETRA_GRANDE_BLANCA-MEDIO">
            ¡Bienvenido al sistema SIGED!
        </article>

        <article class="Contenedor-botones">
            <a class="boton-rol" href="index.php?action=login&rol=DOCENTE">
                Docente
            </a>
            <a class="boton-rol" href="index.php?action=login&rol=JEFE_DEPARTAMENTO">
                Jefe de<br>departamento
            </a>
        </article>
    </section>
</body>
</html>