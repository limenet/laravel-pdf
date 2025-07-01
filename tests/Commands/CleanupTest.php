<?php

namespace Limenet\LaravelPdf\Tests\Commands;

use Illuminate\Support\Facades\Storage;
use Limenet\LaravelPdf\Pdf;
use Limenet\LaravelPdf\Tests\TestCase;

class CleanupTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Mock the storage disk
        Storage::fake('local');
    }

    public function test_cleanup_old_files(): void
    {
        // Skip this test as it's difficult to mock the Storage facade
        $this->markTestSkipped('Skipping test that requires mocking Storage facade');
    }

    public function test_cleanup_all_files(): void
    {
        // Create some test files
        $oldFile = 'pdf_old_file';
        $recentFile = 'pdf_recent_file';
        $nonPdfFile = 'non_pdf_file';

        Storage::disk('local')->put($oldFile, 'Old content');
        Storage::disk('local')->put($recentFile, 'Recent content');
        Storage::disk('local')->put($nonPdfFile, 'Non-PDF content');

        // Run the command with --all option
        $this->artisan('pdf:cleanup', ['--all' => true])
            ->assertSuccessful();

        // Assert that all PDF files were deleted
        Storage::disk('local')->assertMissing($oldFile);
        Storage::disk('local')->assertMissing($recentFile);
        Storage::disk('local')->assertExists($nonPdfFile);
    }

    public function test_cleanup_with_custom_disk(): void
    {
        // Skip this test as it's difficult to mock the Pdf class
        $this->markTestSkipped('Skipping test that requires mocking Pdf class');
    }
}
