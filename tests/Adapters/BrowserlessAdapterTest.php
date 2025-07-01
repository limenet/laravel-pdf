<?php

namespace Limenet\LaravelPdf\Tests\Adapters;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Limenet\LaravelPdf\Adapters\BrowserlessAdapter;
use Limenet\LaravelPdf\DTO\PdfConfig;
use Limenet\LaravelPdf\Tests\TestCase;

class BrowserlessAdapterTest extends TestCase
{
    private BrowserlessAdapter $adapter;

    private PdfConfig $pdfConfig;

    private string $viewRendered = 'test_view';

    private string $headerViewRendered = 'test_header';

    private string $footerViewRendered = 'test_footer';

    private string $pdfContent = 'PDF content';

    protected function setUp(): void
    {
        parent::setUp();

        $this->adapter = new BrowserlessAdapter;
        $this->pdfConfig = new PdfConfig(
            format: 'A4',
            landscape: false,
            marginTop: '1cm',
            marginRight: '1.5cm',
            marginBottom: '2.5cm',
            marginLeft: '1.5cm',
        );

        // Mock the storage disk
        Storage::fake('local');
        Storage::disk('local')->put($this->viewRendered, 'View content');
        Storage::disk('local')->put($this->headerViewRendered, 'Header content');
        Storage::disk('local')->put($this->footerViewRendered, 'Footer content');

        // Configure the test environment
        config(['pdf.browserless.token' => 'test-token']);
        config(['pdf.browserless.endpoint' => 'chrome']);
    }

    public function test_make_in_local_environment(): void
    {
        // Set environment to local
        $this->app->environment('local');

        // Mock the HTTP client
        Http::fake([
            'https://chrome.browserless.io/pdf?token=*' => Http::response($this->pdfContent, 200),
        ]);

        // Call the adapter
        $result = $this->adapter->make(
            $this->pdfConfig,
            $this->viewRendered,
            $this->headerViewRendered,
            $this->footerViewRendered
        );

        // Assert the result
        $this->assertEquals($this->pdfContent, $result);

        // Assert the HTTP request was made with the correct payload
        Http::assertSent(function ($request) {
            // Check the URL
            if ($request->url() !== 'https://chrome.browserless.io/pdf?token=test-token') {
                return false;
            }

            // Check the Content-Type header
            if (! $request->hasHeader('Content-Type', 'application/json')) {
                return false;
            }

            // Check the payload
            return isset($request['html'])
                && isset($request['options']['headerTemplate'])
                && isset($request['options']['footerTemplate'])
                && $request['options']['format'] === 'A4'
                && $request['options']['landscape'] === false
                && $request['options']['margin']['top'] === '1cm'
                && $request['options']['margin']['right'] === '1.5cm'
                && $request['options']['margin']['bottom'] === '2.5cm'
                && $request['options']['margin']['left'] === '1.5cm';
        });
    }

    public function test_make_in_production_environment(): void
    {
        // Set environment to production
        $this->app->environment('production');

        // Mock the URL facade
        URL::shouldReceive('temporarySignedRoute')
            ->once()
            ->with('pdf', \Mockery::any(), ['key' => $this->viewRendered])
            ->andReturn('https://example.com/pdf');

        // Mock the HTTP client
        Http::fake([
            'https://chrome.browserless.io/pdf?token=*' => Http::response($this->pdfContent, 200),
        ]);

        // Call the adapter
        $result = $this->adapter->make(
            $this->pdfConfig,
            $this->viewRendered,
            $this->headerViewRendered,
            $this->footerViewRendered
        );

        // Assert the result
        $this->assertEquals($this->pdfContent, $result);

        // Assert the HTTP request was made with the correct payload
        Http::assertSent(function ($request) {
            // Check the URL
            if ($request->url() !== 'https://chrome.browserless.io/pdf?token=test-token') {
                return false;
            }

            // Check the Content-Type header
            if (! $request->hasHeader('Content-Type', 'application/json')) {
                return false;
            }

            // Check the payload
            return isset($request['url'])
                && isset($request['options']['headerTemplate'])
                && isset($request['options']['footerTemplate'])
                && $request['options']['format'] === 'A4'
                && $request['options']['landscape'] === false;
        });
    }

    public function test_make_with_http_error(): void
    {
        // Mock the HTTP client to return an error
        Http::fake([
            'https://chrome.browserless.io/pdf?token=*' => Http::response('Error', 500),
        ]);

        // Expect an exception
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Failed to generate PDF');

        // Call the adapter
        $this->adapter->make(
            $this->pdfConfig,
            $this->viewRendered,
            $this->headerViewRendered,
            $this->footerViewRendered
        );
    }
}
