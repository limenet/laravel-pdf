<?php

namespace Limenet\LaravelPdf\Adapters;

use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Limenet\LaravelPdf\DTO\PdfConfig;
use Limenet\LaravelPdf\Pdf;
use Symfony\Component\Process\Process;
use Throwable;

class PuppeteerAdapter implements AdapterInterface, ConcurrencyLimiterInterface
{
    use ConcurrencyLimiterTrait;

    public function configPrefix(): string
    {
        return config('pdf.puppeteer');
    }

    public function make(
        PdfConfig $pdfConfig,
        string $viewRendered,
        string $headerViewRendered,
        string $footerViewRendered,
    ): string {
        $output = Pdf::getDisk()->path($viewRendered.'_pdf');

        $payload = [
            'node',
            'vendor/limenet/laravel-pdf/js/pdf.js',
            URL::temporarySignedRoute('pdf', now()->addHour(), ['key' => $viewRendered]),
            Pdf::getDisk()->path($headerViewRendered),
            Pdf::getDisk()->path($footerViewRendered),
            $output,
            $pdfConfig->format,
            $pdfConfig->landscape ? 'yes' : 'no',
            $pdfConfig->marginTop,
            $pdfConfig->marginRight,
            $pdfConfig->marginBottom,
            $pdfConfig->marginLeft,
        ];

        return $this->executeWithConcurrencyLimit(function () use ($output, $payload) {
            try {
                $process = new Process($payload);

                $process->setWorkingDirectory(base_path());
                $process->run();

                if (! $process->isSuccessful()) {
                    throw new Exception($process->getOutput());
                }

                if ($process->getExitCode() !== 0) {
                    Log::error('Could not find generated PDF', func_get_args());

                    throw new Exception('Could not find generated PDF');
                }

                $contents = file_get_contents($output);

                if ($contents === false) {
                    throw new Exception('Could not read generated PDF');
                }

                return $contents;
            } catch (Throwable $th) {
                if (isset($process)) {
                    Log::error('PDF generation failure', ['output' => $process->getOutput()]);
                    $process->stop();
                }
                report($th);
                throw new Exception('Failed to generate PDF', 0, $th);
            }
        });
    }
}
