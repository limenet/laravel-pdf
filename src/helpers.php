<?php

use Limenet\LaravelPdf\ViteInline;

if (! function_exists('asset_inline')) {
    /**
     * Inline an asset. Compatible with asset()
     *
     * @param  string  $path
     * @param  bool|null  $secure
     * @return string
     */
    function asset_inline($path, $secure = null)
    {
        if (! ViteInline::$isEnabled) {
            return asset(...func_get_args());
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException(sprintf('Failed to inline asset: %s', $path));
        }

        $extension = pathinfo($path, PATHINFO_EXTENSION);

        return sprintf(
            'data:image/%s;base64,%s',
            match ($extension) {
                'svg' => 'svg+xml',
                default => $extension,
            },
            base64_encode($contents)
        );
    }
}
