<?php

namespace Limenet\LaravelPdf\Adapters;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Limenet\LaravelPdf\DTO\PdfConfig;
use Limenet\LaravelPdf\Pdf;

class ScreenlyAdapter implements AdapterInterface, ConcurrencyLimiterInterface
{
    use ConcurrencyLimiterTrait;

    public function configPrefix(): string
    {
        return config('pdf.screenly');
    }

    public function make(
        PdfConfig $pdfConfig,
        string $viewRendered,
        string $headerViewRendered,
        string $footerViewRendered,
    ): string {
        $convertToMm = (fn (string $value): string => (string) ((str($value)->before('cm')->numbers()->toInteger()) * 10));
        $payload =
            [
                'file_type' => 'pdf',
                'window_width' => 1440,
                'window_height' => 800,
                'timeout' => 10,
                'css_media_type' => 'print',
                'pdf_show_background' => true,
                'show_browser_header_and_footer' => true,
                'wait_until_network_idle' => true,
                'paper_format' => $pdfConfig->format,
                'paper_orientation' => $pdfConfig->landscape ? 'landscape' : 'portrait',
                'paper_margins_top' => $convertToMm(($pdfConfig->marginTop)),
                'paper_margins_right' => $convertToMm(($pdfConfig->marginRight)),
                'paper_margins_bottom' => $convertToMm(($pdfConfig->marginBottom)),
                'paper_margins_left' => $convertToMm(($pdfConfig->marginLeft)),
                'header_html' => Pdf::getDisk()->get($headerViewRendered),
                'footer_html' => Pdf::getDisk()->get($footerViewRendered),
            ];

        if (app()->environment('local')) {
            $payload['html'] = Pdf::getDisk()->get($viewRendered);
        } else {
            $payload['url'] = URL::temporarySignedRoute('pdf', now()->addHour(), ['key' => $viewRendered]);
        }

        return $this->executeWithConcurrencyLimit(function () use ($payload) {
            $request = Http::withToken(config($this->configPrefix().'.token'))
                ->post('https://3.screeenly.com/api/v1/shots', $payload);

            $url = $request->json('data.shot_url');

            if ($request->toException() !== null || $url === null) {
                $th = $request->toException();

                if ($th !== null) {
                    report($th);
                }

                throw new Exception('Failed to generate PDF', 0, $th);
            }

            return Http::get($url)->body();
        });
    }
}
