<?php

namespace Limenet\LaravelPdf\Tests;

use Limenet\LaravelPdf\LaravelPdfServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    public function getEnvironmentSetUp($app): void
    {
    }

    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function getPackageProviders($app)
    {
        return [
            LaravelPdfServiceProvider::class,
        ];
    }
}
