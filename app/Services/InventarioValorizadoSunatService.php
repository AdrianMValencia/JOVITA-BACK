<?php

namespace App\Services;

use App\Models\Productos;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Inventario valorizado SUNAT – kardex por producto (costo promedio ponderado).
 */
class InventarioValorizadoSunatService
{
    public const COLS = 14;

    private ?bool $hasEfactSerie = null;

    private ?bool $hasEfactNumero = null;

    private ?bool $hasTablaAjusteInventario = null;

    /** @var list<string> */
    public const CABECERAS = [
        'FECHA',
        'TIPO',
        'SERIE',
        'NUMERO',
        'TIPO OPERACION',
        'CANTIDAD (ENTRADAS)',
        'COSTO UNITARIO (ENTRADAS)',
        'COSTO TOTAL (ENTRADAS)',
        'CANTIDAD (SALIDAS)',
        'COSTO UNITARIO (SALIDAS)',
        'COSTO TOTAL (SALIDAS)',
        'CANTIDAD (SALDO FINAL)',
        'COSTO UNITARIO (SALDO FINAL)',
        'COSTO TOTAL (SALDO FINAL)',
    ];

    public function templatePath(): string
    {
        return (string) config('contabilidad.inventario_valorizado_template');
    }

    public function firstDataRow(): int
    {
        $v = config('contabilidad.inventario_valorizado_first_data_row');

        return max(1, (int) ($v ?? 6));
    }

    public function dataSheetIndex(): int
    {
        return max(0, (int) config('contabilidad.inventario_valorizado_sheet_index', 0));
    }

    public function extendExecutionTime(): void
    {
        $maxSec = (int) config('contabilidad.inventario_valorizado_max_execution_seconds', 300);
        if ($maxSec > 0) {
            @set_time_limit($maxSec);
            @ini_set('max_execution_time', (string) $maxSec);
        }

        $memory = (string) config('contabilidad.inventario_valorizado_memory_limit', '512M');
        if ($memory !== '') {
            @ini_set('memory_limit', $memory);
        }
    }

    /**
     * @return list<string>
     */
    private function columnasProductoInventario(): array
    {
        return ['id', 'codigoBarra', 'nombre', 'precioCompra', 'stockActual', 'idPuntoVenta'];
    }

    /**
     * Consulta base de productos (sin ejecutar). Para listado paginado o Excel completo.
     *
     * @return Builder<Productos>
     */
    public function buildQueryProductos(
        ?int $idProducto,
        ?string $codigoBarra,
        ?string $codigo,
        ?string $nombre,
        int $idPuntoVenta
    ): Builder {
        $columnas = $this->columnasProductoInventario();

        if ($idProducto !== null && $idProducto > 0) {
            return Productos::query()
                ->select($columnas)
                ->where('id', $idProducto)
                ->where('idPuntoVenta', $idPuntoVenta);
        }

        $codigoExacto = trim((string) $codigoBarra);
        if ($codigoExacto !== '') {
            return Productos::query()
                ->select($columnas)
                ->where('codigoBarra', $codigoExacto)
                ->where('idPuntoVenta', $idPuntoVenta);
        }

        $q = Productos::query()
            ->select($columnas)
            ->where('idPuntoVenta', $idPuntoVenta);

        $codigoFiltro = trim((string) $codigo);
        $nombreFiltro = trim((string) $nombre);
        if ($codigoFiltro !== '') {
            $q->where('codigoBarra', 'like', '%'.$codigoFiltro.'%');
        }
        if ($nombreFiltro !== '') {
            $q->where('nombre', 'like', '%'.$nombreFiltro.'%');
        }

        return $q->orderBy('codigoBarra')->orderBy('nombre');
    }

    public function esConsultaProductoUnico(?int $idProducto, ?string $codigoBarra): bool
    {
        return ($idProducto !== null && $idProducto > 0)
            || trim((string) $codigoBarra) !== '';
    }

    public function perPageDefault(): int
    {
        return max(1, (int) config('contabilidad.inventario_valorizado_per_page_default', 100));
    }

    public function perPageMax(): int
    {
        return max(1, (int) config('contabilidad.inventario_valorizado_per_page_max', 500));
    }

    public function normalizarPerPage(?int $perPage): int
    {
        $valor = $perPage ?? $this->perPageDefault();

        return min($this->perPageMax(), max(1, $valor));
    }

    /**
     * @return array{paginator: LengthAwarePaginator, productos: Collection<int, Productos>}
     */
    public function resolveProductosPaginados(
        ?int $idProducto,
        ?string $codigoBarra,
        ?string $codigo,
        ?string $nombre,
        int $idPuntoVenta,
        int $page,
        int $perPage
    ): array {
        $query = $this->buildQueryProductos($idProducto, $codigoBarra, $codigo, $nombre, $idPuntoVenta);

        if ($this->esConsultaProductoUnico($idProducto, $codigoBarra)) {
            $items = $query->get();
            $total = $items->count();
            $paginator = new LengthAwarePaginator(
                $items,
                $total,
                max(1, $total),
                1
            );

            return ['paginator' => $paginator, 'productos' => $items];
        }

        $paginator = $query->paginate($perPage, ['*'], 'page', max(1, $page));

        return ['paginator' => $paginator, 'productos' => $paginator->getCollection()];
    }

    /**
     * Todos los productos que coinciden (sin paginación). Usar en Excel.
     *
     * @return Collection<int, Productos>
     */
    public function resolveProductos(
        ?int $idProducto,
        ?string $codigoBarra,
        ?string $codigo,
        ?string $nombre,
        int $idPuntoVenta
    ): Collection {
        return $this->buildQueryProductos($idProducto, $codigoBarra, $codigo, $nombre, $idPuntoVenta)->get();
    }

    /**
     * @return array{
     *   page: int,
     *   per_page: int,
     *   total_productos: int,
     *   total_paginas: int,
     *   productos_en_pagina: int,
     *   tiene_siguiente: bool,
     *   tiene_anterior: bool
     * }
     */
    public function metaPaginacion(LengthAwarePaginator $paginator, int $productosEnPagina): array
    {
        return [
            'page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total_productos' => $paginator->total(),
            'total_paginas' => $paginator->lastPage(),
            'productos_en_pagina' => $productosEnPagina,
            'tiene_siguiente' => $paginator->hasMorePages(),
            'tiene_anterior' => $paginator->currentPage() > 1,
        ];
    }

    /**
     * @deprecated Use resolveProductos; conservado por compatibilidad interna.
     */
    public function resolveProducto(
        ?int $idProducto,
        ?string $codigoBarra,
        ?string $codigo,
        ?string $nombre,
        int $idPuntoVenta
    ): ?Productos {
        return $this->resolveProductos($idProducto, $codigoBarra, $codigo, $nombre, $idPuntoVenta)->first();
    }

    /**
     * @param  Collection<int, Productos>  $productos
     * @return list<array{
     *   producto: array<string, mixed>,
     *   periodo: array<string, mixed>,
     *   saldo_inicial: array<string, float>,
     *   filas: list<array{tipo_operacion: string, columnas: array<string, mixed>, celdas: list<mixed>}>
     * }>
     */
    public function buildReportes(
        Collection $productos,
        int $idPuntoVenta,
        string $fechaInicio,
        string $fechaFin,
        bool $incluirSaldoInicial = true,
        bool $incluirCeldas = false,
        bool $incluirFilasDetalle = true
    ): array {
        $this->extendExecutionTime();

        $inicio = Carbon::parse($fechaInicio)->startOfDay();
        $fin = Carbon::parse($fechaFin)->endOfDay();

        $movimientosPorProducto = $this->bulkCollectMovimientosPorProducto($productos, $idPuntoVenta, $fin);

        $reportes = [];
        foreach ($productos as $producto) {
            $reportes[] = $this->buildReporteFromMovimientos(
                $producto,
                $idPuntoVenta,
                $inicio,
                $fin,
                $incluirSaldoInicial,
                $movimientosPorProducto[(int) $producto->id] ?? [],
                $incluirCeldas,
                $incluirFilasDetalle
            );
        }

        return $reportes;
    }

    /**
     * @return array{
     *   producto: array<string, mixed>,
     *   periodo: array<string, mixed>,
     *   saldo_inicial: array<string, float>,
     *   filas: list<array{tipo_operacion: string, columnas?: array<string, mixed>, celdas?: list<mixed>}>
     * }
     */
    public function buildReporte(
        Productos $producto,
        int $idPuntoVenta,
        string $fechaInicio,
        string $fechaFin,
        bool $incluirSaldoInicial = true,
        bool $incluirCeldas = false
    ): array {
        return $this->buildReportes(
            collect([$producto]),
            $idPuntoVenta,
            $fechaInicio,
            $fechaFin,
            $incluirSaldoInicial,
            $incluirCeldas,
            true
        )[0];
    }

    /**
     * @param  list<array<string, mixed>>  $movimientos
     * @return array{
     *   producto: array<string, mixed>,
     *   periodo: array<string, mixed>,
     *   saldo_inicial: array<string, float>,
     *   saldo_final: array<string, float>,
     *   filas: list<array{tipo_operacion: string, columnas?: array<string, mixed>, celdas?: list<mixed>}>
     * }
     */
    private function buildReporteFromMovimientos(
        Productos $producto,
        int $idPuntoVenta,
        Carbon $inicio,
        Carbon $fin,
        bool $incluirSaldoInicial,
        array $movimientos,
        bool $incluirCeldas,
        bool $incluirFilasDetalle
    ): array {
        $kardex = $this->buildFilasKardex(
            $movimientos,
            $inicio,
            $fin,
            $incluirSaldoInicial,
            $producto,
            $incluirFilasDetalle
        );

        $filas = [];
        if ($incluirFilasDetalle) {
            foreach ($kardex['filas'] as $k) {
                $fila = ['tipo_operacion' => $k['tipo_operacion']];
                if ($incluirCeldas) {
                    $fila['celdas'] = $k['celdas'];
                } else {
                    $fila['columnas'] = $this->celdasToKeyed($k['celdas']);
                }
                $filas[] = $fila;
            }
        }

        return [
            'producto' => [
                'id' => $producto->id,
                'codigoBarra' => $producto->codigoBarra,
                'nombre' => $producto->nombre,
                'idPuntoVenta' => $idPuntoVenta,
            ],
            'periodo' => [
                'fechaInicio' => $inicio->toDateString(),
                'fechaFin' => $fin->toDateString(),
                'incluir_saldo_inicial' => $incluirSaldoInicial,
            ],
            'saldo_inicial' => $kardex['saldo_inicial'],
            'saldo_final' => $kardex['saldo_final'],
            'filas' => $filas,
        ];
    }

    /**
     * Excel liviano: una fila por producto (saldos iniciales y finales del periodo).
     *
     * @param  list<array{producto: array<string, mixed>, saldo_inicial: array<string, float>, saldo_final: array<string, float>}>  $reportes
     */
    public function buildResumenSpreadsheet(
        array $reportes,
        string $fechaInicio,
        string $fechaFin
    ): Spreadsheet {
        $this->extendExecutionTime();

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Resumen');

        $sheet->setCellValue('A1', 'INVENTARIO VALORIZADO SUNAT - RESUMEN');
        $sheet->setCellValue('A2', 'Periodo: '.$this->fechaComoTextoDdMmYyyy($fechaInicio).' al '.$this->fechaComoTextoDdMmYyyy($fechaFin));

        $cabeceras = [
            'CODIGO',
            'PRODUCTO',
            'SALDO INI CANT',
            'SALDO INI UNIT',
            'SALDO INI TOTAL',
            'SALDO FIN CANT',
            'SALDO FIN UNIT',
            'SALDO FIN TOTAL',
        ];
        $row = 4;
        foreach ($cabeceras as $colIndex => $titulo) {
            $coord = Coordinate::stringFromColumnIndex($colIndex + 1).$row;
            $sheet->setCellValue($coord, $titulo);
        }

        $row = 5;
        foreach ($reportes as $reporte) {
            $ini = $reporte['saldo_inicial'];
            $fin = $reporte['saldo_final'];
            $valores = [
                (string) ($reporte['producto']['codigoBarra'] ?? ''),
                (string) ($reporte['producto']['nombre'] ?? ''),
                $ini['cantidad'] ?? 0,
                $ini['costo_unitario'] ?? 0,
                $ini['costo_total'] ?? 0,
                $fin['cantidad'] ?? 0,
                $fin['costo_unitario'] ?? 0,
                $fin['costo_total'] ?? 0,
            ];
            foreach ($valores as $colIndex => $valor) {
                $coord = Coordinate::stringFromColumnIndex($colIndex + 1).$row;
                if ($colIndex <= 1) {
                    $sheet->getCell($coord)->setValueExplicit((string) $valor, DataType::TYPE_STRING);
                } else {
                    $sheet->setCellValue($coord, $valor);
                }
            }
            $row++;
        }

        return $spreadsheet;
    }

    /**
     * @param  list<list<mixed>>  $celdasPorFila
     */
    public function fillTemplateSpreadsheet(
        array $celdasPorFila,
        string $fechaInicio,
        string $fechaFin,
        string $codigoBarra,
        string $nombreProducto
    ): Spreadsheet {
        $this->assertZipExtensionForXlsx();

        $this->extendExecutionTime();

        $path = $this->templatePath();
        if (! is_readable($path)) {
            throw new \RuntimeException('No se encuentra o no se puede leer el template INVENTARIO VALORIZADO SUNAT: '.$path);
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

        $this->escribirCabeceraPeriodo($sheet, $fechaInicio, $fechaFin, $codigoBarra, $nombreProducto);

        $row = $this->firstDataRow();
        $maxCol = self::COLS;
        $lastRow = $row + max(0, count($celdasPorFila)) - 1;
        $this->unmergeRangesOverlappingDataRows($sheet, $row, $lastRow, $maxCol);

        $stringCols = [2, 3, 4, 5];
        $dateCol = 1;

        foreach ($celdasPorFila as $fila) {
            for ($c = 1; $c <= $maxCol; $c++) {
                $coord = Coordinate::stringFromColumnIndex($c).$row;
                $val = $fila[$c - 1] ?? null;

                if ($c === $dateCol) {
                    $textoFecha = $this->fechaComoTextoDdMmYyyy($val);
                    if ($textoFecha === '') {
                        $sheet->setCellValue($coord, '');
                    } else {
                        $sheet->getCell($coord)->setValueExplicit($textoFecha, DataType::TYPE_STRING);
                        $sheet->getStyle($coord)->getNumberFormat()
                            ->setFormatCode(NumberFormat::FORMAT_TEXT);
                    }
                    continue;
                }

                if ($val === null || $val === '') {
                    $sheet->setCellValue($coord, '');
                } elseif (in_array($c, $stringCols, true)) {
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
     * Un libro con una hoja por producto (misma plantilla SUNAT en cada pestaña).
     *
     * @param  list<array{celdasPorFila: list<list<mixed>>, codigoBarra: string, nombreProducto: string}>  $bloques
     */
    public function fillTemplateSpreadsheetMulti(
        array $bloques,
        string $fechaInicio,
        string $fechaFin
    ): Spreadsheet {
        if ($bloques === []) {
            throw new \RuntimeException('Sin productos para generar el Excel de inventario valorizado.');
        }

        $spreadsheet = null;
        $titulosUsados = [];

        foreach ($bloques as $idx => $bloque) {
            $partial = $this->fillTemplateSpreadsheet(
                $bloque['celdasPorFila'],
                $fechaInicio,
                $fechaFin,
                $bloque['codigoBarra'],
                $bloque['nombreProducto']
            );
            $srcSheet = $partial->getSheet($this->dataSheetIndex());
            $titulo = $this->tituloHojaExcel($bloque['codigoBarra'], $idx, $titulosUsados);
            $titulosUsados[] = $titulo;
            $srcSheet->setTitle($titulo);

            if ($spreadsheet === null) {
                $spreadsheet = $partial;
            } else {
                $cloned = clone $srcSheet;
                $cloned->setTitle($titulo);
                $spreadsheet->addSheet($cloned);
                $partial->disconnectWorksheets();
                unset($partial);
            }
        }

        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    /**
     * @param  list<string>  $titulosUsados
     */
    private function tituloHojaExcel(string $codigoBarra, int $indice, array $titulosUsados): string
    {
        $base = preg_replace('/[^A-Za-z0-9_\-]/', '_', trim($codigoBarra) !== '' ? trim($codigoBarra) : 'producto');
        if ($base === '') {
            $base = 'producto';
        }
        if (strlen($base) > 28) {
            $base = substr($base, 0, 28);
        }
        $titulo = $base;
        if (in_array($titulo, $titulosUsados, true)) {
            $sufijo = '_'.($indice + 1);
            $maxBase = 31 - strlen($sufijo);
            $titulo = substr($base, 0, max(1, $maxBase)).$sufijo;
        }

        return substr($titulo, 0, 31);
    }

    private function escribirCabeceraPeriodo(
        Worksheet $sheet,
        string $fechaInicio,
        string $fechaFin,
        string $codigoBarra,
        string $nombreProducto
    ): void {
        foreach (['B1' => $fechaInicio, 'D1' => $fechaFin] as $celda => $fecha) {
            $texto = $this->fechaComoTextoDdMmYyyy($fecha);
            if ($texto === '') {
                $sheet->setCellValue($celda, '');
                continue;
            }
            $sheet->getCell($celda)->setValueExplicit($texto, DataType::TYPE_STRING);
            $sheet->getStyle($celda)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
        }

        $filtro = 'FILTROS: '.trim($codigoBarra).', '.trim($nombreProducto);
        if ($filtro === 'FILTROS: ,') {
            $filtro = 'FILTROS: CODIGO, PRODUCTO';
        }
        $sheet->setCellValue('F1', $filtro);
    }

    /**
     * Carga movimientos de todos los productos en pocas consultas SQL (evita N+1).
     *
     * @param  Collection<int, Productos>  $productos
     * @return array<int, list<array<string, mixed>>>
     */
    private function bulkCollectMovimientosPorProducto(
        Collection $productos,
        int $idPuntoVenta,
        Carbon $fin
    ): array {
        $porId = [];
        $preciosCompra = [];
        foreach ($productos as $producto) {
            $id = (int) $producto->id;
            $porId[$id] = [];
            $preciosCompra[$id] = max(0.0, (float) ($producto->precioCompra ?? 0));
        }

        $ids = array_keys($porId);
        if ($ids === []) {
            return $porId;
        }

        $finSql = $fin->format('Y-m-d H:i:s');
        $chunkSize = max(50, (int) config('contabilidad.inventario_valorizado_bulk_chunk', 250));
        $hasEfactSerie = $this->tieneColumnaEfactSerie();
        $hasEfactNumero = $this->tieneColumnaEfactNumero();

        foreach (array_chunk($ids, $chunkSize) as $idChunk) {
            $compras = DB::table('tbl_compras_detalle as d')
                ->join('tbl_compras as c', 'd.idCompra', '=', 'c.id')
                ->whereIn('d.idProducto', $idChunk)
                ->where('c.idPuntoVenta', $idPuntoVenta)
                ->where(function ($w) {
                    $w->whereNull('c.status')->orWhere('c.status', '!=', 0);
                })
                ->whereRaw('COALESCE(c.fechaCompra, c.created_at) <= ?', [$finSql])
                ->select([
                    'd.idProducto',
                    DB::raw('COALESCE(c.fechaCompra, c.created_at) as fecha_raw'),
                    'c.nombreTipoDocumento',
                    'c.numeroTipoDocumento',
                    'd.cantidad',
                    'd.precio',
                    'd.nuevoPrecio',
                ])
                ->get();

            foreach ($compras as $row) {
                $idProducto = (int) $row->idProducto;
                $fecha = $this->parseFecha($row->fecha_raw ?? null);
                if (! $fecha) {
                    continue;
                }
                $cant = (float) ($row->cantidad ?? 0);
                if ($cant <= 0) {
                    continue;
                }
                $costo = (float) ($row->precio ?? 0);
                if ((float) ($row->nuevoPrecio ?? 0) > 0) {
                    $costo = (float) $row->nuevoPrecio;
                }
                if ($costo <= 0) {
                    $costo = $preciosCompra[$idProducto] ?? 0.0;
                }
                $sn = $this->parseSerieNumero((string) ($row->numeroTipoDocumento ?? ''));
                $porId[$idProducto][] = [
                    'fecha' => $fecha,
                    'tipo_operacion' => 'COMPRA',
                    'tipo_doc' => $this->nombreTipoDocumentoCompra((string) ($row->nombreTipoDocumento ?? ''), $sn['serie']),
                    'serie' => $sn['serie'],
                    'numero' => $this->formatearNumeroDocumento($sn['numero']),
                    'cantidad' => $cant,
                    'costo_unitario' => $costo,
                    'es_entrada' => true,
                ];
            }

            $selectRecibos = [
                'd.idProducto',
                'r.fechaEmision',
                'r.created_at',
                'r.series',
                'r.numeracion',
                'd.cantidad',
                'd.precioCompra',
                'p.precioCompra as precio_compra_producto',
            ];
            if ($hasEfactSerie) {
                $selectRecibos[] = 'r.efact_comprobante_serie';
            }
            if ($hasEfactNumero) {
                $selectRecibos[] = 'r.efact_comprobante_numero';
            }

            $recibos = DB::table('tbl_recibo_detalles as d')
                ->join('tbl_recibos as r', 'd.idRecibo', '=', 'r.id')
                ->leftJoin('tbl_productos as p', 'd.idProducto', '=', 'p.id')
                ->whereIn('d.idProducto', $idChunk)
                ->where('r.idPuntoVenta', $idPuntoVenta)
                ->where(function ($w) {
                    $w->whereNull('r.status')->orWhere('r.status', '!=', 0);
                })
                ->whereRaw('COALESCE(r.fechaEmision, r.created_at) <= ?', [$finSql])
                ->select($selectRecibos)
                ->get();

            foreach ($recibos as $row) {
                $idProducto = (int) $row->idProducto;
                $fecha = $this->parseFecha($row->fechaEmision ?? $row->created_at ?? null);
                if (! $fecha) {
                    continue;
                }
                $cant = (float) ($row->cantidad ?? 0);
                if ($cant <= 0) {
                    continue;
                }
                $serie = '';
                $numero = '';
                if ($hasEfactSerie && $hasEfactNumero && trim((string) ($row->efact_comprobante_serie ?? '')) !== '') {
                    $serie = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $row->efact_comprobante_serie));
                    $numero = preg_replace('/\D+/', '', (string) ($row->efact_comprobante_numero ?? ''));
                } else {
                    $serie = strtoupper(trim((string) ($row->series ?? '')));
                    $numero = preg_replace('/\D+/', '', (string) ($row->numeracion ?? ''));
                }
                $precioProducto = $preciosCompra[$idProducto] ?? 0.0;
                $porId[$idProducto][] = [
                    'fecha' => $fecha,
                    'tipo_operacion' => 'VENTA',
                    'tipo_doc' => $this->nombreTipoDocumentoVenta($serie),
                    'serie' => $serie,
                    'numero' => $this->formatearNumeroDocumento($numero),
                    'cantidad' => $cant,
                    'costo_unitario' => $this->resolverCostoUnitarioReferencia(
                        (float) ($row->precioCompra ?? 0),
                        (float) ($row->precio_compra_producto ?? 0),
                        $precioProducto
                    ),
                    'es_entrada' => false,
                ];
            }

            if ($this->tieneTablaAjusteInventario()) {
                $ajustes = DB::table('tbl_ajuste_inventario')
                    ->whereIn('idProducto', $idChunk)
                    ->where('idPuntoVenta', $idPuntoVenta)
                    ->where('created_at', '<=', $finSql)
                    ->select(['idProducto', 'created_at', 'motivo', 'cantidad'])
                    ->get();

                foreach ($ajustes as $row) {
                    $idProducto = (int) $row->idProducto;
                    $fecha = $this->parseFecha($row->created_at ?? null);
                    if (! $fecha) {
                        continue;
                    }
                    $cant = abs((float) ($row->cantidad ?? 0));
                    if ($cant <= 0) {
                        continue;
                    }
                    $motivo = strtoupper(trim((string) ($row->motivo ?? '')));
                    $esEntrada = $motivo !== 'FALTANTE';
                    $porId[$idProducto][] = [
                        'fecha' => $fecha,
                        'tipo_operacion' => $esEntrada ? 'AJUSTE (+)' : 'AJUSTE (-)',
                        'tipo_doc' => 'AJUSTE INVENTARIO',
                        'serie' => '',
                        'numero' => '',
                        'cantidad' => $cant,
                        'costo_unitario' => $preciosCompra[$idProducto] ?? 0.0,
                        'es_entrada' => $esEntrada,
                    ];
                }
            }
        }

        foreach ($porId as $id => $items) {
            usort($items, static function (array $a, array $b): int {
                $cmp = $a['fecha']->timestamp <=> $b['fecha']->timestamp;
                if ($cmp !== 0) {
                    return $cmp;
                }

                return strcmp($a['tipo_operacion'], $b['tipo_operacion']);
            });
            $porId[$id] = $items;
        }

        return $porId;
    }

    private function tieneColumnaEfactSerie(): bool
    {
        if ($this->hasEfactSerie === null) {
            $this->hasEfactSerie = Schema::hasColumn('tbl_recibos', 'efact_comprobante_serie');
        }

        return $this->hasEfactSerie;
    }

    private function tieneColumnaEfactNumero(): bool
    {
        if ($this->hasEfactNumero === null) {
            $this->hasEfactNumero = Schema::hasColumn('tbl_recibos', 'efact_comprobante_numero');
        }

        return $this->hasEfactNumero;
    }

    private function tieneTablaAjusteInventario(): bool
    {
        if ($this->hasTablaAjusteInventario === null) {
            $this->hasTablaAjusteInventario = Schema::hasTable('tbl_ajuste_inventario');
        }

        return $this->hasTablaAjusteInventario;
    }

    /**
     * @param  list<array<string, mixed>>  $movimientos
     * @return array{
     *   filas: list<array{tipo_operacion: string, celdas: list<mixed>}>,
     *   saldo_inicial: array{cantidad: float, costo_unitario: float, costo_total: float},
     *   saldo_final: array{cantidad: float, costo_unitario: float, costo_total: float}
     * }
     */
    private function buildFilasKardex(
        array $movimientos,
        Carbon $inicio,
        Carbon $fin,
        bool $incluirSaldoInicial,
        Productos $producto,
        bool $generarFilasDetalle = true
    ): array {
        $saldoQty = 0.0;
        $saldoUnit = 0.0;
        $precioCompraProducto = max(0.0, (float) ($producto->precioCompra ?? 0));

        foreach ($movimientos as $mov) {
            if ($mov['fecha']->gt($fin)) {
                break;
            }
            if ($mov['fecha']->lt($inicio)) {
                $this->aplicarAlSaldo($mov, $saldoQty, $saldoUnit, $precioCompraProducto);
            }
        }

        $this->establecerSaldoAperturaPeriodo($movimientos, $inicio, $fin, $producto, $saldoQty, $saldoUnit);

        $saldoInicial = [
            'cantidad' => $saldoQty,
            'costo_unitario' => $saldoUnit,
            'costo_total' => round($saldoQty * $saldoUnit, 2),
        ];

        $filas = [];
        if ($generarFilasDetalle && $incluirSaldoInicial) {
            $filas[] = [
                'tipo_operacion' => 'SALDO INICIAL',
                'celdas' => $this->armarFilaExcel(
                    $inicio,
                    'SI',
                    '0',
                    '0',
                    'SALDO INICIAL',
                    $saldoQty,
                    $saldoUnit,
                    true
                ),
            ];
        }

        foreach ($movimientos as $mov) {
            if ($mov['fecha']->lt($inicio) || $mov['fecha']->gt($fin)) {
                continue;
            }

            if ($mov['es_entrada']) {
                $entQty = (float) $mov['cantidad'];
                $entUnit = (float) $mov['costo_unitario'];
                if ($entUnit <= 0 && $saldoUnit > 0) {
                    $entUnit = $saldoUnit;
                }
                if ($entUnit <= 0) {
                    $entUnit = $precioCompraProducto;
                }
                $entTotal = round($entQty * $entUnit, 2);
                $valorPrevio = round($saldoQty * $saldoUnit, 2);
                $saldoQty += $entQty;
                $saldoUnit = $saldoQty > 0
                    ? round(($valorPrevio + $entTotal) / $saldoQty, 2)
                    : 0.0;
                $saldoTotal = round($saldoQty * $saldoUnit, 2);

                if ($generarFilasDetalle) {
                    $filas[] = [
                        'tipo_operacion' => (string) $mov['tipo_operacion'],
                        'celdas' => [
                            $this->fechaExcel($mov['fecha']),
                            $mov['tipo_doc'],
                            $mov['serie'],
                            $mov['numero'],
                            $mov['tipo_operacion'],
                            $entQty,
                            $entUnit,
                            $entTotal,
                            0,
                            0,
                            0,
                            $saldoQty,
                            $saldoUnit,
                            $saldoTotal,
                        ],
                    ];
                }
            } else {
                $salUnit = $this->resolverCostoUnitarioVenta($saldoUnit, $mov, $precioCompraProducto);
                $salQty = (float) $mov['cantidad'];
                $salTotal = round($salQty * $salUnit, 2);
                $saldoQty -= $salQty;
                if ($saldoQty < 0) {
                    $saldoQty = 0.0;
                }
                if ($saldoQty <= 0 && $saldoUnit <= 0) {
                    $saldoUnit = $salUnit;
                }
                $saldoTotal = round($saldoQty * $saldoUnit, 2);

                if ($generarFilasDetalle) {
                    $filas[] = [
                        'tipo_operacion' => (string) $mov['tipo_operacion'],
                        'celdas' => [
                            $this->fechaExcel($mov['fecha']),
                            $mov['tipo_doc'],
                            $mov['serie'],
                            $mov['numero'],
                            $mov['tipo_operacion'],
                            0,
                            0,
                            0,
                            $salQty,
                            $salUnit,
                            $salTotal,
                            $saldoQty,
                            $saldoUnit,
                            $saldoTotal,
                        ],
                    ];
                }
            }
        }

        $saldoFinal = [
            'cantidad' => $saldoQty,
            'costo_unitario' => $saldoUnit,
            'costo_total' => round($saldoQty * $saldoUnit, 2),
        ];

        return [
            'filas' => $filas,
            'saldo_inicial' => $saldoInicial,
            'saldo_final' => $saldoFinal,
        ];
    }

    /**
     * Si no hay historial de compras, estima existencia al inicio del periodo desde stock actual.
     */
    private function establecerSaldoAperturaPeriodo(
        array $movimientos,
        Carbon $inicio,
        Carbon $fin,
        Productos $producto,
        float &$saldoQty,
        float &$saldoUnit
    ): void {
        $precioProducto = max(0.0, (float) ($producto->precioCompra ?? 0));

        if ($saldoUnit <= 0 && $precioProducto > 0) {
            $saldoUnit = $precioProducto;
        }

        if ($saldoQty > 0) {
            return;
        }

        $ventasPeriodo = 0.0;
        $comprasPeriodo = 0.0;
        foreach ($movimientos as $mov) {
            if ($mov['fecha']->lt($inicio) || $mov['fecha']->gt($fin)) {
                continue;
            }
            if ($mov['es_entrada']) {
                $comprasPeriodo += (float) $mov['cantidad'];
            } else {
                $ventasPeriodo += (float) $mov['cantidad'];
            }
        }

        $stockActual = max(0.0, (float) ($producto->stockActual ?? 0));
        $saldoQty = max(0.0, round($stockActual + $ventasPeriodo - $comprasPeriodo, 4));

        if ($saldoUnit <= 0 && $precioProducto > 0) {
            $saldoUnit = $precioProducto;
        }
    }

    /**
     * Costo de salida: promedio ponderado vigente; si no hay, costo del detalle o del maestro de producto.
     */
    private function resolverCostoUnitarioVenta(float $saldoUnit, array $mov, float $precioCompraProducto): float
    {
        if ($saldoUnit > 0) {
            return $saldoUnit;
        }

        $costoMov = (float) ($mov['costo_unitario'] ?? 0);

        return $costoMov > 0 ? $costoMov : max(0.0, $precioCompraProducto);
    }

    private function resolverCostoUnitarioReferencia(
        float $costoDetalle,
        float $costoProductoJoin,
        float $costoProductoMaestro
    ): float {
        if ($costoDetalle > 0) {
            return $costoDetalle;
        }
        if ($costoProductoJoin > 0) {
            return $costoProductoJoin;
        }

        return max(0.0, $costoProductoMaestro);
    }

    /**
     * Actualiza saldo sin generar fila (movimientos anteriores al periodo).
     */
    private function aplicarAlSaldo(
        array $mov,
        float &$saldoQty,
        float &$saldoUnit,
        float $precioCompraProducto = 0.0
    ): void {
        if ($mov['es_entrada']) {
            $entQty = (float) $mov['cantidad'];
            $entUnit = (float) $mov['costo_unitario'];
            if ($entUnit <= 0 && $saldoUnit > 0) {
                $entUnit = $saldoUnit;
            }
            if ($entUnit <= 0) {
                $entUnit = $precioCompraProducto;
            }
            $valorPrevio = round($saldoQty * $saldoUnit, 2);
            $entTotal = round($entQty * $entUnit, 2);
            $saldoQty += $entQty;
            $saldoUnit = $saldoQty > 0
                ? round(($valorPrevio + $entTotal) / $saldoQty, 2)
                : 0.0;
        } else {
            $saldoQty -= (float) $mov['cantidad'];
            if ($saldoQty < 0) {
                $saldoQty = 0.0;
            }
        }
    }

    /**
     * Fila de saldo inicial (entradas = saldo; salidas en cero).
     *
     * @return list<mixed>
     */
    private function armarFilaExcel(
        Carbon $fecha,
        string $tipoDoc,
        string $serie,
        string $numero,
        string $tipoOperacion,
        float $saldoQty,
        float $saldoUnit,
        bool $comoEntrada
    ): array {
        $total = round($saldoQty * $saldoUnit, 2);
        if ($comoEntrada) {
            return [
                $this->fechaExcel($fecha),
                $tipoDoc,
                $serie,
                $numero,
                $tipoOperacion,
                $saldoQty,
                $saldoUnit,
                $total,
                0,
                0,
                0,
                $saldoQty,
                $saldoUnit,
                $total,
            ];
        }

        return [
            $this->fechaExcel($fecha),
            $tipoDoc,
            $serie,
            $numero,
            $tipoOperacion,
            0,
            0,
            0,
            0,
            0,
            0,
            $saldoQty,
            $saldoUnit,
            $total,
        ];
    }

    /** Fecha visible en Excel/JSON (d/m/Y), no serial numérico. */
    private function fechaExcel(Carbon $fecha): string
    {
        return $fecha->copy()->startOfDay()->format('d/m/Y');
    }

    /** Normaliza fecha a texto d/m/Y (acepta serial Excel, Y-m-d, d/m/Y u otros parseables). */
    private function fechaComoTextoDdMmYyyy(mixed $val): string
    {
        $dt = $this->parseFecha($val);

        return $dt !== null ? $dt->format('d/m/Y') : '';
    }

    private function parseFecha(mixed $raw): ?Carbon
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        if ($raw instanceof Carbon) {
            return $raw->copy()->startOfDay();
        }
        if ($raw instanceof \DateTimeInterface) {
            return Carbon::instance($raw)->startOfDay();
        }
        if (is_int($raw) || is_float($raw)) {
            try {
                $dt = ExcelDate::excelToDateTimeObject((float) $raw);

                return Carbon::instance($dt)->startOfDay();
            } catch (\Throwable) {
                return null;
            }
        }

        $s = trim((string) $raw);
        if ($s === '') {
            return null;
        }

        foreach (['Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d', 'd/m/Y H:i:s', 'd/m/Y'] as $formato) {
            try {
                $parsed = Carbon::createFromFormat($formato, $s);
                if ($parsed instanceof Carbon) {
                    return $parsed->startOfDay();
                }
            } catch (\Throwable) {
                continue;
            }
        }

        try {
            return Carbon::parse($s)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  list<mixed>  $celdas
     * @return array<string, mixed>
     */
    private function celdasToKeyed(array $celdas): array
    {
        $out = [];
        foreach (self::CABECERAS as $i => $nombre) {
            $out[$nombre] = $celdas[$i] ?? null;
        }

        return $out;
    }

    private function nombreTipoDocumentoCompra(string $nombreTipo, string $serie): string
    {
        $n = strtoupper(trim($nombreTipo));
        if ($n !== '') {
            if (! str_contains($n, 'ELECTRONIC')) {
                if (str_contains($n, 'FACTURA')) {
                    return 'FACTURA ELECTRONICA';
                }
                if (str_contains($n, 'BOLETA')) {
                    return 'BOLETA ELECTRONICA';
                }
            }

            return $n;
        }

        return $this->nombreTipoDocumentoVenta($serie);
    }

    private function nombreTipoDocumentoVenta(string $serie): string
    {
        $s = strtoupper(preg_replace('/\s+/', '', $serie));
        if ($s === '') {
            return '';
        }
        if (str_starts_with($s, 'F')) {
            return 'FACTURA ELECTRONICA';
        }
        if (str_starts_with($s, 'B') || str_starts_with($s, 'E')) {
            return 'BOLETA ELECTRONICA';
        }

        return '';
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

        if (preg_match('/^([^\-\/\s]+)\s*[\-\/]+\s*(\d+)\s*$/u', $norm, $m)
            || preg_match('/^([^\-\/\s]+)\s+(\d+)\s*$/u', $norm, $m)) {
            $serie = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $m[1]));

            return ['serie' => $serie, 'numero' => $m[2]];
        }

        if (preg_match('/^(.+?)(\d{3,})$/u', $norm, $m)) {
            $serie = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $m[1]));
            if ($serie !== '') {
                return ['serie' => $serie, 'numero' => $m[2]];
            }
        }

        return ['serie' => '', 'numero' => preg_replace('/\D+/', '', $norm) ?? ''];
    }

    private function formatearNumeroDocumento(string $numero): string
    {
        $digits = preg_replace('/\D+/', '', $numero) ?? '';
        if ($digits === '') {
            return '';
        }

        return str_pad($digits, max(5, strlen($digits)), '0', STR_PAD_LEFT);
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
            if ($rMax < $firstDataRow || $rMin > $lastDataRow) {
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
        $dest = sys_get_temp_dir().DIRECTORY_SEPARATOR.'inv_sunat_tpl_'.uniqid('', true).'.xlsx';
        if (! @copy($sourcePath, $dest)) {
            throw new \RuntimeException('No se pudo copiar el template a temporal: '.$sourcePath);
        }

        $zip = new \ZipArchive();
        if ($zip->open($dest) !== true) {
            @unlink($dest);
            throw new \RuntimeException('No se pudo abrir el template como ZIP (xlsx).');
        }

        $pattern = '/<(?:[\w.-]+:)?dataValidations\b[^>]*\/>|<(?:[\w.-]+:)?dataValidations\b[^>]*>[\s\S]*?<\/(?:[\w.-]+:)?dataValidations>/';

        $names = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $names[] = $zip->getNameIndex($i);
        }

        foreach ($names as $entryName) {
            if (! preg_match('#^xl/worksheets/sheet\d+\.xml$#i', $entryName)) {
                continue;
            }
            $xml = $zip->getFromName($entryName);
            if ($xml === false || $xml === '') {
                continue;
            }
            $cleaned = preg_replace($pattern, '', $xml) ?? $xml;
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
