<?php

namespace Modules\Authentication\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Authentication\Support\PasswordPolicy;
use Modules\Identity\Facades\Identity;
use Tests\TestCase;

class PasswordPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_request_rejects_passwords_below_configured_min_length(): void
    {
        config()->set('authentication.registration.default_method', 'email_password');
        config()->set('authentication.password_policy.enabled', true);
        config()->set('authentication.password_policy.uncompromised', false);
        config()->set('authentication.password_policy.min_length', 12);

        $this->postJson('/api/v1/auth/register', [
            'name' => 'Short Password User',
            'email' => 'short-password@example.com',
            'password' => 'Ab1!',
            'password_confirmation' => 'Ab1!',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_register_request_enforces_configured_password_composition_rules(): void
    {
        config()->set('authentication.registration.default_method', 'email_password');
        config()->set('authentication.password_policy.enabled', true);
        config()->set('authentication.password_policy.uncompromised', false);
        config()->set('authentication.password_policy.min_length', 8);
        config()->set('authentication.password_policy.require_mixed_case', true);
        config()->set('authentication.password_policy.require_numbers', true);
        config()->set('authentication.password_policy.require_symbols', true);

        $this->postJson('/api/v1/auth/register', [
            'name' => 'Weak Password User',
            'email' => 'weak-password@example.com',
            'password' => 'password1',
            'password_confirmation' => 'password1',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_register_request_accepts_password_when_policy_is_satisfied(): void
    {
        config()->set('authentication.registration.default_method', 'email_password');
        config()->set('authentication.password_policy.enabled', true);
        config()->set('authentication.password_policy.uncompromised', false);

        $this->postJson('/api/v1/auth/register', [
            'name' => 'Strong Password User',
            'email' => 'strong-password@example.com',
            'password' => 'StrongPass1!',
            'password_confirmation' => 'StrongPass1!',
        ])->assertCreated();

        $this->assertNotNull(Identity::findByEmail('strong-password@example.com'));
    }

    public function test_reset_password_request_uses_shared_password_policy_rules(): void
    {
        config()->set('authentication.password_policy.enabled', true);
        config()->set('authentication.password_policy.uncompromised', false);
        config()->set('authentication.password_policy.require_symbols', true);

        $request = new \Modules\Authentication\Http\Requests\ResetPasswordRequest();
        $rules = $request->rules();

        $this->assertArrayHasKey('password', $rules);
        $this->assertContains('confirmed', $rules['password']);
        $this->assertTrue(
            collect($rules['password'])->contains(
                static fn (mixed $rule): bool => $rule instanceof \Illuminate\Validation\Rules\Password
            )
        );
    }

    public function test_password_policy_can_fall_back_to_minimal_rules_when_disabled(): void
    {
        config()->set('authentication.password_policy.enabled', false);

        $rules = PasswordPolicy::rules();

        $this->assertSame(['required', 'string', 'confirmed', 'min:8'], $rules);
    }
}
