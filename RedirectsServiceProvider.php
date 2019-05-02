<?php

namespace Statamic\Addons\Redirects;

use Statamic\Extend\ServiceProvider;

class RedirectsServiceProvider extends ServiceProvider
{
    protected $defer = true;

    public function register()
    {
        $storagePath = site_storage_path('addons/redirects/');

        $this->app->singleton(ManualRedirectsManager::class, function ($app) use ($storagePath) {
           return new ManualRedirectsManager($storagePath . 'manual.yaml', $app[RedirectsLogger::class]);
        });

        $this->app->singleton(AutoRedirectsManager::class, function ($app) use ($storagePath) {
            return new AutoRedirectsManager($storagePath . 'auto.yaml', $app[RedirectsLogger::class]);
        });

        $this->app->singleton(RedirectsProcessor::class, function ($app) {
            return new RedirectsProcessor($app[ManualRedirectsManager::class], $app[AutoRedirectsManager::class], $app[RedirectsLogger::class]);
        });

        $this->app->singleton(RedirectsLogger::class, function () use ($storagePath) {
            return new RedirectsLogger($storagePath);
        });
    }

    public function provides()
    {
        return [
            ManualRedirectsManager::class,
            AutoRedirectsManager::class,
            RedirectsProcessor::class,
            RedirectsLogger::class,
        ];
    }
}
