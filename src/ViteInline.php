<?php

namespace Limenet\LaravelPdf;

use Illuminate\Foundation\Vite;

class ViteInline extends Vite
{
    public static bool $isEnabled = false;

    protected function makeScriptTagWithAttributes($url, $attributes)
    {
        if (! self::$isEnabled) {
            return parent::makeScriptTagWithAttributes(...func_get_args());
        }

        $attributes = $this->parseAttributes(array_merge([
            'type' => 'module',
        ], $attributes));

        return sprintf(
            '<script %s>%s</script>'."\n",
            implode(' ', $attributes),
            file_get_contents($this->absolutePath($url))
        );
    }

    protected function makeStylesheetTagWithAttributes($url, $attributes)
    {
        if (! self::$isEnabled) {
            return parent::makeStylesheetTagWithAttributes(...func_get_args());
        }

        return sprintf(
            '<style>%s</style>'."\n",
            file_get_contents($this->absolutePath($url))
        );
    }

    private function absolutePath(string $url): string
    {
        return str($url)
            ->replace(asset(''), public_path('/'))
            ->toString();
    }
}
