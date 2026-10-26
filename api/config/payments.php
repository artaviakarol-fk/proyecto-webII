<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Payment gateway
    |--------------------------------------------------------------------------
    |
    | Provider that charges the payments: "onvo" (ONVO Pay, with card and SINPE
    | Móvil) or "paypal" (PayPal, with card or account). Changing it only needs this variable: no code changes.
    |
    */

    'default' => env('PAYMENT_GATEWAY', 'onvo'),

];
