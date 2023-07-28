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
            throw new RuntimeException(sprintf('Faield to inline asset: %s', $path));
        }

        return sprintf(
            'data:image/%s;base64,%s',
            pathinfo($path, PATHINFO_EXTENSION),
            base64_encode($contents)
        );
    }
}
