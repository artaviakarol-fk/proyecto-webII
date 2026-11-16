<?php

declare(strict_types=1);

return [

    'sale_not_found' => 'La venta no existe.',
    'sale_without_lines' => 'La venta debe tener al menos una línea.',
    'duplicate_product' => 'Un producto solo puede aparecer una vez en la venta.',
    'quantity_out_of_range' => 'La cantidad debe ser un número entero entre 1 y :max.',
    'payment_method_invalid' => 'La forma de pago de la venta no es válida.',
    'sale_not_open' => 'La venta ya no está abierta, por eso no se puede modificar ni cobrar.',
    'sale_already_cancelled' => 'La venta ya está anulada.',
    'product_unavailable' => 'Uno de los productos de la venta ya no está disponible.',
    'buyer_id_required_for_liquor' => 'La venta incluye licor: registre la cédula del comprador en cedula_comprador para completarla.',
    'buyer_id_invalid' => 'La cédula del comprador debe tener entre :min y :max dígitos.',
    'expired_units_warning' => 'Hay :count unidades vencidas de :product en el estante: no se pueden vender, pero las demás sí.',
    'insufficient_stock' => 'No alcanza el stock de :product. Disponible para vender: :available.',

    'ai_unavailable' => 'El asistente de IA no está disponible en este momento. Registre la venta a mano.',
    'ai_invalid_answer' => 'El asistente de IA respondió algo que no se pudo usar. Intente de nuevo o registre la venta a mano.',
    'ai_text_invalid' => 'Escriba la venta en un texto de hasta :max caracteres.',

];
