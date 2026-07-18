<?php

namespace Modules\Authentication\Tests\Unit\Actions;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Mockery;
use Modules\Authentication\Actions\ResetPasswordWithLinkAction;
use Modules\Authentication\Actions\ResetPasswordWithOtpAction;
use Modules\Authentication\Actions\SendPasswordResetLinkAction;
use Modules\Authentication\Actions\SendPasswordResetOtpAction;
use Modules\Authentication\Actions\VerifyPasswordResetOtpAction;
use Modules\Authentication\DTOs\PasswordResetRequestData;
use Modules\Authentication\DTOs\ResetPasswordData;
use Modules\Authentication\Events\PasswordResetCompleted;
use Modules\Authentication\Events\PasswordResetRequested;
use Modules\Authentication\Exceptions\InvalidPasswordResetTokenException;
use Modules\Authentication\Repositories\PasswordResetRepository;
use Modules\Authentication\Services\TokenService;
use Modules\Authentication\Services\VerificationCodeService;
use Modules\Identity\Facades\Identity;
use Tests\TestCase;

class PasswordResetActionsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_send_password_reset_link_action_dispatches_password_reset_requested(): void
    {
        Event::fake([PasswordResetRequested::class]);

        $broker = Mockery::mock();
        $broker->shouldReceive('sendResetLink')
            ->once()
            ->andReturn(Password::RESET_LINK_SENT);

        Password::shouldReceive('broker')->once()->andReturn($broker);

        $result = app(SendPasswordResetLinkAction::class)->execute(
            PasswordResetRequestData::fromArray([
                'auth_method' => 'link',
                'email' => 'reset-link@example.com',
            ]),
            'api'
        );

        $this->assertSame(Password::RESET_LINK_SENT, $result['status']);
        Event::assertDispatched(PasswordResetRequested::class, fn (PasswordResetRequested $event) => $event->payload->source === 'api');
    }

    public function test_send_password_reset_otp_action_dispatches_event_and_returns_delivery_payload_for_known_user(): void
    {
        Event::fake([PasswordResetRequested::class]);

        $user = Identity::createUser([
            'name' => 'Reset Otp User',
            'email' => 'reset-otp@example.com',
            'password' => bcrypt('Password123!'),
        ]);

        $verificationCodeService = Mockery::mock(VerificationCodeService::class);
        $verificationCodeService->shouldReceive('sendCode')
            ->once()
            ->with($user->id, 'email', 'api', true, false, 'forgot_password')
            ->andReturn([
                'status' => 'sent',
                'channel' => 'email',
                'destination' => 'reset-otp@example.com',
            ]);

        $action = new SendPasswordResetOtpAction($verificationCodeService);

        $result = $action->execute(
            PasswordResetRequestData::fromArray([
                'auth_method' => 'email_otp',
                'email' => 'reset-otp@example.com',
            ]),
            'api'
        );

        $this->assertSame('sent', $result['status']);
        $this->assertSame('email_otp', $result['auth_method']);
        $this->assertSame('email', $result['channel']);
        Event::assertDispatched(PasswordResetRequested::class);
    }

    public function test_send_password_reset_otp_action_returns_uniform_response_for_unknown_user(): void
    {
        Event::fake([PasswordResetRequested::class]);

        $verificationCodeService = Mockery::mock(VerificationCodeService::class);
        $verificationCodeService->shouldNotReceive('sendCode');

        $action = new SendPasswordResetOtpAction($verificationCodeService);

        $result = $action->execute(
            PasswordResetRequestData::fromArray([
                'auth_method' => 'email_otp',
                'email' => 'missing-reset@example.com',
            ]),
            'api'
        );

        $this->assertSame('passwords.sent', $result['status']);
        $this->assertSame('missing-reset@example.com', $result['destination']);
        Event::assertDispatched(PasswordResetRequested::class);
    }

    public function test_verify_password_reset_otp_action_returns_reset_grant_payload(): void
    {
        $user = Identity::createUser([
            'name' => 'Verify Reset Otp',
            'email' => 'verify-reset-otp@example.com',
            'password' => bcrypt('Password123!'),
        ]);

        $verificationCodeService = Mockery::mock(VerificationCodeService::class);
        $verificationCodeService->shouldReceive('verifyCode')
            ->once()
            ->andReturnTrue();

        $passwordResetRepository = Mockery::mock(PasswordResetRepository::class);
        $passwordResetRepository->shouldReceive('createOtpGrant')
            ->once()
            ->with($user->id, 'email_otp', 'verify-reset-otp@example.com')
            ->andReturn('reset-grant-token');

        $action = new VerifyPasswordResetOtpAction($verificationCodeService, $passwordResetRepository);

        $result = $action->execute([
            'auth_method' => 'email_otp',
            'email' => 'verify-reset-otp@example.com',
            'code' => '123456',
        ], 'api');

        $this->assertSame('verified', $result['status']);
        $this->assertSame('reset-grant-token', $result['reset_grant']);
        $this->assertSame('set_password', $result['next_step']);
        $this->assertSame('verify-reset-otp@example.com', $result['email']);
    }

    public function test_verify_password_reset_otp_action_throws_on_invalid_code(): void
    {
        $user = Identity::createUser([
            'name' => 'Bad Reset Otp',
            'email' => 'bad-reset-otp@example.com',
            'password' => bcrypt('Password123!'),
        ]);

        $verificationCodeService = Mockery::mock(VerificationCodeService::class);
        $verificationCodeService->shouldReceive('verifyCode')->once()->andReturnFalse();

        $passwordResetRepository = Mockery::mock(PasswordResetRepository::class);
        $passwordResetRepository->shouldNotReceive('createOtpGrant');

        $action = new VerifyPasswordResetOtpAction($verificationCodeService, $passwordResetRepository);

        $this->expectException(InvalidPasswordResetTokenException::class);

        $action->execute([
            'auth_method' => 'email_otp',
            'email' => 'bad-reset-otp@example.com',
            'code' => '000000',
        ], 'api');
    }

    public function test_reset_password_with_link_action_dispatches_password_reset_completed(): void
    {
        Event::fake([PasswordResetCompleted::class]);

        $user = Identity::createUser([
            'name' => 'Link Reset User',
            'email' => 'link-reset@example.com',
            'password' => bcrypt('Password123!'),
        ]);

        Password::shouldReceive('reset')
            ->once()
            ->andReturnUsing(function (array $credentials, callable $callback) use ($user) {
                $callback($user);

                return Password::PASSWORD_RESET;
            });

        $result = app(ResetPasswordWithLinkAction::class)->execute(
            ResetPasswordData::fromArray([
                'auth_method' => 'link',
                'email' => 'link-reset@example.com',
                'token' => 'reset-token',
                'password' => 'NewPassword123!',
                'password_confirmation' => 'NewPassword123!',
            ]),
            'api'
        );

        $this->assertSame(Password::PASSWORD_RESET, $result['status']);
        $this->assertSame('link-reset@example.com', $result['user']->email);
        Event::assertDispatched(PasswordResetCompleted::class);
    }

    public function test_reset_password_with_link_action_does_not_dispatch_completion_event_on_failure(): void
    {
        Event::fake([PasswordResetCompleted::class]);

        Password::shouldReceive('reset')->once()->andReturn(Password::INVALID_TOKEN);

        $this->expectException(InvalidPasswordResetTokenException::class);

        try {
            app(ResetPasswordWithLinkAction::class)->execute(
                ResetPasswordData::fromArray([
                    'auth_method' => 'link',
                    'email' => 'link-reset-fail@example.com',
                    'token' => 'bad-token',
                    'password' => 'NewPassword123!',
                    'password_confirmation' => 'NewPassword123!',
                ]),
                'api'
            );
        } finally {
            Event::assertNotDispatched(PasswordResetCompleted::class);
        }
    }

    public function test_reset_password_with_otp_action_consumes_grant_and_dispatches_password_reset_completed(): void
    {
        Event::fake([PasswordResetCompleted::class]);

        $user = Identity::createUser([
            'name' => 'Otp Reset User',
            'email' => 'otp-reset@example.com',
            'password' => bcrypt('Password123!'),
        ]);

        $grant = app(PasswordResetRepository::class)->createOtpGrant(
            $user->id,
            'email_otp',
            'otp-reset@example.com'
        );

        $result = app(ResetPasswordWithOtpAction::class)->execute(
            ResetPasswordData::fromArray([
                'auth_method' => 'email_otp',
                'email' => 'otp-reset@example.com',
                'reset_grant' => $grant,
                'password' => 'NewPassword123!',
                'password_confirmation' => 'NewPassword123!',
            ]),
            'api'
        );

        $this->assertSame('password_reset', $result['status']);
        $this->assertTrue(Hash::check('NewPassword123!', $result['user']->fresh()->password));
        Event::assertDispatched(PasswordResetCompleted::class);
    }

    public function test_reset_password_with_otp_action_does_not_dispatch_completion_event_for_invalid_grant(): void
    {
        Event::fake([PasswordResetCompleted::class]);

        $this->expectException(InvalidPasswordResetTokenException::class);

        try {
            app(ResetPasswordWithOtpAction::class)->execute(
                ResetPasswordData::fromArray([
                    'auth_method' => 'email_otp',
                    'email' => 'otp-reset-fail@example.com',
                    'reset_grant' => 'invalid-grant',
                    'password' => 'NewPassword123!',
                    'password_confirmation' => 'NewPassword123!',
                ]),
                'api'
            );
        } finally {
            Event::assertNotDispatched(PasswordResetCompleted::class);
        }
    }
}
