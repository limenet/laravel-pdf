<?php

namespace Limenet\LaravelPdf\Adapters;

use Exception;
use Illuminate\Support\Facades\Cache;
use Limenet\LaravelPdf\DTO\PdfConfig;
use Limenet\LaravelPdf\Pdf;
use RuntimeException;

class MultiAdapter implements AdapterInterface
{
    use ConfigTrait;

    public function configPath(): string
    {
        return 'pdf.multi';
    }

    public function make(
        PdfConfig $pdfConfig,
        string $viewRendered,
        string $headerViewRendered,
        string $footerViewRendered,
    ): string {
        $adapterNames = $this->adapterConfig('adapters', []);
        $tried = [];

        foreach ($adapterNames as $name) {
            $adapter = Pdf::resolveAdapter($name);
            $ttl = (int) $this->adapterConfig('liveness_ttl', 300);
            $cacheKey = "pdf_liveness_{$name}";

            $isAlive = Cache::get($cacheKey);

            if ($isAlive === null) {
                $isAlive = $adapter->isAlive();
                Cache::put($cacheKey, $isAlive, $ttl);
            }

            if (! $isAlive) {
                $tried[] = $name.' (down)';

                continue;
            }

            try {
                return $adapter->make($pdfConfig, $viewRendered, $headerViewRendered, $footerViewRendered);
            } catch (Exception $e) {
                Cache::put($cacheKey, false, $ttl);
                report($e);
                $tried[] = $name.' (failed)';
            }
        }

        throw new RuntimeException('No PDF adapter available. Tried: '.implode(', ', $tried));
    }

    public function isAlive(): bool
    {
        foreach ($this->adapterConfig('adapters', []) as $name) {
            if (Pdf::resolveAdapter($name)->isAlive()) {
                return true;
            }
        }

        return false;
    }
}
