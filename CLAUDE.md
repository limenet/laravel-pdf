# laravel-pdf — Developer Guide

## Adding a New Adapter

### 1. Create the adapter class

Create `src/Adapters/YournameAdapter.php`. Every adapter must:

- Implement `AdapterInterface` and `ConcurrencyLimiterInterface`
- Use `ConcurrencyLimiterTrait` and `ConfigTrait`

```php
<?php

namespace Limenet\LaravelPdf\Adapters;

use Exception;
use Illuminate\Support\Facades\Http;
use Limenet\LaravelPdf\DTO\PdfConfig;
use Limenet\LaravelPdf\Pdf;

class YournameAdapter implements AdapterInterface, ConcurrencyLimiterInterface
{
    use ConcurrencyLimiterTrait;
    use ConfigTrait;

    public function configPath(): string
    {
        return 'pdf.yourname';
    }

    public function make(
        PdfConfig $pdfConfig,
        string $viewRendered,
        string $headerViewRendered,
        string $footerViewRendered,
    ): string {
        // Read HTML from disk — parameters are file keys, not HTML strings
        $html = Pdf::getDisk()->get($viewRendered);
        $headerHtml = Pdf::getDisk()->get($headerViewRendered);
        $footerHtml = Pdf::getDisk()->get($footerViewRendered);

        return $this->executeWithConcurrencyLimit(function () use ($html, $headerHtml, $footerHtml, $pdfConfig) {
            $response = Http::post('https://api.yourservice.com/pdf', [
                'html' => $html,
                // map $pdfConfig->format, $pdfConfig->landscape, margins, etc.
            ]);

            if ($response->failed() || $response->body() === '') {
                $th = $response->toException();

                if ($th !== null) {
                    report($th);
                }

                throw new Exception('Failed to generate PDF', 0, $th);
            }

            return $response->body(); // raw PDF binary
        });
    }
}
```

**`PdfConfig` fields available:**

| Field | Type | Example |
|---|---|---|
| `format` | `string` | `'A4'`, `'Letter'` |
| `landscape` | `bool` | `true` / `false` |
| `marginTop` | `string` | `'1cm'` |
| `marginRight` | `string` | `'1.5cm'` |
| `marginBottom` | `string` | `'2.5cm'` |
| `marginLeft` | `string` | `'1.5cm'` |

**Notes:**
- `$viewRendered`, `$headerViewRendered`, `$footerViewRendered` are file keys (e.g. `pdf_abc123`). Read them with `Pdf::getDisk()->get($key)`.
- Header/footer HTML: most cloud APIs do not support native header/footer templates. Concatenate them into the main content string if needed.
- Margin values are CSS strings (`"1cm"`). Convert if the API expects different units (e.g. Screenly needs mm: multiply by 10).
- Always wrap the API call in `$this->executeWithConcurrencyLimit()`.

### 2. Register the config

Add a section to `config/pdf.php`:

```php
// "local", "browserless", "screenly", "tailpdf", "yourname"
'strategy' => env('PDF_STRATEGY', 'local'),

'yourname' => [
    'token' => env('YOURNAME_TOKEN'),
    'concurrency_limit' => env('YOURNAME_CONCURRENCY_LIMIT', 5),
],
```

### 3. Register the strategy

In `src/Pdf.php`, add the import and a case to the `match`:

```php
use Limenet\LaravelPdf\Adapters\YournameAdapter;

$strategy = match (config('pdf.strategy')) {
    'browserless' => app(BrowserlessAdapter::class)->make(...),
    'screenly'    => app(ScreenlyAdapter::class)->make(...),
    'tailpdf'     => app(TailpdfAdapter::class)->make(...),
    'yourname'    => app(YournameAdapter::class)->make(...),
    default       => app(PuppeteerAdapter::class)->make(...),
};
```

### 4. Verify

```bash
# Set in .env:
PDF_STRATEGY=yourname
YOURNAME_TOKEN=your-api-key

# Run linting and tests:
ddev composer run ci-lint
ddev composer run test
```
