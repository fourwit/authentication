<?php

namespace Modules\Authentication\Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Modules\Authentication\Events\UserLoggedOut;
use Modules\Authentication\Facades\Authentication;
use Modules\Identity\Facades\Identity;
use Tests\TestCase;

class LogoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_logout_endpoint_exists(): void
    {
        $this->postJson('/api/v1/auth/logout')->assertUnauthorized();
    }

    public function test_successful_logout_dispatches_user_logged_out_via_facade(): void
    {
        Event::fake([UserLoggedOut::class]);

        $user = Identity::createUser([
            'name' => 'Logout User',
            'email' => 'logout@example.com',
            'password' => bcrypt('password123'),
        ]);

        Authentication::logout($user, 'api');

        Event::assertDispatched(UserLoggedOut::class, function (UserLoggedOut $event) use ($user) {
            return (int) $event->user->id === (int) $user->id && $event->source === 'api';
        });
    }
}
