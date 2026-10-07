<?php

declare(strict_types=1);

namespace ControlAir\LaravelIntacct\Tests\Feature;

use ControlAir\Intacct\Auth\Contracts\TokenStore;
use ControlAir\Intacct\Auth\Tokens\AccessToken;
use ControlAir\Intacct\Auth\Tokens\RefreshToken;
use ControlAir\Intacct\Auth\Tokens\TokenKey;
use ControlAir\Intacct\Auth\Tokens\TokenSet;
use ControlAir\Intacct\Auth\Tokens\TokenType;
use ControlAir\Intacct\Exceptions\ConfigurationException;
use ControlAir\LaravelIntacct\Auth\DatabaseTokenStore;
use ControlAir\LaravelIntacct\Tests\TestCase;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

final class DatabaseTokenStoreTest extends TestCase
{
    use RefreshDatabase;

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
    }

    public function test_it_stores_and_retrieves_a_token_set(): void
    {
        $store = new DatabaseTokenStore();
        $key = new TokenKey('default');
        $tokens = $this->tokenSet('access-1', 'refresh-1');

        $store->put($key, $tokens);
        $stored = $store->get($key);

        $this->assertNotNull($stored);
        $this->assertSame('access-1', $stored->accessToken->reveal());
        $this->assertSame('refresh-1', $stored->refreshToken?->reveal());
        $this->assertSame(TokenType::Bearer, $stored->tokenType);
        $this->assertSame($tokens->expiresAt->getTimestamp(), $stored->expiresAt->getTimestamp());
        $this->assertSame(['offline_access'], $stored->scopes);
    }

    public function test_storing_the_same_key_replaces_the_token_set(): void
    {
        $store = new DatabaseTokenStore();
        $key = new TokenKey('default');

        $store->put($key, $this->tokenSet('access-1', 'refresh-1'));
        $store->put($key, $this->tokenSet('access-2', 'refresh-2'));

        $this->assertSame(1, DB::table('intacct_tokens')->count());
        $stored = $store->get($key);

        $this->assertNotNull($stored);
        $this->assertSame('access-2', $stored->accessToken->reveal());
        $this->assertSame('refresh-2', $stored->refreshToken?->reveal());
    }

    public function test_a_token_set_without_refresh_token_is_retrieved_without_one(): void
    {
        $store = new DatabaseTokenStore();
        $key = new TokenKey('default');

        $store->put($key, $this->tokenSet('access-1', null));

        $this->assertNull($store->get($key)?->refreshToken);
    }

    public function test_a_missing_key_returns_null(): void
    {
        $this->assertNull((new DatabaseTokenStore())->get(new TokenKey('missing')));
    }

    public function test_forget_removes_the_token_set(): void
    {
        $store = new DatabaseTokenStore();
        $key = new TokenKey('default');

        $store->put($key, $this->tokenSet('access-1', 'refresh-1'));
        $store->forget($key);

        $this->assertNull($store->get($key));
        $this->assertSame(0, DB::table('intacct_tokens')->count());
    }

    public function test_tokens_are_encrypted_at_rest(): void
    {
        (new DatabaseTokenStore())->put(new TokenKey('default'), $this->tokenSet('plain-access', 'plain-refresh'));

        $row = DB::table('intacct_tokens')->first();

        $this->assertNotNull($row);
        $this->assertIsString($row->access_token);
        $this->assertIsString($row->refresh_token);
        $this->assertStringNotContainsString('plain-access', $row->access_token);
        $this->assertStringNotContainsString('plain-refresh', $row->refresh_token);
    }

    public function test_the_container_resolves_the_database_store(): void
    {
        $this->assertInstanceOf(DatabaseTokenStore::class, app(TokenStore::class));
    }

    public function test_an_unknown_driver_is_rejected(): void
    {
        config(['intacct.tokens.driver' => 'redis']);

        $this->expectException(ConfigurationException::class);

        app(TokenStore::class);
    }

    private function tokenSet(string $accessToken, ?string $refreshToken): TokenSet
    {
        $refresh = null;

        if ($refreshToken !== null) {
            $refresh = new RefreshToken($refreshToken);
        }

        return new TokenSet(
            accessToken: new AccessToken($accessToken),
            tokenType: TokenType::Bearer,
            expiresAt: new DateTimeImmutable('+1 hour'),
            refreshToken: $refresh,
            scopes: ['offline_access'],
        );
    }
}
