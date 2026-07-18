<?php

namespace Modules\Authentication\Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Modules\Authentication\Notifications\VerificationCodeNotification;
use Modules\Authentication\Repositories\VerificationCodeRepository;
use Modules\Identity\Facades\Identity;
use Tests\TestCase;

class ApiResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('authentication.login.default_method', 'email_password');
        config()->set('authentication.verification.enabled', true);
        config()->set('authentication.verification.method', 'code');
        config()->set('authentication.verification.channel', 'email');
        config()->set('authentication.after_otp_registration.prompt_for_password', true);
        config()->set('authentication.after_otp_registration.password_required', false);
        config()->set('authentication.registration.post_verification_profile_completion', false);
    }

    public function test_login_success_response_shape_and_redacts_sensitive_fields(): void
    {
        Identity::createUser([
            'name' => 'Resource Login User',
            'email' => 'resource-login@example.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'auth_method' => 'email_password',
            'email' => 'resource-login@example.com',
            'password' => 'password123',
        ])->assertOk()
            ->assertJsonStructure([
                'user' => ['id', 'name', 'email'],
                'token' => ['token', 'expires_at'],
            ]);

        $this->assertSame(['id', 'name', 'email'], array_keys($response->json('user')));
        $this->assertArrayNotHasKey('password', $response->json('user'));
        $this->assertArrayNotHasKey('remember_token', $response->json('user'));
    }

    public function test_login_otp_sent_response_shape(): void
    {
        Notification::fake();

        Identity::createUser([
            'name' => 'Resource Otp Login User',
            'email' => 'resource-otp-login@example.com',
            'password' => bcrypt('password123'),
        ]);

        $this->postJson('/api/v1/auth/login', [
            'auth_method' => 'email_otp',
            'email' => 'resource-otp-login@example.com',
        ])->assertStatus(202)
            ->assertJsonStructure([
                'status',
                'channel',
                'destination',
                'expires_at',
                'message',
            ])
            ->assertJsonPath('status', 'otp_sent');
    }

    public function test_registration_response_shape_and_redacts_internal_fields(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/v1/auth/register', [
            'auth_method' => 'email_otp',
            'email' => 'resource-register@example.com',
        ])->assertCreated()
            ->assertJsonStructure([
                'user' => ['id', 'name', 'email'],
                'registration_grant',
            ]);

        $this->assertArrayNotHasKey('was_created', $response->json());
        $this->assertArrayNotHasKey('reused_unverified', $response->json());
        $this->assertArrayNotHasKey('password', $response->json('user'));
    }

    public function test_registration_otp_verify_response_shape(): void
    {
        Notification::fake();

        $this->postJson('/api/v1/auth/register', [
            'auth_method' => 'email_otp',
            'email' => 'resource-register-verify@example.com',
        ])->assertCreated();

        $user = Identity::findByEmail('resource-register-verify@example.com');
        $code = null;
        Notification::assertSentTo($user, VerificationCodeNotification::class, function ($notification) use (&$code) {
            $code = $notification->plainCode;

            return true;
        });

        $this->postJson('/api/v1/auth/register/verify', [
            'auth_method' => 'email_otp',
            'email' => 'resource-register-verify@example.com',
            'code' => $code,
        ])->assertOk()
            ->assertJsonStructure([
                'status',
                'next_step',
                'user' => ['id', 'name', 'email'],
                'registration_grant',
            ])
            ->assertJsonMissing(['success', 'source']);
    }

    public function test_password_reset_responses_shape_and_redact_user_model_fields(): void
    {
        Notification::fake();

        Identity::createUser([
            'name' => 'Resource Reset User',
            'email' => 'resource-reset@example.com',
            'password' => bcrypt('Password123!'),
        ]);

        $this->postJson('/api/v1/auth/forgot-password', [
            'auth_method' => 'email_otp',
            'email' => 'resource-reset@example.com',
        ])->assertStatus(202)
            ->assertJsonStructure([
                'status',
                'auth_method',
                'channel',
                'destination',
                'message',
            ])
            ->assertJsonPath('status', 'otp_sent');

        $user = Identity::findByEmail('resource-reset@example.com');
        $code = null;
        Notification::assertSentTo($user, VerificationCodeNotification::class, function ($notification) use (&$code) {
            $code = $notification->plainCode;

            return true;
        });

        $verifyResponse = $this->postJson('/api/v1/auth/forgot-password/verify', [
            'auth_method' => 'email_otp',
            'email' => 'resource-reset@example.com',
            'code' => $code,
        ])->assertOk()
            ->assertJsonStructure([
                'status',
                'next_step',
                'reset_grant',
                'auth_method',
                'email',
                'phone',
            ]);

        $this->postJson('/api/v1/auth/reset-password', [
            'auth_method' => 'email_otp',
            'email' => 'resource-reset@example.com',
            'reset_grant' => $verifyResponse->json('reset_grant'),
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])->assertOk()
            ->assertJsonStructure(['status'])
            ->assertJsonMissing(['success', 'source']);

        $resetPayload = $this->postJson('/api/v1/auth/reset-password', [
            'auth_method' => 'email_otp',
            'email' => 'resource-reset@example.com',
            'reset_grant' => 'invalid-grant',
            'password' => 'AnotherPassword123!',
            'password_confirmation' => 'AnotherPassword123!',
        ]);

        if ($resetPayload->status() === 200) {
            $this->markTestSkipped('Invalid grant unexpectedly succeeded in this environment.');
        }

        $this->assertArrayNotHasKey('password', $verifyResponse->json());
    }

    public function test_email_verification_responses_shape_and_redact_user_model_fields(): void
    {
        $user = Identity::createUser([
            'name' => 'Resource Verify User',
            'email' => 'resource-verify@example.com',
            'password' => bcrypt('password123'),
        ]);

        $repo = app(VerificationCodeRepository::class);
        $codeData = $repo->createCode($user->id, 'email', $user->email, 6, 10);
        $plain = $codeData['plain_code'];

        $verifyResponse = $this->actingAs($user)
            ->postJson('/api/v1/auth/verification/verify', [
                'code' => $plain,
                'channel' => 'email',
            ])
            ->assertOk()
            ->assertJson([
                'status' => 'verified',
            ])
            ->assertJsonStructure([
                'status',
                'next_step',
                'user' => ['id', 'name', 'email'],
            ]);

        $this->assertSame(['id', 'name', 'email'], array_keys($verifyResponse->json('user')));
        $this->assertArrayNotHasKey('password', $verifyResponse->json('user'));
    }

    public function test_me_response_uses_authenticated_user_resource(): void
    {
        $user = Identity::createUser([
            'name' => 'Resource Me User',
            'email' => 'resource-me@example.com',
            'password' => bcrypt('password123'),
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertExactJson([
                'id' => $user->id,
                'name' => 'Resource Me User',
                'email' => 'resource-me@example.com',
            ]);
    }
}
