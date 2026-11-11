<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Recibo de abono {{ $numero }} · {{ $recibo->negocioName }}</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; padding: 24px; background: #f3f4f6; color: #111827; font: 15px/1.5 system-ui, -apple-system, "Segoe UI", sans-serif; }
        .recibo { max-width: 380px; margin: 0 auto; background: #fff; padding: 24px; border: 1px solid #e5e7eb; }
        h1 { margin: 0; font-size: 20px; text-align: center; }
        .sub { margin: 2px 0 16px; text-align: center; color: #4b5563; font-size: 13px; }
        dl { display: grid; grid-template-columns: auto 1fr; gap: 6px 12px; margin: 0; }
        dt { color: #4b5563; }
        dd { margin: 0; text-align: right; font-variant-numeric: tabular-nums; }
        .linea { border-top: 1px dashed #9ca3af; margin: 14px 0; }
        .total dt, .total dd { font-weight: 700; font-size: 17px; color: #111827; }
        .pie { margin-top: 18px; text-align: center; color: #4b5563; font-size: 12px; }
        button { display: block; margin: 16px auto 0; padding: 8px 20px; font: inherit; cursor: pointer; }
        @media print { body { background: #fff; padding: 0; } .recibo { border: 0; } button { display: none; } }
    </style>
</head>
<body>
    <main class="recibo">
        <h1>{{ $recibo->negocioName }}</h1>
        <p class="sub">Recibo de abono N.º {{ $numero }}</p>

        <dl>
            <dt>Fecha</dt><dd>{{ $fecha }}</dd>
            <dt>Cliente</dt><dd>{{ $recibo->customerName }}</dd>
            <dt>Atendió</dt><dd>{{ $recibo->receivedByName }}</dd>
            <dt>Forma de pago</dt><dd>{{ ucfirst($recibo->method) }}</dd>
        </dl>

        <div class="linea"></div>

        <dl>
            <dt>Saldo anterior</dt><dd>{{ $saldoAnterior }}</dd>
            <dt>Abono</dt><dd>− {{ $monto }}</dd>
        </dl>

        <div class="linea"></div>

        <dl class="total">
            <dt>Saldo restante</dt><dd>{{ $saldoRestante }}</dd>
        </dl>

        <p class="pie">Gracias por su pago.</p>
        <button type="button" onclick="window.print()">Imprimir</button>
    </main>
</body>
</html>
