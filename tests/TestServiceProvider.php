<?php

namespace Bladestan\Tests;

use App\View\Composers\AnotherSampleViewComposer;
use App\View\Composers\SampleViewComposer;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Support\ServiceProvider;

class TestServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $viewFactory = resolve(ViewFactory::class);
        $viewFactory->getFinder()
            ->addLocation(__DIR__ . '/skeleton/resources/views');
        $viewFactory->composer('*', SampleViewComposer::class);
        $viewFactory->composer('*', AnotherSampleViewComposer::class);
        $this->loadViewsFrom(__DIR__ . '/skeleton/resources/namespace/Test', 'Test');
    }
}
