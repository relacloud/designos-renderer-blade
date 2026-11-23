<?php

declare(strict_types=1);

namespace DesignOS\RendererBlade;

use Illuminate\Support\ServiceProvider;

final class RendererBladeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(BladePageRenderer::class);
    }

    public function boot(): void
    {
        $namespace = (string) config('designos.view_namespace', 'designos');
        if (preg_match('/^[A-Za-z][A-Za-z0-9_-]*$/', $namespace) !== 1) {
            throw new \InvalidArgumentException('DesignOS Laravel view namespace must be a simple namespace identifier.');
        }

        $this->loadViewsFrom(
            $this->app->resourcePath('views/vendor/' . $namespace),
            $namespace,
        );
    }
}
