<?php

namespace Limenet\LaravelPdf\Adapters;

use Exception;
use Illuminate\Support\Facades\Http;
use Limenet\LaravelPdf\DTO\PdfConfig;
use Limenet\LaravelPdf\Pdf;

class TailpdfAdapter implements AdapterInterface, ConcurrencyLimiterInterface
{
    use ConcurrencyLimiterTrait;
    use ConfigTrait;

    public function configPath(): string
    {
        return 'pdf.tailpdf';
    }

    public function make(
        PdfConfig $pdfConfig,
        string $viewRendered,
        string $headerViewRendered,
        string $footerViewRendered,
    ): string {
        $content = Pdf::getDisk()->get($headerViewRendered)
            .Pdf::getDisk()->get($viewRendered)
            .Pdf::getDisk()->get($footerViewRendered);

        $payload = [
            'content' => $content,
            'pdfOptions' => [
                'format' => strtolower($pdfConfig->format),
                'landscape' => $pdfConfig->landscape,
                'printBackground' => true,
                'margin' => [
                    'top' => $pdfConfig->marginTop,
                    'right' => $pdfConfig->marginRight,
                    'bottom' => $pdfConfig->marginBottom,
                    'left' => $pdfConfig->marginLeft,
                ],
            ],
        ];

        return $this->executeWithConcurrencyLimit(function () use ($payload) {
            $response = Http::withHeader('X-API-Key', $this->adapterConfig('token'))
                ->post('https://api.tailpdf.com/pdf', $payload);

            if ($response->failed() || $response->body() === '') {
                $th = $response->toException();

                if ($th !== null) {
                    report($th);
                }

                throw new Exception('Failed to generate PDF', 0, $th);
            }

            return $response->body();
        });
    }

    public function isAlive(): bool
    {
        try {
            $response = Http::timeout(3)->get('https://api.tailpdf.com/');

            return ! $response->serverError();
        } catch (Exception) {
            return false;
        }
    }
}
