<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use ZipArchive;

/**
 * Reduce plantillas SUNAT (.xlsx) antes de cargarlas con PhpSpreadsheet.
 * COMPRAS.xlsx oficial puede superar 75 MB en sheet1.xml y agotar RAM en hosting compartido.
 */
class XlsxSunatTemplatePreparer
{
    /**
     * Ruta lista para IOFactory::load: plantilla empaquetada, caché en storage o copia liviana nueva.
     */
    public function resolveForLoad(
        string $sourcePath,
        string $cacheKey,
        int $firstDataRow,
        int $maxCol,
        ?string $bundledRelative = null
    ): string {
        if (! is_readable($sourcePath)) {
            throw new \RuntimeException('Template no legible: '.$sourcePath);
        }

        $bundled = $bundledRelative !== null
            ? resource_path('templates/'.$bundledRelative)
            : resource_path('templates/'.$cacheKey.'_LITE.xlsx');
        if (is_readable($bundled)) {
            return $bundled;
        }

        $cacheDir = storage_path('app/contabilidad/lite_templates');
        if (! is_dir($cacheDir)) {
            @mkdir($cacheDir, 0755, true);
        }
        $cacheFile = $cacheDir.DIRECTORY_SEPARATOR.$cacheKey.'_lite.xlsx';
        $sourceMtime = @filemtime($sourcePath) ?: 0;
        if (is_readable($cacheFile) && (@filemtime($cacheFile) ?: 0) >= $sourceMtime) {
            return $cacheFile;
        }

        $needsHeavyPrune = $this->worksheetXmlExceedsThreshold($sourcePath, 2_000_000);
        if (! $needsHeavyPrune && is_readable($cacheFile)) {
            return $cacheFile;
        }

        $prevMem = (string) ini_get('memory_limit');
        @ini_set('memory_limit', (string) config('contabilidad.excel_build_memory_limit', '1024M'));
        try {
            $this->buildLiteCopy($sourcePath, $cacheFile, $firstDataRow, $maxCol);
        } finally {
            if ($prevMem !== '') {
                @ini_set('memory_limit', $prevMem);
            }
        }

        if (! is_readable($cacheFile)) {
            throw new \RuntimeException('No se pudo generar plantilla liviana: '.$cacheFile);
        }

        return $cacheFile;
    }

    public function buildLiteCopy(
        string $sourcePath,
        string $destPath,
        int $firstDataRow,
        int $maxCol
    ): void {
        if (! @copy($sourcePath, $destPath)) {
            throw new \RuntimeException('No se pudo copiar template a: '.$destPath);
        }

        $zip = new ZipArchive();
        if ($zip->open($destPath) !== true) {
            @unlink($destPath);
            throw new \RuntimeException('No se pudo abrir xlsx como ZIP: '.$destPath);
        }

        $dvPattern = '/<(?:[\w.-]+:)?dataValidations\b[^>]*\/>|<(?:[\w.-]+:)?dataValidations\b[^>]*>[\s\S]*?<\/(?:[\w.-]+:)?dataValidations>/';
        $lastHeaderRow = max(1, $firstDataRow - 1);
        $lastColLetter = Coordinate::stringFromColumnIndex(max(1, $maxCol));

        $toDelete = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);
            if (preg_match('#^xl/comments\d+\.xml$#i', $name)
                || preg_match('#^xl/drawings/#i', $name)
                || preg_match('#^xl/media/#i', $name)) {
                $toDelete[] = $name;
            }
        }
        foreach ($toDelete as $name) {
            $zip->deleteName($name);
        }

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
            $cleaned = $this->pruneWorksheetXml($cleaned, $firstDataRow, $maxCol);
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
    }

    private function worksheetXmlExceedsThreshold(string $xlsxPath, int $bytes): bool
    {
        $zip = new ZipArchive();
        if ($zip->open($xlsxPath) !== true) {
            return false;
        }
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);
            if (! preg_match('#^xl/worksheets/sheet\d+\.xml$#i', $name)) {
                continue;
            }
            $stat = $zip->statIndex($i);
            $zip->close();

            return ($stat['size'] ?? 0) > $bytes;
        }
        $zip->close();

        return false;
    }

    private function pruneWorksheetXml(string $xml, int $firstDataRow, int $maxCol): string
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
}
