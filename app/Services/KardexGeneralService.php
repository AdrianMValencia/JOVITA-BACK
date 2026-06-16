<?php

namespace App\Services;

use App\Models\Productos;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * KARDEX GENERAL – hoja CABECERA (resumen por producto) y DETALLE (movimientos).
 */
class KardexGeneralService
{
    public const HOJA_CABECERA = 'CABECERA';

    public const HOJA_DETALLE = 'DETALLE';

    /** @var list<string> */
    public const CABECERAS_CABECERA = [
        'CODIGO BARRA',
        'CATEGORIA',
        'PRODUCTO',
        'U.M.',
        'CANT. (SALDO INICIAL)',
        'COSTO ( SALDO INICIAL)',
        'CANT. (ENTRADA)',
        'COSTO (ENTRADA)',
        'CANT. (SALIDA)',
        'COSTO (SALIDA)',
        'CANT. (SALDO)',
        'COSTO (SALDO)',
    ];

    /** @var list<string> */
    public const CABECERAS_DETALLE = [
        'FECHA MOVIMIENTO',
        'CODIGO BARRA',
        'CATEGORIA',
        'PRODUCTO',
        'U.M.',
        'MOVIMIENTO',
        'TIPO DOCUMENTO',
        'NRO SERIE',
        'NRO DOCUMENTO',
        'CANT. (ENTRADA)',
        'COSTO (ENTRADA)',
        'TOTAL (ENTRADA)',
        'CANT. (SALIDA)',
        'COSTO (SALIDA)',
        'TOTAL (SALIDA)',
        'SALDO CANTIDAD',
        'SALDO COSTO',
        'SALDO TOTAL',
    ];

    public function __construct(
        private readonly InventarioValorizadoSunatService $inventarioValorizado
    ) {}

    public function extendExecutionTime(): void
    {
        $this->inventarioValorizado->extendExecutionTime();
    }

    public function templatePath(): string
    {
        return (string) config('contabilidad.kardex_general_template');
    }

    public function cabeceraHeaderRow(): int
    {
        return max(1, (int) config('contabilidad.kardex_general_cabecera_header_row', 3));
    }

    public function cabeceraFirstDataRow(): int
    {
        return max(1, (int) config('contabilidad.kardex_general_cabecera_first_data_row', 4));
    }

    public function detalleHeaderRow(): int
    {
        return max(1, (int) config('contabilidad.kardex_general_detalle_header_row', 1));
    }

    public function detalleFirstDataRow(): int
    {
        return max(1, (int) config('contabilidad.kardex_general_detalle_first_data_row', 2));
    }

    public function perPageDefault(): int
    {
        return max(1, (int) config('contabilidad.kardex_general_per_page_default', 100));
    }

    public function perPageMax(): int
    {
        return max(1, (int) config('contabilidad.kardex_general_per_page_max', 500));
    }

    public function normalizarPerPage(?int $perPage): int
    {
        $valor = $perPage ?? $this->perPageDefault();

        return min($this->perPageMax(), max(1, $valor));
    }

    /**
     * @return Builder<Productos>
     */
    public function buildQueryProductos(
        ?int $idProducto,
        ?string $codigoBarra,
        ?string $codigo,
        ?string $nombre,
        int $idPuntoVenta
    ): Builder {
        $columnas = [
            'id', 'codigoBarra', 'nombre', 'precioCompra', 'stockActual',
            'idPuntoVenta', 'nombreCategoria', 'nombreUm',
        ];

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
            $paginator = new LengthAwarePaginator($items, $total, max(1, $total), 1);

            return ['paginator' => $paginator, 'productos' => $items];
        }

        $paginator = $query->paginate($perPage, ['*'], 'page', max(1, $page));

        return ['paginator' => $paginator, 'productos' => $paginator->getCollection()];
    }

    /**
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
     * @param  Collection<int, Productos>  $productos
     * @return list<array{
     *   producto: array<string, mixed>,
     *   columnas: array<string, mixed>,
     *   celdas: list<mixed>
     * }>
     */
    public function buildFilasCabecera(
        Collection $productos,
        int $idPuntoVenta,
        string $fechaInicio,
        string $fechaFin,
        bool $incluirSaldoInicial = true
    ): array {
        $reportes = $this->inventarioValorizado->buildReportes(
            $productos,
            $idPuntoVenta,
            $fechaInicio,
            $fechaFin,
            $incluirSaldoInicial,
            false,
            true
        );

        $porId = $productos->keyBy('id');
        $filas = [];
        foreach ($reportes as $reporte) {
            $producto = $porId->get((int) ($reporte['producto']['id'] ?? 0));
            if (! $producto) {
                continue;
            }
            $filas[] = $this->transformarCabecera($producto, $reporte);
        }

        return $filas;
    }

    /**
     * @return list<array{producto: array<string, mixed>, columnas: array<string, mixed>, celdas: list<mixed>}>
     */
    public function buildFilasDetalle(
        Productos $producto,
        int $idPuntoVenta,
        string $fechaInicio,
        string $fechaFin,
        bool $incluirSaldoInicial = true
    ): array {
        $reporte = $this->inventarioValorizado->buildReportes(
            collect([$producto]),
            $idPuntoVenta,
            $fechaInicio,
            $fechaFin,
            $incluirSaldoInicial,
            false,
            true
        )[0];

        $filas = [];
        foreach ($reporte['filas'] as $fila) {
            $filas[] = $this->transformarDetalle($producto, $fila);
        }

        return $filas;
    }

    /**
     * @param  Collection<int, Productos>  $productos
     * @return list<array{producto: array<string, mixed>, columnas: array<string, mixed>, celdas: list<mixed>}>
     */
    public function buildFilasDetalleTodos(
        Collection $productos,
        int $idPuntoVenta,
        string $fechaInicio,
        string $fechaFin,
        bool $incluirSaldoInicial = true
    ): array {
        $reportes = $this->inventarioValorizado->buildReportes(
            $productos,
            $idPuntoVenta,
            $fechaInicio,
            $fechaFin,
            $incluirSaldoInicial,
            false,
            true
        );

        $porId = $productos->keyBy('id');
        $todas = [];
        foreach ($reportes as $reporte) {
            $producto = $porId->get((int) ($reporte['producto']['id'] ?? 0));
            if (! $producto) {
                continue;
            }
            foreach ($reporte['filas'] as $fila) {
                $todas[] = $this->transformarDetalle($producto, $fila);
            }
        }

        return $todas;
    }

    /**
     * @param  list<array{celdas: list<mixed>}>  $filasCabecera
     * @param  list<array{celdas: list<mixed>}>  $filasDetalle
     */
    public function fillTemplateSpreadsheet(
        array $filasCabecera,
        array $filasDetalle,
        string $fechaInicio,
        string $fechaFin,
        string $codigoFiltro,
        string $nombreFiltro
    ): Spreadsheet {
        $this->assertZipExtensionForXlsx();
        $this->extendExecutionTime();

        $path = $this->templatePath();
        if (! is_readable($path)) {
            throw new \RuntimeException('No se encuentra o no se puede leer el template KARDEX GENERAL: '.$path);
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

        $sheetCabecera = $spreadsheet->getSheetByName(self::HOJA_CABECERA);
        $sheetDetalle = $spreadsheet->getSheetByName(self::HOJA_DETALLE);
        if ($sheetCabecera === null || $sheetDetalle === null) {
            throw new \RuntimeException(
                'El template KARDEX GENERAL.xlsx debe contener las hojas "'.self::HOJA_CABECERA.'" y "'.self::HOJA_DETALLE.'".'
            );
        }

        // CABECERA: fila 1 = fechas (B1, D1); F1 vacío; fila 3 = títulos; fila 4+ = datos.
        $this->escribirFiltrosCabecera($sheetCabecera, $fechaInicio, $fechaFin);
        $this->escribirTitulosColumnas($sheetCabecera, $this->cabeceraHeaderRow(), self::CABECERAS_CABECERA);
        // DETALLE: fila 1 = títulos (no escribir fechas aquí); fila 2+ = datos.
        $this->escribirTitulosColumnas($sheetDetalle, $this->detalleHeaderRow(), self::CABECERAS_DETALLE);

        $this->escribirFilasEnHoja(
            $sheetCabecera,
            $filasCabecera,
            $this->cabeceraFirstDataRow(),
            count(self::CABECERAS_CABECERA),
            [1, 2, 3]
        );
        $this->escribirFilasEnHoja(
            $sheetDetalle,
            $filasDetalle,
            $this->detalleFirstDataRow(),
            count(self::CABECERAS_DETALLE),
            [1, 2, 3, 4, 5, 6, 7, 8]
        );

        $spreadsheet->setActiveSheetIndex(
            $spreadsheet->getIndex($sheetCabecera)
        );

        return $spreadsheet;
    }

    /**
     * @param  array{producto: array<string, mixed>, saldo_inicial: array<string, float>, saldo_final: array<string, float>, filas: list<array{tipo_operacion: string, columnas: array<string, mixed>}>}  $reporte
     * @return array{producto: array<string, mixed>, columnas: array<string, mixed>, celdas: list<mixed>}
     */
    private function transformarCabecera(Productos $producto, array $reporte): array
    {
        $ini = $reporte['saldo_inicial'];
        $fin = $reporte['saldo_final'];
        $cantEntrada = 0.0;
        $costoEntrada = 0.0;
        $cantSalida = 0.0;
        $costoSalida = 0.0;

        foreach ($reporte['filas'] as $fila) {
            if (($fila['tipo_operacion'] ?? '') === 'SALDO INICIAL') {
                continue;
            }
            $c = $fila['columnas'] ?? [];
            $cantEntrada += (float) ($c['CANTIDAD (ENTRADAS)'] ?? 0);
            $costoEntrada += (float) ($c['COSTO TOTAL (ENTRADAS)'] ?? 0);
            $cantSalida += (float) ($c['CANTIDAD (SALIDAS)'] ?? 0);
            $costoSalida += (float) ($c['COSTO TOTAL (SALIDAS)'] ?? 0);
        }

        $productoArr = $this->productoArray($producto);
        $columnas = [
            'CODIGO BARRA' => $productoArr['codigoBarra'],
            'CATEGORIA' => $productoArr['categoria'],
            'PRODUCTO' => $productoArr['nombre'],
            'U.M.' => $productoArr['um'],
            'CANT. (SALDO INICIAL)' => round((float) ($ini['cantidad'] ?? 0), 4),
            'COSTO ( SALDO INICIAL)' => round((float) ($ini['costo_total'] ?? 0), 2),
            'CANT. (ENTRADA)' => round($cantEntrada, 4),
            'COSTO (ENTRADA)' => round($costoEntrada, 2),
            'CANT. (SALIDA)' => round($cantSalida, 4),
            'COSTO (SALIDA)' => round($costoSalida, 2),
            'CANT. (SALDO)' => round((float) ($fin['cantidad'] ?? 0), 4),
            'COSTO (SALDO)' => round((float) ($fin['costo_total'] ?? 0), 2),
        ];

        return [
            'producto' => $productoArr,
            'columnas' => $columnas,
            'celdas' => array_values($columnas),
        ];
    }

    /**
     * @param  array{tipo_operacion: string, columnas: array<string, mixed>}  $filaKardex
     * @return array{producto: array<string, mixed>, columnas: array<string, mixed>, celdas: list<mixed>}
     */
    private function transformarDetalle(Productos $producto, array $filaKardex): array
    {
        $c = $filaKardex['columnas'] ?? [];
        $productoArr = $this->productoArray($producto);
        $movimiento = (string) ($filaKardex['tipo_operacion'] ?? $c['TIPO OPERACION'] ?? '');

        $columnas = [
            'FECHA MOVIMIENTO' => (string) ($c['FECHA'] ?? ''),
            'CODIGO BARRA' => $productoArr['codigoBarra'],
            'CATEGORIA' => $productoArr['categoria'],
            'PRODUCTO' => $productoArr['nombre'],
            'U.M.' => $productoArr['um'],
            'MOVIMIENTO' => $movimiento,
            'TIPO DOCUMENTO' => (string) ($c['TIPO'] ?? ''),
            'NRO SERIE' => (string) ($c['SERIE'] ?? ''),
            'NRO DOCUMENTO' => (string) ($c['NUMERO'] ?? ''),
            'CANT. (ENTRADA)' => (float) ($c['CANTIDAD (ENTRADAS)'] ?? 0),
            'COSTO (ENTRADA)' => (float) ($c['COSTO UNITARIO (ENTRADAS)'] ?? 0),
            'TOTAL (ENTRADA)' => (float) ($c['COSTO TOTAL (ENTRADAS)'] ?? 0),
            'CANT. (SALIDA)' => (float) ($c['CANTIDAD (SALIDAS)'] ?? 0),
            'COSTO (SALIDA)' => (float) ($c['COSTO UNITARIO (SALIDAS)'] ?? 0),
            'TOTAL (SALIDA)' => (float) ($c['COSTO TOTAL (SALIDAS)'] ?? 0),
            'SALDO CANTIDAD' => (float) ($c['CANTIDAD (SALDO FINAL)'] ?? 0),
            'SALDO COSTO' => (float) ($c['COSTO UNITARIO (SALDO FINAL)'] ?? 0),
            'SALDO TOTAL' => (float) ($c['COSTO TOTAL (SALDO FINAL)'] ?? 0),
        ];

        return [
            'producto' => $productoArr,
            'columnas' => $columnas,
            'celdas' => array_values($columnas),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function productoArray(Productos $producto): array
    {
        return [
            'id' => (int) $producto->id,
            'codigoBarra' => (string) ($producto->codigoBarra ?? ''),
            'nombre' => (string) ($producto->nombre ?? ''),
            'categoria' => (string) ($producto->nombreCategoria ?? ''),
            'um' => (string) ($producto->nombreUm ?? ''),
            'idPuntoVenta' => (int) $producto->idPuntoVenta,
        ];
    }

    /**
     * Solo hoja CABECERA: A1/C1 etiquetas; B1/D1 fechas; F1 se deja vacío.
     */
    private function escribirFiltrosCabecera(
        Worksheet $sheet,
        string $fechaInicio,
        string $fechaFin
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
        $sheet->setCellValue('F1', '');
    }

    /**
     * @param  list<string>  $titulos
     */
    private function escribirTitulosColumnas(Worksheet $sheet, int $row, array $titulos): void
    {
        foreach ($titulos as $i => $titulo) {
            $coord = Coordinate::stringFromColumnIndex($i + 1).$row;
            $sheet->getCell($coord)->setValueExplicit((string) $titulo, DataType::TYPE_STRING);
        }
    }

    /**
     * @param  list<array{celdas: list<mixed>}>  $filas
     * @param  list<int>  $stringCols  columnas 1-based que son texto
     */
    private function escribirFilasEnHoja(
        Worksheet $sheet,
        array $filas,
        int $firstRow,
        int $maxCol,
        array $stringCols
    ): void {
        $row = $firstRow;
        $lastRow = $row + max(0, count($filas)) - 1;
        $this->unmergeRangesOverlappingDataRows($sheet, $row, $lastRow, $maxCol);

        foreach ($filas as $fila) {
            $celdas = $fila['celdas'] ?? [];
            for ($c = 1; $c <= $maxCol; $c++) {
                $coord = Coordinate::stringFromColumnIndex($c).$row;
                $val = $celdas[$c - 1] ?? null;
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
    }

    private function fechaComoTextoDdMmYyyy(mixed $val): string
    {
        if ($val === null || $val === '') {
            return '';
        }
        try {
            return Carbon::parse((string) $val)->format('d/m/Y');
        } catch (\Throwable) {
            return (string) $val;
        }
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
        $dest = sys_get_temp_dir().DIRECTORY_SEPARATOR.'kardex_gen_tpl_'.uniqid('', true).'.xlsx';
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
