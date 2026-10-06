<?php

declare(strict_types=1);

namespace ControlAir\LaravelIntacct;

use ControlAir\Intacct\Support\SystemClock;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use Illuminate\Support\ServiceProvider;
use Psr\Clock\ClockInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

final class IntacctServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/intacct.php', 'intacct');

        $this->app->singleton(ClientInterface::class, fn (): Client => new Client());
        $this->app->singleton(HttpFactory::class);
        $this->app->singleton(RequestFactoryInterface::class, HttpFactory::class);
        $this->app->singleton(StreamFactoryInterface::class, HttpFactory::class);
        $this->app->singleton(ClockInterface::class, fn (): SystemClock => new SystemClock());
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/intacct.php' => config_path('intacct.php'),
        ], 'intacct-config');
    }
}
