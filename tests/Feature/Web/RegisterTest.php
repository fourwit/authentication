<?php

namespace Modules\Authentication\Tests\Feature\Web;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Modules\Authentication\Notifications\VerificationCodeNotification;
use Modules\Identity\Facades\Identity;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_page_uses_default_method_view(): void
    {
        config()->set('authentication.registration.default_method', 'email_password');
        config()->set('authentication.password_policy.enabled', true);
        config()->set('authentication.password_policy.strength_meter.enabled', true);
        config()->set('authentication.registration.show_password_strength_meter', true);

        $response = $this->get('/auth/register');

        $response->assertOk();
        $response->assertSee('Register with email and password.', false);
        $response->assertSeeInOrder([
            'name="name"',
            'name="email"',
            'name="password"',
        ], false);
        $response->assertSee('Password strength', false);
    }

    public function test_register_page_renders_email_otp_view_from_query_override(): void
    {
        $response = $this->get('/auth/register?auth_method=email_otp');

        $response->assertOk();
        $response->assertSee('Register with email OTP', false);
        $response->assertSee('name="auth_method" value="email_otp"', false);
        $response->assertSee('name="email"', false);
        $response->assertDontSee('name="name"', false);
        $response->assertDontSee('name="phone"', false);
        $response->assertDontSee('name="password"', false);
        $response->assertDontSee('Password strength', false);
    }

    public function test_register_page_renders_phone_otp_view_from_query_override(): void
    {
        $response = $this->get('/auth/register?auth_method=phone_otp');

        $response->assertOk();
        $response->assertSee('Register with phone OTP', false);
        $response->assertSee('name="auth_method" value="phone_otp"', false);
        $response->assertSee('name="phone"', false);
        $response->assertDontSee('name="password"', false);
        $response->assertDontSee('Password strength', false);
    }

    public function test_register_page_hides_strength_meter_when_registration_toggle_is_disabled(): void
    {
        config()->set('authentication.registration.default_method', 'email_password');
        config()->set('authentication.password_policy.enabled', true);
        config()->set('authentication.password_policy.strength_meter.enabled', true);
        config()->set('authentication.registration.show_password_strength_meter', false);

        $response = $this->get('/auth/register');

        $response->assertOk();
        $response->assertDontSee('Password strength', false);
    }

    public function test_register_page_hides_strength_meter_when_password_policy_meter_is_disabled(): void
    {
        config()->set('authentication.registration.default_method', 'email_password');
        config()->set('authentication.password_policy.enabled', true);
        config()->set('authentication.password_policy.strength_meter.enabled', false);
        config()->set('authentication.registration.show_password_strength_meter', true);

        $response = $this->get('/auth/register');

        $response->assertOk();
        $response->assertDontSee('Password strength', false);
    }

    public function test_register_page_renders_strength_meter_hints_from_policy_config(): void
    {
        config()->set('authentication.registration.default_method', 'email_password');
        config()->set('authentication.password_policy.enabled', true);
        config()->set('authentication.password_policy.strength_meter.enabled', true);
        config()->set('authentication.password_policy.strength_meter.show_hints', true);
        config()->set('authentication.registration.show_password_strength_meter', true);

        $response = $this->get('/auth/register');

        $response->assertOk();
        $response->assertSee('Password strength', false);
        $response->assertSee('data-password-meter-hints', false);
    }

    public function test_register_page_hides_disabled_password_policy_hints(): void
    {
        config()->set('authentication.registration.default_method', 'email_password');
        config()->set('authentication.password_policy.enabled', true);
        config()->set('authentication.password_policy.strength_meter.enabled', true);
        config()->set('authentication.password_policy.strength_meter.show_hints', true);
        config()->set('authentication.registration.show_password_strength_meter', true);
        config()->set('authentication.password_policy.require_mixed_case', false);
        config()->set('authentication.password_policy.require_numbers', false);
        config()->set('authentication.password_policy.require_symbols', true);

        $response = $this->get('/auth/register');

        $response->assertOk();
        $response->assertSee('At least one symbol', false);
        $response->assertDontSee('Uppercase and lowercase letters', false);
        $response->assertDontSee('At least one number', false);
    }

    public function test_register_post_validation_uses_selected_method_and_preserves_method_view(): void
    {
        $response = $this->from('/auth/register?auth_method=phone_otp')
            ->post('/auth/register', [
                'auth_method' => 'phone_otp',
                'name' => 'Phone User',
            ]);

        $response->assertRedirect('/auth/register?auth_method=phone_otp');
        $response->assertSessionHasErrors(['phone']);

        $this->followRedirects($response)
            ->assertSee('Register with phone OTP', false)
            ->assertSee('name="auth_method" value="phone_otp"', false);
    }

    public function test_register_page_keeps_phone_field_and_uses_plain_input_when_phone_input_is_disabled(): void
    {
        config()->set('authentication.phone_input.enabled', false);

        $response = $this->get('/auth/register?auth_method=phone_otp');

        $response->assertOk();
        $response->assertSee('Register with phone OTP', false);
        $response->assertSee('name="phone"', false);
        $response->assertSee('data-phone-library="none"', false);
        $response->assertDontSee('intl-tel-input@', false);
    }

    public function test_register_page_renders_phone_component_with_hidden_normalized_field_when_intl_tel_input_is_enabled(): void
    {
        config()->set('authentication.phone_input.enabled', true);
        config()->set('authentication.phone_input.library', 'intl-tel-input');
        config()->set('authentication.phone_input.cdn', true);
        config()->set('authentication.phone_input.version', '24.0.0');
        config()->set('authentication.registration.default_method', 'phone_otp');

        $response = $this->get('/auth/register');

        $response->assertOk();
        $response->assertSee('name="phone_normalized"', false);
        $response->assertSee('intl-tel-input@24.0.0/build/css/intlTelInput.css', false);
        $response->assertSee('data-phone-library="intl-tel-input"', false);
    }

    public function test_register_page_uses_tel_fallback_when_phone_library_is_none(): void
    {
        config()->set('authentication.phone_input.enabled', true);
        config()->set('authentication.phone_input.library', 'none');
        config()->set('authentication.registration.default_method', 'phone_otp');

        $response = $this->get('/auth/register');

        $response->assertOk();
        $response->assertSee('name="phone_normalized"', false);
        $response->assertSee('data-phone-library="none"', false);
        $response->assertDontSee('intl-tel-input@', false);
    }

    public function test_register_reuses_existing_unverified_user_and_redirects_to_verify_screen(): void
    {
        config()->set('authentication.registration.default_method', 'email_password');
        config()->set('authentication.password_policy.uncompromised', false);

        $user = Identity::createUser([
            'name' => 'Existing Unverified User',
            'email' => 'existing-unverified@example.com',
            'password' => bcrypt('Password123!'),
            'email_verified_at' => null,
        ]);

        $response = $this->post('/auth/register', [
            'auth_method' => 'email_password',
            'name' => 'Existing Unverified User',
            'email' => 'existing-unverified@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertRedirect(route('authentication.verify-email'));
        $response->assertSessionHas('status', "Welcome back! We've sent a fresh verification code to existing-unverified@example.com. Enter it below to activate your account.");
        $this->assertAuthenticatedAs($user->fresh());
        $this->assertSame(1, DB::table('users')->where('email', 'existing-unverified@example.com')->count());
    }

    public function test_email_password_registration_logs_in_directly_when_verification_is_disabled(): void
    {
        config()->set('authentication.verification.enabled', false);
        config()->set('authentication.registration.default_method', 'email_password');
        config()->set('authentication.password_policy.uncompromised', false);

        $response = $this->post('/auth/register', [
            'auth_method' => 'email_password',
            'name' => 'Direct Login User',
            'email' => 'direct-login@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertRedirect('/');
        $response->assertSessionHas('status', 'Your account has been created successfully.');
        $this->assertAuthenticated();
    }

    public function test_email_otp_registration_still_requires_verification_when_verification_flag_is_disabled(): void
    {
        Notification::fake();
        config()->set('authentication.verification.enabled', false);
        config()->set('authentication.registration.default_method', 'email_otp');

        $response = $this->post('/auth/register', [
            'auth_method' => 'email_otp',
            'email' => 'otp-verification-required@example.com',
        ]);

        $user = Identity::findByEmail('otp-verification-required@example.com');

        $response->assertRedirect(route('authentication.verify-email'));
        $response->assertSessionHas('status', "Welcome! We've just sent a verification code to otp-verification-required@example.com. Enter it below to activate your account.");
        $this->assertAuthenticatedAs($user);
        Notification::assertSentTo($user, VerificationCodeNotification::class);
    }

    public function test_email_otp_registration_verification_redirects_to_set_password_then_dashboard(): void
    {
        Notification::fake();
        config()->set('authentication.registration.default_method', 'email_otp');
        config()->set('authentication.after_otp_registration.prompt_for_password', true);
        config()->set('authentication.after_otp_registration.password_required', false);
        config()->set('authentication.registration.post_verification_profile_completion', false);

        $this->post('/auth/register', [
            'auth_method' => 'email_otp',
            'email' => 'otp-register@example.com',
        ])->assertRedirect(route('authentication.verify-email'));

        $user = Identity::findByEmail('otp-register@example.com');
        $code = null;
        Notification::assertSentTo($user, VerificationCodeNotification::class, function ($notification) use (&$code) {
            $code = $notification->plainCode;
            return true;
        });

        $this->actingAs($user)->post('/auth/verify-email', [
            'channel' => 'email',
            'code' => $code,
        ])->assertRedirect(route('auth.set-password'));

        $this->actingAs($user)->post('/auth/set-password', [
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])->assertRedirect('/');
    }

    public function test_email_otp_registration_can_skip_optional_password_and_redirect_to_profile_route(): void
    {
        Notification::fake();
        config()->set('authentication.registration.default_method', 'email_otp');
        config()->set('authentication.after_otp_registration.prompt_for_password', true);
        config()->set('authentication.after_otp_registration.password_required', false);
        config()->set('authentication.registration.post_verification_profile_completion', true);
        config()->set('authentication.registration.profile_completion_route', 'login');

        $this->post('/auth/register', [
            'auth_method' => 'email_otp',
            'email' => 'otp-skip@example.com',
        ]);

        $user = Identity::findByEmail('otp-skip@example.com');
        $code = null;
        Notification::assertSentTo($user, VerificationCodeNotification::class, function ($notification) use (&$code) {
            $code = $notification->plainCode;
            return true;
        });

        $this->actingAs($user)->post('/auth/verify-email', [
            'channel' => 'email',
            'code' => $code,
        ])->assertRedirect(route('auth.set-password'));

        $this->actingAs($user)->post('/auth/set-password/skip')
            ->assertRedirect(route('login'));
    }

    public function test_register_rejects_existing_verified_email_with_validation_error(): void
    {
        config()->set('authentication.registration.default_method', 'email_password');
        config()->set('authentication.password_policy.uncompromised', false);

        $user = Identity::createUser([
            'name' => 'Existing Verified User',
            'email' => 'existing-verified@example.com',
            'password' => bcrypt('Password123!'),
        ]);
        Identity::updateUser($user, ['email_verified_at' => now()]);

        $response = $this->from('/auth/register')->post('/auth/register', [
            'auth_method' => 'email_password',
            'name' => 'Existing Verified User',
            'email' => 'existing-verified@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertRedirect('/auth/register');
        $response->assertSessionHasErrors([
            'email' => 'An account with this email already exists. Please log in instead.',
        ]);
    }
}
