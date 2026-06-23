<?php

/**
 * Reportes contables / SUNAT (RCE compras, RVIE ventas).
 */
return [

    /*
    | Ruta al template COMPRAS.xlsx (PROPUESTA REGISTRO DE COMPRAS ELECTRÓNICO).
    | Por defecto: raíz del proyecto backend (misma carpeta que artisan, composer.json).
    | Suele coincidir con «COMPRAS MODELO.xlsx» de SUNAT (p. ej. carpeta CODIGO FUENTE JOVITA).
    | Opcional en .env: RCE_COMPRAS_TEMPLATE=/ruta/absoluta/COMPRAS.xlsx
    */
    'rce_compras_template' => env(
        'RCE_COMPRAS_TEMPLATE',
        base_path('COMPRAS.xlsx')
    ),

    /*
    | Fila 1-based donde empieza la primera línea de datos (debajo de cabeceras del template COMPRAS).
    */
    'rce_compras_first_data_row' => (int) env('RCE_COMPRAS_FIRST_DATA_ROW', 9),

    /*
    | Índice 0-based de la hoja donde se escriben los datos (primera pestaña = 0).
    */
    'rce_compras_sheet_index' => (int) env('RCE_COMPRAS_SHEET_INDEX', 0),

    /*
    | Tiempo máximo (segundos) para cargar plantilla + escribir Excel (0 = sin cambiar límite PHP).
    */
    'rce_compras_max_execution_seconds' => (int) env('RCE_COMPRAS_MAX_EXECUTION_SECONDS', 180),

    'rce_compras_memory_limit' => env('RCE_COMPRAS_MEMORY_LIMIT', '512M'),

    /*
    | Tasa % IGV a informar en la columna correspondiente del libro RCE compras (p. ej. 18).
    */
    'rce_compras_igv_pct' => env('RCE_COMPRAS_IGV_PCT', '18'),

    /*
    | Dígitos del correlativo dentro del código CAR (relleno izquierdo con ceros).
    */
    'rce_car_numero_digitos' => (int) env('RCE_CAR_NUMERO_DIGITOS', 12),

    /*
    | Template VENTAS.xlsx (PROPUESTA REGISTRO DE VENTAS ELECTRÓNICO - RVIE SIRE SUNAT).
    | Opcional: RVIE_VENTAS_TEMPLATE=/ruta/absoluta/VENTAS.xlsx
    */
    'rvie_ventas_template' => env(
        'RVIE_VENTAS_TEMPLATE',
        base_path('VENTAS.xlsx')
    ),

    'rvie_ventas_first_data_row' => (int) env('RVIE_VENTAS_FIRST_DATA_ROW', 7),

    'rvie_ventas_sheet_index' => (int) env('RVIE_VENTAS_SHEET_INDEX', 0),

    /*
    | Template INVENTARIO VALORIZADO SUNAT.xlsx (kardex valorizado por producto).
    | Opcional: INVENTARIO_VALORIZADO_TEMPLATE=/ruta/absoluta/INVENTARIO VALORIZADO SUNAT.xlsx
    */
    'inventario_valorizado_template' => env(
        'INVENTARIO_VALORIZADO_TEMPLATE',
        base_path('INVENTARIO VALORIZADO SUNAT.xlsx')
    ),

    'inventario_valorizado_first_data_row' => (int) env('INVENTARIO_VALORIZADO_FIRST_DATA_ROW', 6),

    'inventario_valorizado_sheet_index' => (int) env('INVENTARIO_VALORIZADO_SHEET_INDEX', 0),

    /*
    | Tiempo y memoria para listado/Excel de inventario valorizado (muchos productos).
    */
    'inventario_valorizado_max_execution_seconds' => (int) env('INVENTARIO_VALORIZADO_MAX_EXECUTION_SECONDS', 300),

    'inventario_valorizado_memory_limit' => env('INVENTARIO_VALORIZADO_MEMORY_LIMIT', '512M'),

    'inventario_valorizado_bulk_chunk' => (int) env('INVENTARIO_VALORIZADO_BULK_CHUNK', 250),

    /*
    | Máximo de productos con kardex completo (una hoja plantilla por producto) en Excel.
    | Por encima de este límite se genera un Excel resumen (una fila por producto).
    */
    'inventario_valorizado_excel_max_productos_detalle' => (int) env(
        'INVENTARIO_VALORIZADO_EXCEL_MAX_PRODUCTOS_DETALLE',
        25
    ),

    'inventario_valorizado_per_page_default' => (int) env('INVENTARIO_VALORIZADO_PER_PAGE_DEFAULT', 100),

    'inventario_valorizado_per_page_max' => (int) env('INVENTARIO_VALORIZADO_PER_PAGE_MAX', 500),

    /*
    | Template KARDEX GENERAL.xlsx (hojas CABECERA fila 4, DETALLE fila 2).
    */
    'kardex_general_template' => env(
        'KARDEX_GENERAL_TEMPLATE',
        base_path('KARDEX GENERAL.xlsx')
    ),

    'kardex_general_cabecera_header_row' => (int) env('KARDEX_GENERAL_CABECERA_HEADER_ROW', 3),

    'kardex_general_cabecera_first_data_row' => (int) env('KARDEX_GENERAL_CABECERA_FIRST_DATA_ROW', 4),

    'kardex_general_detalle_header_row' => (int) env('KARDEX_GENERAL_DETALLE_HEADER_ROW', 1),

    'kardex_general_detalle_first_data_row' => (int) env('KARDEX_GENERAL_DETALLE_FIRST_DATA_ROW', 2),

    'kardex_general_per_page_default' => (int) env('KARDEX_GENERAL_PER_PAGE_DEFAULT', 100),

    'kardex_general_per_page_max' => (int) env('KARDEX_GENERAL_PER_PAGE_MAX', 500),

];
