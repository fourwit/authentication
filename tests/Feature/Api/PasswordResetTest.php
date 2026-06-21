<?php

namespace Modules\Authentication\Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Modules\Authentication\Notifications\VerificationCodeNotification;
use Modules\Identity\Enums\UserStatus;
use Modules\Identity\Facades\Identity;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_endpoint_accepts_email(): void
    {
        $this->postJson('/api/v1/auth/forgot-password', [
            'auth_method' => 'link',
            'email' => 'missing@example.com',
        ])->assertOk();
    }

    public function test_forgot_password_endpoint_uses_default_method_when_omitted(): void
    {
        config()->set('authentication.password_reset.default_method', 'link');

        $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'missing@example.com',
        ])->assertOk();
    }

    public function test_forgot_password_endpoint_rejects_invalid_phone(): void
    {
        config()->set('authentication.phone_input.default_country', 'IN');

        $this->postJson('/api/v1/auth/forgot-password', [
            'auth_method' => 'phone_otp',
            'phone' => '12345',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['phone']);
    }

    public function test_email_otp_password_reset_can_be_verified_and_completed(): void
    {
        Notification::fake();

        Identity::createUser([
            'name' => 'Reset User',
            'email' => 'reset-otp@example.com',
            'password' => bcrypt('Password123!'),
        ]);

        $this->postJson('/api/v1/auth/forgot-password', [
            'auth_method' => 'email_otp',
            'email' => 'reset-otp@example.com',
        ])->assertStatus(202)
            ->assertJsonPath('status', 'otp_sent');

        $user = Identity::findByEmail('reset-otp@example.com');
        $code = null;
        Notification::assertSentTo($user, VerificationCodeNotification::class, function ($notification) use (&$code) {
            $code = $notification->plainCode;
            return true;
        });

        $verifyResponse = $this->postJson('/api/v1/auth/forgot-password/verify', [
            'auth_method' => 'email_otp',
            'email' => 'reset-otp@example.com',
            'code' => $code,
        ])->assertOk();

        $grant = $verifyResponse->json('reset_grant');

        $this->postJson('/api/v1/auth/reset-password', [
            'auth_method' => 'email_otp',
            'email' => 'reset-otp@example.com',
            'reset_grant' => $grant,
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])->assertOk();
    }

    public function test_email_otp_password_reset_still_sends_code_when_verification_flag_is_disabled(): void
    {
        Notification::fake();
        config()->set('authentication.verification.enabled', false);

        $user = Identity::createUser([
            'name' => 'Reset Disabled Verification User',
            'email' => 'reset-disabled@example.com',
            'password' => bcrypt('Password123!'),
        ]);

        $this->postJson('/api/v1/auth/forgot-password', [
            'auth_method' => 'email_otp',
            'email' => 'reset-disabled@example.com',
        ])->assertStatus(202)
            ->assertJsonPath('status', 'otp_sent');

        Notification::assertSentTo($user, VerificationCodeNotification::class);
    }

    public function test_suspended_account_is_blocked_from_api_password_reset(): void
    {
        Identity::createUser([
            'name' => 'Suspended Api Reset User',
            'email' => 'suspended-api-reset@example.com',
            'password' => bcrypt('Password123!'),
            'status' => UserStatus::SUSPENDED->value,
        ]);

        $this->postJson('/api/v1/auth/forgot-password', [
            'auth_method' => 'email_otp',
            'email' => 'suspended-api-reset@example.com',
        ])->assertStatus(403)
            ->assertJsonPath('message', 'Your account is suspended. Please contact support.');
    }

    public function test_pending_account_is_allowed_to_start_api_password_reset(): void
    {
        Notification::fake();

        $user = Identity::createUser([
            'name' => 'Pending Api Reset User',
            'email' => 'pending-api-reset@example.com',
            'password' => bcrypt('Password123!'),
            'status' => UserStatus::PENDING->value,
        ]);

        $this->postJson('/api/v1/auth/forgot-password', [
            'auth_method' => 'email_otp',
            'email' => 'pending-api-reset@example.com',
        ])->assertStatus(202)
            ->assertJsonPath('status', 'otp_sent');

        Notification::assertSentTo($user, VerificationCodeNotification::class);
    }
}
