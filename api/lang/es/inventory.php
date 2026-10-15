<?php

declare(strict_types=1);

return [

    'product_not_found' => 'El producto no existe.',
    'photo_not_found' => 'El producto todavía no tiene foto.',
    'code_in_use' => 'Ya hay otro producto con ese código en el negocio.',
    'code_invalid' => 'El código solo puede tener letras, números, punto, guion y guion bajo, y hasta :max caracteres.',
    'name_invalid' => 'El nombre es obligatorio y puede tener hasta :max caracteres.',
    'category_invalid' => 'La categoría puede tener hasta :max caracteres.',
    'amount_out_of_range' => 'El monto debe ser un número positivo con hasta dos decimales.',
    'price_must_be_positive' => 'El precio de venta debe ser mayor que cero.',
    'stock_out_of_range' => 'El stock debe ser un número entero entre 0 y :max.',
    'receipt_not_found' => 'La entrada de mercadería no existe.',
    'supplier_invalid' => 'El proveedor es obligatorio y puede tener hasta :max caracteres.',
    'quantity_out_of_range' => 'La cantidad debe ser un número entero entre 1 y :max.',
    'receipt_without_lines' => 'La entrada debe tener al menos una línea.',
    'receipt_in_the_future' => 'La fecha de la entrada no puede ser posterior a hoy.',
    'receipt_date_invalid' => 'La fecha de la entrada no es válida.',
    'stock_limit_exceeded' => 'La entrada dejaría un producto con más de :max unidades en stock.',

];
