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
    'payment_above_balance' => 'El abono de :amount es mayor que lo que debe :customer (:balance). Para cancelar todo, el abono es :balance.',
    'payment_nothing_owed' => ':customer no debe nada, así que no hay nada que abonar.',
    'payment_not_positive' => 'El abono tiene que ser mayor que cero.',
    'payment_method_not_available' => 'Por ahora los abonos se reciben en efectivo; ":method" todavía no está disponible.',
    'payment_not_found' => 'El abono no existe.',
    'limit_above_maximum' => 'El límite de fiado no puede pasar de :max. Para casos de emergencia está la ampliación del límite.',

];
