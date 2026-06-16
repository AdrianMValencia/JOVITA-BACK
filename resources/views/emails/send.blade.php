<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Formulario de Contacto</title>
</head>
<body>
    <h1>Quechuas People</h1>
    <table class="table table-bordered">
        <tbody>
            <tr><td>Nombre</td><td><strong>: {{$mensaje->nombres}}</strong></td></tr>
            <tr><td>Correo</td><td><strong>: {{$mensaje->correo}}</strong></td></tr>
            <tr><td>Telefono</td><td><strong>: {{$mensaje->telefono}}</strong></td></tr>
            <tr><td>Mensaje</td><td><strong>: {{$mensaje->mensaje}}</strong></td></tr>
        </tbody>
    </table>

    <h3>TOUR</h3>
    <table class="table table-bordered">
        <tbody>
            <tr><td>Tour</td><td><strong>:
                @if($mensaje->translate == "ES")
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
