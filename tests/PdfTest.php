<?php

namespace Limenet\LaravelPdf\Tests;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Illuminate\View\View as IlluminateView;
use Limenet\LaravelPdf\Adapters\BrowserlessAdapter;
use Limenet\LaravelPdf\Adapters\PuppeteerAdapter;
use Limenet\LaravelPdf\Adapters\ScreenlyAdapter;
use Limenet\LaravelPdf\Pdf;
use Mockery;

class PdfTest extends TestCase
{
    private IlluminateView $view;

    private string $pdfContent = 'PDF content';

    protected function setUp(): void
    {
        parent::setUp();

        // Mock the view
        $this->view = Mockery::mock(IlluminateView::class);
        $this->view->shouldReceive('getName')->andReturn('test.view');
        $this->view->shouldReceive('getData')->andReturn(['title' => 'Test PDF']);
        $this->view->shouldReceive('render')->andReturn('<html><body>Test content</body></html>');

        // Mock the storage disk
        Storage::fake('local');
    }

    public function test_response_method(): void
    {
        // Mock the adapter to return PDF content
        $this->mockAdapter(PuppeteerAdapter::class);

        // Create a PDF instance
        $pdf = new Pdf(
            view: $this->view,
            filename: 'test.pdf'
        );

        // Get the response
        $response = $pdf->response();

        // Assert the response
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/pdf', $response->headers->get('Content-Type'));
        $this->assertEquals('inline; filename="test.pdf.pdf"', $response->headers->get('Content-Disposition'));
        $this->assertEquals($this->pdfContent, $response->getContent());
    }

    public function test_file_method(): void
    {
        // Mock the adapter to return PDF content
        $this->mockAdapter(PuppeteerAdapter::class);

        // Create a PDF instance
        $pdf = new Pdf(
            view: $this->view,
            filename: 'test.pdf'
        );

        // Get the file content
        $content = $pdf->file();

        // Assert the content
        $this->assertEquals($this->pdfContent, $content);
    }

    public function test_caching(): void
    {
        // Mock the adapter to return PDF content
        $this->mockAdapter(PuppeteerAdapter::class, 1); // Should only be called once

        // Create a PDF instance with caching
        $pdf = new Pdf(
            view: $this->view,
            filename: 'test.pdf',
            cacheSeconds: 60
        );

        // Get the file content (first call)
        $content1 = $pdf->file();

        // Get the file content again (should use cache)
        $content2 = $pdf->file();

        // Assert the content
        $this->assertEquals($this->pdfContent, $content1);
        $this->assertEquals($this->pdfContent, $content2);

        // Assert the cache was used
        $this->assertTrue(Cache::store('file')->has('PDF_test.view_test.pdf_'));
    }

    public function test_browserless_strategy(): void
    {
        // Set the strategy to browserless
        config(['pdf.strategy' => 'browserless']);

        // Mock the adapter to return PDF content
        $this->mockAdapter(BrowserlessAdapter::class);

        // Create a PDF instance
        $pdf = new Pdf(
            view: $this->view,
            filename: 'test.pdf'
        );

        // Get the file content
        $content = $pdf->file();

        // Assert the content
        $this->assertEquals($this->pdfContent, $content);
    }

    public function test_screenly_strategy(): void
    {
        // Set the strategy to screenly
        config(['pdf.strategy' => 'screenly']);

        // Mock the adapter to return PDF content
        $this->mockAdapter(ScreenlyAdapter::class);

        // Create a PDF instance
        $pdf = new Pdf(
            view: $this->view,
            filename: 'test.pdf'
        );

        // Get the file content
        $content = $pdf->file();

        // Assert the content
        $this->assertEquals($this->pdfContent, $content);
    }

    public function test_custom_header_and_footer(): void
    {
        // Mock the adapter to return PDF content
        $this->mockAdapter(PuppeteerAdapter::class);

        // Mock the Markdown renderer
        $markdownMock = Mockery::mock('Illuminate\Mail\Markdown');
        $markdownMock->shouldReceive('render')
            ->with('header.view', ['header' => 'value'])
            ->andReturn((object) ['toHtml' => fn () => '<body>Header content</body>']);
        $markdownMock->shouldReceive('render')
            ->with('footer.view', ['footer' => 'value'])
            ->andReturn((object) ['toHtml' => fn () => '<body>Footer content</body>']);
        $this->app->instance('Illuminate\Mail\Markdown', $markdownMock);

        // Create a PDF instance with custom header and footer
        $pdf = new Pdf(
            view: $this->view,
            filename: 'test.pdf',
            headerView: 'header.view',
            headerData: ['header' => 'value'],
            footerView: 'footer.view',
            footerData: ['footer' => 'value']
        );

        // Get the file content
        $content = $pdf->file();

        // Assert the content
        $this->assertEquals($this->pdfContent, $content);
    }

    public function test_inline_assets(): void
    {
        // Enable inline assets
        config(['pdf.inline_assets' => true]);

        // Mock the adapter to return PDF content
        $this->mockAdapter(PuppeteerAdapter::class);

        // Create a PDF instance
        $pdf = new Pdf(
            view: $this->view,
            filename: 'test.pdf'
        );

        // Get the file content
        $content = $pdf->file();

        // Assert the content
        $this->assertEquals($this->pdfContent, $content);
        $this->assertTrue(\Limenet\LaravelPdf\ViteInline::$isEnabled);
    }

    /**
     * Mock an adapter to return PDF content.
     */
    private function mockAdapter(string $adapterClass, ?int $times = null): void
    {
        $adapterMock = Mockery::mock($adapterClass);

        $expectation = $adapterMock->shouldReceive('make')
            ->withArgs(function ($pdfConfig, $viewRendered, $headerViewRendered, $footerViewRendered) {
                return true; // Accept any arguments
            })
            ->andReturn($this->pdfContent);

        if ($times !== null) {
            $expectation->times($times);
        }

        $this->app->instance($adapterClass, $adapterMock);
    }
}
