<?php

namespace Modules\Authentication\Tests\Feature;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Event;
use Modules\Authentication\Actions\LogoutUserAction;
use Modules\Authentication\Events\UserLoggedOut;
use Modules\Authentication\Services\TokenService;
use Mockery;
use Tests\TestCase;

class LogoutUserActionTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_successful_logout_revokes_token_and_dispatches_user_logged_out(): void
    {
        Event::fake([UserLoggedOut::class]);
        config()->set('authentication.token_driver', 'sanctum');

        $user = new FakeLogoutUser();
        $action = app(LogoutUserAction::class);

        $action->execute($user, 'api');

        $this->assertTrue($user->tokenRevoked);
        Event::assertDispatched(UserLoggedOut::class, function (UserLoggedOut $event) use ($user) {
            return $event->payload->userId === 1 && $event->payload->source === 'api';
        });
    }

    public function test_logout_does_not_dispatch_user_logged_out_when_token_revocation_fails(): void
    {
        Event::fake([UserLoggedOut::class]);

        $user = new FakeLogoutUser();
        $tokenService = Mockery::mock(TokenService::class);
        $tokenService->shouldReceive('revokeCurrentToken')
            ->once()
            ->with($user)
            ->andThrow(new \RuntimeException('Token revocation failed'));

        $action = new LogoutUserAction($tokenService);
        $action->execute($user, 'web');

        Event::assertNotDispatched(UserLoggedOut::class);
    }
}

class FakeLogoutUser implements Authenticatable
{
    public bool $tokenRevoked = false;

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

    public function currentAccessToken(): object
    {
        $user = $this;

        return new class($user)
        {
            public function __construct(private FakeLogoutUser $user) {}

            public function delete(): void
            {
                $this->user->tokenRevoked = true;
            }
        };
    }
}
