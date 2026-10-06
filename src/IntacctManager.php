<?php

declare(strict_types=1);

namespace ControlAir\LaravelIntacct;

use ControlAir\Intacct\Auth\Contracts\TokenStore;
use ControlAir\Intacct\Auth\OAuth\OAuthClient;
use ControlAir\Intacct\Auth\Tokens\ManagedAccessTokenProvider;
use ControlAir\Intacct\Auth\Tokens\TokenKey;
use ControlAir\Intacct\Auth\Tokens\TokenManager;
use ControlAir\Intacct\Configuration\ApiConfiguration;
use ControlAir\Intacct\Configuration\OAuthApplication;
use ControlAir\Intacct\Exceptions\ConfigurationException;
use ControlAir\Intacct\IntacctClient;
use Illuminate\Contracts\Config\Repository;
use Psr\Clock\ClockInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Builds and caches one set of SDK objects per configured Intacct connection.
 */
final class IntacctManager
{
    /** @var array<string, OAuthApplication> */
    private array $applications = [];

    /** @var array<string, OAuthClient> */
    private array $oauthClients = [];

    /** @var array<string, TokenManager> */
    private array $tokenManagers = [];

    /** @var array<string, IntacctClient> */
    private array $clients = [];

    public function __construct(
        private readonly Repository $config,
        private readonly ClientInterface $httpClient,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
        private readonly ClockInterface $clock,
        private readonly TokenStore $tokenStore,
    ) {
    }

    public function connection(?string $name = null): IntacctClient
    {
        $name = $this->resolveName($name);

        if (! isset($this->clients[$name])) {
            $this->clients[$name] = new IntacctClient(
                tokens: new ManagedAccessTokenProvider($this->tokenManager($name), $this->tokenKey($name)),
                httpClient: $this->httpClient,
                requestFactory: $this->requestFactory,
                streamFactory: $this->streamFactory,
                configuration: new ApiConfiguration(entityId: $this->optionalValue($name, 'entity_id')),
            );
        }

        return $this->clients[$name];
    }

    public function tokenManager(?string $name = null): TokenManager
    {
        $name = $this->resolveName($name);

        if (! isset($this->tokenManagers[$name])) {
            $this->tokenManagers[$name] = new TokenManager(
                oauth: $this->oauthClient($name),
                store: $this->tokenStore,
                clock: $this->clock,
            );
        }

        return $this->tokenManagers[$name];
    }

    public function oauthClient(?string $name = null): OAuthClient
    {
        $name = $this->resolveName($name);

        if (! isset($this->oauthClients[$name])) {
            $this->oauthClients[$name] = new OAuthClient(
                application: $this->application($name),
                httpClient: $this->httpClient,
                requestFactory: $this->requestFactory,
                streamFactory: $this->streamFactory,
                clock: $this->clock,
            );
        }

        return $this->oauthClients[$name];
    }

    public function application(?string $name = null): OAuthApplication
    {
        $name = $this->resolveName($name);

        if (! isset($this->applications[$name])) {
            $this->applications[$name] = new OAuthApplication(
                clientId: $this->requiredValue($name, 'client_id'),
                clientSecret: $this->optionalValue($name, 'client_secret'),
            );
        }

        return $this->applications[$name];
    }

    public function tokenKey(?string $name = null): TokenKey
    {
        $name = $this->resolveName($name);

        return new TokenKey($this->requiredValue($name, 'token_key'));
    }

    public function getDefaultConnection(): string
    {
        return $this->config->string('intacct.default');
    }

    private function resolveName(?string $name): string
    {
        if ($name === null) {
            return $this->getDefaultConnection();
        }

        return $name;
    }

    /**
     * @return array<mixed>
     */
    private function connectionConfig(string $name): array
    {
        $config = $this->config->get("intacct.connections.{$name}");

        if (! is_array($config)) {
            throw new ConfigurationException(sprintf('The Intacct connection "%s" is not configured.', $name));
        }

        return $config;
    }

    private function requiredValue(string $name, string $key): string
    {
        $value = $this->optionalValue($name, $key);

        if ($value === null) {
            throw new ConfigurationException(sprintf('The Intacct connection "%s" is missing "%s".', $name, $key));
        }

        return $value;
    }

    private function optionalValue(string $name, string $key): ?string
    {
        $value = $this->connectionConfig($name)[$key] ?? null;

        if (! is_string($value) || $value === '') {
            return null;
        }

        return $value;
    }
}
