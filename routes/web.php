<?php

use Illuminate\Support\Facades\Route;
use Limenet\LaravelPdf\Pdf;

Route::get('/pdf/{key}', fn (string $key) => str_starts_with($key, 'pdf_') ? Pdf::getDisk()->get($key) : abort(400))->middleware(['signed'])->name('pdf');
