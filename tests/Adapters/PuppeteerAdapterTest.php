<?php

namespace Limenet\LaravelPdf\Tests\Adapters;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Limenet\LaravelPdf\Adapters\PuppeteerAdapter;
use Limenet\LaravelPdf\DTO\PdfConfig;
use Limenet\LaravelPdf\Tests\TestCase;
use Mockery;
use Symfony\Component\Process\Process;

class PuppeteerAdapterTest extends TestCase
{
    private PuppeteerAdapter $adapter;

    private PdfConfig $pdfConfig;

    private string $viewRendered = 'test_view';

    private string $headerViewRendered = 'test_header';

    private string $footerViewRendered = 'test_footer';

    private string $pdfContent = 'PDF content';

    protected function setUp(): void
    {
        parent::setUp();

        $this->adapter = new PuppeteerAdapter;
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
    }

    public function test_make_successful(): void
    {
        // Mock the URL facade
        URL::shouldReceive('temporarySignedRoute')
            ->once()
            ->with('pdf', Mockery::any(), ['key' => $this->viewRendered])
            ->andReturn('https://example.com/pdf');

        // Mock the Process class
        $processMock = Mockery::mock(Process::class);
        $processMock->shouldReceive('setWorkingDirectory')->once()->with(base_path())->andReturnSelf();
        $processMock->shouldReceive('run')->once();
        $processMock->shouldReceive('isSuccessful')->once()->andReturn(true);
        $processMock->shouldReceive('getExitCode')->once()->andReturn(0);

        // Mock the file_get_contents function
        $this->mockFileGetContents($this->viewRendered.'_pdf', $this->pdfContent);

        // Replace the Process class with our mock
        $this->mockProcess($processMock);

        // Call the adapter
        $result = $this->adapter->make(
            $this->pdfConfig,
            $this->viewRendered,
            $this->headerViewRendered,
            $this->footerViewRendered
        );

        // Assert the result
        $this->assertEquals($this->pdfContent, $result);
    }

    public function test_make_process_failed(): void
    {
        // Mock the URL facade
        URL::shouldReceive('temporarySignedRoute')
            ->once()
            ->with('pdf', Mockery::any(), ['key' => $this->viewRendered])
            ->andReturn('https://example.com/pdf');

        // Mock the Process class
        $processMock = Mockery::mock(Process::class);
        $processMock->shouldReceive('setWorkingDirectory')->once()->with(base_path())->andReturnSelf();
        $processMock->shouldReceive('run')->once();
        $processMock->shouldReceive('isSuccessful')->once()->andReturn(false);
        $processMock->shouldReceive('getOutput')->once()->andReturn('Process failed');
        $processMock->shouldReceive('stop')->once();

        // Replace the Process class with our mock
        $this->mockProcess($processMock);

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

    public function test_make_exit_code_non_zero(): void
    {
        // Mock the URL facade
        URL::shouldReceive('temporarySignedRoute')
            ->once()
            ->with('pdf', Mockery::any(), ['key' => $this->viewRendered])
            ->andReturn('https://example.com/pdf');

        // Mock the Process class
        $processMock = Mockery::mock(Process::class);
        $processMock->shouldReceive('setWorkingDirectory')->once()->with(base_path())->andReturnSelf();
        $processMock->shouldReceive('run')->once();
        $processMock->shouldReceive('isSuccessful')->once()->andReturn(true);
        $processMock->shouldReceive('getExitCode')->once()->andReturn(1);
        $processMock->shouldReceive('getOutput')->once()->andReturn('Exit code non-zero');
        $processMock->shouldReceive('stop')->once();

        // Replace the Process class with our mock
        $this->mockProcess($processMock);

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

    public function test_make_file_get_contents_failed(): void
    {
        // Mock the URL facade
        URL::shouldReceive('temporarySignedRoute')
            ->once()
            ->with('pdf', Mockery::any(), ['key' => $this->viewRendered])
            ->andReturn('https://example.com/pdf');

        // Mock the Process class
        $processMock = Mockery::mock(Process::class);
        $processMock->shouldReceive('setWorkingDirectory')->once()->with(base_path())->andReturnSelf();
        $processMock->shouldReceive('run')->once();
        $processMock->shouldReceive('isSuccessful')->once()->andReturn(true);
        $processMock->shouldReceive('getExitCode')->once()->andReturn(0);
        $processMock->shouldReceive('stop')->once();

        // Mock the file_get_contents function to return false
        $this->mockFileGetContents($this->viewRendered.'_pdf', false);

        // Replace the Process class with our mock
        $this->mockProcess($processMock);

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

    /**
     * Mock the Process class constructor.
     */
    private function mockProcess($processMock): void
    {
        // Use a test double for the Process class
        $this->app->bind(Process::class, function () use ($processMock) {
            return $processMock;
        });
    }

    /**
     * Mock the file_get_contents function.
     */
    private function mockFileGetContents(string $path, $returnValue): void
    {
        // Create a mock for the file_get_contents function
        $this->app->bind('file_get_contents', function () use ($path, $returnValue) {
            return function ($filePath) use ($path, $returnValue) {
                if ($filePath === $path) {
                    return $returnValue;
                }

                return \file_get_contents($filePath);
            };
        });
    }
}
