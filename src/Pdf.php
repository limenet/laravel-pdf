<?php

namespace Limenet\LaravelPdf;

use Exception;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\Response;
use Illuminate\Mail\Markdown;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;
use voku\helper\ASCII;

class Pdf
{
    private string $cacheKey;

    /**
     * @param  view-string|null  $headerView
     * @param  view-string|null  $footerView
     */
    public function __construct(
        private readonly View $view,
        private ?string $filename = null,
        private readonly ?string $extraKey = null,
        private readonly ?string $headerView = null,
        private readonly array $headerData = [],
        private readonly ?string $footerView = null,
        private readonly array $footerData = [],
        private readonly ?int $cacheSeconds = null,
    ) {
        $this->filename = $this->filename ?: $this->view->getData()['title'] ?? Str::uuid();
        $this->cacheKey = sprintf('PDF_%s_%s_%s', $this->view->getName(), $this->filename, $this->extraKey);
    }

    public static function getDisk(): FilesystemAdapter
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

    protected function generate(): string
    {
        return $this->isCached() && $this->getFile() !== null
            ? $this->getFile()
            : retry(2, fn (): string => $this->makeFresh(), 500);
    }

    protected function makeFresh(): string
    {
        $main = $this->htmlToDisk($this->view->render());
        $output = self::getDisk()->path($main.'_pdf');

        try {
            $process = new Process([
                'node',
                'vendor/limenet/laravel-pdf/js/pdf.js',
                URL::temporarySignedRoute('pdf', now()->addHour(), ['key' => $main]),
                self::getDisk()->path($this->snippet($this->headerView, $this->headerData)),
                self::getDisk()->path($this->snippet($this->footerView, $this->footerData)),
                $output,
            ]);

            $process->setWorkingDirectory(base_path());
            $process->run();

            if (! $process->isSuccessful()) {
                throw new Exception($process->getOutput());
            }

            if ($process->getExitCode() !== 0) {
                Log::error('Could not find generated PDF', func_get_args());

                throw new Exception('Could not find generated PDF');
            }

            $this->cachePdf($output);

            $contents = file_get_contents($output);
            if ($contents !== false) {
                return $contents;
            }

            throw new Exception('Could not read generated PDF');
        } catch (Throwable $th) {
            if (isset($process)) {
                Log::error('PDF generation failure', ['output' => $process->getOutput()]);
                $process->stop();
            }
            report($th);
            throw new Exception('Failed to generate PDF', 0, $th);
        }
    }

    /**
     * @param  view-string|null  $name
     */
    protected function snippet(?string $name, array $data = []): string
    {
        if (empty($name)) {
            return $this->htmlToDisk('<span></span>');
        }

        $rendered = app(Markdown::class)->render($name, $data)->toHtml();
        $body = $this->extractBody($rendered);

        return $this->htmlToDisk($body);
    }

    protected function extractBody(string $html): string
    {
        preg_match("/<body[^>]*>(.*?)<\/body>/is", $html, $matches);

        return $matches[1];
    }

    protected function htmlToDisk(string $contents): string
    {
        $hash = md5($contents);
        $file = sprintf('pdf_%s', $hash);

        self::getDisk()->put($file, $contents);

        return $file;
    }

    protected function isCached(): bool
    {
        if ($this->cacheSeconds === null) {
            return false;
        }

        if (config('cache.default') === 'array') {
            Cache::store('file')->forget($this->cacheKey);
        }

        return Cache::store('file')->has($this->cacheKey);
    }

    protected function cachePdf(string $path): void
    {
        if ($this->cacheSeconds === null) {
            return;
        }

        Cache::store('file')->remember($this->cacheKey, now()->addSeconds($this->cacheSeconds), fn () => file_get_contents($path));
    }

    protected function getFile(): ?string
    {
        return Cache::store('file')->get($this->cacheKey);
    }
}
