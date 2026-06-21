<?php

namespace Modules\Authentication\Tests\Feature\Web;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Modules\Authentication\Notifications\VerificationCodeNotification;
use Modules\Identity\Enums\UserStatus;
use Modules\Identity\Facades\Identity;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_uses_default_method_view(): void
    {
        config()->set('authentication.login.default_method', 'email_password');
        config()->set('authentication.login.show_alternative_methods', true);
        config()->set('authentication.login.alternative_methods', ['email_otp']);

        $response = $this->get('/auth/login');

        $response->assertOk();
        $response->assertSee('Access your account with your email or phone and password.', false);
        $response->assertSee('name="auth_method" value="email_password"', false);
        $response->assertSeeInOrder([
            'name="email"',
            'name="password"',
            'name="remember"',
        ], false);
        $response->assertSee('Sign in with email code instead', false);
    }

    public function test_login_page_renders_email_otp_view_from_query_override(): void
    {
        config()->set('authentication.login.alternative_methods', ['email_otp']);

        $response = $this->get('/auth/login?auth_method=email_otp');

        $response->assertOk();
        $response->assertSee('Continue with your email address and receive a one-time code.', false);
        $response->assertSee('name="auth_method" value="email_otp"', false);
        $response->assertSee('name="email"', false);
        $response->assertDontSee('name="password"', false);
        $response->assertSee('name="remember"', false);
    }

    public function test_login_page_does_not_open_phone_otp_view_when_phone_is_not_an_allowed_alternative(): void
    {
        config()->set('authentication.phone_input.enabled', true);
        config()->set('authentication.login.default_method', 'email_password');
        config()->set('authentication.login.alternative_methods', ['email_otp']);
        config()->set('authentication.login.show_alternative_methods', true);

        $response = $this->get('/auth/login?auth_method=phone_otp');

        $response->assertOk();
        $response->assertSee('Access your account with your email or phone and password.', false);
        $response->assertSee('name="auth_method" value="email_password"', false);
        $response->assertDontSee('name="phone"', false);
        $response->assertSee('name="password"', false);
        $response->assertSee('name="remember"', false);
    }

    public function test_login_page_renders_phone_otp_view_when_phone_is_an_allowed_alternative(): void
    {
        config()->set('authentication.phone_input.enabled', true);
        config()->set('authentication.login.default_method', 'email_password');
        config()->set('authentication.login.alternative_methods', ['phone_otp']);
        config()->set('authentication.login.show_alternative_methods', true);

        $response = $this->get('/auth/login?auth_method=phone_otp');

        $response->assertOk();
        $response->assertSee('Continue with your phone number and receive a one-time code.', false);
        $response->assertSee('name="auth_method" value="phone_otp"', false);
        $response->assertSee('name="phone"', false);
        $response->assertDontSee('name="password"', false);
    }

    public function test_login_page_hides_alternative_links_when_display_flag_is_disabled(): void
    {
        config()->set('authentication.login.default_method', 'email_password');
        config()->set('authentication.login.alternative_methods', ['email_otp']);
        config()->set('authentication.login.show_alternative_methods', false);

        $response = $this->get('/auth/login');

        $response->assertOk();
        $response->assertDontSee('Sign in with email code instead', false);
    }

    public function test_login_page_hides_alternative_links_when_no_alternative_methods_are_configured(): void
    {
        config()->set('authentication.login.default_method', 'email_password');
        config()->set('authentication.login.alternative_methods', []);
        config()->set('authentication.login.show_alternative_methods', true);

        $response = $this->get('/auth/login');

        $response->assertOk();
        $response->assertDontSee('Sign in with email code instead', false);
        $response->assertDontSee('Sign in with phone code instead', false);
    }

    public function test_login_page_hides_remember_when_global_toggle_is_disabled(): void
    {
        config()->set('authentication.login.remember_me', false);

        $response = $this->get('/auth/login');

        $response->assertOk();
        $response->assertDontSee('name="remember"', false);
    }

    public function test_login_post_validation_preserves_selected_method_view(): void
    {
        config()->set('authentication.phone_input.enabled', true);
        config()->set('authentication.login.default_method', 'email_password');
        config()->set('authentication.login.alternative_methods', ['phone_otp']);
        config()->set('authentication.login.show_alternative_methods', true);

        $response = $this->from('/auth/login?auth_method=phone_otp')
            ->post('/auth/login', [
                'auth_method' => 'phone_otp',
            ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors(['phone']);

        $this->followRedirects($response)
            ->assertSee('Continue with your phone number and receive a one-time code.', false)
            ->assertSee('name="auth_method" value="phone_otp"', false);
    }

    public function test_login_rejects_email_otp_method_with_clear_message(): void
    {
        Identity::createUser([
            'name' => 'Otp Login User',
            'email' => 'otp@example.com',
            'password' => bcrypt('Password123!'),
        ]);

        $response = $this->from('/auth/login')
            ->post('/auth/login', [
                'auth_method' => 'email_otp',
                'email' => 'otp@example.com',
            ]);

        $response->assertRedirect(route('authentication.login.verify'));
    }

    public function test_email_otp_login_can_send_code_and_complete_web_login(): void
    {
        Notification::fake();

        $user = Identity::createUser([
            'name' => 'Otp Login User',
            'email' => 'otp-login@example.com',
            'password' => bcrypt('Password123!'),
        ]);

        $this->post('/auth/login', [
            'auth_method' => 'email_otp',
            'email' => 'otp-login@example.com',
        ])->assertRedirect(route('authentication.login.verify'));

        $this->get(route('authentication.login.verify'))
            ->assertOk()
            ->assertSee('Verify your sign in', false)
            ->assertSee('otp-login@example.com', false)
            ->assertSee('Resend code', false);

        $code = null;
        Notification::assertSentTo($user, VerificationCodeNotification::class, function ($notification) use (&$code) {
            $code = $notification->plainCode;
            return true;
        });

        $this->post('/auth/login/verify', [
            'code' => $code,
        ])->assertRedirect('/');

        $this->assertAuthenticated();
    }

    public function test_email_otp_login_still_sends_code_for_web_when_verification_flag_is_disabled(): void
    {
        Notification::fake();
        config()->set('authentication.verification.enabled', false);

        $user = Identity::createUser([
            'name' => 'Otp Web Disabled Flag User',
            'email' => 'otp-web-disabled@example.com',
            'password' => bcrypt('Password123!'),
        ]);

        $this->post('/auth/login', [
            'auth_method' => 'email_otp',
            'email' => 'otp-web-disabled@example.com',
        ])->assertRedirect(route('authentication.login.verify'));

        Notification::assertSentTo($user, VerificationCodeNotification::class);
    }

    public function test_email_otp_login_redirects_to_set_password_when_registration_follow_up_is_pending(): void
    {
        Notification::fake();
        config()->set('authentication.after_otp_registration.prompt_for_password', true);
        config()->set('authentication.after_otp_registration.password_required', false);

        $user = Identity::createUser([
            'name' => 'Pending Follow Up User',
            'email' => 'pending-followup@example.com',
            'password' => bcrypt('TemporaryPassword123!'),
        ]);

        Identity::setMetadata($user, 'registration_auth_method', 'email_otp');
        Identity::setMetadata($user, 'registration_password_pending', true);
        Identity::forgetMetadata($user, 'registration_flow_completed_at');

        $this->post('/auth/login', [
            'auth_method' => 'email_otp',
            'email' => 'pending-followup@example.com',
        ])->assertRedirect(route('authentication.login.verify'));

        $code = null;
        Notification::assertSentTo($user, VerificationCodeNotification::class, function ($notification) use (&$code) {
            $code = $notification->plainCode;
            return true;
        });

        $this->post('/auth/login/verify', [
            'code' => $code,
        ])->assertRedirect(route('auth.set-password'));

        $this->assertAuthenticatedAs($user->fresh());
    }

    public function test_profile_page_shows_set_password_reminder_when_password_is_missing(): void
    {
        $user = Identity::createUser([
            'name' => 'Missing Password User',
            'email' => 'missing-password@example.com',
            'password' => bcrypt('TemporaryPassword123!'),
        ]);

        Identity::setMetadata($user, 'registration_auth_method', 'email_otp');
        Identity::setMetadata($user, 'registration_password_pending', false);
        Identity::setMetadata($user, 'registration_password_missing', true);
        Identity::setMetadata($user, 'registration_flow_completed_at', now()->toISOString());

        $this->actingAs($user)
            ->get(route('authentication.me'))
            ->assertOk()
            ->assertSee('Your account does not have a password yet.', false)
            ->assertSee('Set password', false)
            ->assertDontSee('Change password', false);
    }

    public function test_set_password_page_is_accessible_from_profile_when_password_is_still_missing(): void
    {
        $user = Identity::createUser([
            'name' => 'Profile Set Password User',
            'email' => 'profile-set-password@example.com',
            'password' => bcrypt('TemporaryPassword123!'),
        ]);

        Identity::setMetadata($user, 'registration_auth_method', 'email_otp');
        Identity::setMetadata($user, 'registration_password_pending', false);
        Identity::setMetadata($user, 'registration_password_missing', true);
        Identity::setMetadata($user, 'registration_flow_completed_at', now()->toISOString());

        $this->actingAs($user)
            ->get(route('auth.set-password'))
            ->assertOk()
            ->assertSee('Set your password', false);
    }

    public function test_email_password_login_still_works_for_web(): void
    {
        config()->set('authentication.login.default_method', 'email_password');

        Identity::createUser([
            'name' => 'Web Login User',
            'email' => 'web-login@example.com',
            'password' => bcrypt('Password123!'),
        ]);

        $response = $this->post('/auth/login', [
            'auth_method' => 'email_password',
            'email' => 'web-login@example.com',
            'password' => 'Password123!',
        ]);

        $response->assertRedirect('/');
        $this->assertAuthenticated();
    }

    public function test_suspended_account_is_blocked_from_web_login(): void
    {
        Identity::createUser([
            'name' => 'Suspended User',
            'email' => 'suspended-login@example.com',
            'password' => bcrypt('Password123!'),
            'status' => UserStatus::SUSPENDED->value,
        ]);

        $this->from('/auth/login')->post('/auth/login', [
            'auth_method' => 'email_password',
            'email' => 'suspended-login@example.com',
            'password' => 'Password123!',
        ])->assertRedirect('/auth/login')
            ->assertSessionHas('error', 'Your account is suspended. Please contact support.');
    }

    public function test_inactive_account_is_blocked_from_web_login(): void
    {
        Identity::createUser([
            'name' => 'Inactive User',
            'email' => 'inactive-login@example.com',
            'password' => bcrypt('Password123!'),
            'status' => UserStatus::INACTIVE->value,
        ]);

        $this->from('/auth/login')->post('/auth/login', [
            'auth_method' => 'email_password',
            'email' => 'inactive-login@example.com',
            'password' => 'Password123!',
        ])->assertRedirect('/auth/login')
            ->assertSessionHas('error', 'This account is inactive. Please contact support.');
    }

    public function test_pending_account_can_still_login_to_complete_steps(): void
    {
        config()->set('authentication.login.default_method', 'email_password');

        Identity::createUser([
            'name' => 'Pending User',
            'email' => 'pending-login@example.com',
            'password' => bcrypt('Password123!'),
            'status' => UserStatus::PENDING->value,
        ]);

        $this->post('/auth/login', [
            'auth_method' => 'email_password',
            'email' => 'pending-login@example.com',
            'password' => 'Password123!',
        ])->assertRedirect('/');

        $this->assertAuthenticated();
    }
}
