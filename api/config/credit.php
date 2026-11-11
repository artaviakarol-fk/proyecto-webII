<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Fiado
    |--------------------------------------------------------------------------
    |
    | Base limit of fiado, in colones: the limit a new customer gets and the most the
    | administration can set for a customer. ₡90 000, confirmed with the owner of the
    | Minisúper Teresita. The exceptional increases of the cashier (US-304) go on top of it.
    |
    */

    'base_limit' => (string) env('LIMITE_FIADO_BASE', '90000'),

    /*
    | The most a customer may owe counting the exceptional increases a cashier gives (US-304):
    | ₡120 000, that is the base limit plus up to ₡30 000, agreed with the owner.
    */

    'max_limit' => (string) env('LIMITE_FIADO_MAXIMO', '120000'),

];
