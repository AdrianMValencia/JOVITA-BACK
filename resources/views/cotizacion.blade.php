<!DOCTYPE html>
<html lang="en">

<head>
    <title>PEDIDO</title>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <meta name="description" content=""/>
</head>

<body  style="font-family: Arial, Helvetica, sans-serif;">

    <div class="container-fluid">
        <div class="row">
            <div class="col-lg-12 text-center" style="text-align: center;">
                <h3 style="color: #1A2A54; font-weight: bolder;">JOVIA</h3>
            </div>
            <div class="col-lg-12 text-center" style="font-size: 14px; text-align: center;">
                <strong>JOVIA</strong> <br />
                AVENIDA IQUITOS 333 <br />
                LA VICTORIA - LIMA - LIMA <br />
                <strong>RUC 20503074339</strong> <br />
                <strong>RECIBO</strong><br />
                <strong>{{ $cotizacion->numero }}</strong>
            </div>

            <div class="col-lg-12">
                <table>
                    <tr>
                        <td style="font-size: 14px; width: 160px;"><strong>ADQUIRIENTE </strong></td>
                    </tr>
                    <tr>
                        <td style="font-size: 14px; width: 100px;">RUC: {{ $cotizacion->documento }}</td>
                    </tr>
                    <tr>
                        <td style="font-size: 14px; width: 600px;">{{ $cotizacion->razonSocial }}</td>
                    </tr>
                    <tr>
                        <td style="font-size: 14px; width: 600px;">{{ $cotizacion->clientes->direccion }}</td>
                    </tr>
                    <tr>
                        <td style="font-size: 14px; width: 600px;"><strong>FECHA EMISIÓN:</strong> {{ date("d/m/Y", strtotime($cotizacion->fechaCotizacion)) }}</td>
                    </tr>
                    <tr>
                        <td style="font-size: 14px; width: 600px;"><strong>IGV:</strong> 18.00%</td>
                    </tr>
                </table>
            </div>
            <hr />
            <div class="col-lg-12">
                <table class="table" width="100" style="width: 100%;">
                    <tr>
                        <td style="text-align: left;font-weight:bolder; font-size: 14px;">DESCRIPCIÓN</td>
                        <td style="text-align: right;font-weight:bolder; font-size: 14px;">CANT.</td>
                        <td style="text-align: right;font-weight:bolder; font-size: 14px;">V/U</td>
                        <td style="text-align: right;font-weight:bolder; font-size: 14px;">P/U</td>
                        <td style="text-align: right;font-weight:bolder; font-size: 14px;">% Descuento</td>
                        <td style="text-align: right;font-weight:bolder; font-size: 14px;">IGV</td>
                        <td style="text-align: right;font-weight:bolder; font-size: 14px;">SUBTOTAL</td>
                        <td style="text-align: right;font-weight:bolder; font-size: 14px;">TOTAL</td>
                    </tr>
                    @foreach ($cotizacion->detalles as $item)
                    <tr>
                        <td style="font-size: 14px;">{{ $item->nombre }}</td>
                        <td style="text-align: right; font-weight:bolder; font-size: 14px;">{{ $item->cantidad }}</td>
                        <td style="text-align: right; font-size: 14px;">{{ number_format(($item->precio / 1.18), 2) }}</td>
                        <td style="text-align: right; font-size: 14px;">{{ number_format($item->precio, 2) }}</td>
                        <td style="text-align: right; font-size: 14px;">% {{ number_format($item->porcentajeDesc, 2) }}</td>
                        <td style="text-align: right; font-size: 14px;">{{ number_format($item->igv, 2) }}</td>
                        <td style="text-align: right; font-size: 14px;">{{ number_format($item->subtotal, 2) }}</td>
                        <td style="text-align: right; font-size: 14px;">{{ number_format($item->total, 2) }}</td>
                    </tr>
                    @endforeach
                </table>
            </div>
            <hr />
            <div class="col-lg-12 mt-4 text-right">
                <table width="100" style="width: 100%;">
                    <tr>
                        <td style="width: 350px; text-align: right;"><strong>GRAVADA</strong></td>
                        <td style="width: 100px; text-align:right;">{{ number_format($cotizacion->subtotal, 2) }}</td>
                    </tr>
                    <tr>
                        <td style="width: 350px; text-align: right;"><strong>IGV 18.00%</strong></td>
                        <td style="width: 100px; text-align: right;">{{ number_format($cotizacion->impuesto, 2) }}</td>
                    </tr>
                    <tr>
                        <td style="width: 350px; text-align: right;"><strong>TOTAL</strong></td>
                        <td style="width: 100px; text-align: right;">{{ number_format($cotizacion->total, 2) }}</td>
                    </tr>
                </table>
            </div>

            <div class="col-lg-12 text-left mt-4" style="text-align: center; margin-top: 3%;">
                {{-- <div><strong>IMPORTE EN LETRAS:</strong> {{  \NumeroALetras::convertir($cotizacion->total, 'soles') }}</div> --}}
            </div>

        </div>
    </div>
</body>

</html>
