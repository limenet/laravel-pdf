<?php

namespace Limenet\LaravelPdf;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Foundation\Vite;
use Illuminate\Support\Facades\App;
use Limenet\LaravelPdf\Commands\Cleanup;
use Spatie\LaravelPackageTools\Commands\InstallCommand;
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
            ->hasRoute('web')
            ->hasInstallCommand(fn (InstallCommand $command) => $command->publishConfigFile());
    }

    public function packageRegistered(): void
    {
        if (config('pdf.inline_assets')) {
            App::bind(Vite::class, fn (Application $app) => new ViteInline);
        }
    }
}
