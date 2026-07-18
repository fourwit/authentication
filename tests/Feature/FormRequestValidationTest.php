<?php

namespace Modules\Authentication\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Modules\Authentication\Notifications\VerificationCodeNotification;
use Modules\Identity\Facades\Identity;
use Tests\TestCase;

class FormRequestValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_login_otp_verify_requires_code(): void
    {
        $this->postJson('/api/v1/auth/login/verify', [
            'auth_method' => 'email_otp',
            'email' => 'missing@example.com',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['code']);
    }

    public function test_api_login_otp_resend_requires_email_for_email_otp(): void
    {
        $this->postJson('/api/v1/auth/login/verify/resend', [
            'auth_method' => 'email_otp',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_api_password_reset_otp_verify_requires_code(): void
    {
        $this->postJson('/api/v1/auth/forgot-password/verify', [
            'auth_method' => 'email_otp',
            'email' => 'missing@example.com',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['code']);
    }

    public function test_api_registration_otp_verify_requires_code(): void
    {
        $this->postJson('/api/v1/auth/register/verify', [
            'auth_method' => 'email_otp',
            'email' => 'missing@example.com',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['code']);
    }

    public function test_api_registration_otp_resend_does_not_require_code(): void
    {
        Notification::fake();

        $this->postJson('/api/v1/auth/register', [
            'auth_method' => 'email_otp',
            'email' => 'resend-validation@example.com',
        ])->assertCreated();

        $this->postJson('/api/v1/auth/register/verify/resend', [
            'auth_method' => 'email_otp',
            'email' => 'resend-validation@example.com',
        ])->assertOk();

        $user = Identity::findByEmail('resend-validation@example.com');
        Notification::assertSentTo($user, VerificationCodeNotification::class);
    }

    public function test_web_login_otp_verify_requires_code(): void
    {
        Notification::fake();

        Identity::createUser([
            'name' => 'Web Otp User',
            'email' => 'web-otp-validation@example.com',
            'password' => bcrypt('Password123!'),
        ]);

        $this->post('/auth/login', [
            'auth_method' => 'email_otp',
            'email' => 'web-otp-validation@example.com',
        ])->assertRedirect(route('authentication.login.verify'));

        $this->post('/auth/login/verify', [])
            ->assertSessionHasErrors(['code']);
    }

    public function test_web_email_verification_requires_code(): void
    {
        $user = Identity::createUser([
            'name' => 'Verify User',
            'email' => 'verify-validation@example.com',
            'password' => bcrypt('Password123!'),
        ]);

        $this->actingAs($user)
            ->post('/auth/verify-email', [])
            ->assertSessionHasErrors(['code']);
    }

    public function test_web_email_verification_resend_rejects_invalid_channel(): void
    {
        $user = Identity::createUser([
            'name' => 'Resend User',
            'email' => 'resend-validation@example.com',
            'password' => bcrypt('Password123!'),
        ]);

        $this->actingAs($user)
            ->post('/auth/verify-email/resend', [
                'channel' => 'sms',
            ])->assertSessionHasErrors(['channel']);
    }

    public function test_web_password_reset_otp_verify_requires_code(): void
    {
        Notification::fake();

        Identity::createUser([
            'name' => 'Reset Web User',
            'email' => 'reset-web-validation@example.com',
            'password' => bcrypt('Password123!'),
        ]);

        $this->post('/auth/forgot-password', [
            'auth_method' => 'email_otp',
            'email' => 'reset-web-validation@example.com',
        ])->assertRedirect(route('authentication.password.verify'));

        $this->post('/auth/forgot-password/verify', [])
            ->assertSessionHasErrors(['code']);
    }
}
