<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>RECIBOS</title>
</head>
<style>
    table, th, td {
      border: 1px solid black;
      padding: 5px;
    }
    table {
      border-spacing: 15px;
    }
</style>
<body>
    <h2>Estimado(a) {{ $recibos->razonSocial }}: </h2><br>
    <p> Mediante el presente, hacemos el envio de su Recibo {{ $recibos->series }} - {{ $recibos->numeracion }}</p>
    <p><br><br>Atentamente. <br> Jovita</p>
</body>
</html>
