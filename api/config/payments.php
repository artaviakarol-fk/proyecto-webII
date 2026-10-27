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

    /*
    | Number that receives the SINPE Móvil transfers, shown to the buyer while the payment is pending.
    */

    'sinpe_number' => env('ONVO_SINPE_NUMBER', '+50670196686'),

];
