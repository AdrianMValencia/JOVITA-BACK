<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Tour</title>
</head>
<body>
    <h1>
        {{$reserva->nombre}}
    </h1>
    <br>
    <table class="table table-bordered">
        <tbody>
            <tr><td>Personas</td><td><strong>: {{$reserva->cantidad}}</strong></td></tr>
            <tr><td>Fecha Inicio</td><td><strong>: {{$reserva->fechaInicio}}</strong></td></tr>
            <tr><td>Fecha Fin</td><td><strong>: {{$reserva->fechaFin}}</strong></td></tr>
        </tbody>
    </table>
    <br>

    <h3>TOUR</h3>
    <table class="table table-bordered">
        <tbody>
            <tr><td>Tour</td><td><strong>:
                @if($translate == "ES")
                    {{$tour->titulo_es}}
                @else
                    {{$tour->titulo_en}}
                @endif
            </strong></td></tr>
            <tr><td>Precio por Persona</td><td><strong>: ${{number_format($tour->precio, 2)}}</strong></td></tr>
            <tr><td>Total</td><td><strong>: ${{number_format($tour->precio * $reserva->cantidad, 2)}}</strong></td></tr>
            <tr><td>Imagen</td><td>: <img src="https://tripinperu.pe/quechuas/backend/storage/app/public/tour1/{{$tour->img_portada}}" width="20%"></td></tr>
        </tbody>
    </table>
</body>
</html>
