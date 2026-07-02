<?php

namespace App\Services;

use App\Models\DatosEmpresa;
use App\Support\SunatAfectacionIgv;
use Luecano\NumeroALetras\NumeroALetras;

class JsonUblService
{
    public function __construct(
        private readonly EfactEmisionContextEnricher $contextEnricher,
    ) {
    }

    /**
     * Genera una estructura JSON+ básica para boleta/factura.
     *
     * @return array{success: bool, json?: string, filename?: string, error?: string}
     */
    public function generarJson(array $params, string $serie, int $numero): array
    {
        /** @var ComprobanteIgvService $igvService */
        $igvService = app(ComprobanteIgvService::class);
        $params = $igvService->aplicarIgvAParams($params);
        $params = $this->contextEnricher->enriquecer($params);

        $idPv = isset($params['idPuntoVenta']) ? (int) $params['idPuntoVenta'] : null;
        $empresa = $this->contextEnricher->resolverDatosEmpresa($idPv);
        if (! $empresa || empty($empresa->ruc)) {
            return [
                'success' => false,
                'error' => 'No se encontraron datos de la empresa para generar JSON+.',
            ];
        }

        $tipoDoc = $this->resolverTipoDocumento($params['tipoComprobante'] ?? '', $serie);
        $fecha = $this->resolverFecha($params['fechaEmision'] ?? null);
        $moneda = $this->resolverMoneda($params['moneda'] ?? ($params['tipoMoneda'] ?? 'PEN'));
        $detalles = is_array($params['detalles'] ?? null) ? $params['detalles'] : [];
        if ($detalles === []) {
            return [
                'success' => false,
                'error' => 'El comprobante no tiene líneas de detalle.',
            ];
        }

        $totalGravada = round((float) ($params['totalGravada'] ?? 0), 2);
        $totalIgv = round((float) ($params['totalIgv'] ?? 0), 2);
        $total = round((float) ($params['total'] ?? ($totalGravada + $totalIgv)), 2);

        $agr = [
            '10' => ['base' => 0.0, 'igv' => 0.0],
            '20' => ['base' => 0.0],
            '30' => ['base' => 0.0],
        ];
        $invoiceLines = [];
        $hayLineasNoGravadas = false;

        foreach ($detalles as $idx => $item) {
            $codigoAfectacion = SunatAfectacionIgv::resolveCodigo($item);
            if (! SunatAfectacionIgv::isGravadoOneroso($codigoAfectacion)) {
                $hayLineasNoGravadas = true;
            }

            $cantidad = round((float) ($item['cantidad'] ?? 1), 10);
            $subtotal = round((float) ($item['subtotal'] ?? 0), 2);
            $igv = round((float) ($item['igv'] ?? 0), 2);
            $lineTotal = round((float) ($item['total'] ?? ($subtotal + $igv)), 2);

            if (SunatAfectacionIgv::requiereIgvCero($codigoAfectacion) && $igv > 0.0005) {
                return [
                    'success' => false,
                    'error' => 'Las líneas exoneradas (20) o inafectas (30) deben tener IGV 0. Revise codigoAfectacionIgv / importes.',
                ];
            }

            if (SunatAfectacionIgv::isGravadoOneroso($codigoAfectacion)) {
                $agr['10']['base'] += $subtotal;
                $agr['10']['igv'] += $igv;
            } elseif ($codigoAfectacion === SunatAfectacionIgv::EXONERADO) {
                $agr['20']['base'] += $subtotal;
            } else {
                $agr['30']['base'] += $subtotal;
            }

            $invoiceLines[] = $this->construirInvoiceLineJson(
                $item,
                $idx,
                $moneda,
                $codigoAfectacion,
                $cantidad,
                $subtotal,
                $igv,
                $lineTotal
            );
        }

        $lineExtensionLegal = $totalGravada;
        $totalIgvDocumento = $totalIgv;
        $totalDocumento = $total;

        if ($hayLineasNoGravadas) {
            $lineExtensionLegal = round($agr['10']['base'] + $agr['20']['base'] + $agr['30']['base'], 2);
            $totalIgvDocumento = round($agr['10']['igv'], 2);
            $totalDocumento = round($lineExtensionLegal + $totalIgvDocumento, 2);
        }

        if ($tipoDoc === '01') {
            $rucFactura = $this->normalizarRucReceptorFactura($params);
            if ($rucFactura === null) {
                return [
                    'success' => false,
                    'error' => 'La factura electrónica exige RUC del cliente (11 dígitos). Envíe rucCliente, numeroRuc o numeroDocumento con RUC válido.',
                ];
            }
            $tipoDocCliente = '6';
            $numeroDocCliente = $rucFactura;
        } else {
            [$tipoDocCliente, $numeroDocCliente] = $this->resolverIdentificacionClienteBoleta($params, $tipoDoc);
        }

        $formatter = new NumeroALetras();
        $unidadMoneda = $moneda === 'USD' ? 'DOLARES AMERICANOS' : 'SOLES';
        $totalEnLetras = $formatter->toInvoice($totalDocumento, 2, $unidadMoneda);

        $serieNorm = $this->serieNormalizada($serie);
        $correlativoPad = $this->correlativoPad8(max(1, $numero));
        $idComprobante = $serieNorm . '-' . $correlativoPad;

        $notes = [[
            '_' => $totalEnLetras,
            'languageLocaleID' => '1000',
        ]];
        $nombreVendedor = trim((string) ($params['nombreVendedor'] ?? ($params['vendedor'] ?? '')));
        if ($nombreVendedor !== '') {
            $notes[] = [
                '_' => 'Vendedor: ' . $nombreVendedor,
            ];
        }

        $invoiceRoot = array_merge(
            [
                'UBLVersionID' => [['_'=> '2.1']],
                'CustomizationID' => [['_'=> '2.0']],
                'ID' => [['_'=> $idComprobante]],
                'IssueDate' => [['_'=> $fecha]],
                'IssueTime' => [[
                    '_' => $params['issueTime'] ?? date('H:i:s'),
                ]],
            ],
            [
                'InvoiceTypeCode' => [[
                    '_' => $tipoDoc,
                    'listName' => 'Tipo de Documento',
                    'listSchemeURI' => 'urn:pe:gob:sunat:cpe:see:gem:catalogos:catalogo51',
                    'listID' => '0101',
                    'name' => 'Tipo de Operacion',
                    'listURI' => 'urn:pe:gob:sunat:cpe:see:gem:catalogos:catalogo01',
                    'listAgencyName' => 'PE:SUNAT',
                ]],
                'Note' => $notes,
                'DocumentCurrencyCode' => [[
                    '_' => $moneda,
                    'listID' => 'ISO 4217 Alpha',
                    'listName' => 'Currency',
                    'listAgencyName' => 'United Nations Economic Commission for Europe',
                ]],
                'LineCountNumeric' => [['_'=> (string) count($invoiceLines)]],
                'Signature' => [[
                    'ID' => [['_'=> 'IDSignature']],
                    'SignatoryParty' => [[
                        'PartyIdentification' => [[
                            'ID' => [['_'=> preg_replace('/\D/', '', (string) $empresa->ruc)]],
                        ]],
                        'PartyName' => [[
                            'Name' => [['_'=> (string) ($empresa->nombreLegal ?? 'EMPRESA')]],
                        ]],
                    ]],
                    'DigitalSignatureAttachment' => [[
                        'ExternalReference' => [[
                            'URI' => [['_'=> 'IDSignature']],
                        ]],
                    ]],
                ]],
                'AccountingSupplierParty' => $this->accountingSupplierParty($empresa, $params),
                'AccountingCustomerParty' => $this->accountingCustomerParty($tipoDoc, $params, $numeroDocCliente, $tipoDocCliente),
            ]
        );

        if ($nombreVendedor !== '') {
            $invoiceRoot['SellerSupplierParty'] = $this->sellerSupplierParty($nombreVendedor);
        }

        if ($tipoDoc === '01') {
            $invoiceRoot['PaymentTerms'] = [[
                'ID' => [[
                    '_' => 'FormaPago',
                ]],
                'PaymentMeansID' => [[
                    '_' => $params['formaPago'] ?? 'Contado',
                ]],
            ]];
        }

        if ($hayLineasNoGravadas) {
            $invoiceRoot['TaxTotal'] = $this->construirTaxTotalDocumentoVariosTipos($agr, $moneda);
        } else {
            $invoiceRoot['TaxTotal'] = [[
                'TaxAmount' => [[
                    '_' => number_format($totalIgv, 2, '.', ''),
                    'currencyID' => $moneda,
                ]],
                'TaxSubtotal' => [[
                    'TaxableAmount' => [[
                        '_' => number_format($totalGravada, 2, '.', ''),
                        'currencyID' => $moneda,
                    ]],
                    'TaxAmount' => [[
                        '_' => number_format($totalIgv, 2, '.', ''),
                        'currencyID' => $moneda,
                    ]],
                    'TaxCategory' => [[
                        'TaxScheme' => [[
                            'ID' => [[
                                '_' => '1000',
                                'schemeName' => 'Codigo de tributos',
                                'schemeURI' => 'urn:pe:gob:sunat:cpe:see:gem:catalogos:catalogo05',
                                'schemeAgencyName' => 'PE:SUNAT',
                            ]],
                            'Name' => [[
                                '_' => 'IGV',
                            ]],
                            'TaxTypeCode' => [[
                                '_' => 'VAT',
                            ]],
                        ]],
                    ]],
                ]],
            ]];
        }

        $invoiceRoot['LegalMonetaryTotal'] = [[
            'LineExtensionAmount' => [[
                '_' => number_format($lineExtensionLegal, 2, '.', ''),
                'currencyID' => $moneda,
            ]],
            'TaxInclusiveAmount' => [[
                '_' => number_format($totalDocumento, 2, '.', ''),
                'currencyID' => $moneda,
            ]],
            'PayableAmount' => [[
                '_' => number_format($totalDocumento, 2, '.', ''),
                'currencyID' => $moneda,
            ]],
        ]];

        $invoiceRoot['InvoiceLine'] = $invoiceLines;

        $payload = [
            '_D' => 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2',
            '_A' => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
            '_B' => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
            '_E' => 'urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2',
            'Invoice' => [$invoiceRoot],
        ];

        return [
            'success' => true,
            'json' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            'filename' => $this->generarNombreArchivo(
                (string) $empresa->ruc,
                $tipoDoc,
                $serieNorm,
                max(1, $numero)
            ),
        ];
    }

    /**
     * Línea de factura/boleta JSON+ según afectación IGV (SUNAT cat. 07).
     */
    private function construirInvoiceLineJson(
        array $item,
        int $idx,
        string $moneda,
        string $codigoAfectacion,
        float $cantidad,
        float $subtotal,
        float $igv,
        float $lineTotal
    ): array {
        $valorUnitario = $cantidad > 0 ? round($subtotal / $cantidad, 10) : 0.0;
        $precioVentaUnitario = $cantidad > 0 ? round($lineTotal / $cantidad, 10) : 0.0;

        if (! SunatAfectacionIgv::isGravadoOneroso($codigoAfectacion)) {
            $valorUnitario = $precioVentaUnitario;
        }

        return [
            'ID' => [[
                '_' => (string) ($idx + 1),
            ]],
            'Note' => [[
                '_' => 'UNIDAD',
            ]],
            'InvoicedQuantity' => [[
                '_' => (string) $cantidad,
                'unitCode' => 'NIU',
                'unitCodeListID' => 'UN/ECE rec 20',
                'unitCodeListAgencyName' => 'United Nations Economic Commission for Europe',
            ]],
            'LineExtensionAmount' => [[
                '_' => number_format($subtotal, 2, '.', ''),
                'currencyID' => $moneda,
            ]],
            'PricingReference' => [[
                'AlternativeConditionPrice' => [[
                    'PriceAmount' => [[
                        '_' => number_format($precioVentaUnitario, 10, '.', ''),
                        'currencyID' => $moneda,
                    ]],
                    'PriceTypeCode' => [[
                        '_' => '01',
                        'listName' => 'Tipo de Precio',
                        'listAgencyName' => 'PE:SUNAT',
                        'listURI' => 'urn:pe:gob:sunat:cpe:see:gem:catalogos:catalogo16',
                    ]],
                ]],
            ]],
            'TaxTotal' => $this->construirTaxTotalLineaJson($moneda, $codigoAfectacion, $subtotal, $igv),
            'Item' => $this->construirItemJson($item),
            'Price' => [[
                'PriceAmount' => [[
                    '_' => number_format($valorUnitario, 10, '.', ''),
                    'currencyID' => $moneda,
                ]],
            ]],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function construirTaxTotalLineaJson(string $moneda, string $codigoAfectacion, float $subtotal, float $igv): array
    {
        if ($codigoAfectacion === SunatAfectacionIgv::EXONERADO) {
            return [[
                'TaxAmount' => [[
                    '_' => number_format(0, 2, '.', ''),
                    'currencyID' => $moneda,
                ]],
                'TaxSubtotal' => [[
                    'TaxableAmount' => [[
                        '_' => number_format($subtotal, 2, '.', ''),
                        'currencyID' => $moneda,
                    ]],
                    'TaxAmount' => [[
                        '_' => number_format(0, 2, '.', ''),
                        'currencyID' => $moneda,
                    ]],
                    'TaxCategory' => [[
                        'Percent' => [[
                            '_' => 0,
                        ]],
                        'TaxExemptionReasonCode' => [[
                            '_' => '20',
                            'listAgencyName' => 'PE:SUNAT',
                            'listName' => 'Afectacion del IGV',
                            'listURI' => 'urn:pe:gob:sunat:cpe:see:gem:catalogos:catalogo07',
                        ]],
                        'TaxScheme' => [[
                            'ID' => [[
                                '_' => '9997',
                                'schemeName' => 'Codigo de tributos',
                                'schemeURI' => 'urn:pe:gob:sunat:cpe:see:gem:catalogos:catalogo05',
                                'schemeAgencyName' => 'PE:SUNAT',
                            ]],
                            'Name' => [[
                                '_' => 'EXO',
                            ]],
                            'TaxTypeCode' => [[
                                '_' => 'VAT',
                            ]],
                        ]],
                    ]],
                ]],
            ]];
        }

        if ($codigoAfectacion === SunatAfectacionIgv::INAFECTO) {
            return [[
                'TaxAmount' => [[
                    '_' => number_format(0, 2, '.', ''),
                    'currencyID' => $moneda,
                ]],
                'TaxSubtotal' => [[
                    'TaxableAmount' => [[
                        '_' => number_format($subtotal, 2, '.', ''),
                        'currencyID' => $moneda,
                    ]],
                    'TaxAmount' => [[
                        '_' => number_format(0, 2, '.', ''),
                        'currencyID' => $moneda,
                    ]],
                    'TaxCategory' => [[
                        'Percent' => [[
                            '_' => 0,
                        ]],
                        'TaxExemptionReasonCode' => [[
                            '_' => '30',
                            'listAgencyName' => 'PE:SUNAT',
                            'listName' => 'Afectacion del IGV',
                            'listURI' => 'urn:pe:gob:sunat:cpe:see:gem:catalogos:catalogo07',
                        ]],
                        'TaxScheme' => [[
                            'ID' => [[
                                '_' => '9998',
                                'schemeName' => 'Codigo de tributos',
                                'schemeURI' => 'urn:pe:gob:sunat:cpe:see:gem:catalogos:catalogo05',
                                'schemeAgencyName' => 'PE:SUNAT',
                            ]],
                            'Name' => [[
                                '_' => 'INA',
                            ]],
                            'TaxTypeCode' => [[
                                '_' => 'FRE',
                            ]],
                        ]],
                    ]],
                ]],
            ]];
        }

        return [[
            'TaxAmount' => [[
                '_' => number_format($igv, 2, '.', ''),
                'currencyID' => $moneda,
            ]],
            'TaxSubtotal' => [[
                'TaxableAmount' => [[
                    '_' => number_format($subtotal, 2, '.', ''),
                    'currencyID' => $moneda,
                ]],
                'TaxAmount' => [[
                    '_' => number_format($igv, 2, '.', ''),
                    'currencyID' => $moneda,
                ]],
                'TaxCategory' => [[
                    'Percent' => [[
                        '_' => 18.00,
                    ]],
                    'TaxExemptionReasonCode' => [[
                        '_' => '10',
                        'listAgencyName' => 'PE:SUNAT',
                        'listName' => 'Afectacion del IGV',
                        'listURI' => 'urn:pe:gob:sunat:cpe:see:gem:catalogos:catalogo07',
                    ]],
                    'TaxScheme' => [[
                        'ID' => [[
                            '_' => '1000',
                            'schemeName' => 'Codigo de tributos',
                            'schemeURI' => 'urn:pe:gob:sunat:cpe:see:gem:catalogos:catalogo05',
                            'schemeAgencyName' => 'PE:SUNAT',
                        ]],
                        'Name' => [[
                            '_' => 'IGV',
                        ]],
                        'TaxTypeCode' => [[
                            '_' => 'VAT',
                        ]],
                    ]],
                ]],
            ]],
        ]];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function construirItemJson(array $item): array
    {
        $row = [
            'Description' => [[
                '_' => (string) ($item['nombre'] ?? 'PRODUCTO'),
            ]],
        ];
        $codigo = '';
        if (! empty($item['codigoBarra'])) {
            $codigo = (string) $item['codigoBarra'];
        } elseif (! empty($item['idProducto'])) {
            $codigo = (string) $item['idProducto'];
        }
        if ($codigo !== '') {
            $row['SellersItemIdentification'] = [[
                'ID' => [[
                    '_' => $codigo,
                ]],
            ]];
        }

        return [$row];
    }

    /**
     * TaxTotal a nivel documento con uno o más TaxSubtotal (gravado / exonerado / inafecto).
     *
     * @param  array{10: array{base: float, igv: float}, 20: array{base: float}, 30: array{base: float}}  $agr
     * @return array<int, array<string, mixed>>
     */
    private function construirTaxTotalDocumentoVariosTipos(array $agr, string $moneda): array
    {
        $totalIgv = round($agr['10']['igv'], 2);
        $subtotals = [];

        $b10 = round($agr['10']['base'], 2);
        if ($b10 > 0 || $totalIgv > 0) {
            $subtotals[] = [
                'TaxableAmount' => [[
                    '_' => number_format($b10, 2, '.', ''),
                    'currencyID' => $moneda,
                ]],
                'TaxAmount' => [[
                    '_' => number_format($totalIgv, 2, '.', ''),
                    'currencyID' => $moneda,
                ]],
                'TaxCategory' => [[
                    'TaxScheme' => [[
                        'ID' => [[
                            '_' => '1000',
                            'schemeName' => 'Codigo de tributos',
                            'schemeURI' => 'urn:pe:gob:sunat:cpe:see:gem:catalogos:catalogo05',
                            'schemeAgencyName' => 'PE:SUNAT',
                        ]],
                        'Name' => [[
                            '_' => 'IGV',
                        ]],
                        'TaxTypeCode' => [[
                            '_' => 'VAT',
                        ]],
                    ]],
                ]],
            ];
        }

        $b20 = round($agr['20']['base'], 2);
        if ($b20 > 0) {
            $subtotals[] = [
                'TaxableAmount' => [[
                    '_' => number_format($b20, 2, '.', ''),
                    'currencyID' => $moneda,
                ]],
                'TaxAmount' => [[
                    '_' => number_format(0, 2, '.', ''),
                    'currencyID' => $moneda,
                ]],
                'TaxCategory' => [[
                    'TaxScheme' => [[
                        'ID' => [[
                            '_' => '9997',
                            'schemeName' => 'Codigo de tributos',
                            'schemeURI' => 'urn:pe:gob:sunat:cpe:see:gem:catalogos:catalogo05',
                            'schemeAgencyName' => 'PE:SUNAT',
                        ]],
                        'Name' => [[
                            '_' => 'EXO',
                        ]],
                        'TaxTypeCode' => [[
                            '_' => 'VAT',
                        ]],
                    ]],
                ]],
            ];
        }

        $b30 = round($agr['30']['base'], 2);
        if ($b30 > 0) {
            $subtotals[] = [
                'TaxableAmount' => [[
                    '_' => number_format($b30, 2, '.', ''),
                    'currencyID' => $moneda,
                ]],
                'TaxAmount' => [[
                    '_' => number_format(0, 2, '.', ''),
                    'currencyID' => $moneda,
                ]],
                'TaxCategory' => [[
                    'TaxScheme' => [[
                        'ID' => [[
                            '_' => '9998',
                            'schemeName' => 'Codigo de tributos',
                            'schemeURI' => 'urn:pe:gob:sunat:cpe:see:gem:catalogos:catalogo05',
                            'schemeAgencyName' => 'PE:SUNAT',
                        ]],
                        'Name' => [[
                            '_' => 'INA',
                        ]],
                        'TaxTypeCode' => [[
                            '_' => 'FRE',
                        ]],
                    ]],
                ]],
            ];
        }

        return [[
            'TaxAmount' => [[
                '_' => number_format($totalIgv, 2, '.', ''),
                'currencyID' => $moneda,
            ]],
            'TaxSubtotal' => $subtotals,
        ]];
    }

    /**
     * Emisor con dirección fiscal y código de establecimiento anexo (AddressTypeCode).
     * Obligatorio para validación SUNAT en factura (error 3030 si falta).
     */
    private function accountingSupplierParty(object $empresa, array $params): array
    {
        return [[
            'Party' => [[
                'PartyIdentification' => [[
                    'ID' => [[
                        '_' => preg_replace('/\D/', '', (string) $empresa->ruc),
                        'schemeID' => '6',
                        'schemeName' => 'Documento de Identidad',
                        'schemeAgencyName' => 'PE:SUNAT',
                        'schemeURI' => 'urn:pe:gob:sunat:cpe:see:gem:catalogos:catalogo06',
                    ]],
                ]],
                'PartyName' => [[
                    'Name' => [[
                        '_' => (string) ($empresa->nombreComercial ?? $empresa->nombreLegal ?? 'EMPRESA'),
                    ]],
                ]],
                'PartyLegalEntity' => [[
                    'RegistrationName' => [[
                        '_' => (string) ($empresa->nombreLegal ?? 'EMPRESA'),
                    ]],
                    'RegistrationAddress' => $this->registrationAddressEmisor($empresa, $params),
                ]],
            ]],
        ]];
    }

    private function registrationAddressEmisor(object $empresa, array $params): array
    {
        $ubigeo = $this->normalizarUbigeo6(
            (string) ($params['ubigeoEmisor'] ?? $empresa->ubigeo ?? '150101')
        );
        $codigoLocal = $this->normalizarCodigoLocalAnexo(
            (string) ($params['codigoEstablecimiento'] ?? $params['codigoLocalAnexo'] ?? '0000')
        );
        $linea = (string) ($params['direccionEmisor'] ?? $empresa->direccion ?? 'SIN DOMICILIO FISCAL');
        $distrito = (string) ($params['distritoEmisor'] ?? $empresa->distrito ?? 'LIMA');
        $provincia = (string) ($params['provinciaEmisor'] ?? $empresa->provincia ?? 'LIMA');
        $departamento = (string) ($params['departamentoEmisor'] ?? $empresa->departamento ?? 'LIMA');

        return [[
            'ID' => [[
                '_' => $ubigeo,
                'schemeAgencyName' => 'PE:INEI',
                'schemeName' => 'Ubigeos',
            ]],
            'AddressTypeCode' => [[
                '_' => $codigoLocal,
                'listAgencyName' => 'PE:SUNAT',
                'listName' => 'Establecimientos anexos',
            ]],
            'CityName' => [[
                '_' => $provincia,
            ]],
            'CountrySubentity' => [[
                '_' => $departamento,
            ]],
            'District' => [[
                '_' => $distrito,
            ]],
            'AddressLine' => [[
                'Line' => [[
                    '_' => $linea,
                ]],
            ]],
            'Country' => [[
                'IdentificationCode' => [[
                    '_' => 'PE',
                    'listID' => 'ISO 3166-1',
                    'listAgencyName' => 'United Nations Economic Commission for Europe',
                    'listName' => 'Country',
                ]],
            ]],
        ]];
    }

    private function accountingCustomerParty(
        string $tipoDoc,
        array $params,
        string $numeroDocCliente,
        string $tipoDocCliente
    ): array {
        $nombreCliente = (string) ($params['cliente'] ?? ($params['razonSocial'] ?? 'CLIENTE'));
        $nombreComercial = (string) ($params['nombreComercialCliente'] ?? $nombreCliente);

        $partyLegal = [
            'RegistrationName' => [[
                '_' => $nombreCliente,
            ]],
        ];
        if ($tipoDoc === '01') {
            $partyLegal['RegistrationAddress'] = $this->registrationAddressClienteFactura($params);
        }

        return [[
            'Party' => [[
                'PartyIdentification' => [[
                    'ID' => [[
                        '_' => $numeroDocCliente,
                        'schemeID' => $tipoDocCliente,
                        'schemeName' => 'Documento de Identidad',
                        'schemeAgencyName' => 'PE:SUNAT',
                        'schemeURI' => 'urn:pe:gob:sunat:cpe:see:gem:catalogos:catalogo06',
                    ]],
                ]],
                'PartyName' => [[
                    'Name' => [[
                        '_' => $nombreComercial,
                    ]],
                ]],
                'PartyLegalEntity' => [
                    $partyLegal,
                ],
            ]],
        ]];
    }

    /**
     * Dirección del adquirente en factura (sample Factura JSON+ Gravada).
     */
    private function registrationAddressClienteFactura(array $params): array
    {
        $ubigeo = $this->normalizarUbigeo6(
            (string) ($params['ubigeoCliente'] ?? $params['ubigeo'] ?? '150101')
        );
        $linea = (string) ($params['direccion'] ?? $params['direccionCliente'] ?? 'CIUDAD');
        $distrito = (string) ($params['distritoCliente'] ?? $params['distrito'] ?? 'LIMA');
        $provincia = (string) ($params['provinciaCliente'] ?? $params['provincia'] ?? 'LIMA');
        $departamento = (string) ($params['departamentoCliente'] ?? $params['departamento'] ?? 'LIMA');

        return [[
            'ID' => [[
                '_' => $ubigeo,
                'schemeAgencyName' => 'PE:INEI',
                'schemeName' => 'Ubigeos',
            ]],
            'CityName' => [[
                '_' => $provincia,
            ]],
            'CountrySubentity' => [[
                '_' => $departamento,
            ]],
            'District' => [[
                '_' => $distrito,
            ]],
            'AddressLine' => [[
                'Line' => [[
                    '_' => $linea,
                ]],
            ]],
            'Country' => [[
                'IdentificationCode' => [[
                    '_' => 'PE',
                    'listID' => 'ISO 3166-1',
                    'listAgencyName' => 'United Nations Economic Commission for Europe',
                    'listName' => 'Country',
                ]],
            ]],
        ]];
    }

    private function normalizarUbigeo6(string $raw): string
    {
        $d = preg_replace('/\D/', '', $raw);

        return strlen($d) >= 6 ? substr($d, 0, 6) : str_pad($d, 6, '0', STR_PAD_RIGHT);
    }

    private function normalizarCodigoLocalAnexo(string $raw): string
    {
        $d = preg_replace('/\D/', '', $raw);

        return substr(str_pad($d !== '' ? $d : '0', 4, '0', STR_PAD_LEFT), 0, 4);
    }

    /**
     * Factura (01): el receptor debe identificarse con RUC (11 dígitos, schemeID 6). Error SUNAT 2017 si no.
     */
    private function normalizarRucReceptorFactura(array $params): ?string
    {
        $candidatos = [
            $params['rucCliente'] ?? null,
            $params['numeroRuc'] ?? null,
            $params['ruc'] ?? null,
            $params['numeroDocumento'] ?? null,
            $params['documento'] ?? null,
        ];
        foreach ($candidatos as $c) {
            if ($c === null || $c === '' || $c === '-') {
                continue;
            }
            $soloDigitos = preg_replace('/\D/', '', (string) $c);
            if (strlen($soloDigitos) === 11) {
                return $soloDigitos;
            }
        }

        return null;
    }

    private function resolverTipoDocumento(string $tipo, string $serie): string
    {
        $tipo = strtoupper(trim($tipo));
        $serie = strtoupper(trim($serie));
        if (str_contains($tipo, 'FACTURA') || str_starts_with($serie, 'F')) {
            return '01';
        }

        return '03';
    }

    /**
     * @return array{0: string, 1: string} [schemeID, numero]
     */
    private function resolverIdentificacionClienteBoleta(array $params, string $tipoComprobante): array
    {
        $candidatos = [
            $params['numeroDocumento'] ?? null,
            $params['numeroDoi'] ?? null,
            $params['documento'] ?? null,
        ];

        $numeroDoc = '';
        foreach ($candidatos as $candidato) {
            if ($candidato === null || $candidato === '' || $candidato === '-') {
                continue;
            }
            $digitos = preg_replace('/\D/', '', (string) $candidato);
            if ($digitos === '' || strlen($digitos) < 8) {
                continue;
            }
            if (strlen($digitos) > strlen($numeroDoc)) {
                $numeroDoc = $digitos;
            }
        }

        if ($numeroDoc === '') {
            return ['0', '00000000'];
        }

        if (strlen($numeroDoc) === 11) {
            return ['6', $numeroDoc];
        }

        if (strlen($numeroDoc) > 8) {
            $numeroDoc = substr($numeroDoc, 0, 8);
        } elseif (strlen($numeroDoc) < 8) {
            $numeroDoc = str_pad($numeroDoc, 8, '0', STR_PAD_LEFT);
        }

        $tipoExplicito = trim((string) ($params['tipoDocumentoCliente'] ?? ($params['tipoDocumento'] ?? '')));
        if ($tipoExplicito !== '' && preg_match('/^\d+$/', $tipoExplicito)) {
            return [$tipoExplicito, $numeroDoc];
        }

        return [$this->resolverTipoDocCliente($numeroDoc, $tipoComprobante), $numeroDoc];
    }

    private function sellerSupplierParty(string $nombreVendedor): array
    {
        return [[
            'Party' => [[
                'PartyName' => [[
                    'Name' => [[
                        '_' => 'Vendedor: ' . $nombreVendedor,
                    ]],
                ]],
            ]],
        ]];
    }

    private function resolverTipoDocCliente(string $numero, string $tipoComprobante): string
    {
        $digits = preg_replace('/\D/', '', $numero);
        if (strlen($digits) === 11) {
            return '6';
        }
        if (strlen($digits) === 8) {
            return '1';
        }
        if ($tipoComprobante === '01') {
            return '6';
        }

        return '0';
    }

    private function resolverMoneda(string $moneda): string
    {
        $normalized = strtoupper(trim($moneda));
        if (in_array($normalized, ['USD', '$', 'DOLAR', 'DOLARES'], true)) {
            return 'USD';
        }

        return 'PEN';
    }

    private function resolverFecha(?string $fecha): string
    {
        if (! empty($fecha)) {
            try {
                return (new \DateTime($fecha))->format('Y-m-d');
            } catch (\Throwable $e) {
                // Fallback a fecha actual.
            }
        }

        return date('Y-m-d');
    }

    private function generarNombreArchivo(string $ruc, string $tipoDoc, string $serieNormalizada, int $numero): string
    {
        $ruc = preg_replace('/\D/', '', $ruc) ?: $ruc;
        $correlativo = $this->correlativoPad8($numero);

        return "{$ruc}-{$tipoDoc}-{$serieNormalizada}-{$correlativo}.json";
    }

    /**
     * Serie en 4 caracteres (mismo criterio que nombre de archivo SUNAT/eFact).
     */
    private function serieNormalizada(string $serie): string
    {
        $serie = strtoupper(trim($serie));

        return substr(str_pad(preg_replace('/[^A-Z0-9]/', '', $serie), 4, '0', STR_PAD_RIGHT), 0, 4);
    }

    /**
     * Correlativo con 8 dígitos; debe coincidir con el tramo final del nombre del archivo
     * y con Invoice/ID (Serie-Correlativo) para evitar error 1049 de la OSE.
     */
    private function correlativoPad8(int $numero): string
    {
        return str_pad((string) max(1, $numero), 8, '0', STR_PAD_LEFT);
    }
}
