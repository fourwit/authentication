<?php

namespace Modules\Authentication\Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Modules\Authentication\Notifications\VerificationCodeNotification;
use Modules\Identity\Facades\Identity;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_registration_uses_default_method_when_auth_method_missing(): void
    {
        config()->set('authentication.registration.default_method', 'email_password');
        config()->set('authentication.password_policy.uncompromised', false);

        $this->postJson('/api/v1/auth/register', [
            'name' => 'Test User',
            'email' => 'register@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])->assertCreated();

        $this->assertNotNull(Identity::findByEmail('register@example.com'));
    }

    public function test_api_registration_validates_only_email_for_email_otp_method(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'auth_method' => 'email_otp',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['email'])
            ->assertJsonMissingValidationErrors(['password']);
    }

    public function test_api_registration_validates_only_phone_for_phone_otp_method(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'auth_method' => 'phone_otp',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['phone'])
            ->assertJsonMissingValidationErrors(['password', 'email']);
    }

    public function test_api_registration_accepts_email_otp_method_with_optional_fields(): void
    {
        config()->set('authentication.phone_input.default_country', 'IN');
        config()->set('authentication.registration.fields_per_method.email_otp', [
            'email' => ['required' => true],
            'name' => ['required' => false],
            'username' => ['required' => false],
            'phone' => ['required' => false],
            'first_name' => ['required' => false],
            'last_name' => ['required' => false],
        ]);

        $this->postJson('/api/v1/auth/register', [
            'auth_method' => 'email_otp',
            'email' => 'otp@example.com',
            'name' => 'Otp User',
            'username' => 'otp-user',
            'phone' => '9999999999',
            'first_name' => 'Otp',
            'last_name' => 'User',
        ])->assertCreated();

        $user = Identity::findByEmail('otp@example.com');

        $this->assertNotNull($user);
        $this->assertSame('otp-user', $user->username);
    }

    public function test_api_registration_normalizes_phone_before_user_creation(): void
    {
        config()->set('authentication.phone_input.default_country', 'IN');
        config()->set('authentication.phone_input.store_format', 'e164');
        config()->set('authentication.password_policy.uncompromised', false);
        config()->set('authentication.registration.fields_per_method.email_password.phone', ['required' => false]);

        $this->postJson('/api/v1/auth/register', [
            'auth_method' => 'email_password',
            'name' => 'Phone User',
            'email' => 'phone-register@example.com',
            'phone' => '09876 543210',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])->assertCreated();

        $user = Identity::findByEmail('phone-register@example.com');

        $this->assertNotNull($user);
        $this->assertSame('+919876543210', $user->phone);
    }

    public function test_api_registration_rejects_invalid_phone_for_default_country(): void
    {
        config()->set('authentication.phone_input.default_country', 'IN');
        config()->set('authentication.password_policy.uncompromised', false);
        config()->set('authentication.registration.fields_per_method.email_password.phone', ['required' => false]);

        $this->postJson('/api/v1/auth/register', [
            'auth_method' => 'email_password',
            'name' => 'Invalid Phone User',
            'email' => 'invalid-phone@example.com',
            'phone' => '12345',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['phone']);
    }

    public function test_unknown_auth_method_falls_back_to_default_method(): void
    {
        config()->set('authentication.registration.default_method', 'email_otp');
        config()->set('authentication.registration.fields_per_method.email_otp', [
            'email' => ['required' => true],
            'name' => ['required' => false],
            'first_name' => ['required' => false],
            'last_name' => ['required' => false],
            'username' => ['required' => false],
            'phone' => ['required' => false],
        ]);

        $this->postJson('/api/v1/auth/register', [
            'auth_method' => 'unknown_method',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['email'])
            ->assertJsonMissingValidationErrors(['phone', 'password']);
    }

    public function test_api_registration_reuses_existing_unverified_user_instead_of_creating_duplicate(): void
    {
        config()->set('authentication.registration.default_method', 'email_password');
        config()->set('authentication.password_policy.uncompromised', false);

        $user = Identity::createUser([
            'name' => 'Existing Api User',
            'email' => 'existing-api-unverified@example.com',
            'password' => bcrypt('Password123!'),
            'email_verified_at' => null,
        ]);

        $this->postJson('/api/v1/auth/register', [
            'name' => 'Existing Api User',
            'email' => 'existing-api-unverified@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])->assertCreated();

        $this->assertSame(1, DB::table('users')->where('email', 'existing-api-unverified@example.com')->count());
        $this->assertSame($user->id, Identity::findByEmail('existing-api-unverified@example.com')->id);
    }

    public function test_api_registration_rejects_existing_verified_email(): void
    {
        config()->set('authentication.registration.default_method', 'email_password');
        config()->set('authentication.password_policy.uncompromised', false);

        $user = Identity::createUser([
            'name' => 'Existing Verified Api User',
            'email' => 'existing-api-verified@example.com',
            'password' => bcrypt('Password123!'),
        ]);
        Identity::updateUser($user, ['email_verified_at' => now()]);

        $this->postJson('/api/v1/auth/register', [
            'name' => 'Existing Verified Api User',
            'email' => 'existing-api-verified@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])->assertStatus(422)
            ->assertJsonValidationErrors([
                'email' => 'An account with this email already exists. Please log in instead.',
            ]);
    }

    public function test_api_email_otp_registration_still_sends_verification_code_when_verification_flag_is_disabled(): void
    {
        Notification::fake();
        config()->set('authentication.verification.enabled', false);

        $this->postJson('/api/v1/auth/register', [
            'auth_method' => 'email_otp',
            'email' => 'api-otp-verification-required@example.com',
        ])->assertCreated();

        $user = Identity::findByEmail('api-otp-verification-required@example.com');

        $this->assertNotNull($user);
        Notification::assertSentTo($user, VerificationCodeNotification::class);
    }

    public function test_api_email_otp_registration_verification_returns_set_password_next_step(): void
    {
        Notification::fake();
        config()->set('authentication.after_otp_registration.prompt_for_password', true);
        config()->set('authentication.after_otp_registration.password_required', false);
        config()->set('authentication.registration.post_verification_profile_completion', false);

        $this->postJson('/api/v1/auth/register', [
            'auth_method' => 'email_otp',
            'email' => 'api-otp-register@example.com',
        ])->assertCreated();

        $user = Identity::findByEmail('api-otp-register@example.com');
        $code = null;
        Notification::assertSentTo($user, VerificationCodeNotification::class, function ($notification) use (&$code) {
            $code = $notification->plainCode;
            return true;
        });

        $this->postJson('/api/v1/auth/register/verify', [
            'auth_method' => 'email_otp',
            'email' => 'api-otp-register@example.com',
            'code' => $code,
        ])->assertOk()
            ->assertJson([
                'status' => 'verified',
                'next_step' => 'set_password',
            ]);
    }
}
