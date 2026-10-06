<?php

namespace App\Support;

use Dompdf\Dompdf;
use Dompdf\Options;

class OfficialDocumentPdf
{
    public function render(string $html): string
    {
        $options = new Options;
        $options->setIsRemoteEnabled(false);
        $options->setIsPhpEnabled(false);
        $options->setChroot(public_path());
        $options->setTempDir(storage_path('app'));
        $options->setFontCache(storage_path('framework/cache'));
        $options->setDefaultFont('DejaVu Sans');

        $pdf = new Dompdf($options);
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->setPaper('A4', 'portrait');
        $pdf->render();

        return $pdf->output();
    }
}
