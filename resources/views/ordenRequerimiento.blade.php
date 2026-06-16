<!DOCTYPE html>
<html lang="en">

<head>
    <title>PEDIDOS</title>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <meta name="description" content=""/>
</head>

<body  style="font-family: Arial, Helvetica, sans-serif;">

    <div class="container-fluid">
        <div class="row">
            <div class="col-lg-12 text-center" style="font-size: 13px; text-align: center;">
                <strong class="text-uppercase">{{ $ordenRequerimiento->puntoventa }}</strong> <br />
                <div style="font-size: 8px;">DIRECCION: {{$puntoVenta->direccion}}</div>
                <div style="font-size: 8px;margin: 0px; padding: 0px">{{ $ubigeo->ubigeo }}</div>
                <div style="font-size: 8px;margin: 0px; padding: 0px">{{ $puntoVenta->celular }}</div>
                <div style="font-size: 8px;margin: 0px; padding: 0px">ORDEN DE REQUERIMIENTO {{ str_pad($ordenRequerimiento->id,4,"0",STR_PAD_LEFT) }}</div>
            </div>

            <div class="col-lg-12">
                <table style="width: 100%;">
                    <tr>
                        <td colspan="2" style="font-size: 8px; text-align: right;">{{ date("d/m/Y h:m", strtotime($ordenRequerimiento->created_at)) }}</td>
                    </tr>
                    <tr>
                        <td style="font-size: 8px; width: 30%;"><strong>CAJERO: </strong></td>
                        <td style="font-size: 8px; width: 70%;">{{ $ordenRequerimiento->vendedor }}</td>
                    </tr>
                    <tr>
                        <td colspan="2" style="font-size: 8px; width: 70%;">{{ $cajas->nombre }}</td>
                    </tr>
                </table>
            </div>
            <br>
            <div class="col-lg-12">
                <table class="table" width="100" style="width: 100%;">
                    <tr>
                        <td style="border-right: 1px solid #212121; text-align: center;font-weight:bolder; font-size: 7px;">DESC</td>
                        <td style="border-right: 1px solid #212121; text-align: center;font-weight:bolder; font-size: 7px;">CANT PQTE</td>
                        <td style="border-right: 1px solid #212121; text-align: center;font-weight:bolder; font-size: 7px;">PAQTE CONTIENE</td>
                        <td style="border-right: 1px solid #212121; text-align: center;font-weight:bolder; font-size: 7px;">PRECIO COMPRA</td>
                        <td style="border-right: 1px solid #212121; text-align: center;font-weight:bolder; font-size: 7px;">TOTAL</td>
                    </tr>
                    <tr>
                        <td style="margin-top: 0; margin-bottom: 0; padding-bottom: 0; padding-top: 0;" colspan="5"><hr /></td>
                    </tr>
                    @foreach ($ordenRequerimiento->detalles as $item)
                    <tr>
                        <td style="border-right: 1px solid #212121; text-align: left; font-size: 5px;">{{ $item->nombre }}</td>
                        <td style="border-right: 1px solid #212121; text-align: right; font-weight:bolder; font-size: 7px;">{{ $item->cantidadPaquetes }}</td>
                        <td style="border-right: 1px solid #212121; text-align: right; font-weight:bolder; font-size: 7px;">{{ $item->cantidad }}</td>
                        <td style="border-right: 1px solid #212121; text-align: right; font-size: 7px;">{{ number_format($item->precioCompra, 2) }}</td>
                        <td style="border-right: 1px solid #212121; text-align: right; font-size: 7px;">{{ number_format($item->total, 2) }}</td>
                    </tr>
                    @endforeach
                </table>
            </div>
            <div class="col-lg-12 mt-4 text-right">
                <table width="100" style="width: 100%;">
                    <tr>
                        <td style="font-size:10px;text-align: right;width: 70%;"><strong>TOTAL:</strong></td>
                        <td style="font-size:10px;text-align: right;width: 30%;">{{ number_format($ordenRequerimiento->total, 2) }}</td>
                    </tr>
                </table>
            </div>
            <br>
            <div class="col-lg-12 mt-4 text-center" style="font-size: 8px; text-align: center;">
                GRACIAS POR SU COMPRA<br>
                WWW.INCODIGOS.COM
            </div>
        </div>
    </div>
</body>

</html>
