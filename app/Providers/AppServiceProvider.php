<?php

namespace App\Providers;

use App\Services\NerestService;
use App\Services\NerestFilesystemAdapter;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Encryption\Encrypter;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use League\Flysystem\Filesystem;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->app->singleton(NerestService::class, function() {
            return new NerestService(env('NEREST_SECRET'));
        });

        Storage::extend('nerest', function (Application $app, array $config) {
            $adapter = new NerestFilesystemAdapter(
                Http::baseUrl($config['url']),
                $config['secret'],
                $config['prefix'] ?? ''
            );

            return new FilesystemAdapter(
                new Filesystem($adapter, $config),
                $adapter,
                $config
            );
        });
    }
}
