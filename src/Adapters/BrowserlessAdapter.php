<?php

namespace Limenet\LaravelPdf\Adapters;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Limenet\LaravelPdf\DTO\PdfConfig;
use Limenet\LaravelPdf\Pdf;

class BrowserlessAdapter implements AdapterInterface
{
    public function make(
        PdfConfig $pdfConfig,
        string $viewRendered,
        string $headerViewRendered,
        string $footerViewRendered,
    ): string {
        $payload = [
            'options' => [
                'format' => $pdfConfig->format,
                'landscape' => $pdfConfig->landscape,
                'headerTemplate' => Pdf::getDisk()->path($headerViewRendered),
                'footerTemplate' => Pdf::getDisk()->path($footerViewRendered),
                'margin' => [
                    'top' => $pdfConfig->marginTop,
                    'right' => $pdfConfig->marginRight,
                    'bottom' => $pdfConfig->marginBottom,
                    'left' => $pdfConfig->marginLeft,
                ],
                'displayHeaderFooter' => true,
            ],
            'gotoOptions' => [
                'waitUntil' => 'networkidle2',
            ],
            'userAgent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/109.0.0.0 Safari/537.36',
        ];

        if (app()->environment('local')) {
            $payload['html'] = Pdf::getDisk()->get($viewRendered);
            $payload['options']['headerTemplate'] = Pdf::getDisk()->get($headerViewRendered);
            $payload['options']['footerTemplate'] = Pdf::getDisk()->get($footerViewRendered);
        } else {
            $payload['url'] = URL::temporarySignedRoute('pdf', now()->addHour(), ['key' => $viewRendered]);
            $payload['options']['headerTemplate'] = URL::temporarySignedRoute('pdf', now()->addHour(), ['key' => $headerViewRendered]);
            $payload['options']['footerTemplate'] = URL::temporarySignedRoute('pdf', now()->addHour(), ['key' => $footerViewRendered]);
        }

        $url = sprintf('https://chrome.browserless.io/pdf?token=%s', config('pdf.browserless.token'));

        $request = Http::post($url, $payload);

        if ($request->toException() !== null) {
            $th = $request->toException();
            report($th);
            throw new Exception('Failed to generate PDF', 0, $th);
        }

        return $request->body();
    }
}
