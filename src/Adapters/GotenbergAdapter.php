<?php

namespace Limenet\LaravelPdf\Adapters;

use Exception;
use Illuminate\Support\Facades\Http;
use Limenet\LaravelPdf\DTO\PdfConfig;
use Limenet\LaravelPdf\Pdf;

class GotenbergAdapter implements AdapterInterface, ConcurrencyLimiterInterface
{
    use ConcurrencyLimiterTrait;
    use ConfigTrait;

    private const float CM_TO_INCHES = 0.393701;

    /** @var array<string, array{float, float}> */
    private const array PAPER_SIZES = [
        'A3' => [11.69, 16.54],
        'A4' => [8.27, 11.69],
        'A5' => [5.83, 8.27],
        'Letter' => [8.5, 11.0],
        'Legal' => [8.5, 14.0],
        'Tabloid' => [11.0, 17.0],
    ];

    public function configPath(): string
    {
        return 'pdf.gotenberg';
    }

    public function make(
        PdfConfig $pdfConfig,
        string $viewRendered,
        string $headerViewRendered,
        string $footerViewRendered,
    ): string {
        $html = Pdf::getDisk()->get($viewRendered) ?? '';
        $headerHtml = Pdf::getDisk()->get($headerViewRendered) ?? '';
        $footerHtml = Pdf::getDisk()->get($footerViewRendered) ?? '';

        return $this->executeWithConcurrencyLimit(function () use ($html, $headerHtml, $footerHtml, $pdfConfig) {
            [$width, $height] = self::PAPER_SIZES[$pdfConfig->format] ?? self::PAPER_SIZES['A4'];

            if ($pdfConfig->landscape) {
                [$width, $height] = [$height, $width];
            }

            $response = Http::attach('files', $html, 'index.html')
                ->attach('files', $headerHtml, 'header.html')
                ->attach('files', $footerHtml, 'footer.html')
                ->post($this->adapterConfig('url').'/forms/chromium/convert/html', [
                    'paperWidth' => $width,
                    'paperHeight' => $height,
                    'marginTop' => $this->toInches($pdfConfig->marginTop),
                    'marginRight' => $this->toInches($pdfConfig->marginRight),
                    'marginBottom' => $this->toInches($pdfConfig->marginBottom),
                    'marginLeft' => $this->toInches($pdfConfig->marginLeft),
                    'landscape' => $pdfConfig->landscape ? 'true' : 'false',
                ]);

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
            $response = Http::timeout(3)->get($this->adapterConfig('url').'/health');

            return ! $response->serverError();
        } catch (Exception) {
            return false;
        }
    }

    private function toInches(string $value): float
    {
        return (float) preg_replace('/[^0-9.]/', '', $value) * self::CM_TO_INCHES;
    }
}
