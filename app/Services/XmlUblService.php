<?php

namespace App\Services;

use App\Models\DatosEmpresa;
use App\Support\SunatAfectacionIgv;
use Luecano\NumeroALetras\NumeroALetras;
use DateTime;
use Greenter\Model\Client\Client;
use Greenter\Model\Company\Address;
use Greenter\Model\Company\Company;
use Greenter\Model\Sale\Invoice;
use Greenter\Model\Sale\Legend;
use Greenter\Model\Sale\SaleDetail;
use Greenter\Xml\Builder\InvoiceBuilder;

/**
 * Genera el XML UBL 2.1 para SUNAT usando la librería greenter.
 *
 * El documento se genera SIN firma digital ya que eFact actúa como PSE
 * y se encarga de firmar el documento antes de enviarlo a SUNAT.
 */
class XmlUblService
{
    /**
     * Genera el XML UBL 2.1 de un comprobante electrónico.
     *
     * @param  array  $params   Datos del request (sale data)
     * @param  string $serie    Serie del comprobante (e.g. BE01, F001)
     * @param  int    $numero   Número correlativo
     * @return array{success: bool, xml?: string, filename?: string, error?: string}
     */
    public function generarXml(array $params, string $serie, int $numero): array
    {
        /** @var ComprobanteIgvService $igvService */
        $igvService = app(ComprobanteIgvService::class);
        $params = $igvService->aplicarIgvAParams($params);

        $empresa = DatosEmpresa::first();

        if (! $empresa || empty($empresa->ruc)) {
            return [
                'success' => false,
                'error'   => 'No se encontraron datos de la empresa en la base de datos.',
            ];
        }

        try {
            $tipoDocCpe   = $this->resolverTipoDocumento($params['tipoComprobante'] ?? '', $serie);
            $codigoMoneda = $this->resolverMoneda($params['moneda'] ?? ($params['tipoMoneda'] ?? 'PEN'));
            $fechaEmision = $this->resolverFecha($params['fechaEmision'] ?? null);

            $totalGravada = round((float) ($params['totalGravada'] ?? 0), 2);
            $totalIgv     = round((float) ($params['totalIgv'] ?? 0), 2);
            $mtoImpVenta  = round((float) ($params['total'] ?? ($totalGravada + $totalIgv)), 2);

            // Validar detalles
            $detalles = $params['detalles'] ?? [];
            if (empty($detalles) || !is_array($detalles)) {
                return [
                    'success' => false,
                    'error'   => 'El comprobante no tiene líneas de detalle.',
                ];
            }

            $totalesResueltos = $this->resolverTotalesOperativosXml(
                $detalles,
                $totalGravada,
                $totalIgv,
                $mtoImpVenta,
                $codigoMoneda
            );

            // Generar número de documento cliente
            $numDocCliente  = (string) ($params['documento'] ?? ($params['numeroDocumento'] ?? '00000000'));
            $tipoDocCliente = $this->resolverTipoDocCliente($numDocCliente, $tipoDocCpe);
            if ($tipoDocCliente === '0') {
                $numDocCliente = '00000000';
            }

            // Construir XML manualmente
            $xml = $this->construirXmlGreenter(
                $empresa,
                $serie,
                (int) $numero,
                $tipoDocCpe,
                $fechaEmision,
                $codigoMoneda,
                $numDocCliente,
                $tipoDocCliente,
                $params['cliente'] ?? ($params['razonSocial'] ?? 'VARIOS'),
                $detalles,
                $totalesResueltos
            );

            $filename = $this->generarNombreArchivo(
                (string) $empresa->ruc,
                (string) ($params['tipoComprobante'] ?? ''),
                $tipoDocCpe,
                $serie,
                $numero,
                $fechaEmision,
                (string) ($params['tipoResumen'] ?? '')
            );

            return [
                'success'  => true,
                'xml'      => $xml,
                'filename' => $filename,
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error'   => 'Error generando XML UBL 2.1: ' . $e->getMessage(),
            ];
        }
    }

    // =========================================================================
    // Helpers privados
    // =========================================================================

    private function construirXmlGreenter(
        $empresa,
        string $serie,
        int $numero,
        string $tipoDocCpe,
        DateTime $fechaEmision,
        string $codigoMoneda,
        string $numDocCliente,
        string $tipoDocCliente,
        string $razonSocialCliente,
        array $detalles,
        array $totalesResueltos
    ): string {
        // Empresa (proveedor)
        $company = (new Company())
            ->setRuc((string) $empresa->ruc)
            ->setRazonSocial((string) ($empresa->nombreLegal ?? 'EMPRESA'))
            ->setNombreComercial((string) ($empresa->nombreComercial ?? $empresa->nombreLegal ?? 'EMPRESA'))
            ->setAddress((new Address())
                ->setUbigueo('150101')
                ->setDireccion((string) ($empresa->direccion ?? 'SIN DOMICILIO'))
                ->setDistrito('LIMA')
                ->setProvincia('LIMA')
                ->setDepartamento('LIMA')
                ->setUrbanizacion('-')
                ->setCodLocal('0000'));

        // Cliente
        $client = (new Client())
            ->setTipoDoc($tipoDocCliente)
            ->setNumDoc($numDocCliente)
            ->setRznSocial($razonSocialCliente);

        // Líneas de detalle
        $saleDetails = [];

        foreach ($detalles as $item) {
            $cantidad = (float) ($item['cantidad'] ?? 1);
            $subtotal = round((float) ($item['subtotal'] ?? 0), 2);
            $igv = round((float) ($item['igv'] ?? 0), 2);
            $total = round((float) ($item['total'] ?? ($subtotal + $igv)), 2);
            $codigoAfectacion = SunatAfectacionIgv::resolveCodigo($item);

            if (SunatAfectacionIgv::requiereIgvCero($codigoAfectacion) && $igv > 0.0005) {
                throw new \InvalidArgumentException(
                    'Las líneas exoneradas (20) o inafectas (30) deben tener IGV 0 (XML UBL).'
                );
            }

            $precioUnit = $cantidad > 0 ? round($subtotal / $cantidad, 6) : $subtotal;
            $precioConIgv = $cantidad > 0 ? round($total / $cantidad, 6) : $total;

            $detail = (new SaleDetail())
                ->setUnidad('NIU')
                ->setCantidad($cantidad)
                ->setDescripcion((string) ($item['nombre'] ?? 'PRODUCTO'))
                ->setTipAfeIgv($codigoAfectacion);

            if (SunatAfectacionIgv::isGravadoOneroso($codigoAfectacion)) {
                $detail
                    ->setMtoBaseIgv($subtotal)
                    ->setPorcentajeIgv(18.00)
                    ->setIgv($igv)
                    ->setTotalImpuestos($igv)
                    ->setMtoValorVenta($subtotal)
                    ->setMtoValorUnitario($precioUnit)
                    ->setMtoPrecioUnitario($precioConIgv);
            } else {
                $pu = $cantidad > 0 ? round($total / $cantidad, 6) : $total;
                $detail
                    ->setMtoBaseIgv($subtotal)
                    ->setPorcentajeIgv(0.0)
                    ->setIgv(0.0)
                    ->setTotalImpuestos(0.0)
                    ->setMtoValorVenta($subtotal)
                    ->setMtoValorUnitario($pu)
                    ->setMtoPrecioUnitario($pu);
            }

            $saleDetails[] = $detail;
        }

        // Leyenda (monto en letras)
        $legend = (new Legend())
            ->setCode('1000')
            ->setValue($totalesResueltos['leyendaMonto']);

        // Factura / Boleta
        $invoice = (new Invoice())
            ->setUblVersion('2.1')
            ->setTipoOperacion('0101')
            ->setTipoDoc($tipoDocCpe)
            ->setSerie($serie)
            ->setCorrelativo((string) $numero)
            ->setFechaEmision($fechaEmision)
            ->setTipoMoneda($codigoMoneda)
            ->setCompany($company)
            ->setClient($client)
            ->setMtoOperGravadas($totalesResueltos['mtoOperGravadas'])
            ->setMtoOperInafectas($totalesResueltos['mtoOperInafectas'])
            ->setMtoOperExoneradas($totalesResueltos['mtoOperExoneradas'])
            ->setMtoIGV($totalesResueltos['mtoIGV'])
            ->setTotalImpuestos($totalesResueltos['mtoIGV'])
            ->setValorVenta($totalesResueltos['valorVenta'])
            ->setSubTotal($totalesResueltos['mtoImpVenta'])
            ->setMtoImpVenta($totalesResueltos['mtoImpVenta'])
            ->setDetails($saleDetails)
            ->setLegends([$legend]);

        $builder = new InvoiceBuilder();

        return $builder->build($invoice);
    }

    /**
     * Totales de cabecera Greenter / SUNAT según afectación por línea.
     *
     * @return array{
     *   hayNoGravadas: bool,
     *   mtoOperGravadas: float,
     *   mtoOperInafectas: float,
     *   mtoOperExoneradas: float,
     *   mtoIGV: float,
     *   valorVenta: float,
     *   mtoImpVenta: float,
     *   leyendaMonto: string
     * }
     */
    private function resolverTotalesOperativosXml(
        array $detalles,
        float $totalGravada,
        float $totalIgv,
        float $mtoImpVenta,
        string $codigoMoneda
    ): array {
        $agr = [
            '10' => ['base' => 0.0, 'igv' => 0.0],
            '20' => ['base' => 0.0],
            '30' => ['base' => 0.0],
        ];
        $hayNoGravadas = false;

        foreach ($detalles as $item) {
            $codigo = SunatAfectacionIgv::resolveCodigo($item);
            if (! SunatAfectacionIgv::isGravadoOneroso($codigo)) {
                $hayNoGravadas = true;
            }

            $subtotal = round((float) ($item['subtotal'] ?? 0), 2);
            $igv = round((float) ($item['igv'] ?? 0), 2);

            if (SunatAfectacionIgv::requiereIgvCero($codigo) && $igv > 0.0005) {
                throw new \InvalidArgumentException(
                    'Las líneas exoneradas (20) o inafectas (30) deben tener IGV 0 (XML UBL).'
                );
            }

            if (SunatAfectacionIgv::isGravadoOneroso($codigo)) {
                $agr['10']['base'] += $subtotal;
                $agr['10']['igv'] += $igv;
            } elseif ($codigo === SunatAfectacionIgv::EXONERADO) {
                $agr['20']['base'] += $subtotal;
            } else {
                $agr['30']['base'] += $subtotal;
            }
        }

        if (! $hayNoGravadas) {
            $mtoImp = round($mtoImpVenta, 2);
        } else {
            $valorVenta = round($agr['10']['base'] + $agr['20']['base'] + $agr['30']['base'], 2);
            $igvDoc = round($agr['10']['igv'], 2);
            $mtoImp = round($valorVenta + $igvDoc, 2);
        }

        $formatter = new NumeroALetras();
        $unidad = ($codigoMoneda === 'USD') ? 'DOLARES AMERICANOS' : 'SOLES';
        $leyendaMonto = $formatter->toInvoice($mtoImp, 2, $unidad);

        if (! $hayNoGravadas) {
            return [
                'hayNoGravadas' => false,
                'mtoOperGravadas' => round($totalGravada, 2),
                'mtoOperInafectas' => 0.0,
                'mtoOperExoneradas' => 0.0,
                'mtoIGV' => round($totalIgv, 2),
                'valorVenta' => round($totalGravada, 2),
                'mtoImpVenta' => $mtoImp,
                'leyendaMonto' => $leyendaMonto,
            ];
        }

        return [
            'hayNoGravadas' => true,
            'mtoOperGravadas' => round($agr['10']['base'], 2),
            'mtoOperInafectas' => round($agr['30']['base'], 2),
            'mtoOperExoneradas' => round($agr['20']['base'], 2),
            'mtoIGV' => round($agr['10']['igv'], 2),
            'valorVenta' => round($agr['10']['base'] + $agr['20']['base'] + $agr['30']['base'], 2),
            'mtoImpVenta' => $mtoImp,
            'leyendaMonto' => $leyendaMonto,
        ];
    }

    private function resolverTipoDocumento(string $tipo, string $serie): string
    {
        $tipo  = strtoupper($tipo);
        $serie = strtoupper($serie);

        if (str_contains($tipo, 'NOTA DE CREDITO') || str_starts_with($serie, 'FC') || str_starts_with($serie, 'BC')) {
            return '07';
        }

        if (str_contains($tipo, 'NOTA DE DEBITO') || str_starts_with($serie, 'FD') || str_starts_with($serie, 'BD')) {
            return '08';
        }

        if (str_contains($tipo, 'RETENCION') || str_starts_with($serie, 'R')) {
            return '20';
        }

        if (str_contains($tipo, 'PERCEPCION') || str_starts_with($serie, 'P')) {
            return '40';
        }

        if (str_contains($tipo, 'GUIA DE REMISION - REMITENTE') || str_contains($tipo, 'GUIA DE REMISION REMITENTE') || str_starts_with($serie, 'T')) {
            return '09';
        }

        if (str_contains($tipo, 'GUIA DE REMISION - TRANSPORTISTA') || str_contains($tipo, 'GUIA DE REMISION TRANSPORTISTA') || str_starts_with($serie, 'V')) {
            return '31';
        }

        if (str_contains($tipo, 'FACTURA') || str_starts_with($serie, 'F')) {
            return '01';
        }

        if (str_contains($tipo, 'BOLETA') || str_starts_with($serie, 'B')) {
            return '03';
        }

        return '03'; // por defecto boleta
    }

    private function resolverTipoDocCliente(string $numDoc, string $tipoDocCpe): string
    {
        $digits = preg_replace('/\D/', '', $numDoc);

        // Boleta con cliente genérico: SUNAT/eFact suele usar código 0.
        if ($tipoDocCpe === '03' && ($digits === '' || $digits === '0' || $digits === '00000000')) {
            return '0';
        }

        if (strlen($digits) === 11) {
            return '6'; // RUC
        }

        if (strlen($digits) === 8) {
            return '1'; // DNI
        }

        if ($tipoDocCpe === '01') {
            return '6'; // Factura siempre requiere RUC
        }

        return '0'; // Boleta — consumidor final sin documento
    }

    private function resolverMoneda(string $moneda): string
    {
        $map = [
            'S/'     => 'PEN',
            'PEN'    => 'PEN',
            'SOL'    => 'PEN',
            'SOLES'  => 'PEN',
            '$'      => 'USD',
            'USD'    => 'USD',
            'DOLAR'  => 'USD',
            'DOLARES'=> 'USD',
        ];

        return $map[strtoupper(trim($moneda))] ?? 'PEN';
    }

    private function resolverFecha(?string $fecha): \DateTime
    {
        if ($fecha) {
            try {
                return new \DateTime($fecha);
            } catch (\Throwable $e) {
                // fall through to default
            }
        }

        return new \DateTime();
    }

    private function generarNombreArchivo(
        string $ruc,
        string $tipoComprobante,
        string $codigoTipoDocumento,
        string $serie,
        int $numero,
        \DateTime $fechaEmision,
        string $tipoResumen = ''
    ): string {
        $ruc = preg_replace('/\D/', '', $ruc) ?: $ruc;
        $tipoResumen = strtoupper(trim($tipoResumen));
        $tipoComprobanteUpper = strtoupper(trim($tipoComprobante));

        $codigoEspecial = $this->resolverCodigoEspecial($tipoComprobanteUpper, $tipoResumen);
        if ($codigoEspecial !== null) {
            $fecha = $fechaEmision->format('Ymd');
            return $ruc . '-' . $codigoEspecial . '-' . $fecha . '-' . max(1, $numero) . '.xml';
        }

        $serieNormalizada = strtoupper(preg_replace('/[^A-Z0-9]/', '', $serie));
        $serieNormalizada = substr(str_pad($serieNormalizada, 4, '0', STR_PAD_RIGHT), 0, 4);
        $correlativo = str_pad((string) max(1, $numero), 8, '0', STR_PAD_LEFT);

        return $ruc . '-' . $codigoTipoDocumento . '-' . $serieNormalizada . '-' . $correlativo . '.xml';
    }

    private function resolverCodigoEspecial(string $tipoComprobante, string $tipoResumen): ?string
    {
        if (in_array($tipoResumen, ['RR', 'RA', 'RC'], true)) {
            return $tipoResumen;
        }

        if (str_contains($tipoComprobante, 'REVERSION')) {
            return 'RR';
        }

        if (str_contains($tipoComprobante, 'COMUNICACION DE BAJA') || str_contains($tipoComprobante, 'BAJA')) {
            return 'RA';
        }

        if (str_contains($tipoComprobante, 'RESUMEN DIARIO') || str_contains($tipoComprobante, 'RESUMEN')) {
            return 'RC';
        }

        return null;
    }
}
