<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

/**
 * Servicio para comunicación con la OSE eFact.
 *
 * Flujo:
 * 1. obtenerToken()           → POST /oauth/token         → access_token
 * 2. enviarDocumento()        → POST /v1/document          → ticket
 * 3. obtenerCdr()             → GET  /v1/cdr/{ticket}      → CDR (aceptado/observado/rechazado)
 * 4. obtenerXml()             → GET  /v1/xml/{ticket}      → XML firmado (base64)
 * 5. obtenerPdf()             → GET  /v1/pdf/{ticket}      → PDF (base64)
 */
class EfactOseService
{
    private string $baseUrl;
    private string $clientId;
    private string $clientSecret;
    private string $username;
    private string $password;

    /** Token JWT/Bearer fijo (QA o depuración); mismo header que Postman: Authorization: Bearer … */
    private string $bearerTokenOverride;

    public function __construct()
    {
        $esPrueba = config('services.efact.env', 'prueba') !== 'produccion';

        $this->baseUrl      = rtrim(
            $esPrueba
                ? config('services.efact.url_test', 'https://ose-gw1.efact.pe/api-efact-ose')
                : config('services.efact.url',      'https://ose-gw1.efact.pe/api-efact-ose'),
            '/'
        );
        $this->clientId     = (string) ($esPrueba
            ? config('services.efact.client_id_test', 'client')
            : config('services.efact.client_id', 'client'));
        $this->clientSecret = (string) ($esPrueba
            ? config('services.efact.client_secret_test', 'secret')
            : config('services.efact.client_secret', 'secret'));
        $this->username     = (string) ($esPrueba
            ? config('services.efact.username_test', config('services.efact.client_id_test', ''))
            : config('services.efact.username', config('services.efact.client_id', '')));
        $this->password     = (string) ($esPrueba
            ? config('services.efact.password_test', config('services.efact.client_secret_test', ''))
            : config('services.efact.password', config('services.efact.client_secret', '')));

        $override = (string) ($esPrueba
            ? (config('services.efact.bearer_token_test') ?: config('services.efact.bearer_token', ''))
            : (config('services.efact.bearer_token') ?: ''));
        $this->bearerTokenOverride = trim($override);
    }

    /**
     * Token para llamadas a la OSE: Bearer manual (Postman) o access_token vía OAuth.
     *
     * @return array{success: bool, token?: string, expires_in?: int, error?: string, body?: array}
     */
    private function tokenParaRequests(): array
    {
        if ($this->bearerTokenOverride !== '') {
            return ['success' => true, 'token' => $this->bearerTokenOverride];
        }

        return $this->obtenerToken();
    }

    // -------------------------------------------------------------------------
    // 1. TOKEN
    // -------------------------------------------------------------------------

    /**
     * Obtiene un access_token de la OSE usando OAuth2 client_credentials.
     *
     * @return array{success: bool, token?: string, expires_in?: int, error?: string}
     */
    public function obtenerToken(): array
    {
        if ($this->username === '' || $this->password === '') {
            return [
                'success' => false,
                'error'   => 'Faltan las credenciales REST de eFact para obtener el token',
                'body'    => [],
            ];
        }

        try {
            $response = Http::asForm()
                ->withBasicAuth($this->clientId, $this->clientSecret)
                ->acceptJson()
                ->timeout(30)
                ->post("{$this->baseUrl}/oauth/token", [
                    'grant_type' => 'password',
                    'username' => $this->username,
                    'password' => $this->password,
                ]);

            if (! $response->successful()) {
                $response = Http::asForm()
                    ->withBasicAuth($this->clientId, $this->clientSecret)
                    ->acceptJson()
                    ->timeout(30)
                    ->post("{$this->baseUrl}/oauth/token", [
                        'grant_type' => 'client_credentials',
                    ]);
            }

            if (! $response->successful()) {
                return [
                    'success' => false,
                    'error'   => 'Error al obtener token: HTTP ' . $response->status(),
                    'body'    => $this->decodeResponseBody($response),
                ];
            }

            $data = $this->decodeResponseBody($response);
            $token = $data['access_token'] ?? null;

            if (empty($token)) {
                return [
                    'success' => false,
                    'error'   => 'access_token no encontrado en respuesta',
                    'body'    => $data,
                ];
            }

            return [
                'success'    => true,
                'token'      => $token,
                'expires_in' => $data['expires_in'] ?? null,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error'   => 'Excepción al obtener token: ' . $e->getMessage(),
            ];
        }
    }

    // -------------------------------------------------------------------------
    // 2. ENVIAR DOCUMENTO
    // -------------------------------------------------------------------------

    /**
     * Envía el documento electrónico (JSON+/XML) a la OSE y obtiene ticket.
     *
     * La OSE eFact espera multipart/form-data con el campo file.
     *
     * @param  mixed  $documento  UploadedFile, ruta local, contenido string o base64 en array
     * @return array{success: bool, ticket?: string, body?: array, error?: string, error_code?: string, status?: int}
     */
    public function enviarDocumento(mixed $documento): array
    {
        $tokenResult = $this->tokenParaRequests();
        if (! $tokenResult['success']) {
            return ['success' => false, 'error' => $tokenResult['error'], 'body' => $tokenResult['body'] ?? []];
        }

        $documentoPreparado = $this->resolverDocumento($documento);
        if (! $documentoPreparado['success']) {
            return $documentoPreparado;
        }

        try {
            $response = Http::withToken($tokenResult['token'])
                ->acceptJson()
                ->timeout(60)
                ->attach(
                    'file',
                    $documentoPreparado['content'],
                    $documentoPreparado['filename'],
                    ['Content-Type' => $documentoPreparado['mime']]
                )
                ->post("{$this->baseUrl}/v1/document");

            $body = $this->decodeResponseBody($response);

            if (! $response->successful()) {
                return [
                    'success' => false,
                    'status'  => $response->status(),
                    'error'   => 'Error al enviar documento: HTTP ' . $response->status(),
                    'body'    => $body,
                ];
            }

            $ticket = $this->extraerTicketDesdeBodyDocumento($body);

            return [
                'success' => true,
                'ticket'  => $ticket,
                'status'  => $response->status(),
                'body'    => $body,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error'   => 'Excepción al enviar documento: ' . $e->getMessage(),
            ];
        }
    }

    // -------------------------------------------------------------------------
    // 3. CDR
    // -------------------------------------------------------------------------

    /**
     * Consulta el CDR (Constancia de Recepción) de un ticket.
     *
     * @return array{success: bool, content?: string, filename?: string, mime?: string, body?: array, error?: string, status?: int}
     */
    public function obtenerCdr(string $ticket): array
    {
        return $this->descargarArchivo("{$this->baseUrl}/v1/cdr/{$ticket}", 'cdr-' . $ticket . '.xml', 'application/xml');
    }

    // -------------------------------------------------------------------------
    // 4. XML FIRMADO
    // -------------------------------------------------------------------------

    /**
     * Obtiene el XML firmado a partir del ticket.
     *
     * @return array{success: bool, content?: string, filename?: string, mime?: string, body?: array, error?: string, status?: int}
     */
    public function obtenerXml(string $ticket): array
    {
        return $this->descargarArchivo("{$this->baseUrl}/v1/xml/{$ticket}", 'xml-' . $ticket . '.xml', 'application/xml');
    }

    // -------------------------------------------------------------------------
    // 5. PDF
    // -------------------------------------------------------------------------

    /**
     * Obtiene el PDF a partir del ticket.
     *
     * @return array{success: bool, content?: string, filename?: string, mime?: string, body?: array, error?: string, status?: int}
     */
    public function obtenerPdf(string $ticket): array
    {
        return $this->descargarArchivo("{$this->baseUrl}/v1/pdf/{$ticket}", 'pdf-' . $ticket . '.pdf', 'application/pdf');
    }

    /**
     * @return array{success: bool, content?: string, filename?: string, mime?: string, body?: array, error?: string, status?: int}
     */
    private function descargarArchivo(string $url, string $defaultFilename, string $defaultMime): array
    {
        $tokenResult = $this->tokenParaRequests();
        if (! $tokenResult['success']) {
            return ['success' => false, 'error' => $tokenResult['error'], 'body' => $tokenResult['body'] ?? []];
        }

        try {
            $response = Http::withToken($tokenResult['token'])
                ->timeout(30)
                ->get($url);

            if (! $response->successful()) {
                return [
                    'success' => false,
                    'status'  => $response->status(),
                    'error'   => 'Error al descargar archivo eFact: HTTP ' . $response->status(),
                    'body'    => $this->decodeResponseBody($response),
                ];
            }

            return [
                'success'    => true,
                'status'     => $response->status(),
                'content'    => $response->body(),
                'filename'   => $this->resolverNombreDescarga($response->header('Content-Disposition'), $defaultFilename),
                'mime'       => $response->header('Content-Type') ?: $defaultMime,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error'   => 'Excepción al descargar archivo eFact: ' . $e->getMessage(),
            ];
        }
    }

    // -------------------------------------------------------------------------
    // Helper: construir payload para la OSE a partir de datos internos
    // -------------------------------------------------------------------------

    /**
     * Mapea la cabecera + detalle internos al JSON que espera la OSE eFact
     * en el endpoint POST /v1/document.
     *
     * Ajustar según la especificación técnica de eFact que se recibe al
     * registrar el RUC 20610164537.
     */
    public function buildPayload(array $cabecera, array $detalle): array
    {
        // Determinar tipo de comprobante SUNAT (01=Factura, 03=Boleta)
        $tipoDocSunat = '01'; // por defecto Factura
        $tipoLower    = strtolower($cabecera['tipo'] ?? '');
        if (str_contains($tipoLower, 'boleta') || str_contains($tipoLower, '03')) {
            $tipoDocSunat = '03';
        }

        $items = [];
        foreach ($detalle as $idx => $item) {
            $items[] = [
                'numero_orden'            => $idx + 1,
                'codigo'                  => $item['idProducto'] ?? ($idx + 1),
                'descripcion'             => $item['producto'] ?? '',
                'unidad_de_medida'        => $item['unidadMedida'] ?? 'NIU',
                'cantidad'                => (float) ($item['cantidad'] ?? 1),
                'valor_unitario'          => round((float) ($item['subtotal'] ?? 0) / max((float) ($item['cantidad'] ?? 1), 1), 6),
                'precio_unitario'         => round((float) ($item['precio'] ?? 0), 6),
                'subtotal'                => round((float) ($item['subtotal'] ?? 0), 2),
                'tipo_de_igv'             => 1, // Gravado Operación Onerosa
                'igv'                     => round((float) ($item['igv'] ?? 0), 2),
                'total'                   => round((float) ($item['total'] ?? 0), 2),
                'anticipo_regularizacion' => false,
            ];
        }

        $totalVenta   = round((float) ($cabecera['total'] ?? 0), 2);
        $totalIgv     = round((float) ($cabecera['igv'] ?? 0), 2);
        $totalGravadas = round((float) ($cabecera['subTotal'] ?? 0), 2);

        return [
            'operacion'        => 'generar_comprobante',
            'tipo_de_comprobante' => (int) $tipoDocSunat,
            'serie'            => $cabecera['serie'] ?? '',
            'numero'           => (int) ($cabecera['numero'] ?? 0),
            'sunat_transaction' => 1,
            'cliente_tipo_de_documento' => (int) ($cabecera['tipoDocumento']  ?? 6),
            'cliente_numero_de_documento' => (string) ($cabecera['numeroDocumento'] ?? ''),
            'cliente_denominacion' => $cabecera['cliente'] ?? '',
            'cliente_direccion'    => $cabecera['direccion'] ?? '',
            'cliente_email'        => $cabecera['correo'] ?? '',
            'fecha_de_emision'     => $cabecera['fecha'] ?? now()->format('Y-m-d'),
            'moneda'               => $this->codigoMoneda((int) ($cabecera['idMoneda'] ?? 7)),
            'tipo_de_cambio'       => (float) ($cabecera['tipoCambio'] ?? 1),
            'porcentaje_de_igv'    => 18.00,
            'total_gravada'        => $totalGravadas,
            'total_igv'            => $totalIgv,
            'total'                => $totalVenta,
            'items'                => $items,
        ];
    }

    private function codigoMoneda(int $idMoneda): string
    {
        // Mapeo simple de id interno al código ISO 4217
        return match ($idMoneda) {
            1, 2  => 'USD', // Dólares
            7     => 'PEN', // Soles (por defecto)
            default => 'PEN',
        };
    }

    /**
     * @return array{success: bool, content?: string, filename?: string, mime?: string, error?: string, error_code?: string, status?: int}
     */
    private function resolverDocumento(mixed $documento): array
    {
        if ($documento instanceof UploadedFile) {
            return $this->documentoDesdeUploadedFile($documento, $documento->getClientOriginalName());
        }

        if (is_string($documento)) {
            return $this->documentoDesdeString($documento, 'documento.xml');
        }

        if (! is_array($documento)) {
            return [
                'success' => false,
                'error' => 'No se recibió ningún documento para enviar a eFact',
                'error_code' => 'document_missing',
                'status' => 422,
            ];
        }

        $filename = $this->normalizarNombreArchivo($documento['filename'] ?? $documento['name'] ?? 'documento.xml');

        foreach (['file', 'uploadedFile', 'uploaded_file'] as $key) {
            if (($documento[$key] ?? null) instanceof UploadedFile) {
                return $this->documentoDesdeUploadedFile($documento[$key], $filename);
            }
        }

        foreach (['path', 'xml_path'] as $key) {
            if (is_string($documento[$key] ?? null) && trim($documento[$key]) !== '') {
                return $this->documentoDesdeRuta($documento[$key], $filename);
            }
        }

        foreach (['content', 'xml', 'xml_content'] as $key) {
            if (is_string($documento[$key] ?? null) && trim($documento[$key]) !== '') {
                return $this->documentoDesdeContenido($documento[$key], $filename);
            }
        }

        foreach (['base64', 'xml_base64'] as $key) {
            if (is_string($documento[$key] ?? null) && trim($documento[$key]) !== '') {
                return $this->documentoDesdeBase64($documento[$key], $filename);
            }
        }

        return [
            'success' => false,
            'error' => 'No se recibió ningún documento para enviar a eFact',
            'error_code' => 'document_missing',
            'status' => 422,
        ];
    }

    /**
     * @return array{success: bool, content?: string, filename?: string, mime?: string, error?: string, error_code?: string, status?: int}
     */
    private function documentoDesdeUploadedFile(UploadedFile $file, ?string $filename = null): array
    {
        if (! $file->isValid()) {
            return [
                'success' => false,
                'error' => 'El archivo enviado no es válido',
                'error_code' => 'document_invalid',
                'status' => 422,
            ];
        }

        $path = $file->getRealPath();
        if (! $path) {
            return [
                'success' => false,
                'error' => 'No se pudo leer el archivo enviado',
                'error_code' => 'document_read_failed',
                'status' => 422,
            ];
        }

        return $this->documentoDesdeRuta($path, $filename ?? $file->getClientOriginalName());
    }

    /**
     * @return array{success: bool, content?: string, filename?: string, mime?: string, error?: string, error_code?: string, status?: int}
     */
    private function documentoDesdeRuta(string $path, ?string $filename = null): array
    {
        $path = trim($path);
        if ($path === '' || ! is_file($path)) {
            return [
                'success' => false,
                'error' => 'La ruta del documento no existe o no es accesible',
                'error_code' => 'document_path_not_found',
                'status' => 422,
            ];
        }

        $content = @file_get_contents($path);
        if ($content === false || $content === '') {
            return [
                'success' => false,
                'error' => 'No se pudo leer el archivo indicado',
                'error_code' => 'document_read_failed',
                'status' => 422,
            ];
        }

        return [
            'success' => true,
            'content' => $content,
            'filename' => $this->normalizarNombreArchivo($filename ?? basename($path)),
            'mime' => $this->resolverMimePorNombre($filename ?? basename($path)),
        ];
    }

    /**
     * @return array{success: bool, content?: string, filename?: string, mime?: string, error?: string, error_code?: string, status?: int}
     */
    private function documentoDesdeContenido(string $content, ?string $filename = null): array
    {
        $content = trim($content);
        if ($content === '') {
            return [
                'success' => false,
                'error' => 'El contenido del documento está vacío',
                'error_code' => 'document_empty',
                'status' => 422,
            ];
        }

        if (! $this->pareceXml($content) && ! $this->pareceJson($content)) {
            return $this->documentoDesdeString($content, $filename ?? 'documento.xml');
        }

        return [
            'success' => true,
            'content' => $content,
            'filename' => $this->normalizarNombreArchivo($filename ?? 'documento.xml'),
            'mime' => $this->resolverMimePorNombre($filename ?? null, $content),
        ];
    }

    /**
     * @return array{success: bool, content?: string, filename?: string, mime?: string, error?: string, error_code?: string, status?: int}
     */
    private function documentoDesdeBase64(string $base64, ?string $filename = null): array
    {
        $content = base64_decode(trim($base64), true);
        if ($content === false || trim($content) === '') {
            return [
                'success' => false,
                'error' => 'El documento en base64 no es válido',
                'error_code' => 'document_base64_invalid',
                'status' => 422,
            ];
        }

        if (! $this->pareceXml($content) && ! $this->pareceJson($content)) {
            return [
                'success' => false,
                'error' => 'El contenido decodificado no parece JSON ni XML válido',
                'error_code' => 'document_invalid',
                'status' => 422,
            ];
        }

        return [
            'success' => true,
            'content' => $content,
            'filename' => $this->normalizarNombreArchivo($filename ?? 'documento.json'),
            'mime' => $this->resolverMimePorNombre($filename ?? null, $content),
        ];
    }

    /**
     * @return array{success: bool, content?: string, filename?: string, mime?: string, error?: string, error_code?: string, status?: int}
     */
    private function documentoDesdeString(string $value, ?string $filename = null): array
    {
        $value = trim($value);
        if ($value === '') {
            return [
                'success' => false,
                'error' => 'No se recibió ningún documento para enviar a eFact',
                'error_code' => 'document_missing',
                'status' => 422,
            ];
        }

        if (is_file($value)) {
            return $this->documentoDesdeRuta($value, $filename);
        }

        if ($this->pareceXml($value) || $this->pareceJson($value)) {
            return $this->documentoDesdeContenido($value, $filename);
        }

        return $this->documentoDesdeBase64($value, $filename);
    }

    private function pareceXml(string $value): bool
    {
        return preg_match('/^\s*(<\?xml|<[a-zA-Z][^>]*>)/', $value) === 1;
    }

    private function pareceJson(string $value): bool
    {
        $trimmed = trim($value);
        if ($trimmed === '' || (! str_starts_with($trimmed, '{') && ! str_starts_with($trimmed, '['))) {
            return false;
        }

        json_decode($trimmed, true);
        return json_last_error() === JSON_ERROR_NONE;
    }

    private function normalizarNombreArchivo(?string $filename): string
    {
        $filename = trim((string) $filename);
        if ($filename === '') {
            $filename = 'documento.json';
        }

        $lower = strtolower($filename);
        if (str_ends_with($lower, '.xml') || str_ends_with($lower, '.json')) {
            return $filename;
        }

        return $filename . '.json';
    }

    private function resolverMimePorNombre(?string $filename, ?string $content = null): string
    {
        $nombre = strtolower((string) $filename);
        if (str_ends_with($nombre, '.xml')) {
            return 'text/xml';
        }
        if (str_ends_with($nombre, '.json')) {
            return 'application/json';
        }
        if (is_string($content) && $this->pareceXml($content)) {
            return 'text/xml';
        }

        return 'application/json';
    }

    private function decodeResponseBody($response): array
    {
        $body = $response->json();

        if (is_array($body)) {
            return $body;
        }

        return ['raw' => $response->body()];
    }

    /**
     * POST /v1/document puede devolver el ticket en distintas rutas del JSON o solo en cuerpo crudo.
     */
    private function extraerTicketDesdeBodyDocumento(array $body): ?string
    {
        $candidatos = [
            $body['ticket'] ?? null,
            $body['Ticket'] ?? null,
            is_array($body['data'] ?? null) ? ($body['data']['ticket'] ?? $body['data']['Ticket'] ?? null) : null,
            is_array($body['result'] ?? null) ? ($body['result']['ticket'] ?? $body['result']['Ticket'] ?? null) : null,
            is_array($body['body'] ?? null) ? ($body['body']['ticket'] ?? $body['body']['Ticket'] ?? null) : null,
        ];
        foreach ($candidatos as $c) {
            $n = $this->normalizarTicketOse($c);
            if ($n !== null && $n !== '') {
                return $n;
            }
        }

        $rec = $this->buscarClaveTicketRecursivo($body);
        if ($rec !== null && $rec !== '') {
            return $this->normalizarTicketOse($rec);
        }

        $raw = $body['raw'] ?? null;
        if (is_string($raw) && $raw !== '') {
            $u = $this->extraerUuidEnTexto($raw);
            if ($u !== null) {
                return $u;
            }
        }

        $enc = json_encode($body);
        if ($enc !== false && $enc !== '') {
            $u = $this->extraerUuidEnTexto($enc);
            if ($u !== null) {
                return $u;
            }
        }

        return null;
    }

    private function normalizarTicketOse(mixed $valor): ?string
    {
        if ($valor === null || (! is_string($valor) && ! is_numeric($valor))) {
            return null;
        }
        $t = trim((string) $valor);
        $t = trim($t, " \t\n\r\0\x0B\"'");
        if ($t === '') {
            return null;
        }
        if (preg_match('/^([a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12})$/i', $t, $m) === 1) {
            return strtolower($m[1]);
        }

        return $t;
    }

    private function extraerUuidEnTexto(string $texto): ?string
    {
        if ($texto === '') {
            return null;
        }
        if (preg_match('/([a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12})/i', $texto, $m) === 1) {
            return strtolower($m[1]);
        }

        return null;
    }

    private function buscarClaveTicketRecursivo(array $data, int $profundidad = 0): ?string
    {
        if ($profundidad > 14) {
            return null;
        }
        foreach ($data as $clave => $valor) {
            if (($clave === 'ticket' || $clave === 'Ticket') && (is_string($valor) || is_numeric($valor))) {
                $s = trim((string) $valor);
                if ($s !== '') {
                    return $s;
                }
            }
            if (is_array($valor)) {
                $r = $this->buscarClaveTicketRecursivo($valor, $profundidad + 1);
                if ($r !== null && $r !== '') {
                    return $r;
                }
            }
        }

        return null;
    }

    private function resolverNombreDescarga(?string $contentDisposition, string $defaultFilename): string
    {
        $contentDisposition = (string) $contentDisposition;
        if (preg_match('/filename\*?=(?:UTF-8\'\')?"?([^";]+)"?/i', $contentDisposition, $matches) === 1) {
            return trim(urldecode($matches[1]));
        }

        return $defaultFilename;
    }
}
