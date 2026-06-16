<?php

namespace App\Services;

use App\Models\Clientes;
use App\Models\DatosEmpresa;
use App\Models\Recibos;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * PROPUESTA REGISTRO DE VENTAS ELECTRÓNICO (RVIE) – plantilla SUNAT SIRE.
 *
 * Fuente de datos: {@see Recibos} (tbl_recibos), con CPE SUNAT preferente en efact_comprobante_*.
 */
class RvieSireVentasService
{
    /**
     * Columnas alineadas a «VENTAS MODELO.xlsx» / VENTAS.xlsx (40 columnas, fila de datos configurable, p. ej. 7).
     *
     * @var list<string>
     */
    public const CABECERAS_RVIE = [
        'NÚMERO DE ORDEN',
        'FECHA DE EMISIÓN',
        'FECHA DE VCTO O PAGO',
        'TIPO (COMPROBANTE)',
        'SERIE',
        'NÚMERO',
        'NÚMERO FINAL',
        'TIPO DOC. IDENTIDAD (CLIENTE)',
        'NÚMERO DOC. IDENTIDAD (CLIENTE)',
        'DENOMINACIÓN O RAZÓN SOCIAL',
        '% IGV / TASA',
        'VALOR FACTURADO EXPORTACIÓN',
        'BASE IMPONIBLE (OPER. GRAVADA)',
        'DCTO. BASE IMPONIBLE',
        'IGV / IPM',
        'DCTO. IGV / IPM',
        'OPERACIÓN EXONERADA',
        'OPERACIÓN INAFECTA',
        'ISC',
        'BASE IMPONIBLE VENTA ARROZ PILADO',
        'IVAP',
        'ICBPER',
        'OTROS TRIBUTOS Y CARGOS',
        'IMPORTE TOTAL',
        'CÓDIGO DE MONEDA',
        'TIPO DE CAMBIO',
        'FECHA (DOCUMENTO DE REFERENCIA)',
        'TIPO (DOCUMENTO DE REFERENCIA)',
        'SERIE (DOCUMENTO DE REFERENCIA)',
        'NÚMERO (DOCUMENTO DE REFERENCIA)',
        'ID PROYECTO / OPERADORES / ATRIBUCIÓN',
        'CÓDIGO ANOTACIÓN DEL REGISTRO (CAR)',
        'TIPO DE NOTA',
        'ESTADO DEL COMPROBANTE',
        'VALOR FOB EMBARCADO',
        'VALOR OPERACIONES GRATUITAS',
        'OTROS',
        'ANOTADO EN EL REGISTRO DE VENTAS (COMPARAR)',
        'CP EMITIDO EN PERIODO A DECLARAR',
        'DIFERENCIA IGV',
    ];

    public function templatePath(): string
    {
        return (string) config('contabilidad.rvie_ventas_template');
    }

    public function firstDataRow(): int
    {
        $v = config('contabilidad.rvie_ventas_first_data_row');
        if ($v === null || $v === '') {
            return 7;
        }

        return max(1, (int) $v);
    }

    public function dataSheetIndex(): int
    {
        $i = (int) config('contabilidad.rvie_ventas_sheet_index', 0);

        return max(0, $i);
    }

    /**
     * @return Collection<int, Recibos>
     */
    public function queryRecibos(?int $idPuntoVenta, string $fechaInicio, string $fechaFin, bool $soloActivas = true): Collection
    {
        $inicio = Carbon::parse($fechaInicio)->startOfDay()->toDateString();
        $fin = Carbon::parse($fechaFin)->endOfDay()->toDateString();

        $q = Recibos::query()
            ->with(['clientes.tipodoi', 'monedas'])
            ->whereBetween('fechaEmision', [$inicio.' 00:00:00', $fin.' 23:59:59'])
            ->orderBy('fechaEmision', 'asc');

        if ($idPuntoVenta !== null && $idPuntoVenta > 0) {
            $q->where('idPuntoVenta', $idPuntoVenta);
        }

        if ($soloActivas) {
            $q->where(function ($w) {
                $w->whereNull('status')->orWhere('status', '!=', 0);
            });
        }

        return $q->get();
    }

    /**
     * @return list<array{recibo_id: int|null, id_punto_venta: int|null, columnas: array<string, mixed>, celdas: list<mixed>}>
     */
    public function buildFilas(Collection $recibos): array
    {
        $filas = [];
        $orden = 0;
        foreach ($recibos as $r) {
            $orden++;
            $celdas = $this->mapReciboToCeldas($r, $orden);
            $filas[] = [
                'recibo_id' => $r->id ?? null,
                'id_punto_venta' => $r->idPuntoVenta ?? null,
                'columnas' => $this->celdasToKeyed($celdas),
                'celdas' => $celdas,
            ];
        }

        return $filas;
    }

    /**
     * @param  list<mixed>  $celdas
     * @return array<string, mixed>
     */
    public function celdasToKeyed(array $celdas): array
    {
        $out = [];
        $pad = strlen((string) count(self::CABECERAS_RVIE)) > 9 ? 3 : 2;
        foreach (self::CABECERAS_RVIE as $i => $_label) {
            $idx = str_pad((string) ($i + 1), $pad, '0', STR_PAD_LEFT);
            $out['c'.$idx] = $celdas[$i] ?? '';
        }

        return $out;
    }

    /**
     * @return list<mixed>
     */
    public function mapReciboToCeldas(Recibos $r, int $orden): array
    {
        $fechaEmision = $this->fechaEmisionCarbon($r);
        $fechaTxt = $fechaEmision ? $fechaEmision->format('d/m/Y') : '';

        $sn = $this->serieNumeroSunatDesdeRecibo($r);
        $tipoComp = $this->codigoTipoComprobanteRvio($sn['serie']);

        $docTipo = $this->documentoYTipoIdentidadClienteRvio($r);
        $documentoCliente = $docTipo['numero'];
        $tipoDocCli = $docTipo['tipo'];
        $razon = trim((string) ($r->razonSocial ?? ''));

        [$baseGrav, $igvGrav, $totalLin] = $this->resolverBaseIgvTotal($r);

        $pctIgvTxt = trim((string) config('contabilidad.rce_compras_igv_pct', '18'));
        $pctCell = ($baseGrav > 0 || $igvGrav > 0) ? $pctIgvTxt : '';

        $otros = (float) ($r->otrosCargo ?? 0);
        $monedaCodigo = $this->codigoMonedaIsoSunatParaRvio($r);

        $tc = $r->tipoCambio ?? 1;
        $tcTxt = number_format((float) $tc, 3, '.', '');

        $rucEmp = $this->rucContribuyenteEmpresa11();
        $car = $this->armarCodigoCAR($rucEmp, $tipoComp, $sn['serie'], $sn['numero']);

        $tipoNota = $tipoComp === '07' ? '01' : '';

        return [
            $orden,
            $fechaTxt,
            '',
            $tipoComp,
            $sn['serie'],
            $sn['numero'],
            '',
            $tipoDocCli,
            $documentoCliente,
            $razon,
            $pctCell,
            0.0,
            round($baseGrav, 2),
            0.0,
            round($igvGrav, 2),
            0.0,
            0.0,
            0.0,
            0.0,
            0.0,
            0.0,
            0.0,
            round($otros, 2),
            round($totalLin, 2),
            $monedaCodigo,
            $tcTxt,
            '', '', '', '',
            '',
            $car,
            $tipoNota,
            '1',
            0.0,
            0.0,
            '',
            '',
            '',
            '',
        ];
    }

    /**
     * Código de moneda SUNAT (ISO 4217). En BD suele figurar "S/" o abreviaturas, no PEN.
     */
    private function codigoMonedaIsoSunatParaRvio(Recibos $r): string
    {
        $candidatos = [trim((string) ($r->moneda ?? ''))];
        if ($r->relationLoaded('monedas') && $r->getRelation('monedas')) {
            $m = $r->getRelation('monedas');
            $candidatos[] = trim((string) ($m->abreviatura ?? ''));
            $candidatos[] = trim((string) ($m->nombre ?? ''));
            if (isset($m->codigo)) {
                $candidatos[] = trim((string) $m->codigo);
            }
        }

        foreach ($candidatos as $raw) {
            if ($raw === '') {
                continue;
            }
            $u = strtoupper(preg_replace('/\s+/u', '', $raw));

            if ($u === 'PEN' || $u === 'S/.' || $u === 'S/' || str_starts_with($u, 'S/')
                || str_contains($u, 'SOLES') || $u === 'SOL' || $u === 'NS') {
                return 'PEN';
            }
            if ($u === 'USD' || $u === '$' || str_contains($u, 'USD') || str_contains($u, 'DOLAR')) {
                return 'USD';
            }
            if (strlen($u) === 3 && ctype_alpha($u)) {
                return $u;
            }
        }

        return 'PEN';
    }

    /**
     * @param  list<list<mixed>>  $celdasPorFila
     */
    public function fillTemplateSpreadsheet(array $celdasPorFila): Spreadsheet
    {
        $this->assertZipExtensionForXlsx();

        $maxSec = (int) config('contabilidad.rce_compras_max_execution_seconds', 180);
        if ($maxSec > 0) {
            @set_time_limit($maxSec);
        }

        $path = $this->templatePath();
        if (! is_readable($path)) {
            throw new \RuntimeException('No se encuentra o no se puede leer el template RVIE VENTAS: '.$path);
        }

        $sanitizedPath = $this->copyTemplateStrippingDataValidationsXml($path);
        try {
            $reader = IOFactory::createReaderForFile($sanitizedPath);
            $reader->setReadDataOnly(false);
            $reader->setReadEmptyCells(false);
            $reader->setIncludeCharts(false);
            if (method_exists($reader, 'setIgnoreRowsWithNoCells')) {
                $reader->setIgnoreRowsWithNoCells(true);
            }
            $spreadsheet = $reader->load($sanitizedPath);
        } finally {
            if (is_file($sanitizedPath)) {
                @unlink($sanitizedPath);
            }
        }

        $idx = $this->dataSheetIndex();
        if ($idx >= $spreadsheet->getSheetCount()) {
            $idx = 0;
        }
        $sheet = $spreadsheet->getSheet($idx);
        $spreadsheet->setActiveSheetIndex($idx);

        $row = $this->firstDataRow();
        $maxCol = count(self::CABECERAS_RVIE);
        $lastRow = $row + max(0, count($celdasPorFila)) - 1;
        $this->unmergeRangesOverlappingDataRows($sheet, $row, $lastRow, $maxCol);

        $explicitStringCols = [
            4, 5, 6, 7, 8, 9, 10, 11, 25, 26, 28, 29, 30, 31, 32, 33, 34, 37, 38, 39, 40,
        ];
        $dateCols = [2, 3, 27];

        foreach ($celdasPorFila as $fila) {
            for ($c = 1; $c <= $maxCol; $c++) {
                $coord = Coordinate::stringFromColumnIndex($c).$row;
                if (in_array($c, $dateCols, true)) {
                    $textoFecha = $this->fechaComoTextoDdMmYyyy($fila[$c - 1] ?? null);
                    if ($textoFecha === '') {
                        $sheet->setCellValue($coord, '');
                    } else {
                        $sheet->getCell($coord)->setValueExplicit($textoFecha, DataType::TYPE_STRING);
                        $sheet->getStyle($coord)->getNumberFormat()
                            ->setFormatCode(NumberFormat::FORMAT_TEXT);
                    }
                    continue;
                }

                $val = $fila[$c - 1] ?? null;
                $isEmpty = $val === null || $val === '';

                if ($isEmpty) {
                    $sheet->setCellValue($coord, '');
                } elseif (in_array($c, $explicitStringCols, true)) {
                    $sheet->getCell($coord)->setValueExplicit((string) $val, DataType::TYPE_STRING);
                } elseif (is_int($val) || is_float($val)) {
                    $sheet->setCellValue($coord, $val);
                } else {
                    $sheet->setCellValue($coord, $val);
                }
            }
            $row++;
        }

        return $spreadsheet;
    }

    private function fechaEmisionCarbon(Recibos $r): ?Carbon
    {
        $raw = $r->fechaEmision ?? $r->created_at ?? null;
        if ($raw === null || $raw === '') {
            return null;
        }
        try {
            return Carbon::parse($raw);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array{serie: string, numero: string}
     */
    private function serieNumeroSunatDesdeRecibo(Recibos $r): array
    {
        $attrs = $r->getAttributes();
        $cpeSerie = '';
        $cpeNum = '';
        if (array_key_exists('efact_comprobante_serie', $attrs)) {
            $cpeSerie = trim((string) ($attrs['efact_comprobante_serie'] ?? ''));
        }
        if (array_key_exists('efact_comprobante_numero', $attrs)) {
            $cpeNum = trim((string) ($attrs['efact_comprobante_numero'] ?? ''));
        }

        if ($cpeSerie !== '' && $cpeNum !== '') {
            return [
                'serie' => strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $cpeSerie)),
                'numero' => preg_replace('/\D+/', '', $cpeNum),
            ];
        }

        $serie = strtoupper(trim((string) ($r->series ?? '')));
        $num = preg_replace('/\D+/', '', (string) ($r->numeracion ?? ''));

        return ['serie' => $serie, 'numero' => $num];
    }

    /**
     * Códigos tipo comprobante SUNAT (T.11) según prefijo de serie CPE.
     */
    private function codigoTipoComprobanteRvio(string $serieNorm): string
    {
        $s = strtoupper(preg_replace('/\s+/', '', $serieNorm));
        if ($s === '') {
            return '';
        }
        if (str_starts_with($s, 'FC') || str_starts_with($s, 'BC')) {
            return '07';
        }
        if (str_starts_with($s, 'FD') || str_starts_with($s, 'BD')) {
            return '08';
        }
        if (str_starts_with($s, 'F')) {
            return '01';
        }
        if (str_starts_with($s, 'B') || str_starts_with($s, 'E')) {
            return '03';
        }

        return '';
    }

    /**
     * Columnas 8–9 RVIE: tipo y número de documento del cliente (catálogo SUNAT T.12).
     *
     * @return array{numero: string, tipo: string}
     */
    private function documentoYTipoIdentidadClienteRvio(Recibos $r): array
    {
        $cliente = $r->relationLoaded('clientes') ? $r->getRelation('clientes') : null;
        if (! $cliente instanceof Clientes && $r->idCliente) {
            $cliente = Clientes::query()->with('tipodoi')->find($r->idCliente);
        }

        $docRecibo = preg_replace('/\D+/', '', (string) ($r->documento ?? ''));
        if ($docRecibo === '0') {
            $docRecibo = '';
        }
        $docCliente = $cliente instanceof Clientes
            ? preg_replace('/\D+/', '', (string) ($cliente->numeroDoi ?? ''))
            : '';
        if ($docCliente === '0') {
            $docCliente = '';
        }

        $numero = $docRecibo !== '' ? $docRecibo : $docCliente;

        $tipo = '';
        if ($cliente instanceof Clientes) {
            $tipo = $this->codigoTipoDocSunatDesdeTipoDoi($cliente);
        }
        if ($tipo === '') {
            $tipo = $this->inferTipoDocumentoCliente($numero);
        }

        return ['numero' => $numero, 'tipo' => $tipo];
    }

    /**
     * Código tipo documento identidad (SUNAT tabla 12) desde tbl_tipo_doi o por longitud del número.
     */
    private function codigoTipoDocSunatDesdeTipoDoi(Clientes $c): string
    {
        $td = $c->relationLoaded('tipodoi') ? $c->getRelation('tipodoi') : null;
        if ($td === null && $c->idTipoDoi) {
            $td = $c->tipodoi()->first();
        }
        if ($td !== null) {
            $codRaw = trim((string) ($td->codigo ?? ''));
            if ($codRaw !== '' && ctype_digit($codRaw)) {
                $n = (int) $codRaw;
                if ($n >= 1 && $n <= 9) {
                    return (string) $n;
                }
            }
            $tipoNombre = strtoupper((string) ($td->tipo ?? ''));
            if (str_contains($tipoNombre, 'RUC')) {
                return '6';
            }
            if (str_contains($tipoNombre, 'DNI')) {
                return '1';
            }
            if (str_contains($tipoNombre, 'CE') || str_contains($tipoNombre, 'CARNET') || str_contains($tipoNombre, 'C.E')) {
                return '4';
            }
            if (str_contains($tipoNombre, 'PASAPORTE')) {
                return '7';
            }
        }

        return '';
    }

    private function inferTipoDocumentoCliente(string $digits): string
    {
        if (strlen($digits) === 11) {
            return '6';
        }
        if (strlen($digits) === 8) {
            return '1';
        }

        return '';
    }

    /**
     * @return array{0: float, 1: float, 2: float} base, igv, total
     */
    private function resolverBaseIgvTotal(Recibos $r): array
    {
        $grav = (float) ($r->totalGravada ?? 0);
        $igv = (float) ($r->totalIgv ?? 0);
        $total = (float) ($r->total ?? 0);

        if ($grav <= 0 && $total > 0 && $igv > 0) {
            $grav = round(max(0.0, $total - $igv), 2);
        }

        if (($grav <= 0 && $igv <= 0) && $total > 0) {
            $pctIgvTxt = trim((string) config('contabilidad.rce_compras_igv_pct', '18'));
            $pctNum = (float) str_replace(',', '.', preg_replace('/[^\d.-]/', '', $pctIgvTxt ?: '18'));
            if ($pctNum <= 0) {
                $pctNum = 18.0;
            }
            $grav = round($total / (1 + $pctNum / 100), 2);
            $igv = round(max(0.0, $total - $grav), 2);
        }

        if ($total <= 0 && ($grav > 0 || $igv > 0)) {
            $total = round($grav + $igv, 2);
        }

        return [$grav, $igv, $total];
    }

    /** Fecha en texto d/m/Y (plantillas SUNAT; evita seriales Excel que muestran ###). */
    private function fechaComoTextoDdMmYyyy(mixed $val): string
    {
        if ($val === null || $val === '') {
            return '';
        }
        if (is_int($val) || is_float($val)) {
            try {
                $dt = ExcelDate::excelToDateTimeObject((float) $val);

                return Carbon::instance($dt)->format('d/m/Y');
            } catch (\Throwable) {
                return '';
            }
        }
        $s = trim((string) $val);
        if ($s === '') {
            return '';
        }
        $parsed = Carbon::createFromFormat('d/m/Y', $s);
        if ($parsed !== false) {
            return $parsed->format('d/m/Y');
        }
        try {
            return Carbon::parse($s)->format('d/m/Y');
        } catch (\Throwable) {
            return $s;
        }
    }

    private static ?string $rucEmpresaContribuyenteCache = null;

    private function rucContribuyenteEmpresa11(): string
    {
        if (self::$rucEmpresaContribuyenteCache !== null) {
            return self::$rucEmpresaContribuyenteCache;
        }

        $raw = DatosEmpresa::query()->orderByDesc('id')->value('ruc');
        $digits = preg_replace('/\D+/', '', (string) ($raw ?? ''));
        if ($digits === '') {
            self::$rucEmpresaContribuyenteCache = '';

            return '';
        }
        self::$rucEmpresaContribuyenteCache = strlen($digits) <= 11
            ? str_pad($digits, 11, '0', STR_PAD_LEFT)
            : substr($digits, 0, 11);

        return self::$rucEmpresaContribuyenteCache;
    }

    private function armarCodigoCAR(string $rucEmpresa11, string $tipoComprobante, string $serie, string $numeroCorrelativo): string
    {
        $rucDigits = preg_replace('/\D+/', '', $rucEmpresa11);
        $ruc = str_pad(strlen($rucDigits) <= 11 ? substr($rucDigits, 0, 11) : substr($rucDigits, 0, 11), 11, '0', STR_PAD_LEFT);

        $tipo = preg_replace('/\D+/', '', (string) $tipoComprobante);
        $tipo2 = strlen($tipo) >= 2 ? substr($tipo, 0, 2) : str_pad((string) $tipo, 2, '0', STR_PAD_LEFT);

        $s = strtoupper(preg_replace('/[^A-Z0-9]/', '', (string) $serie));

        $n = preg_replace('/\D+/', '', (string) $numeroCorrelativo);
        $digits = max(9, min(15, (int) config('contabilidad.rce_car_numero_digitos', 12)));
        $nPart = $n !== '' ? str_pad(substr($n, 0, $digits), $digits, '0', STR_PAD_LEFT) : str_repeat('0', $digits);

        return $ruc.$tipo2.$s.$nPart;
    }

    private function unmergeRangesOverlappingDataRows(Worksheet $sheet, int $firstDataRow, int $lastDataRow, int $maxColIndex): void
    {
        if ($lastDataRow < $firstDataRow) {
            return;
        }

        $merges = $sheet->getMergeCells();
        if ($merges === []) {
            return;
        }

        $toRemove = [];
        foreach ($merges as $mergeRange) {
            try {
                [$start, $end] = Coordinate::rangeBoundaries($mergeRange);
            } catch (\Throwable) {
                continue;
            }
            [$c1, $r1] = $start;
            [$c2, $r2] = $end;
            $rMin = min($r1, $r2);
            $rMax = max($r1, $r2);
            $cMin = min($c1, $c2);
            $cMax = max($c1, $c2);
            if ($rMax < $firstDataRow || $rMin > $lastDataRow) {
                continue;
            }
            if ($cMax < 1 || $cMin > $maxColIndex) {
                continue;
            }
            $toRemove[] = $mergeRange;
        }

        foreach ($toRemove as $mergeRange) {
            try {
                $sheet->unmergeCells($mergeRange);
            } catch (\Throwable) {
            }
        }
    }

    private function copyTemplateStrippingDataValidationsXml(string $sourcePath): string
    {
        $dest = sys_get_temp_dir().DIRECTORY_SEPARATOR.'rvie_tpl_'.uniqid('', true).'.xlsx';
        if (! @copy($sourcePath, $dest)) {
            throw new \RuntimeException('No se pudo copiar el template a temporal: '.$sourcePath);
        }

        $zip = new \ZipArchive();
        if ($zip->open($dest) !== true) {
            @unlink($dest);
            throw new \RuntimeException('No se pudo abrir el template como ZIP (xlsx): '.$dest);
        }

        $pattern = '/<(?:[\w.-]+:)?dataValidations\b[^>]*\/>|<(?:[\w.-]+:)?dataValidations\b[^>]*>[\s\S]*?<\/(?:[\w.-]+:)?dataValidations>/';

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entryName = $zip->getNameIndex($i);
            if (! preg_match('#^xl/worksheets/sheet\d+\.xml$#i', (string) $entryName)) {
                continue;
            }
            $xml = $zip->getFromIndex($i);
            if ($xml === false || $xml === '') {
                continue;
            }
            $cleaned = preg_replace($pattern, '', $xml);
            if ($cleaned === null) {
                $cleaned = $xml;
            }
            if ($cleaned !== $xml) {
                $zip->deleteName($entryName);
                $zip->addFromString($entryName, $cleaned);
            }
        }

        $zip->close();

        return $dest;
    }

    private function assertZipExtensionForXlsx(): void
    {
        if (extension_loaded('zip') && class_exists(\ZipArchive::class)) {
            return;
        }

        throw new \RuntimeException(
            'PHP no tiene habilitada la extensión ZIP (ZipArchive), necesaria para leer y generar .xlsx.'
        );
    }
}
