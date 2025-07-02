<?php

namespace Limenet\LaravelPdf\Adapters;

interface ConcurrencyLimiterInterface
{
    /**
     * Get the concurrency limit from configuration
     *
     * @return int The concurrency limit
     */
    public function getConcurrencyLimit(): int;
}
