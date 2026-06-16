<!DOCTYPE html>
<html lang="en">

<head>
    <title>CIERRE DE CAJA</title>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <meta name="description" content=""/>
    <style>
        body{
            font-size: 9px;
            text-align: center !important;
        }
    </style>
</head>

<body  style="font-family: Arial, Helvetica, sans-serif;">

    <div class="container-fluid">
        <div class="row">
            <div class="col-lg-12 text-center" style="text-align: center;">
                <h3 style="color: #1A2A54; font-weight: bolder;">{{ $puntoVenta->nombre }}</h3>
            </div>
            <div class="col-lg-12 text-center" style="text-align: center;">
                DIRECCION: {{$puntoVenta->direccion}} <br />
                TELEFONO Y CORREO: <strong>{{ $puntoVenta->telefono }}<strong> / <strong>{{ $puntoVenta->correo }}</strong>
            </div><br />

            @if ($idUusario == 0)
                <div class="col-lg-12" style="text-align: center;">
                    <h4 style="margin: 0; padding: 0px">CORTE DEL DIA</h4>
                    <div style="font-weight: bolder;">{{ $fechaDia }}</div>
                    REALIZADO EL: {{ $fechaTitulo }}<br>
                    CAJERO: TODOS<br>
                    {{ $cajas->nombre }}
                </div><br />
            @else
                <div class="col-lg-12" style="text-align: center;">
                    <h4 style="margin: 0; padding: 0px">CORTE DEL DIA</h4>
                    <div style="font-weight: bolder;">{{ $fechaDia }}</div>
                    REALIZADO EL: {{ $fechaTitulo }}<br>
                    CAJERO: {{ $usuarios->nombre }}<br>
                    {{ $cajas->nombre }}
                </div><br />
            @endif


            <div class="col-lg-12" style="text-align: center;">
                <h4 style="margin: 0; padding: 0px">=== VENTAS DEL DIA ===</h4>
                <div>{{ $cantidadVentasDia }} VENTAS DEL DÍA</div>
            </div><br />

            <div class="col-lg-12" style="text-align: center;">
                <h4 style="margin: 0; padding: 0px">=== ENTRADAS DE EFECTIVO ===</h4>
                <table style="width: 100%;">
                    <tr>
                        <td>INICIO CAJA:</td>
                        <td></td>
                        <td>{{ number_format($inicioCaja, 2) }}</td>
                    </tr>
                    <tr>
                        <td>ENTRADA DINERO:</td>
                        <td></td>
                        <td>{{ number_format($entradaDinero, 2) }}</td>
                    </tr>
                    <tr>
                        <td>TRASPASO ENTRE TIENDAS:</td>
                        <td></td>
                        <td>{{ number_format($totalTraspasoEntreTiendas, 2) }}</td>
                    </tr>
                    <tr>
                        <td>TOTAL:</td>
                        <td></td>
                        <td>{{ number_format($entradaTotal, 2) }}</td>
                    </tr>
                </table>
            </div><br />

            <div class="col-lg-12" style="text-align: center;">
                <h4 style="margin: 0; padding: 0px">=== VENTAS DEL DIA ===</h4>
                <table style="width: 100%;">
                    @foreach ($medioPago as $item)
                    <tr>
                        <td style="text-transform: uppercase;">{{ $item->nombre }}:</td>
                        <td>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</td>
                        <td>{{ number_format($item->total, 2) }}</td>
                    </tr>
                    @endforeach
                </table>
            </div><br />

            <div class="col-lg-12" style="text-align: center;">
                <h4 style="margin: 0; padding: 0px">=== SALIDAS / PROVEEDORES ===</h4>
                <table style="width: 100%;">
                    <tr>
                        <td>TRASPASO ENTRE TIENDAS:</td>
                        <td>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</td>
                        <td>{{ number_format($totalTraspasoEntreTiendasDestino, 2) }}</td>
                    </tr>
                    <tr>
                        <td>TOTAL:</td>
                        <td>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</td>
                        <td>{{ number_format($salidasTotal, 2) }}</td>
                    </tr>
                </table>
            </div><br />

            <div class="col-lg-12" style="text-align: center;">
                <h4 style="margin: 0; padding: 0px">=== PAGOS DE CREDITOS ===</h4>
                <table style="width: 100%;">
                    <tr>
                        <td>TOTAL:</td>
                        <td>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</td>
                        <td>{{ number_format($pagoCreaditos, 2) }}</td>
                    </tr>
                </table>
            </div><br />

            <div class="col-lg-12" style="text-align: center;">
                <h4 style="margin: 0; padding: 0px">=== DINERO EN CAJA ===</h4>
                <table style="width: 100%;">
                    <tr>
                        <td>ENTRADA DE EFECTIVO:</td>
                        <td></td>
                        <td>{{ number_format($entradaTotal, 2) }}</td>
                    </tr>
                    <tr>
                        <td>PAGOS CON EFECTIVO:</td>
                        <td></td>
                        <td>{{ number_format($pagoEfectivo, 2) }}</td>
                    </tr>
                    <tr>
                        <td>PAGOS CON CREDITOS:</td>
                        <td></td>
                        <td>{{ number_format($pagoCreaditos, 2) }}</td>
                    </tr>
                    <tr>
                        <td>PAGOS DE SALIDAS / PROVEEDORES:</td>
                        <td></td>
                        <td>{{ number_format($salidasTotal, 2) }}</td>
                    </tr>
                    <tr>
                        <td>INGRESO DE SOBRANTE:</td>
                        <td></td>
                        <td>{{ number_format($ingresoSobranteTotal, 2) }}</td>
                    </tr>
                    <tr>
                        <td>TOTAL:</td>
                        <td></td>
                        <td>{{ number_format($totalGeneral, 2) }}</td>
                    </tr>
                </table>
            </div><br />

            <div class="col-lg-12" style="text-align: center;">
                <h4 style="margin: 0; padding: 0px">=== GANANCIA DEL DIA ===</h4>
                <table style="width: 100%;">
                    <tr>
                        <td>VALOR DE VENTA DE LOS PRODUCTOS:</td>
                        <td></td>
                        <td>{{ number_format($valorVenta, 2) }}</td>
                    </tr>
                    <tr>
                        <td>VALOR DE COMPRA DE LOS PRODUCTOS:</td>
                        <td></td>
                        <td>{{ number_format($valorComra, 2) }}</td>
                    </tr>
                    <tr>
                        <td>GANANCIA:</td>
                        <td></td>
                        <td>{{ number_format($ganancia, 2) }}</td>
                    </tr>
                </table>
            </div><br />

            <div class="col-lg-12" style="text-align: center;">
                <h4 style="margin: 0; padding: 0px">=== VENTAS POR CATEGORIAS ===</h4>
                <table style="width: 100%;">
                    @foreach ($categorias as $item)
                        <tr>
                            <td>{{ $item->nombre }}:</td>
                            <td>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</td>
                            <td>{{ number_format($item->total, 2) }}</td>
                        </tr>
                    @endforeach
                </table>
            </div>

        </div>
    </div>
</body>

</html>
