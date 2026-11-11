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

];
