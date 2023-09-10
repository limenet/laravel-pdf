<?php

namespace Limenet\LaravelPdf\Adapters;

use Limenet\LaravelPdf\DTO\PdfConfig;

interface AdapterInterface
{
    public function make(
        PdfConfig $pdfConfig,
        string $viewRendered,
        string $headerViewRendered,
        string $footerViewRendered,
    ): string;
}
