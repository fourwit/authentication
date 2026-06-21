<?php

namespace Modules\Authentication\Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_verification_send_requires_auth(): void
    {
        $this->postJson('/api/v1/auth/email/verification/send', [
            'email' => 'missing@example.com',
        ])->assertUnauthorized();
    }
}
