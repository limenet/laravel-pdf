<?php

namespace Limenet\LaravelPdf;

use Exception;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\Response;
use Illuminate\Mail\Markdown;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
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
        private readonly string $format = 'A4',
        private readonly bool $landscape = false,
        private readonly string $marginTop = '1cm',
        private readonly string $marginRight = '1.5cm',
        private readonly string $marginBottom = '2.5cm',
        private readonly string $marginLeft = '1.5cm',
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
        $strategy = match (config('pdf.strategy')) {
            'browserless' => $this->makeFreshBrowserless(...),
            default => $this->makeFreshLocal(...)
        };

        return $this->isCached() && $this->getFile() !== null
            ? $this->getFile()
            : retry(2, fn (): string => $strategy(), 500);
    }

    protected function makeFreshLocal(): string
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
                $this->format,
                $this->landscape ? 'yes' : 'no',
                $this->marginTop,
                $this->marginRight,
                $this->marginBottom,
                $this->marginLeft,
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

    protected function makeFreshBrowserless(): string
    {
        $rendered = $this->view->render();
        $main = $this->htmlToDisk($rendered);

        $payload = [
            'options' => [
                'format' => $this->format,
                'landscape' => $this->landscape,
                'headerTemplate' => self::getDisk()->path($this->snippet($this->headerView, $this->headerData)),
                'footerTemplate' => self::getDisk()->path($this->snippet($this->footerView, $this->footerData)),
                'margin' => [
                    'top' => $this->marginTop,
                    'right' => $this->marginRight,
                    'bottom' => $this->marginBottom,
                    'left' => $this->marginLeft,
                ],
            ],
            'gotoOptions' => [
                'waitUntil' => 'networkidle2',
            ],
            'userAgent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/109.0.0.0 Safari/537.36',
        ];

        if (app()->environment('local')) {
            $payload['html'] = $rendered;
        } else {
            $payload['url'] = URL::temporarySignedRoute('pdf', now()->addHour(), ['key' => $main]);
        }

        $url = sprintf('https://chrome.browserless.io/pdf?token=%s', config('pdf.browserless.token'));

        $request = Http::post($url, $payload);

        if ($request->toException() !== null) {
            $th = $request->toException();
            report($th);
            throw new Exception('Failed to generate PDF', 0, $th);
        }

        $this->cachePdf($request->body());

        return $request->body();
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
