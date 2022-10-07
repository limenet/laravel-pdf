<?php

namespace Limenet\LaravelPdf\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Limenet\LaravelPdf\LaravelPdf
 */
class LaravelPdf extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \Limenet\LaravelPdf\LaravelPdf::class;
    }
}
