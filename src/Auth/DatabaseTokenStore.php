<?php

declare(strict_types=1);

namespace ControlAir\LaravelIntacct\Auth;

use ControlAir\Intacct\Auth\Contracts\TokenStore;
use ControlAir\Intacct\Auth\Tokens\AccessToken;
use ControlAir\Intacct\Auth\Tokens\RefreshToken;
use ControlAir\Intacct\Auth\Tokens\TokenKey;
use ControlAir\Intacct\Auth\Tokens\TokenSet;
use ControlAir\Intacct\Auth\Tokens\TokenType;
use ControlAir\LaravelIntacct\Models\IntacctToken;

final class DatabaseTokenStore implements TokenStore
{
    public function get(TokenKey $key): ?TokenSet
    {
        $token = IntacctToken::query()->where('key', $key->value)->first();

        if ($token === null) {
            return null;
        }

        return new TokenSet(
            accessToken: new AccessToken($token->access_token),
            tokenType: TokenType::from($token->token_type),
            expiresAt: $token->expires_at,
            refreshToken: $this->refreshToken($token->refresh_token),
            scopes: $token->scopes,
        );
    }

    public function put(TokenKey $key, TokenSet $tokens): void
    {
        IntacctToken::query()->updateOrCreate(
            ['key' => $key->value],
            [
                'access_token' => $tokens->accessToken->reveal(),
                'refresh_token' => $tokens->refreshToken?->reveal(),
                'token_type' => $tokens->tokenType->value,
                'expires_at' => $tokens->expiresAt,
                'scopes' => $tokens->scopes,
            ],
        );
    }

    public function forget(TokenKey $key): void
    {
        IntacctToken::query()->where('key', $key->value)->delete();
    }

    private function refreshToken(?string $value): ?RefreshToken
    {
        if ($value === null) {
            return null;
        }

        return new RefreshToken($value);
    }
}
