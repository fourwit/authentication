<?php

namespace Modules\Authentication\Tests\Feature;

use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Authentication\Services\TokenService;
use Tests\TestCase;

class TokenServiceTest extends TestCase
{
    public function test_issue_for_api_login_uses_default_token_expiry_when_remember_is_false(): void
    {
        config()->set('authentication.token_driver', 'sanctum');
        config()->set('authentication.login.api_tokens.expires_minutes', 60);
        config()->set('authentication.login.api_tokens.remember_expires_minutes', 43200);

        $user = new FakeTokenUser();
        $service = app(TokenService::class);

        $result = $service->issueForLogin($user, false, 'api');

        $this->assertSame('fake-token', $result['token']);
        $this->assertNotNull($result['expires_at']);
        $this->assertEqualsWithDelta(60, now()->diffInMinutes($user->lastExpiresAt, false), 1);
    }

    public function test_issue_for_api_login_uses_remember_token_expiry_when_remember_is_true(): void
    {
        config()->set('authentication.token_driver', 'sanctum');
        config()->set('authentication.login.api_tokens.expires_minutes', 60);
        config()->set('authentication.login.api_tokens.remember_expires_minutes', 43200);

        $user = new FakeTokenUser();
        $service = app(TokenService::class);

        $result = $service->issueForLogin($user, true, 'api');

        $this->assertSame('fake-token', $result['token']);
        $this->assertNotNull($result['expires_at']);
        $this->assertEqualsWithDelta(43200, now()->diffInMinutes($user->lastExpiresAt, false), 1);
    }

    public function test_issue_for_web_login_does_not_apply_api_token_expiry(): void
    {
        config()->set('authentication.token_driver', 'sanctum');
        config()->set('authentication.login.api_tokens.expires_minutes', 60);
        config()->set('authentication.login.api_tokens.remember_expires_minutes', 43200);

        $user = new FakeTokenUser();
        $service = app(TokenService::class);

        $result = $service->issueForLogin($user, true, 'web');

        $this->assertSame('fake-token', $result['token']);
        $this->assertNull($result['expires_at']);
        $this->assertNull($user->lastExpiresAt);
    }
}

class FakeTokenUser implements Authenticatable
{
    public ?\Illuminate\Support\Carbon $lastExpiresAt = null;

    public function getAuthIdentifierName()
    {
        return 'id';
    }

    public function getAuthIdentifier()
    {
        return 1;
    }

    public function getAuthPasswordName()
    {
        return 'password';
    }

    public function getAuthPassword()
    {
        return 'password';
    }

    public function getRememberToken()
    {
        return null;
    }

    public function setRememberToken($value)
    {
    }

    public function getRememberTokenName()
    {
        return 'remember_token';
    }

    public function createToken($name, array $abilities = ['*'], $expiresAt = null): object
    {
        $this->lastExpiresAt = $expiresAt;

        return new class {
            public string $plainTextToken = 'fake-token';
        };
    }
}
