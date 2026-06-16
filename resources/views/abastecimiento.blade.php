<!DOCTYPE html>
<html lang="en">

<head>
    <title>ABASTECIMIENTO</title>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <meta name="description" content=""/>
</head>

<body  style="font-family: Arial, Helvetica, sans-serif;">

    <div class="container-fluid">
        <div class="row">
            <div class="col-lg-12 text-center" style="font-size: 13px; text-align: center;">
                <strong class="text-uppercase">{{ $abastecimiento->puntoVenta }}</strong> <br />
                <div style="font-size: 11px;">DIRECCION: {{$puntoVenta->direccion}}</div>
                <div style="font-size: 11px;margin: 0px; padding: 0px">{{ $ubigeo->ubigeo }}</div>
                <div style="font-size: 11px;margin: 0px; padding: 0px">{{ $puntoVenta->celular }}</div>
                <div style="font-size: 11px;margin: 0px; padding: 0px">ABASTECIMIENTO {{ $abastecimiento->numeroEnvio }}</div>
            </div>

            <div class="col-lg-12">
                <table style="width: 100%;">
                    <tr>
                        <td colspan="2" style="font-size: 11px; text-align: right;">{{ date("d/m/Y h:m", strtotime($abastecimiento->created_at)) }}</td>
                    </tr>
                    <tr>
                        <td style="font-size: 11px; width: 30%;"><strong>CAJERO: </strong></td>
                        <td style="font-size: 11px; width: 70%;">{{ $abastecimiento->vendedor }}</td>
                    </tr>
                    <tr>
                        <td colspan="2" style="font-size: 11px; width: 70%;">{{ $cajas->nombre }}</td>
                    </tr>
                    <tr>
                        <td style="font-size: 11px; width: 30%;"><strong>PUNTO DE VENTA DESTINO: </strong></td>
                    </tr>
                    <tr>
                        <td colspan="2" style="font-size: 11px; width: 70%;">{{ $abastecimiento->detalles[0]->puntoVentaNew }}</td>
                    </tr>
                </table>
            </div>
            <br>
            <div class="col-lg-12">
                <table class="table" width="100" style="width: 100%;">
                    <tr>
                        <td style="text-align: left;font-weight:bolder; font-size: 11px;">DESC</td>
                        <td style="text-align: left;font-weight:bolder; font-size: 11px;">CANT.</td>
                        <td style="text-align: right;font-weight:bolder; font-size: 11px;">PRECIO COMPRA</td>
                        <td style="text-align: right;font-weight:bolder; font-size: 11px;">TOTAL</td>
                    </tr>
                    <tr>
                        <td colspan="4"><hr /></td>
                    </tr>
                    @foreach ($abastecimiento->detalles as $item)
                    <tr>
                        <td style="font-size: 11px;">{{ $item->nombre }}</td>
                        <td style="text-align: left; font-weight:bolder; font-size: 11px;">{{ number_format($item->cantidad, 2) }}</td>
                        <td style="text-align: right; font-size: 11px;">{{ number_format($item->precioCompra, 2) }}</td>
                        <td style="text-align: right; font-size: 11px;">{{ number_format($item->precioCompra * $item->cantidad, 2) }}</td>
                    </tr>
                    @endforeach
                </table>
            </div>
            <div class="col-lg-12 mt-4 text-right">
                <table width="100" style="width: 100%;">
                    <tr>
                        <td style="font-size:15px;text-align: right;width: 70%;"><strong>TOTAL:</strong></td>
                        <td style="font-size:15px;text-align: right;width: 30%;">{{ number_format($abastecimiento->total, 2) }}</td>
                    </tr>
                </table>
            </div>
            <br>
            <div class="col-lg-12 mt-4 text-center" style="font-size: 11px; text-align: center;">
                GRACIAS POR SU COMPRA<br>
                WWW.INCODIGOS.COM
            </div>
        </div>
    </div>
</body>

</html>
