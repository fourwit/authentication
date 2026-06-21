<?php

namespace Modules\Authentication\Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_logout_endpoint_exists(): void
    {
        $this->postJson('/api/v1/auth/logout')->assertUnauthorized();
    }
}
