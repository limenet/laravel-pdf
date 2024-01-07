<?php

namespace Limenet\LaravelPdf;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\Response;
use Illuminate\Mail\Markdown;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Limenet\LaravelPdf\Adapters\BrowserlessAdapter;
use Limenet\LaravelPdf\Adapters\PuppeteerAdapter;
use Limenet\LaravelPdf\DTO\PdfConfig;
use RuntimeException;
use voku\helper\ASCII;

class Pdf
{
    private string $cacheKey;

    private PdfConfig $pdfConfig;

    /**
     * @param  view-string|null  $headerView
     * @param  view-string|null  $footerView
     */
    public function __construct(
        private readonly View $view,
        private ?string $filename = null,
        ?string $extraKey = null,
        private readonly ?string $headerView = null,
        private readonly array $headerData = [],
        private readonly ?string $footerView = null,
        private readonly array $footerData = [],
        private readonly ?int $cacheSeconds = null,
        string $format = 'A4',
        bool $landscape = false,
        string $marginTop = '1cm',
        string $marginRight = '1.5cm',
        string $marginBottom = '2.5cm',
        string $marginLeft = '1.5cm',
    ) {
        if (config('pdf.inline_assets')) {
            ViteInline::$isEnabled = true;
        }

        $this->filename = $filename ?: $view->getData()['title'] ?? Str::uuid();
        $this->cacheKey = sprintf('PDF_%s_%s_%s', $view->getName(), $this->filename, $extraKey);

        $this->pdfConfig = new PdfConfig(
            format: $format,
            landscape: $landscape,
            marginTop: $marginTop,
            marginRight: $marginRight,
            marginBottom: $marginBottom,
            marginLeft: $marginLeft,
        );
    }

    final public static function getDisk(): FilesystemAdapter
    {
        return Storage::disk('local');
    }

    public function response(): Response
    {
        return response(
            $this->generate(),
            200,
            array_filter([
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => $this->filename !== null
                    ? sprintf('inline; filename="%s.pdf"', ASCII::to_filename($this->filename))
                    : null,
            ])
        );
    }

    public function file(): string
    {
        return $this->generate() ?: throw new RuntimeException();
    }

    private function generate(): string
    {
        $strategy = match (config('pdf.strategy')) {
            'browserless' => app(BrowserlessAdapter::class)->make(...),
            default => app(PuppeteerAdapter::class)->make(...),
        };

        $generated = $this->isCached() && $this->getFile() !== null
            ? $this->getFile()
            : retry(2, fn (): string => $strategy(
                $this->pdfConfig,
                $this->htmlToDisk($this->view->render()),
                $this->htmlToDisk($this->snippet($this->headerView, $this->headerData)),
                $this->htmlToDisk($this->snippet($this->footerView, $this->footerData)),
            ), 500);

        $this->cachePdf($generated);

        return $generated;
    }

    private function htmlToDisk(string $contents): string
    {
        $hash = md5($contents);
        $file = sprintf('pdf_%s', $hash);

        self::getDisk()->put($file, $contents);

        return $file;
    }

    private function getFile(): ?string
    {
        return Cache::store('file')->get($this->cacheKey);
    }

    private function isCached(): bool
    {
        if ($this->cacheSeconds === null) {
            return false;
        }

        if (config('cache.default') === 'array') {
            Cache::store('file')->forget($this->cacheKey);
        }

        return Cache::store('file')->has($this->cacheKey);
    }

    private function cachePdf(string $contents): void
    {
        if ($this->cacheSeconds === null) {
            return;
        }

        Cache::store('file')
            ->remember(
                $this->cacheKey,
                now()->addSeconds($this->cacheSeconds),
                fn () => $contents
            );
    }

    /**
     * @param  view-string|null  $name
     */
    private function snippet(?string $name, array $data = []): string
    {
        if (empty($name)) {
            return '<span></span>';
        }

        $rendered = app(Markdown::class)->render($name, $data)->toHtml();
        $body = $this->extractBody($rendered);

        return $body;
    }

    private function extractBody(string $html): string
    {
        preg_match("/<body[^>]*>(.*?)<\/body>/is", $html, $matches);

        return $matches[1];
    }
}
