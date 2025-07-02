<?php

namespace Limenet\LaravelPdf\Adapters;

trait ConfigTrait
{
    public function adapterConfig(string $path, mixed $default = null): mixed
    {
        return config($this->configPath().'.'.$path, $default);
    }
}
