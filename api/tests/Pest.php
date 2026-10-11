<?php

/*
|--------------------------------------------------------------------------
| Configuración de Pest
|--------------------------------------------------------------------------
|
| Las pruebas de la carpeta Feature usan el TestCase de Laravel, así tienen
| acceso a la aplicación, la base de datos y los helpers HTTP.
|
*/

pest()->extend(Tests\TestCase::class)->in('Feature');
