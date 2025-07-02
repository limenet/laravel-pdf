<?php

namespace Limenet\LaravelPdf\Adapters;

use Exception;
use Illuminate\Support\Facades\Cache;

trait ConcurrencyLimiterTrait
{
    public function getConcurrencyLimit(): int
    {
        return $this->adapterConfig('concurrency_limit', 5);
    }

    /**
     * Get the cache key to use for tracking active requests
     *
     * This method can be overridden by the class using this trait
     *
     * @return string The cache key
     */
    private function getConcurrencyKey(): string
    {
        return strtolower(str_replace('\\', '_', get_class($this))).'_active_requests';
    }

    /**
     * Wait until we're below the concurrency limit
     *
     * @param  int  $limit  The maximum number of concurrent requests
     *
     * @throws Exception If waiting for an available slot times out
     */
    private function waitForAvailableSlot(int $limit): void
    {
        // Maximum time to wait in seconds
        $maxWaitTime = 60;
        $startTime = time();
        $cacheKey = $this->getConcurrencyKey();

        while (true) {
            // Get current active requests count
            $activeRequests = cache()->get($cacheKey, 0);

            // If we're below the limit, we can proceed
            if ($activeRequests < $limit) {
                break;
            }

            // Check if we've waited too long
            if (time() - $startTime > $maxWaitTime) {
                throw new Exception("Timed out waiting for available slot for {$cacheKey}");
            }

            // Wait a bit before checking again
            usleep(100000); // 100ms
        }
    }

    /**
     * Increment the active requests counter
     */
    private function incrementActiveRequests(): void
    {
        cache()->increment($this->getConcurrencyKey(), 1);
    }

    /**
     * Decrement the active requests counter
     */
    private function decrementActiveRequests(): void
    {
        $cacheKey = $this->getConcurrencyKey();
        $currentValue = cache()->get($cacheKey, 0);
        if ($currentValue > 0) {
            cache()->decrement($cacheKey, 1);
        }
    }

    /**
     * Execute a callback with concurrency limiting
     *
     * @param  callable  $callback  The callback to execute
     * @return mixed The result of the callback
     *
     * @throws Exception If the callback throws an exception
     */
    protected function executeWithConcurrencyLimit(callable $callback): mixed
    {
        // Wait until we're below the concurrency limit
        $this->waitForAvailableSlot($this->getConcurrencyLimit());

        // Increment the active requests counter
        $this->incrementActiveRequests();

        try {
            // Execute the callback
            return $callback();
        } finally {
            // Always decrement the active requests counter, even if an exception occurs
            $this->decrementActiveRequests();
        }
    }
}
