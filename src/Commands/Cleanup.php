<?php

namespace Limenet\LaravelPdf\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Limenet\LaravelPdf\Pdf;

class Cleanup extends Command
{
    protected $signature = 'pdf:cleanup {--all}';

    protected $description = 'Cleans up temporary files used for PDF generation';

    public function handle(): int
    {
        collect(Pdf::getDisk()->allFiles())
            ->filter(fn (string $file): bool => Str::startsWith($file, 'pdf_'))
            ->filter(
                fn (string $file): bool => $this->option('all') === true
                || Pdf::getDisk()->lastModified($file) < (time() - 5 * 60)
            )
            ->each(fn (string $file): bool => Pdf::getDisk()->delete($file));

        return self::SUCCESS;
    }
}
