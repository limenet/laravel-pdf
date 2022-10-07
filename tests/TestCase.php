<?php

namespace Limenet\LaravelPdf\Tests;

use Limenet\LaravelPdf\LaravelPdfServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
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

    public function getEnvironmentSetUp($app)
    {
    }
}
