<?php

namespace Limenet\LaravelPdf\DTO;

class PdfConfig
{
    public function __construct(
        public readonly string $format,
        public readonly bool $landscape,
        public readonly string $marginTop,
        public readonly string $marginRight,
        public readonly string $marginBottom,
        public readonly string $marginLeft,
    ) {}
}
