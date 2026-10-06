<?php

declare(strict_types=1);

namespace ControlAir\LaravelIntacct\Tests\Feature;

use ControlAir\Intacct\Auth\OAuth\OAuthClient;
use ControlAir\Intacct\Auth\Tokens\TokenManager;
use ControlAir\Intacct\Configuration\OAuthApplication;
use ControlAir\Intacct\Exceptions\ConfigurationException;
use ControlAir\Intacct\IntacctClient;
use ControlAir\LaravelIntacct\Facades\Intacct;
use ControlAir\LaravelIntacct\IntacctManager;
use ControlAir\LaravelIntacct\Tests\TestCase;

final class IntacctManagerTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app->make('config')->set('intacct.default', 'foo');
        $app->make('config')->set('intacct.connections', [
            'foo' => $this->connectionConfig('foo-client', 'foo-key', 'Central'),
            'bar' => $this->connectionConfig('bar-client', 'bar-key', ''),
            'incomplete' => $this->connectionConfig('', 'incomplete-key', ''),
        ]);
    }

    public function test_the_container_resolves_the_manager_and_the_default_connection(): void
    {
        $manager = app(IntacctManager::class);

        $this->assertSame($manager, app(IntacctManager::class));
        $this->assertSame($manager->connection('foo'), app(IntacctClient::class));
        $this->assertSame($manager->application('foo'), app(OAuthApplication::class));
        $this->assertSame($manager->oauthClient('foo'), app(OAuthClient::class));
        $this->assertSame($manager->tokenManager('foo'), app(TokenManager::class));
    }

    public function test_the_connection_config_is_translated_to_sdk_objects(): void
    {
        $manager = app(IntacctManager::class);

        $this->assertSame('foo-client', $manager->application('foo')->clientId);
        $this->assertSame('foo-client-secret', $manager->application('foo')->clientSecret());
        $this->assertSame('foo-key', $manager->tokenKey('foo')->value);
    }

    public function test_an_empty_entity_id_is_treated_as_missing(): void
    {
        $this->assertInstanceOf(IntacctClient::class, app(IntacctManager::class)->connection('bar'));
    }

    public function test_named_connections_are_independent_and_reused(): void
    {
        $manager = app(IntacctManager::class);

        $this->assertNotSame($manager->connection('foo'), $manager->connection('bar'));
        $this->assertNotSame($manager->tokenManager('foo'), $manager->tokenManager('bar'));
        $this->assertSame($manager->connection('bar'), $manager->connection('bar'));
        $this->assertSame('bar-client', $manager->application('bar')->clientId);
        $this->assertSame('bar-key', $manager->tokenKey('bar')->value);
    }

    public function test_an_unknown_connection_is_rejected(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('The Intacct connection "missing" is not configured.');

        app(IntacctManager::class)->connection('missing');
    }

    public function test_a_connection_without_client_id_is_rejected(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('The Intacct connection "incomplete" is missing "client_id".');

        app(IntacctManager::class)->connection('incomplete');
    }

    public function test_the_facade_proxies_the_manager(): void
    {
        $this->assertSame(app(IntacctManager::class)->connection('bar'), Intacct::connection('bar'));
        $this->assertSame(app(IntacctClient::class), Intacct::connection());
    }

    /**
     * @return array<string, string>
     */
    private function connectionConfig(string $clientId, string $tokenKey, string $entityId): array
    {
        return [
            'client_id' => $clientId,
            'client_secret' => $clientId . '-secret',
            'company_id' => 'company',
            'user_id' => 'user',
            'entity_id' => $entityId,
            'token_key' => $tokenKey,
        ];
    }
}
