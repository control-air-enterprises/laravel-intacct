<?php

declare(strict_types=1);

namespace ControlAir\LaravelIntacct\Tests\Feature;

use ArrayObject;
use ControlAir\Intacct\Auth\Contracts\TokenStore;
use ControlAir\Intacct\Auth\OAuth\ClientCredentialsGrant;
use ControlAir\Intacct\Auth\Tokens\AccessToken;
use ControlAir\Intacct\Auth\Tokens\RefreshToken;
use ControlAir\Intacct\Auth\Tokens\TokenKey;
use ControlAir\Intacct\Auth\Tokens\TokenSet;
use ControlAir\Intacct\Auth\Tokens\TokenType;
use ControlAir\Intacct\Exceptions\ConfigurationException;
use ControlAir\LaravelIntacct\Auth\ClientCredentialsTokenProvider;
use ControlAir\LaravelIntacct\IntacctManager;
use ControlAir\LaravelIntacct\Tests\TestCase;
use DateTimeImmutable;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Psr\Clock\ClockInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;

final class ClientCredentialsTokenProviderTest extends TestCase
{
    use RefreshDatabase;

    private MockHandler $responses;

    /** @var ArrayObject<int, mixed> */
    private ArrayObject $history;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app->make('config')->set('intacct.connections.default', [
            'client_id' => 'client',
            'client_secret' => 'secret',
            'company_id' => 'company',
            'user_id' => 'user',
            'entity_id' => 'Central',
            'token_key' => 'default',
        ]);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->responses = new MockHandler();
        $this->history = new ArrayObject();
        $history = $this->history;
        $stack = HandlerStack::create($this->responses);
        $stack->push(Middleware::history($history));

        $this->app?->instance(ClientInterface::class, new Client(['handler' => $stack]));
    }

    public function test_it_authenticates_when_no_token_is_stored(): void
    {
        $this->responses->append($this->tokenResponse('new-access'));

        $token = $this->provider()->getAccessToken();

        $this->assertSame('new-access', $token->reveal());
        $this->assertCount(1, $this->history);
        $this->assertSame([
            'grant_type' => 'client_credentials',
            'client_id' => 'client',
            'client_secret' => 'secret',
            'username' => 'user@company|Central',
        ], json_decode($this->requestBody(0), true));
        $this->assertSame('new-access', $this->storedTokens()?->accessToken->reveal());
    }

    public function test_it_returns_a_valid_stored_token_without_calling_sage(): void
    {
        $this->storeTokens('stored-access', null, '+1 hour');

        $token = $this->provider()->getAccessToken();

        $this->assertSame('stored-access', $token->reveal());
        $this->assertCount(0, $this->history);
    }

    public function test_it_authenticates_again_when_the_token_expired_without_refresh_token(): void
    {
        $this->storeTokens('expired-access', null, '-1 minute');
        $this->responses->append($this->tokenResponse('new-access'));

        $token = $this->provider()->getAccessToken();

        $this->assertSame('new-access', $token->reveal());
        $this->assertCount(1, $this->history);
        $this->assertStringContainsString('client_credentials', $this->requestBody(0));
    }

    public function test_it_refreshes_when_the_token_expired_with_refresh_token(): void
    {
        $this->storeTokens('expired-access', 'refresh-1', '-1 minute');
        $this->responses->append($this->tokenResponse('refreshed-access'));

        $token = $this->provider()->getAccessToken();

        $this->assertSame('refreshed-access', $token->reveal());
        $this->assertCount(1, $this->history);
        $this->assertStringContainsString('grant_type=refresh_token', $this->requestBody(0));
    }

    public function test_it_releases_the_lock_after_obtaining_a_token(): void
    {
        $this->responses->append($this->tokenResponse('new-access'));

        $this->provider()->getAccessToken();

        $this->assertTrue($this->cacheLocks()->lock('intacct:token-refresh:default', 30)->get());
    }

    public function test_it_waits_for_the_lock_held_by_another_worker(): void
    {
        $this->cacheLocks()->lock('intacct:token-refresh:default', 30)->get();

        try {
            $this->provider(lockWaitSeconds: 0)->getAccessToken();
            $this->fail('The provider obtained a token while another worker held the lock.');
        } catch (LockTimeoutException) {
            $this->assertCount(0, $this->history);
        }
    }

    public function test_a_connection_without_company_id_is_rejected(): void
    {
        config(['intacct.connections.default.company_id' => '']);

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('The Intacct connection "default" is missing "company_id".');

        app(IntacctManager::class)->connection();
    }

    private function provider(int $lockWaitSeconds = 30): ClientCredentialsTokenProvider
    {
        $manager = app(IntacctManager::class);

        return new ClientCredentialsTokenProvider(
            manager: $manager->tokenManager(),
            store: app(TokenStore::class),
            clock: app(ClockInterface::class),
            locks: $this->cacheLocks(),
            key: $manager->tokenKey(),
            grant: ClientCredentialsGrant::forUsername('user', 'company', 'Central'),
            lockWaitSeconds: $lockWaitSeconds,
        );
    }

    private function cacheLocks(): LockProvider
    {
        $store = app(Repository::class)->getStore();
        $this->assertInstanceOf(LockProvider::class, $store);

        return $store;
    }

    private function storeTokens(string $accessToken, ?string $refreshToken, string $expiresAt): void
    {
        $refresh = null;

        if ($refreshToken !== null) {
            $refresh = new RefreshToken($refreshToken);
        }

        app(TokenStore::class)->put(new TokenKey('default'), new TokenSet(
            accessToken: new AccessToken($accessToken),
            tokenType: TokenType::Bearer,
            expiresAt: new DateTimeImmutable($expiresAt),
            refreshToken: $refresh,
        ));
    }

    private function storedTokens(): ?TokenSet
    {
        return app(TokenStore::class)->get(new TokenKey('default'));
    }

    private function requestBody(int $index): string
    {
        $entry = $this->history[$index];
        $this->assertIsArray($entry);

        $request = $entry['request'];
        $this->assertInstanceOf(RequestInterface::class, $request);

        return (string) $request->getBody();
    }

    private function tokenResponse(string $accessToken): Response
    {
        return new Response(200, ['Content-Type' => 'application/json'], (string) json_encode([
            'access_token' => $accessToken,
            'token_type' => 'Bearer',
            'expires_in' => 3600,
        ]));
    }
}
