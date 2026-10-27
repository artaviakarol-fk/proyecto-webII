<?php

declare(strict_types=1);

return [

    'sale_not_found' => 'La venta no existe.',
    'payment_not_found' => 'La venta todavía no tiene un pago.',
    'sale_not_payable' => 'La venta ya no está abierta, por eso no se puede pagar.',
    'payment_already_started' => 'La venta ya tiene un pago pendiente o pagado.',
    'payment_already_settled' => 'El pago ya tiene un resultado final y no puede cambiar.',
    'payment_rejected' => 'El pago fue rechazado (código: :code). Intente con otro medio de pago.',
    'gateway_unavailable' => 'La pasarela de pago no está disponible en este momento. Intente de nuevo en unos segundos.',
    'invalid_charge_reference' => 'La referencia del cobro no es válida.',
    'amount_must_be_positive' => 'El monto del pago debe ser mayor que cero.',
    'method_invalid' => 'El método de pago no es válido.',
    'method_not_supported' => 'La pasarela :provider no admite el método de pago :method.',
    'payment_not_refundable' => 'Solo se puede reembolsar un pago pagado, y una sola vez.',
    'provider_mismatch' => 'El pago se cobró con :charged y la pasarela configurada es :configured: el reembolso debe hacerse con la misma pasarela que cobró.',
    'refund_rejected' => 'La pasarela rechazó el reembolso (motivo: :reason).',
    'refund_not_supported' => 'La pasarela :provider no permite reembolsos desde este sistema; hágalo desde su panel.',
    'sinpe_instructions' => 'Transfiera por SINPE Móvil al número :number el monto exacto de ₡:amount, desde la cuenta con la identificación registrada.',

];
