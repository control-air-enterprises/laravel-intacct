<?php

declare(strict_types=1);

namespace ControlAir\LaravelIntacct\Auth;

use ControlAir\Intacct\Auth\Contracts\RevocableAccessTokenProvider;
use ControlAir\Intacct\Auth\Contracts\TokenStore;
use ControlAir\Intacct\Auth\OAuth\ClientCredentialsGrant;
use ControlAir\Intacct\Auth\Tokens\AccessToken;
use ControlAir\Intacct\Auth\Tokens\TokenKey;
use ControlAir\Intacct\Auth\Tokens\TokenManager;
use ControlAir\Intacct\Exceptions\AuthenticationException;
use ControlAir\Intacct\Exceptions\MissingTokenException;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Cache\LockProvider;
use Psr\Clock\ClockInterface;

/**
 * Returns a valid access token, refreshing or re-authenticating under a cache lock.
 */
final readonly class ClientCredentialsTokenProvider implements RevocableAccessTokenProvider
{
    public const int REFRESH_LEEWAY_SECONDS = 60;

    private const int LOCK_SECONDS = 30;

    public function __construct(
        private TokenManager $manager,
        private TokenStore $store,
        private ClockInterface $clock,
        private LockProvider $locks,
        private TokenKey $key,
        private ClientCredentialsGrant $grant,
        private int $lockWaitSeconds = 30,
    ) {
    }

    public function getAccessToken(): AccessToken
    {
        $tokens = $this->store->get($this->key);

        if ($tokens !== null && ! $tokens->expiresWithin($this->clock, self::REFRESH_LEEWAY_SECONDS)) {
            return $tokens->accessToken;
        }

        $lock = $this->acquireRefreshLock();

        try {
            return $this->validOrNewAccessToken();
        } finally {
            $lock->release();
        }
    }

    public function invalidate(AccessToken $rejected): void
    {
        $lock = $this->acquireRefreshLock();

        try {
            $this->forgetIfStillStored($rejected);
        } finally {
            $lock->release();
        }
    }

    private function acquireRefreshLock(): Lock
    {
        $lock = $this->locks->lock("intacct:token-refresh:{$this->key->value}", self::LOCK_SECONDS);
        $lock->block($this->lockWaitSeconds);

        return $lock;
    }

    private function forgetIfStillStored(AccessToken $rejected): void
    {
        $tokens = $this->store->get($this->key);

        if ($tokens === null) {
            return;
        }

        if (! hash_equals($tokens->accessToken->reveal(), $rejected->reveal())) {
            return;
        }

        $this->store->forget($this->key);
    }

    private function validOrNewAccessToken(): AccessToken
    {
        try {
            return $this->manager->getValidAccessToken($this->key);
        } catch (MissingTokenException|AuthenticationException) {
            return $this->manager->authenticate($this->key, $this->grant)->accessToken;
        }
    }
}
