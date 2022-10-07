<?php

namespace Limenet\LaravelPdf;

use Limenet\LaravelPdf\Commands\Cleanup;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LaravelPdfServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('laravel-pdf')
            ->hasConfigFile()
            ->hasViews()
            ->hasCommand(Cleanup::class)
            ->hasRoute('web');
    }
}
