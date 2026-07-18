<?php

namespace Modules\Authentication\Tests\Unit\Actions;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Mockery;
use Modules\Authentication\Actions\InitializeOtpRegistrationAction;
use Modules\Authentication\Actions\IssueRegistrationGrantAction;
use Modules\Authentication\Actions\RegisterUserAction;
use Modules\Authentication\Actions\ResendRegistrationOtpAction;
use Modules\Authentication\Actions\SetRegistrationPasswordAction;
use Modules\Authentication\Actions\SkipRegistrationPasswordAction;
use Modules\Authentication\Actions\VerifyRegistrationOtpAction;
use Modules\Authentication\DTOs\RegisterUserData;
use Modules\Authentication\Events\UserRegistered;
use Modules\Authentication\Exceptions\InvalidCredentialsException;
use Modules\Authentication\Repositories\RegistrationGrantRepository;
use Modules\Authentication\Services\RegistrationFollowUpService;
use Modules\Authentication\Services\VerificationCodeService;
use Modules\Identity\Facades\Identity;
use Tests\TestCase;

class RegistrationActionsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('authentication.after_otp_registration.prompt_for_password', true);
        config()->set('authentication.after_otp_registration.password_required', false);
        config()->set('authentication.registration.post_verification_profile_completion', false);
    }

    public function test_register_user_action_creates_user_dispatches_event_and_returns_payload(): void
    {
        Event::fake([UserRegistered::class]);

        $result = app(RegisterUserAction::class)->execute(
            RegisterUserData::fromArray([
                'auth_method' => 'email_otp',
                'email' => 'register-action@example.com',
            ]),
            'api'
        );

        $this->assertTrue($result['was_created']);
        $this->assertFalse($result['reused_unverified']);
        $this->assertSame('register-action@example.com', $result['user']->email);
        Event::assertDispatched(UserRegistered::class, fn (UserRegistered $event) => $event->payload->source === 'api');
    }

    public function test_register_user_action_reuses_unverified_user_without_dispatching_user_registered(): void
    {
        Event::fake([UserRegistered::class]);

        $existing = Identity::createUser([
            'name' => 'Existing Unverified',
            'email' => 'reuse-register@example.com',
            'password' => bcrypt(str()->random(40)),
        ]);

        $result = app(RegisterUserAction::class)->execute(
            RegisterUserData::fromArray([
                'auth_method' => 'email_otp',
                'email' => 'reuse-register@example.com',
            ]),
            'api'
        );

        $this->assertFalse($result['was_created']);
        $this->assertTrue($result['reused_unverified']);
        $this->assertSame($existing->id, $result['user']->id);
        Event::assertNotDispatched(UserRegistered::class);
    }

    public function test_register_user_action_rejects_verified_existing_email(): void
    {
        Event::fake([UserRegistered::class]);

        Identity::createUser([
            'name' => 'Verified Existing',
            'email' => 'verified-existing@example.com',
            'password' => bcrypt('password123'),
            'email_verified_at' => now(),
        ]);

        $this->expectException(ValidationException::class);

        try {
            app(RegisterUserAction::class)->execute(
                RegisterUserData::fromArray([
                    'auth_method' => 'email_password',
                    'email' => 'verified-existing@example.com',
                    'password' => 'Password123!',
                ]),
                'api'
            );
        } finally {
            Event::assertNotDispatched(UserRegistered::class);
        }
    }

    public function test_initialize_otp_registration_action_sets_follow_up_metadata(): void
    {
        $user = Identity::createUser([
            'name' => 'Init Otp User',
            'email' => 'init-otp@example.com',
            'password' => bcrypt(str()->random(40)),
        ]);

        app(InitializeOtpRegistrationAction::class)->execute($user, 'email_otp');

        $fresh = $user->fresh();
        $this->assertSame('email_otp', Identity::getMetadata($fresh, RegistrationFollowUpService::META_AUTH_METHOD));
        $this->assertTrue((bool) Identity::getMetadata($fresh, RegistrationFollowUpService::META_PASSWORD_PENDING));
        $this->assertTrue((bool) Identity::getMetadata($fresh, RegistrationFollowUpService::META_PASSWORD_MISSING));
    }

    public function test_verify_registration_otp_action_returns_verified_payload_with_grant(): void
    {
        $user = Identity::createUser([
            'name' => 'Verify Reg Otp',
            'email' => 'verify-reg-otp@example.com',
            'password' => bcrypt(str()->random(40)),
        ]);

        Identity::setMetadata($user, RegistrationFollowUpService::META_AUTH_METHOD, 'email_otp');
        Identity::setMetadata($user, RegistrationFollowUpService::META_PASSWORD_PENDING, true);

        $verificationCodeService = Mockery::mock(VerificationCodeService::class);
        $verificationCodeService->shouldReceive('verifyCode')
            ->once()
            ->andReturnTrue();

        $issueGrant = Mockery::mock(IssueRegistrationGrantAction::class);
        $issueGrant->shouldReceive('execute')
            ->once()
            ->with($user->id, 'email_otp', true)
            ->andReturn('verified-grant-token');

        $action = new VerifyRegistrationOtpAction(
            app(RegistrationFollowUpService::class),
            $verificationCodeService,
            $issueGrant,
        );

        $result = $action->execute([
            'auth_method' => 'email_otp',
            'email' => 'verify-reg-otp@example.com',
            'code' => '123456',
        ], 'api');

        $this->assertSame('verified', $result['status']);
        $this->assertSame('verified-grant-token', $result['registration_grant']);
        $this->assertSame('set_password', $result['next_step']);
        $this->assertNotNull($result['user']);
    }

    public function test_verify_registration_otp_action_throws_when_user_is_missing(): void
    {
        $verificationCodeService = Mockery::mock(VerificationCodeService::class);
        $verificationCodeService->shouldNotReceive('verifyCode');

        $action = new VerifyRegistrationOtpAction(
            app(RegistrationFollowUpService::class),
            $verificationCodeService,
            app(IssueRegistrationGrantAction::class),
        );

        $this->expectException(InvalidCredentialsException::class);

        $action->execute([
            'auth_method' => 'email_otp',
            'email' => 'missing-reg-otp@example.com',
            'code' => '123456',
        ], 'api');
    }

    public function test_set_registration_password_action_updates_password_and_returns_next_step(): void
    {
        $user = Identity::createUser([
            'name' => 'Set Password User',
            'email' => 'set-password@example.com',
            'password' => bcrypt(str()->random(40)),
        ]);

        Identity::setMetadata($user, RegistrationFollowUpService::META_AUTH_METHOD, 'email_otp');
        Identity::setMetadata($user, RegistrationFollowUpService::META_PASSWORD_PENDING, true);
        Identity::setMetadata($user, RegistrationFollowUpService::META_PASSWORD_MISSING, true);

        $grant = app(RegistrationGrantRepository::class)->createGrant($user->id, 'email_otp', verified: true);

        $result = app(SetRegistrationPasswordAction::class)->execute([
            'registration_grant' => $grant,
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ], 'api');

        $this->assertSame('password_set', $result['status']);
        $this->assertSame('dashboard', $result['next_step']);
        $this->assertFalse((bool) Identity::getMetadata($result['user'], RegistrationFollowUpService::META_PASSWORD_PENDING));
    }

    public function test_skip_registration_password_action_marks_password_missing_and_returns_next_step(): void
    {
        $user = Identity::createUser([
            'name' => 'Skip Password User',
            'email' => 'skip-password@example.com',
            'password' => bcrypt(str()->random(40)),
        ]);

        Identity::setMetadata($user, RegistrationFollowUpService::META_AUTH_METHOD, 'email_otp');
        Identity::setMetadata($user, RegistrationFollowUpService::META_PASSWORD_PENDING, true);
        Identity::setMetadata($user, RegistrationFollowUpService::META_PASSWORD_MISSING, true);

        $grant = app(RegistrationGrantRepository::class)->createGrant($user->id, 'email_otp', verified: true);

        $result = app(SkipRegistrationPasswordAction::class)->execute([
            'registration_grant' => $grant,
        ], 'web');

        $this->assertSame('password_skipped', $result['status']);
        $this->assertSame('dashboard', $result['next_step']);
        $this->assertTrue((bool) Identity::getMetadata($result['user'], RegistrationFollowUpService::META_PASSWORD_MISSING));
    }

    public function test_resend_registration_otp_action_delegates_to_verification_infrastructure(): void
    {
        $user = Identity::createUser([
            'name' => 'Resend Reg Otp',
            'email' => 'resend-reg-otp@example.com',
            'password' => bcrypt(str()->random(40)),
        ]);

        Identity::setMetadata($user, RegistrationFollowUpService::META_AUTH_METHOD, 'email_otp');

        $verificationCodeService = Mockery::mock(VerificationCodeService::class);
        $verificationCodeService->shouldReceive('resendCode')
            ->once()
            ->with($user->id, 'email', 'api', 'register')
            ->andReturn(['status' => 'sent', 'channel' => 'email']);

        $action = new ResendRegistrationOtpAction(
            app(RegistrationFollowUpService::class),
            $verificationCodeService,
        );

        $result = $action->execute([
            'auth_method' => 'email_otp',
            'email' => 'resend-reg-otp@example.com',
        ], 'api');

        $this->assertSame('sent', $result['status']);
        $this->assertSame('email', $result['channel']);
    }
}
