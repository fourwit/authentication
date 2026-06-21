<?php

namespace Modules\Authentication\Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Modules\Authentication\Notifications\VerificationCodeNotification;
use Modules\Authentication\Support\IdentityUserLookup;
use Modules\Identity\Enums\UserStatus;
use Modules\Identity\Facades\Identity;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_via_api(): void
    {
        config()->set('authentication.login.default_method', 'email_password');

        $user = Identity::createUser([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
        ]);

        $this->postJson('/api/v1/auth/login', [
            'auth_method' => 'email_password',
            'email' => 'test@example.com',
            'password' => 'password123',
        ])->assertOk();
    }

    public function test_phone_login_identifier_is_normalized_for_lookup(): void
    {
        config()->set('authentication.phone_input.enabled', true);
        config()->set('authentication.phone_input.default_country', 'IN');
        config()->set('authentication.phone_input.store_format', 'e164');

        $user = Identity::createUser([
            'name' => 'Phone Login User',
            'email' => 'phone-login@example.com',
            'phone' => '+919876543210',
            'password' => bcrypt('password123'),
        ]);

        $resolved = IdentityUserLookup::findByPhone('09876 543210');

        $this->assertNotNull($resolved);
        $this->assertSame($user->id, $resolved->id);
    }

    public function test_login_defaults_to_configured_method_when_auth_method_missing(): void
    {
        config()->set('authentication.login.default_method', 'email_password');

        Identity::createUser([
            'name' => 'Default Login User',
            'email' => 'default-login@example.com',
            'password' => bcrypt('password123'),
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'default-login@example.com',
            'password' => 'password123',
        ])->assertOk();
    }

    public function test_email_otp_login_sends_code_via_api_and_can_be_verified(): void
    {
        Notification::fake();

        $user = Identity::createUser([
            'name' => 'Email Otp Api User',
            'email' => 'otp-api@example.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'auth_method' => 'email_otp',
            'email' => 'otp-api@example.com',
        ])->assertStatus(202);

        $code = null;
        Notification::assertSentTo($user, VerificationCodeNotification::class, function ($notification) use (&$code) {
            $code = $notification->plainCode;
            return true;
        });

        $this->postJson('/api/v1/auth/login/verify', [
            'auth_method' => 'email_otp',
            'email' => 'otp-api@example.com',
            'code' => $code,
        ])->assertOk();
    }

    public function test_email_otp_login_still_sends_code_when_verification_flag_is_disabled(): void
    {
        Notification::fake();
        config()->set('authentication.verification.enabled', false);

        $user = Identity::createUser([
            'name' => 'Email Otp Disabled Flag User',
            'email' => 'otp-disabled-flag@example.com',
            'password' => bcrypt('password123'),
        ]);

        $this->postJson('/api/v1/auth/login', [
            'auth_method' => 'email_otp',
            'email' => 'otp-disabled-flag@example.com',
        ])->assertStatus(202);

        Notification::assertSentTo($user, VerificationCodeNotification::class);
    }

    public function test_email_otp_login_reissues_a_fresh_code_after_cooldown(): void
    {
        Notification::fake();

        $user = Identity::createUser([
            'name' => 'Email Otp Retry User',
            'email' => 'otp-retry@example.com',
            'password' => bcrypt('password123'),
        ]);

        $this->postJson('/api/v1/auth/login', [
            'auth_method' => 'email_otp',
            'email' => 'otp-retry@example.com',
        ])->assertStatus(202);

        Notification::assertSentToTimes($user, VerificationCodeNotification::class, 1);

        $this->travel(config('authentication.otp.resend_cooldown_seconds', 60) + 1)->seconds();

        $this->postJson('/api/v1/auth/login', [
            'auth_method' => 'email_otp',
            'email' => 'otp-retry@example.com',
        ])->assertStatus(202);

        Notification::assertSentToTimes($user, VerificationCodeNotification::class, 2);
    }

    public function test_phone_otp_login_validates_only_phone(): void
    {
        config()->set('authentication.phone_input.enabled', true);
        config()->set('authentication.phone_input.default_country', 'IN');
        config()->set('authentication.login.default_method', 'email_password');
        config()->set('authentication.login.alternative_methods', ['phone_otp']);
        config()->set('authentication.login.show_alternative_methods', true);

        $this->postJson('/api/v1/auth/login', [
            'auth_method' => 'phone_otp',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['phone']);
    }

    public function test_suspended_account_is_blocked_from_api_login(): void
    {
        config()->set('authentication.login.default_method', 'email_password');

        Identity::createUser([
            'name' => 'Suspended Api User',
            'email' => 'suspended-api-login@example.com',
            'password' => bcrypt('password123'),
            'status' => UserStatus::SUSPENDED->value,
        ]);

        $this->postJson('/api/v1/auth/login', [
            'auth_method' => 'email_password',
            'email' => 'suspended-api-login@example.com',
            'password' => 'password123',
        ])->assertStatus(403)
            ->assertJsonPath('message', 'Your account is suspended. Please contact support.');
    }

    public function test_inactive_account_is_blocked_from_api_login(): void
    {
        config()->set('authentication.login.default_method', 'email_password');

        Identity::createUser([
            'name' => 'Inactive Api User',
            'email' => 'inactive-api-login@example.com',
            'password' => bcrypt('password123'),
            'status' => UserStatus::INACTIVE->value,
        ]);

        $this->postJson('/api/v1/auth/login', [
            'auth_method' => 'email_password',
            'email' => 'inactive-api-login@example.com',
            'password' => 'password123',
        ])->assertStatus(403)
            ->assertJsonPath('message', 'This account is inactive. Please contact support.');
    }
}
