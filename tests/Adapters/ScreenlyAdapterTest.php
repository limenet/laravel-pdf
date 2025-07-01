<?php

namespace Limenet\LaravelPdf\Tests\Adapters;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Limenet\LaravelPdf\Adapters\ScreenlyAdapter;
use Limenet\LaravelPdf\DTO\PdfConfig;
use Limenet\LaravelPdf\Pdf;
use Limenet\LaravelPdf\Tests\TestCase;

class ScreenlyAdapterTest extends TestCase
{
    private ScreenlyAdapter $adapter;

    private PdfConfig $pdfConfig;

    private string $viewRendered = 'test_view';

    private string $headerViewRendered = 'test_header';

    private string $footerViewRendered = 'test_footer';

    private string $pdfContent = 'PDF content';

    private string $shotUrl = 'https://screenly.com/shot/123';

    protected function setUp(): void
    {
        parent::setUp();

        $this->adapter = new ScreenlyAdapter;
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
        config(['pdf.screenly.token' => 'test-token']);
    }

    public function test_make_in_local_environment(): void
    {
        // Set environment to local
        $this->app->environment('local');

        // Mock the HTTP client
        Http::fake([
            'https://3.screeenly.com/api/v1/shots' => Http::response(['data' => ['shot_url' => $this->shotUrl]], 200),
            $this->shotUrl => Http::response($this->pdfContent, 200),
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
            if ($request->url() !== 'https://3.screeenly.com/api/v1/shots') {
                return false;
            }

            // Check the Authorization header (withToken adds 'Bearer ' prefix)
            if (! $request->hasHeader('Authorization')) {
                return false;
            }

            $authHeader = $request->header('Authorization')[0];
            if ($authHeader !== 'Bearer test-token') {
                return false;
            }

            // Check the payload
            return isset($request['html'])
                && isset($request['header_html'])
                && isset($request['footer_html'])
                && $request['paper_format'] === 'A4'
                && $request['paper_orientation'] === 'portrait'
                && $request['paper_margins_top'] === '10'
                && $request['paper_margins_right'] === '15'
                && $request['paper_margins_bottom'] === '25'
                && $request['paper_margins_left'] === '15';
        });

        // Assert the second HTTP request to get the PDF
        Http::assertSent(function ($request) {
            // Check if this is the request to get the PDF
            return $request->url() === $this->shotUrl && $request->method() === 'GET';
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
            'https://3.screeenly.com/api/v1/shots' => Http::response(['data' => ['shot_url' => $this->shotUrl]], 200),
            $this->shotUrl => Http::response($this->pdfContent, 200),
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
            if ($request->url() !== 'https://3.screeenly.com/api/v1/shots') {
                return false;
            }

            // Check the Authorization header (withToken adds 'Bearer ' prefix)
            if (! $request->hasHeader('Authorization')) {
                return false;
            }

            $authHeader = $request->header('Authorization')[0];
            if ($authHeader !== 'Bearer test-token') {
                return false;
            }

            // Check the payload
            return isset($request['url'])
                && isset($request['header_html'])
                && isset($request['footer_html'])
                && $request['paper_format'] === 'A4'
                && $request['paper_orientation'] === 'portrait';
        });

        // Assert the second HTTP request to get the PDF
        Http::assertSent(function ($request) {
            // Check if this is the request to get the PDF
            return $request->url() === $this->shotUrl && $request->method() === 'GET';
        });
    }

    public function test_make_with_http_error(): void
    {
        // Mock the HTTP client to return an error
        Http::fake([
            'https://3.screeenly.com/api/v1/shots' => Http::response('Error', 500),
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

    public function test_make_with_missing_shot_url(): void
    {
        // Mock the HTTP client to return a response without a shot_url
        Http::fake([
            'https://3.screeenly.com/api/v1/shots' => Http::response(['data' => []], 200),
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
