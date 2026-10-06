<?php

declare(strict_types=1);

namespace ControlAir\LaravelIntacct;

use ControlAir\Intacct\Auth\Contracts\TokenStore;
use ControlAir\Intacct\Auth\OAuth\OAuthClient;
use ControlAir\Intacct\Auth\Tokens\TokenManager;
use ControlAir\Intacct\Configuration\OAuthApplication;
use ControlAir\Intacct\Exceptions\ConfigurationException;
use ControlAir\Intacct\IntacctClient;
use ControlAir\Intacct\Support\SystemClock;
use ControlAir\LaravelIntacct\Auth\DatabaseTokenStore;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use Illuminate\Contracts\Foundation\Application;
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
        $this->app->singleton(TokenStore::class, fn (Application $app): TokenStore => $this->tokenStore($app));

        $this->app->singleton(IntacctManager::class);
        $this->app->bind(IntacctClient::class, fn (Application $app): IntacctClient => $app->make(IntacctManager::class)->connection());
        $this->app->bind(OAuthApplication::class, fn (Application $app): OAuthApplication => $app->make(IntacctManager::class)->application());
        $this->app->bind(OAuthClient::class, fn (Application $app): OAuthClient => $app->make(IntacctManager::class)->oauthClient());
        $this->app->bind(TokenManager::class, fn (Application $app): TokenManager => $app->make(IntacctManager::class)->tokenManager());
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/intacct.php' => config_path('intacct.php'),
        ], 'intacct-config');

        $this->publishesMigrations([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'intacct-migrations');
    }

    private function tokenStore(Application $app): TokenStore
    {
        $driver = $app->make('config')->string('intacct.tokens.driver');

        if ($driver !== 'database') {
            throw new ConfigurationException(sprintf('Unsupported Intacct token store driver "%s".', $driver));
        }

        return new DatabaseTokenStore();
    }
}
