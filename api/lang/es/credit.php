<?php

declare(strict_types=1);

return [

    'customer_not_found' => 'El cliente no existe.',
    'name_invalid' => 'El nombre del cliente es obligatorio y puede tener hasta :max caracteres.',
    'phone_invalid' => 'El teléfono solo puede tener dígitos, espacios, guiones y un + al inicio.',
    'customer_inactive' => ':customer está inactivo y no puede comprar a fiado.',
    'sale_not_found' => 'La venta no existe.',
    'limit_exceeded' => 'La venta de :sale no cabe en el fiado de :customer: debe :balance de un límite de :limit y le quedan :available disponibles.',
    'reason_invalid' => 'El motivo es obligatorio y puede tener hasta :max caracteres.',
    'increase_above_maximum' => 'La ampliación de :increase dejaría el límite de :customer en :would_be, y el máximo es :max. Se le puede ampliar hasta :left.',
    'increase_not_found' => 'La ampliación no existe.',
    'increase_already_reverted' => 'La ampliación ya fue revertida.',
    'increase_expired' => 'La ampliación ya venció, así que no hay nada que revertir.',
    'limit_above_maximum' => 'El límite de fiado no puede pasar de :max. Para casos de emergencia está la ampliación del límite.',

];
