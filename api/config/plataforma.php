<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Alquiler de la plataforma
    |--------------------------------------------------------------------------
    |
    | Cada negocio paga un mes a la vez. Un negocio nuevo empieza con unos días
    | de prueba gratis. Unos días antes de vencer, el administrador ve un aviso;
    | al vencer tiene unos días de gracia en los que todo sigue funcionando, y
    | después el servicio se suspende hasta que pague.
    |
    */

    'precio_mensual' => (int) env('PLATAFORMA_PRECIO_MENSUAL', 15000),

    'dias_prueba' => (int) env('PLATAFORMA_DIAS_PRUEBA', 5),

    'dias_aviso' => (int) env('PLATAFORMA_DIAS_AVISO', 7),

    'dias_gracia' => (int) env('PLATAFORMA_DIAS_GRACIA', 3),

];
