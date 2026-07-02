<?php

namespace App\Services;

/**
 * Ajustes al PDF devuelto por la OSE cuando no se genera representación local.
 */
class EfactPdfPostProcessor
{
    public function personalizarContenidoPdf(string $pdfBinario): string
    {
        $urlOse = trim((string) config('services.efact.web_url', 'www.efact.pe'));
        $urlOse = preg_replace('#^https?://#i', '', $urlOse) ?: 'www.efact.pe';
        $urlOsePadded = str_pad(substr($urlOse, 0, 15), 15, ' ', STR_PAD_RIGHT);

        $reemplazos = [
            'www.sunat.gob.pe' => $urlOsePadded,
            'sunat.gob.pe' => str_pad(substr($urlOse, 0, 12), 12, ' ', STR_PAD_RIGHT),
            '(www.sunat.gob.pe)' => '(' . trim($urlOsePadded) . ')',
        ];

        $pdf = str_replace(array_keys($reemplazos), array_values($reemplazos), $pdfBinario);

        return $this->reemplazarEnStreamsComprimidos($pdf, [
            'www.sunat.gob.pe' => $urlOsePadded,
            'sunat.gob.pe' => str_pad(substr($urlOse, 0, 12), 12, ' ', STR_PAD_RIGHT),
        ]);
    }

    /**
     * @param  array<string, string>  $reemplazos
     */
    private function reemplazarEnStreamsComprimidos(string $pdf, array $reemplazos): string
    {
        return (string) preg_replace_callback(
            '/(\d+) 0 obj\s*<<([^>]*)>>\s*stream\r?\n(.*?)\r?\nendstream/s',
            function (array $matches) use ($reemplazos) {
                $dict = $matches[2];
                $streamRaw = $matches[3];
                $decoded = $this->descomprimirStreamPdf($streamRaw);
                if ($decoded === null) {
                    return $matches[0];
                }

                $modificado = $decoded;
                foreach ($reemplazos as $buscar => $reemplazar) {
                    if (strlen($buscar) === strlen($reemplazar)) {
                        $modificado = str_replace($buscar, $reemplazar, $modificado);
                    }
                }

                if ($modificado === $decoded) {
                    return $matches[0];
                }

                $recomprimido = gzcompress($modificado);
                if ($recomprimido === false) {
                    return $matches[0];
                }

                $nuevoDict = preg_replace(
                    '/\/Length\s+\d+/',
                    '/Length ' . strlen($recomprimido),
                    $dict,
                    1
                ) ?? $dict;

                return $matches[1] . " 0 obj\n<<" . $nuevoDict . ">>\nstream\n" . $recomprimido . "\nendstream";
            },
            $pdf
        );
    }

    private function descomprimirStreamPdf(string $streamRaw): ?string
    {
        $intentos = [
            static fn (string $s) => @gzuncompress($s),
            static fn (string $s) => @gzinflate($s),
            static fn (string $s) => @gzinflate(substr($s, 2, -4)),
        ];

        foreach ($intentos as $intentar) {
            $decoded = $intentar($streamRaw);
            if (is_string($decoded) && $decoded !== '') {
                return $decoded;
            }
        }

        return null;
    }
}
