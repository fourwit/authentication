<?php

namespace Modules\Authentication\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Modules\Authentication\Notifications\VerificationCodeNotification;
use Modules\Authentication\Repositories\RegistrationGrantRepository;
use Modules\Identity\Facades\Identity;
use Tests\TestCase;

class RegistrationGrantTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('authentication.password_policy.uncompromised', false);
        config()->set('authentication.after_otp_registration.prompt_for_password', true);
        config()->set('authentication.after_otp_registration.password_required', false);
        config()->set('authentication.registration.post_verification_profile_completion', false);
    }

    public function test_api_otp_registration_returns_registration_grant(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/v1/auth/register', [
            'auth_method' => 'email_otp',
            'email' => 'grant-register@example.com',
        ])->assertCreated();

        $response->assertJsonStructure(['user', 'registration_grant']);
        $this->assertNotEmpty($response->json('registration_grant'));
    }

    public function test_api_set_registration_password_rejects_user_id_without_grant(): void
    {
        Notification::fake();

        $this->postJson('/api/v1/auth/register', [
            'auth_method' => 'email_otp',
            'email' => 'grant-user-id@example.com',
        ])->assertCreated();

        $user = Identity::findByEmail('grant-user-id@example.com');
        $this->assertNotNull($user);

        $this->postJson('/api/v1/auth/register/set-password', [
            'user_id' => $user->id,
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['registration_grant', 'user_id']);
    }

    public function test_api_set_registration_password_rejects_unverified_registration_grant(): void
    {
        Notification::fake();

        $registerResponse = $this->postJson('/api/v1/auth/register', [
            'auth_method' => 'email_otp',
            'email' => 'grant-unverified@example.com',
        ])->assertCreated();

        $unverifiedGrant = $registerResponse->json('registration_grant');
        $this->assertNotEmpty($unverifiedGrant);

        $this->postJson('/api/v1/auth/register/set-password', [
            'registration_grant' => $unverifiedGrant,
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])->assertStatus(422)
            ->assertJson(['message' => 'Invalid or expired registration grant.']);

        $this->assertNotNull(
            app(RegistrationGrantRepository::class)->getGrant($unverifiedGrant),
            'Unverified grants must not be consumed when password completion is rejected.'
        );
    }

    public function test_api_set_registration_password_accepts_verified_registration_grant(): void
    {
        Notification::fake();

        $this->postJson('/api/v1/auth/register', [
            'auth_method' => 'email_otp',
            'email' => 'grant-verified@example.com',
        ])->assertCreated();

        $user = Identity::findByEmail('grant-verified@example.com');
        $code = null;
        Notification::assertSentTo($user, VerificationCodeNotification::class, function ($notification) use (&$code) {
            $code = $notification->plainCode;

            return true;
        });

        $verifyResponse = $this->postJson('/api/v1/auth/register/verify', [
            'auth_method' => 'email_otp',
            'email' => 'grant-verified@example.com',
            'code' => $code,
        ])->assertOk();

        $verifiedGrant = $verifyResponse->json('registration_grant');
        $this->assertNotEmpty($verifiedGrant);

        $this->postJson('/api/v1/auth/register/set-password', [
            'registration_grant' => $verifiedGrant,
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])->assertOk()
            ->assertJson([
                'status' => 'password_set',
                'next_step' => 'dashboard',
            ]);
    }

    public function test_registration_grant_is_single_use(): void
    {
        Notification::fake();

        $this->postJson('/api/v1/auth/register', [
            'auth_method' => 'email_otp',
            'email' => 'grant-single-use@example.com',
        ])->assertCreated();

        $user = Identity::findByEmail('grant-single-use@example.com');
        $code = null;
        Notification::assertSentTo($user, VerificationCodeNotification::class, function ($notification) use (&$code) {
            $code = $notification->plainCode;

            return true;
        });

        $verifiedGrant = $this->postJson('/api/v1/auth/register/verify', [
            'auth_method' => 'email_otp',
            'email' => 'grant-single-use@example.com',
            'code' => $code,
        ])->assertOk()
            ->json('registration_grant');

        $payload = [
            'registration_grant' => $verifiedGrant,
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ];

        $successCount = 0;
        foreach ([1, 2] as $attempt) {
            $response = $this->postJson('/api/v1/auth/register/set-password', $payload);
            if ($response->status() === 200) {
                $successCount++;
            }
        }

        $this->assertSame(1, $successCount, 'Exactly one password completion request may consume a verified grant.');
        $this->assertNull(app(RegistrationGrantRepository::class)->getGrant($verifiedGrant));
    }

    public function test_expired_registration_grant_is_rejected(): void
    {
        config()->set('authentication.after_otp_registration.registration_grant_expires_minutes', 0);

        $user = Identity::createUser([
            'name' => 'Expired Grant User',
            'email' => 'expired-grant@example.com',
            'password' => bcrypt(str()->random(40)),
        ]);

        $repository = app(RegistrationGrantRepository::class);
        $grant = $repository->createGrant((int) $user->id, 'email_otp', verified: true);

        $this->travel(1)->seconds();

        $this->postJson('/api/v1/auth/register/set-password', [
            'registration_grant' => $grant,
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])->assertStatus(422)
            ->assertJson(['message' => 'Invalid or expired registration grant.']);
    }

    public function test_registration_grant_repository_consumption_is_exclusive(): void
    {
        $user = Identity::createUser([
            'name' => 'Exclusive Grant User',
            'email' => 'exclusive-grant@example.com',
            'password' => bcrypt(str()->random(40)),
        ]);

        $repository = app(RegistrationGrantRepository::class);
        $grant = $repository->createGrant((int) $user->id, 'email_otp', verified: true);

        $results = [
            $repository->consumeGrantForCompletion($grant),
            $repository->consumeGrantForCompletion($grant),
        ];

        $successes = array_filter($results, fn ($result) => $result !== null);

        $this->assertCount(1, $successes, 'Only one consume attempt may succeed for a verified grant.');
        $this->assertSame((int) $user->id, reset($successes)['user_id']);
        $this->assertNull($repository->getGrant($grant));
    }

    public function test_unverified_grant_is_not_consumed_on_failed_completion_attempt(): void
    {
        $user = Identity::createUser([
            'name' => 'Unverified Grant User',
            'email' => 'unverified-grant@example.com',
            'password' => bcrypt(str()->random(40)),
        ]);

        $repository = app(RegistrationGrantRepository::class);
        $grant = $repository->createGrant((int) $user->id, 'email_otp', verified: false);

        $result = $repository->consumeGrantForCompletion($grant);

        $this->assertNull($result);
        $this->assertNotNull($repository->getGrant($grant));
        $this->assertFalse((bool) $repository->getGrant($grant)['verified']);
    }
}
