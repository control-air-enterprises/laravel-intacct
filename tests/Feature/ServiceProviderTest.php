<?php

declare(strict_types=1);

namespace ControlAir\LaravelIntacct\Tests\Feature;

use ControlAir\Intacct\Support\SystemClock;
use ControlAir\LaravelIntacct\IntacctServiceProvider;
use ControlAir\LaravelIntacct\Tests\TestCase;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Psr\Clock\ClockInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

final class ServiceProviderTest extends TestCase
{
    public function test_the_service_provider_is_loaded(): void
    {
        $this->assertArrayHasKey(IntacctServiceProvider::class, app()->getLoadedProviders());
    }

    public function test_the_package_config_is_merged(): void
    {
        $this->assertSame('default', config('intacct.default'));
        $this->assertSame('database', config('intacct.tokens.driver'));
        $this->assertIsArray(config('intacct.connections.default'));
    }

    public function test_the_psr_dependencies_resolve_to_concrete_implementations(): void
    {
        $this->assertInstanceOf(Client::class, app(ClientInterface::class));
        $this->assertInstanceOf(HttpFactory::class, app(RequestFactoryInterface::class));
        $this->assertInstanceOf(HttpFactory::class, app(StreamFactoryInterface::class));
        $this->assertInstanceOf(SystemClock::class, app(ClockInterface::class));
    }

    public function test_the_config_can_be_published(): void
    {
        $published = config_path('intacct.php');
        File::delete($published);

        $exitCode = Artisan::call('vendor:publish', ['--tag' => 'intacct-config']);

        $this->assertSame(0, $exitCode);

        $this->assertFileExists($published);

        File::delete($published);
    }

    public function test_the_migrations_can_be_published(): void
    {
        $exitCode = Artisan::call('vendor:publish', ['--tag' => 'intacct-migrations']);

        $this->assertSame(0, $exitCode);

        $published = File::glob(database_path('migrations/*_create_intacct_tokens_table.php'));

        $this->assertCount(1, $published);

        File::delete($published);
    }
}
