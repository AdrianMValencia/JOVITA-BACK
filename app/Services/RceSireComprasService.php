<?php

namespace App\Services;

use App\Models\Compras;
use App\Models\DatosEmpresa;
use App\Models\Proveedores;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class RceSireComprasService
{
    /**
     * Columnas según «COMPRAS MODELO» SUNAT: 40 datos en cada fila a partir de la fila configurada (p. ej. 9).
     * Posición crítica: % IGV en columna 12; primer par BASE–IGV en 13–14; siguientes destinaciones en 15–18.
     *
     * @var list<string>
     */
    public const CABECERAS_RCE = [
        'NÚMERO DE ORDEN',
        'FECHA DE EMISIÓN',
        'FECHA DE VENCIMIENTO / PAGO',
        'TIPO (COMPROBANTE) (T.11)',
        'SERIE',
        'AÑO EMISIÓN DAM / DSI',
        'NÚMERO',
        'NÚMERO FINAL',
        'TIPO (PROVEEDOR) (T.12)',
        'NÚMERO DE RUC',
        'DENOMINACIÓN O RAZÓN SOCIAL',
        '% IGV / TASA IGV',
        'BASE IMPONIBLE DEST. GRAV. Y/O EXPORT.',
        'IGV / IPM DEST. GRAV. Y/O EXPORT.',
        'BASE IMPONIBLE DEST. GRAV. Y/O EXP. Y NO GRAV.',
        'IGV / IPM DEST. GRAV. Y/O EXP. Y NO GRAV.',
        'BASE IMPONIBLE DEST. OPER. NO GRAV.',
        'IGV / IPM DEST. OPER. NO GRAV.',
        'VALOR ADQUISICIONES NO GRAVADAS',
        'ISC',
        'ICBPER',
        'OTROS TRIBUTOS Y CARGOS',
        'IMPORTE TOTAL',
        'CÓDIGO DE MONEDA',
        'TIPO DE CAMBIO',
        'FECHA (DOCUMENTO DE REFERENCIA)',
        'TIPO (DOCUMENTO DE REFERENCIA)',
        'SERIE (DOCUMENTO DE REFERENCIA)',
        'CÓDIGO DAM O DSI (DOCUMENTO DE REFERENCIA)',
        'NÚMERO (DOCUMENTO DE REFERENCIA)',
        'CLASIFICACIÓN BIENES Y SERVICIOS',
        'IDENTIFICACIÓN PROYECTO / OPERADORES / PARTICIPACIONES',
        'PORCENTAJE DE PARTICIPACIÓN',
        'IMPUESTO LEY 31053',
        'CAR CP A MODIFICAR',
        'CÓDIGO ANOTACIÓN DEL REGISTRO (CAR)',
        'DETRACCIÓN',
        'TIPO DE NOTA',
        'ESTADO DEL COMPROBANTE',
        'CÓDIGO TIPO DE CARGA',
    ];

    private const COL_EXCEL_RUC_PROVEEDOR = 10;

    private const COL_EXCEL_PCT_IGV = 12;

    private const COL_EXCEL_COD_MONEDA = 24;

    private const COL_EXCEL_TIPO_CAMBIO = 25;

    private const COL_EXCEL_CAR_CP_MODIFICAR = 35;

    private const COL_EXCEL_CODIGO_CAR = 36;

    private const COL_EXCEL_ESTADO_COMPROBANTE = 39;

    private const COL_EXCEL_COD_TIPO_CARGA = 40;

    public function templatePath(): string
    {
        return (string) config('contabilidad.rce_compras_template');
    }

    public function firstDataRow(): int
    {
        $v = config('contabilidad.rce_compras_first_data_row');
        if ($v === null || $v === '') {
            return 9;
        }

        return max(1, (int) $v);
    }

    public function dataSheetIndex(): int
    {
        $i = (int) config('contabilidad.rce_compras_sheet_index', 0);

        return max(0, $i);
    }

    public function extendExecutionTime(): void
    {
        $maxSec = (int) config('contabilidad.rce_compras_max_execution_seconds', 180);
        if ($maxSec > 0) {
            @set_time_limit($maxSec);
        }
        $memory = (string) config('contabilidad.rce_compras_memory_limit', '512M');
        if ($memory !== '') {
            @ini_set('memory_limit', $memory);
        }
    }

    /**
     * @return Collection<int, Compras>
     */
    public function queryCompras(?int $idPuntoVenta, string $fechaInicio, string $fechaFin, bool $soloActivas = true): Collection
    {
        $inicio = Carbon::parse($fechaInicio)->startOfDay()->toDateString();
        $fin = Carbon::parse($fechaFin)->endOfDay()->toDateString();

        $q = Compras::query()
            ->with(['proveedores.tipodoi', 'detalles'])
            ->whereRaw(
                'COALESCE(DATE(`fechaCompra`), DATE(`tbl_compras`.`created_at`)) BETWEEN ? AND ?',
                [$inicio, $fin]
            )
            ->orderByRaw('COALESCE(`fechaCompra`, `tbl_compras`.`created_at`) ASC');

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
     * @return list<array{compra_id: int|null, id_punto_venta: int|null, columnas: array<string, mixed>, celdas: list<mixed>}>
     */
    public function buildFilas(Collection $compras): array
    {
        $filas = [];
        $orden = 0;
        foreach ($compras as $c) {
            $orden++;
            $celdas = $this->mapCompraToCeldas($c, $orden);
            $filas[] = [
                'compra_id' => $c->id ?? null,
                'id_punto_venta' => $c->idPuntoVenta ?? null,
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
        $pad = strlen((string) count(self::CABECERAS_RCE)) > 9 ? 3 : 2;
        foreach (self::CABECERAS_RCE as $i => $_label) {
            $idx = str_pad((string) ($i + 1), $pad, '0', STR_PAD_LEFT);
            $out['c'.$idx] = $celdas[$i] ?? '';
        }

        return $out;
    }

    /**
     * @return list<mixed>
     */
    public function mapCompraToCeldas(Compras $c, int $orden): array
    {
        $fechaEmision = $this->fechaCompraCarbon($c);
        $fechaEmisionTxt = $fechaEmision ? $fechaEmision->format('d/m/Y') : '';

        $fechaVenc = $this->maxFechaVencimientoDetalle($c);
        $fechaVencTxt = $fechaVenc ? $fechaVenc->format('d/m/Y') : '';

        $tipoComp = $this->inferCodigoTipoComprobante($c);
        $sn = $this->serieNumeroDesdeModeloCompra($c);

        $proveedor = $c->relationLoaded('proveedores') ? $c->getRelation('proveedores') : null;
        if (! $proveedor instanceof Proveedores && $c->idProveedor) {
            $proveedor = Proveedores::query()->find($c->idProveedor);
        }

        $tipoDocProv = $this->inferTipoDocumentoProveedor($c, $proveedor);
        $numDocTxt = preg_replace('/\D+/', '', $this->numeroDocumentoProveedor($c, $proveedor));
        $nombreProv = $this->nombreProveedor($c, $proveedor);

        $total = (float) ($c->totalCompras ?? 0);
        $percepcion = (float) ($c->percepcion ?? 0);

        $pctIgvTxt = trim((string) config('contabilidad.rce_compras_igv_pct', '18'));
        $pctNum = (float) str_replace(',', '.', preg_replace('/[^\d.-]/', '', $pctIgvTxt ?: '18'));
        if ($pctNum <= 0) {
            $pctNum = 18.0;
        }

        $baseGrav = $total > 0 ? round($total / (1 + $pctNum / 100), 2) : 0.0;
        $igvGrav = $total > 0 ? round(max(0.0, $total - $baseGrav), 2) : 0.0;

        $otrosNumeric = $percepcion > 0 ? round($percepcion, 2) : 0.0;

        $rucEmp = $this->rucContribuyenteEmpresa11();
        $car = $this->armarCodigoCAR($rucEmp, $tipoComp, $sn['serie'], $sn['numero']);

        $valorAdqNoGrav = $total > 0 ? 0.0 : 0.0;
        $isc = $icbper = 0.0;
        $gravBase = $total > 0 ? round($baseGrav, 2) : 0.0;
        $gravIgv = $total > 0 ? round($igvGrav, 2) : 0.0;
        $pctCell = $total > 0 ? $pctIgvTxt : '';
        $totalCell = $total > 0 ? round($total, 2) : 0.0;

        return [
            $orden,
            $fechaEmisionTxt,
            $fechaVencTxt,
            $tipoComp,
            $sn['serie'],
            '',
            $sn['numero'],
            '',
            $tipoDocProv,
            $numDocTxt,
            $nombreProv,
            $pctCell,
            $gravBase,
            $gravIgv,
            0.0,
            0.0,
            0.0,
            0.0,
            $valorAdqNoGrav,
            $isc,
            $icbper,
            $otrosNumeric,
            $totalCell,
            'PEN',
            $this->formatoTipoCambio(),
            '', '', '', '', '',
            '', '', '',
            0,
            '',
            $car,
            '',
            '',
            $this->estadoComprobanteSunatPorDefecto(),
            '0',
        ];
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

    private function formatoTipoCambio(): string
    {
        return number_format((float) '1', 3, '.', '');
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

    private function estadoComprobanteSunatPorDefecto(): string
    {
        return '1';
    }

    /**
     * @param  list<list<mixed>>  $celdasPorFila
     */
    public function fillTemplateSpreadsheet(array $celdasPorFila): Spreadsheet
    {
        $this->extendExecutionTime();
        $this->assertZipExtensionForXlsx();

        $path = $this->templatePath();
        if (! is_readable($path)) {
            throw new \RuntimeException('No se encuentra o no se puede leer el template RCE COMPRAS: '.$path);
        }

        // Plantilla SUNAT COMPRAS.xlsx trae millones de celdas vacías (hasta col. WYP): sin podar, PhpSpreadsheet agota RAM.
        $sanitizedPath = $this->copyTemplatePreparedForLoad($path);
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
        $maxCol = count(self::CABECERAS_RCE);
        $lastRow = $row + max(0, count($celdasPorFila)) - 1;
        $this->unmergeRangesOverlappingDataRows($sheet, $row, $lastRow, $maxCol);

        $explicitStringCols = [
            4, // tipo comprobante SUNAT (01 / 03 / …)
            5,
            7,
            8,
            9,
            self::COL_EXCEL_RUC_PROVEEDOR,
            11,
            self::COL_EXCEL_PCT_IGV,
            self::COL_EXCEL_COD_MONEDA,
            self::COL_EXCEL_TIPO_CAMBIO,
            self::COL_EXCEL_CAR_CP_MODIFICAR,
            self::COL_EXCEL_CODIGO_CAR,
            37,
            38,
            self::COL_EXCEL_ESTADO_COMPROBANTE,
            self::COL_EXCEL_COD_TIPO_CARGA,
        ];

        $dateCols = [2, 3, 26];

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

    /**
     * Si una fusión cruza la banda de filas donde escribimos datos, Excel/PhpSpreadsheet puede
     * asignar el valor a la celda maestra (p. ej. fila 1). Quitamos solo fusiones que intersectan esa banda.
     */
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
                // rango ya no válido u hoja protegida
            }
        }
    }

    /**
     * Copia el .xlsx y lo deja liviano para PhpSpreadsheet (validaciones, filas de ejemplo y columnas sobrantes).
     */
    private function copyTemplatePreparedForLoad(string $sourcePath): string
    {
        $dest = sys_get_temp_dir().DIRECTORY_SEPARATOR.'rce_tpl_'.uniqid('', true).'.xlsx';
        if (! @copy($sourcePath, $dest)) {
            throw new \RuntimeException('No se pudo copiar el template a temporal: '.$sourcePath);
        }

        $zip = new \ZipArchive();
        if ($zip->open($dest) !== true) {
            @unlink($dest);
            throw new \RuntimeException('No se pudo abrir el template como ZIP (xlsx): '.$dest);
        }

        $dvPattern = '/<(?:[\w.-]+:)?dataValidations\b[^>]*\/>|<(?:[\w.-]+:)?dataValidations\b[^>]*>[\s\S]*?<\/(?:[\w.-]+:)?dataValidations>/';
        $firstDataRow = $this->firstDataRow();
        $maxCol = count(self::CABECERAS_RCE);
        $lastHeaderRow = max(1, $firstDataRow - 1);
        $lastColLetter = Coordinate::stringFromColumnIndex($maxCol);

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entryName = (string) $zip->getNameIndex($i);
            if (! preg_match('#^xl/worksheets/sheet\d+\.xml$#i', $entryName)) {
                continue;
            }
            $xml = $zip->getFromIndex($i);
            if ($xml === false || $xml === '') {
                continue;
            }
            $cleaned = preg_replace($dvPattern, '', $xml) ?? $xml;
            $cleaned = $this->pruneWorksheetXmlForRceLoad($cleaned, $firstDataRow, $maxCol);
            $cleaned = preg_replace(
                '/<dimension ref="[^"]*"\s*\/>/',
                '<dimension ref="A1:'.$lastColLetter.$lastHeaderRow.'"/>',
                $cleaned
            ) ?? $cleaned;
            $cleaned = preg_replace(
                '/<dimension ref="[^"]*">/',
                '<dimension ref="A1:'.$lastColLetter.$lastHeaderRow.'">',
                $cleaned
            ) ?? $cleaned;
            if ($cleaned !== $xml) {
                $zip->deleteName($entryName);
                $zip->addFromString($entryName, $cleaned);
            }
        }

        $zip->close();

        return $dest;
    }

    /**
     * La plantilla oficial SUNAT incluye filas de ejemplo con celdas hasta columnas enormes (p. ej. WYP).
     * Conserva solo cabeceras (filas &lt; firstDataRow) y columnas RCE (40).
     */
    private function pruneWorksheetXmlForRceLoad(string $xml, int $firstDataRow, int $maxCol): string
    {
        $out = preg_replace_callback(
            '/<row r="(\d+)"([^>]*)>(.*?)<\/row>/s',
            static function (array $m) use ($firstDataRow, $maxCol): string {
                $rn = (int) $m[1];
                if ($rn >= $firstDataRow) {
                    return '';
                }
                $inner = preg_replace_callback(
                    '/<c r="([A-Za-z]+\d+)"([^>]*)(?:\/>|>.*?<\/c>)/s',
                    static function (array $c) use ($maxCol): string {
                        if (! preg_match('/^([A-Za-z]+)/', $c[1], $col)) {
                            return $c[0];
                        }
                        if (Coordinate::columnIndexFromString(strtoupper($col[1])) > $maxCol) {
                            return '';
                        }

                        return $c[0];
                    },
                    $m[3]
                );

                return '<row r="'.$m[1].'"'.$m[2].'>'.$inner.'</row>';
            },
            $xml
        );

        return is_string($out) ? $out : $xml;
    }

    /**
     * Los .xlsx son ZIP; PhpSpreadsheet necesita la extensión PHP zip (clase ZipArchive).
     */
    private function assertZipExtensionForXlsx(): void
    {
        if (extension_loaded('zip') && class_exists(\ZipArchive::class)) {
            return;
        }

        throw new \RuntimeException(
            'PHP no tiene habilitada la extensión ZIP (ZipArchive), necesaria para leer y generar .xlsx. '
            .'En XAMPP: edite php.ini (p. ej. C:\\xampp\\php\\php.ini), descomente la línea extension=zip, guarde y reinicie el servidor (Apache o php artisan serve). '
            .'Compruebe con: php -m (debe aparecer "zip").'
        );
    }

    private function fechaCompraCarbon(Compras $c): ?Carbon
    {
        $raw = $c->fechaCompra ?? $c->created_at ?? null;
        if ($raw === null || $raw === '') {
            return null;
        }

        try {
            return Carbon::parse($raw);
        } catch (\Throwable) {
            return null;
        }
    }

    private function maxFechaVencimientoDetalle(Compras $c): ?Carbon
    {
        $max = null;
        $detalles = $c->relationLoaded('detalles') ? $c->getRelation('detalles') : collect();
        foreach ($detalles as $d) {
            $fv = $d->fechaVencimiento ?? null;
            if (! $fv) {
                continue;
            }
            try {
                $dt = Carbon::parse($fv);
                if ($max === null || $dt->gt($max)) {
                    $max = $dt;
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return $max;
    }

    private function inferCodigoTipoComprobante(Compras $c): string
    {
        $nombre = strtoupper((string) ($c->nombreTipoDocumento ?? ''));
        if (str_contains($nombre, 'FACTURA')) {
            return '01';
        }
        if (str_contains($nombre, 'BOLETA')) {
            return '03';
        }
        if (str_contains($nombre, 'NOTA DE CR') || str_contains($nombre, 'NOTA DE CRÉDITO') || str_contains($nombre, 'NOTA CREDITO')) {
            return '07';
        }
        if (str_contains($nombre, 'NOTA DE D') || str_contains($nombre, 'NOTA DE DÉBITO') || str_contains($nombre, 'NOTA DEBITO')) {
            return '08';
        }

        return '';
    }

    /**
     * Serie y correlativo del comprobante de compra (SUNAT): columnas distintas en BD o texto compuesto en numeroTipoDocumento.
     *
     * @return array{serie: string, numero: string}
     */
    private function serieNumeroDesdeModeloCompra(Compras $c): array
    {
        $attrs = $c->getAttributes();
        foreach (['serieTipoDocumento', 'serieComprobante', 'serie', 'seriecpe', 'serie_cpe'] as $k) {
            if (! array_key_exists($k, $attrs)) {
                continue;
            }
            $s = trim((string) ($attrs[$k] ?? ''));
            if ($s === '') {
                continue;
            }
            $parsed = $this->parseSerieNumero((string) ($c->numeroTipoDocumento ?? ''));
            $serie = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $s));
            $num = $parsed['numero'] !== ''
                ? $parsed['numero']
                : preg_replace('/\D+/', '', (string) ($c->numeroTipoDocumento ?? ''));

            return ['serie' => $serie, 'numero' => $num];
        }

        return $this->parseSerieNumero((string) ($c->numeroTipoDocumento ?? ''));
    }

    /**
     * @return array{serie: string, numero: string}
     */
    private function parseSerieNumero(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return ['serie' => '', 'numero' => ''];
        }

        $norm = preg_replace('/[\x{00A0}\x{2010}\x{2011}\x{2012}\x{2013}\x{2014}\x{2212}\x{FF0D}]+/u', '-', $raw);
        $norm = trim((string) $norm);

        // Serie | correlativo con separador explícito (-, /, espacio)
        if (preg_match('/^([^\-\/\s]+)\s*[\-\/]+\s*(\d+)\s*$/u', $norm, $m)
            || preg_match('/^([^\-\/\s]+)\s+(\d+)\s*$/u', $norm, $m)) {
            $serie = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $m[1]));
            if ($serie !== '' && preg_match('/\pL/u', $serie)) {
                return ['serie' => $serie, 'numero' => $m[2]];
            }
        }

        // Formato clásico «F001 - 123» (guión/espacio entre medio)
        if (preg_match('/^([A-Za-z0-9]+)\s*[\-\s]\s*(\d+)$/u', $norm, $m)) {
            return ['serie' => strtoupper($m[1]), 'numero' => $m[2]];
        }

        // Pegado «F03310713», «FE3312227»: el correlativo son los dígitos finales (≥3)
        if (preg_match('/^(.+?)(\d{3,})$/u', $norm, $m)) {
            $serie = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $m[1]));
            if ($serie !== '' && preg_match('/\pL/u', $serie)) {
                return ['serie' => $serie, 'numero' => $m[2]];
            }
        }

        $digits = preg_replace('/\D+/', '', $norm);

        return ['serie' => '', 'numero' => $digits ?? ''];
    }

    private function inferTipoDocumentoProveedor(Compras $c, ?Proveedores $p): string
    {
        $n = preg_replace('/\D+/', '', (string) ($this->numeroDocumentoProveedor($c, $p)));
        if (strlen($n) === 11) {
            return '6';
        }
        if (strlen($n) === 8) {
            return '1';
        }

        return '';
    }

    private function numeroDocumentoProveedor(Compras $c, ?Proveedores $p): string
    {
        $rucCompra = preg_replace('/\D+/', '', (string) ($c->rucProveedor ?? ''));
        if ($rucCompra !== '') {
            return $rucCompra;
        }
        if ($p) {
            return preg_replace('/\D+/', '', (string) ($p->numeroDoi ?? ''));
        }

        return '';
    }

    private function nombreProveedor(Compras $c, ?Proveedores $p): string
    {
        $rs = trim((string) ($c->razonSocial ?? ''));
        if ($rs !== '') {
            return $rs;
        }
        $nom = trim((string) ($c->nombreProveedor ?? ''));
        if ($nom !== '') {
            return $nom;
        }
        if ($p) {
            $r = trim((string) ($p->razonsocial ?? ''));
            if ($r !== '') {
                return $r;
            }

            return trim((string) ($p->nombre ?? ''));
        }

        return '';
    }
}
