<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Representación impresa CPE</title>
    <style>
        body {
            font-family: "Courier New", Courier, monospace;
            font-size: 10px;
            color: #000;
            margin: 8px;
        }
        .center { text-align: center; }
        .line { border-top: 1px solid #000; margin: 6px 0; }
        table { width: 100%; border-collapse: collapse; }
        td { vertical-align: top; padding: 1px 0; }
        .right { text-align: right; }
        .bold { font-weight: bold; }
        .qr { text-align: center; margin-top: 8px; }
        .footer { text-align: center; font-size: 9px; margin-top: 6px; line-height: 1.3; }
    </style>
</head>
<body>
    <div class="center bold">{{ strtoupper($empresa->nombreLegal ?? $empresa->nombreComercial ?? 'EMPRESA') }}</div>
    <div class="center">RUC: {{ $empresa->ruc }}</div>
    @php
        $direccion = trim((string) ($empresa->direccion ?? ($puntoVenta->direccion ?? '')));
        $ubicacion = trim(collect([
            $ubigeo->distrito ?? null,
            $ubigeo->provincia ?? null,
            $ubigeo->departamento ?? null,
        ])->filter()->implode(' - '));
    @endphp
    @if($direccion !== '')
        <div class="center">{{ $direccion }}</div>
    @endif
    @if($ubicacion !== '')
        <div class="center">{{ $ubicacion }}</div>
    @endif

    <div class="line"></div>
    <div class="center bold">BOLETA DE VENTA ELECTRÓNICA</div>
    <div class="center">Nro. {{ $serie }}-{{ $numero }}</div>
    <div class="line"></div>

    <table>
        <tr>
            <td>Fecha emisión:</td>
            <td class="right">{{ $fechaEmision }} {{ $horaEmision }}</td>
        </tr>
        <tr>
            <td>CAJERO:</td>
            <td class="right">{{ $vendedor }}</td>
        </tr>
        <tr>
            <td>Forma de pago:</td>
            <td class="right">-</td>
        </tr>
        <tr>
            <td>Moneda:</td>
            <td class="right">SOLES (PEN)</td>
        </tr>
        <tr>
            <td>IGV:</td>
            <td class="right">18.00%</td>
        </tr>
    </table>

    <div class="line"></div>
    <div>Cliente: {{ $recibo?->razonSocial ?? ($clienteNombre ?? 'CLIENTE') }}</div>
    <div>Doc.: {{ $numDocCliente }}</div>
    <div class="line"></div>

    <table>
        <tr class="bold">
            <td>Cód.</td>
            <td>Cant.</td>
            <td>Descripción</td>
            <td class="right">Importe</td>
        </tr>
        @foreach($detalles as $item)
            @php
                $codigo = $item->codigoBarra ?? ($item->idProducto ?? '-');
                $nombre = $item->nombre ?? ('Producto ' . ($item->idProducto ?? ''));
                $cantidad = number_format((float) ($item->cantidad ?? 0), 2, '.', '');
                $importe = number_format((float) ($item->total ?? 0), 2, '.', '');
            @endphp
            <tr>
                <td>{{ $codigo }}</td>
                <td>{{ $cantidad }}</td>
                <td>{{ $nombre }}</td>
                <td class="right">{{ $importe }}</td>
            </tr>
        @endforeach
    </table>

    <div class="line"></div>
    @php
        $gravada = $recibo?->totalGravada ?? ($totalGravada ?? 0);
        $igv = $recibo?->totalIgv ?? ($totalIgv ?? 0);
        $totalDoc = $recibo?->total ?? ($total ?? 0);
    @endphp
    <table>
        <tr><td>OP. GRAVADAS:</td><td class="right">S/ {{ number_format((float) $gravada, 2, '.', '') }}</td></tr>
        <tr><td>OP. INAFECTAS:</td><td class="right">S/ 0.00</td></tr>
        <tr><td>OP. EXONERADAS:</td><td class="right">S/ 0.00</td></tr>
        <tr><td>IGV:</td><td class="right">S/ {{ number_format((float) $igv, 2, '.', '') }}</td></tr>
        <tr class="bold"><td>TOTAL:</td><td class="right">S/ {{ number_format((float) $totalDoc, 2, '.', '') }}</td></tr>
    </table>

    <div class="qr">
        @if(!empty($qrSrc))
            <img src="{{ $qrSrc }}" alt="QR" width="180" height="180">
        @endif
    </div>

    <div class="footer">
        Representación impresa de comprobante de pago<br>
        electrónico. Consulte en {{ $urlOse }}
    </div>
</body>
</html>
