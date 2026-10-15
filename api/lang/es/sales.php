<?php

declare(strict_types=1);

return [

    'sale_not_found' => 'La venta no existe.',
    'sale_without_lines' => 'La venta debe tener al menos una línea.',
    'duplicate_product' => 'Un producto solo puede aparecer una vez en la venta.',
    'quantity_out_of_range' => 'La cantidad debe ser un número entero entre 1 y :max.',
    'amount_out_of_range' => 'El monto de la venta no es válido.',
    'sale_not_open' => 'La venta ya no está abierta, por eso no se puede modificar ni cobrar.',
    'sale_already_cancelled' => 'La venta ya está anulada.',
    'product_unavailable' => 'Uno de los productos de la venta ya no está disponible.',
    'buyer_id_required_for_liquor' => 'La venta incluye licor: registre la cédula del comprador en cedula_comprador para completarla.',
    'buyer_id_invalid' => 'La cédula del comprador debe tener entre :min y :max dígitos.',
    'insufficient_stock' => 'No alcanza el stock de :product. Disponible para vender: :available.',

];
