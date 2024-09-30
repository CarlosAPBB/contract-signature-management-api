<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalles del Contrato</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
</head>

<body>
    <div class="container mt-5">
        <h1 class="text-center">Gestión de Contratos</h1>

        <div class="card mt-4">
            <div class="card-body">
                <h5 class="card-title">Detalles del Contrato</h5>
                <p><strong>Nombre:</strong> {{ $contract->name }}</p>

                <div class="text-center mt-4">
                    <p>Para firmar el documento, haga clic en el enlace a continuación:</p>
                    <a id="signing-link" class="btn btn-primary" href="http://localhost:4200/protected/contract-signing/{{$contract->id}}" target="_blank">Firmar Documento</a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>

</html>
