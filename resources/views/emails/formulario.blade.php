<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Formulario de Contacto</title>
</head>
<body>
    <h1>Pacha Inca</h1>
    <table class="table table-bordered">
        <tbody>
            <tr><td>Nombre</td><td><strong>: {{$nombres}}</strong></td></tr>
            <tr><td>Correo</td><td><strong>: {{$correo}}</strong></td></tr>
            <tr><td>Teléfono</td><td><strong>: {{$telefono}}</strong></td></tr>
            <tr><td>Mensaje</td><td><strong>: {{$mensaje}}</strong></td></tr>
        </tbody>
    </table>
</body>
</html>
