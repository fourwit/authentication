<?php

namespace Modules\Authentication\Tests\Feature\Web;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Modules\Authentication\Notifications\VerificationCodeNotification;
use Modules\Identity\Enums\UserStatus;
use Modules\Identity\Facades\Identity;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_page_uses_default_method_view(): void
    {
        config()->set('authentication.password_reset.default_method', 'link');

        $response = $this->get('/auth/forgot-password');

        $response->assertOk();
        $response->assertSee('send you a secure reset link', false);
        $response->assertSee('name="auth_method" value="link"', false);
        $response->assertSee('name="email"', false);
    }

    public function test_forgot_password_page_renders_email_otp_view_from_query_override(): void
    {
        $response = $this->get('/auth/forgot-password?auth_method=email_otp');

        $response->assertOk();
        $response->assertSee('send you a recovery code', false);
        $response->assertSee('name="auth_method" value="email_otp"', false);
        $response->assertSee('name="email"', false);
    }

    public function test_email_otp_password_reset_flow_verifies_code_and_redirects_to_reset_screen(): void
    {
        Notification::fake();

        Identity::createUser([
            'name' => 'Reset User',
            'email' => 'web-reset-otp@example.com',
            'password' => bcrypt('Password123!'),
        ]);

        $this->post('/auth/forgot-password', [
            'auth_method' => 'email_otp',
            'email' => 'web-reset-otp@example.com',
        ])->assertRedirect(route('authentication.password.verify'));

        $user = Identity::findByEmail('web-reset-otp@example.com');
        $code = null;
        Notification::assertSentTo($user, VerificationCodeNotification::class, function ($notification) use (&$code) {
            $code = $notification->plainCode;
            return true;
        });

        $this->get(route('authentication.password.verify'))
            ->assertOk()
            ->assertSee('Verify your recovery code', false);

        $this->post('/auth/forgot-password/verify', [
            'code' => $code,
        ])->assertRedirect(route('authentication.password.reset'));

        $this->get(route('authentication.password.reset'))
            ->assertOk()
            ->assertSee('Choose a new password for your account.', false)
            ->assertSee('name="reset_grant"', false);
    }

    public function test_email_otp_password_reset_rate_limit_stays_on_forgot_password_form(): void
    {
        Notification::fake();
        config()->set('authentication.password_reset.default_method', 'email_otp');
        config()->set('authentication.otp.max_per_hour', 1);
        config()->set('authentication.otp.resend_cooldown_seconds', 0);

        Identity::createUser([
            'name' => 'Rate Limit Reset User',
            'email' => 'web-reset-rate-limit@example.com',
            'password' => bcrypt('Password123!'),
        ]);

        $this->post('/auth/forgot-password', [
            'auth_method' => 'email_otp',
            'email' => 'web-reset-rate-limit@example.com',
        ])->assertRedirect(route('authentication.password.verify'));

        session()->forget('pending_password_reset');

        $response = $this->from('/auth/forgot-password?auth_method=email_otp')->post('/auth/forgot-password', [
            'auth_method' => 'email_otp',
            'email' => 'web-reset-rate-limit@example.com',
        ]);

        $response->assertRedirect('/auth/forgot-password?auth_method=email_otp');
        $response->assertSessionHas('error', 'Too many password reset code requests were made for this account. Please try again later.');
    }

    public function test_suspended_account_is_blocked_from_web_password_reset(): void
    {
        Identity::createUser([
            'name' => 'Suspended Reset User',
            'email' => 'suspended-reset@example.com',
            'password' => bcrypt('Password123!'),
            'status' => UserStatus::SUSPENDED->value,
        ]);

        $this->from('/auth/forgot-password?auth_method=email_otp')->post('/auth/forgot-password', [
            'auth_method' => 'email_otp',
            'email' => 'suspended-reset@example.com',
        ])->assertRedirect('/auth/forgot-password?auth_method=email_otp')
            ->assertSessionHas('error', 'Your account is suspended. Please contact support.');
    }

    public function test_inactive_account_is_blocked_from_web_password_reset(): void
    {
        Identity::createUser([
            'name' => 'Inactive Reset User',
            'email' => 'inactive-reset@example.com',
            'password' => bcrypt('Password123!'),
            'status' => UserStatus::INACTIVE->value,
        ]);

        $this->from('/auth/forgot-password?auth_method=email_otp')->post('/auth/forgot-password', [
            'auth_method' => 'email_otp',
            'email' => 'inactive-reset@example.com',
        ])->assertRedirect('/auth/forgot-password?auth_method=email_otp')
            ->assertSessionHas('error', 'This account is inactive. Please contact support.');
    }
}
