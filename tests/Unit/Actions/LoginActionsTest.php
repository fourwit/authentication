<?php

namespace Modules\Authentication\Tests\Unit\Actions;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Mockery;
use Modules\Authentication\Actions\InitiateLoginOtpAction;
use Modules\Authentication\Actions\LoginUserAction;
use Modules\Authentication\Actions\ResendLoginOtpAction;
use Modules\Authentication\Actions\ResendVerificationCode;
use Modules\Authentication\Actions\SendLoginVerificationCodeAction;
use Modules\Authentication\Actions\VerifyLoginOtpAction;
use Modules\Authentication\DTOs\LoginData;
use Modules\Authentication\Events\FailedLoginRecorded;
use Modules\Authentication\Events\UserLoggedIn;
use Modules\Authentication\Exceptions\InvalidCredentialsException;
use Modules\Authentication\Services\FailedLoginService;
use Modules\Authentication\Services\TokenService;
use Modules\Authentication\Services\VerificationCodeService;
use Modules\Identity\Facades\Identity;
use Tests\TestCase;

class LoginActionsTest extends TestCase
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

        config()->set('authentication.login.default_method', 'email_password');
        config()->set('authentication.guards.web', 'web');
        config()->set('authentication.token_driver', 'sanctum');
        config()->set('authentication.verification.enabled', true);
        config()->set('authentication.verification.method', 'code');
        config()->set('authentication.verification.channel', 'email');
    }

    public function test_login_user_action_returns_session_payload_and_dispatches_user_logged_in(): void
    {
        Event::fake([UserLoggedIn::class]);

        Identity::createUser([
            'name' => 'Login Action User',
            'email' => 'login-action@example.com',
            'password' => bcrypt('password123'),
        ]);

        $data = LoginData::fromArray([
            'auth_method' => 'email_password',
            'email' => 'login-action@example.com',
            'password' => 'password123',
        ]);

        $result = app(LoginUserAction::class)->execute($data, 'api');

        $this->assertTrue($result['success']);
        $this->assertSame('api', $result['source']);
        $this->assertArrayHasKey('user', $result);
        $this->assertArrayHasKey('token', $result);
        $this->assertTrue(Auth::check());
        Event::assertDispatched(UserLoggedIn::class, fn (UserLoggedIn $event) => $event->payload->source === 'api');
    }

    public function test_login_user_action_does_not_dispatch_user_logged_in_on_invalid_credentials(): void
    {
        Event::fake([UserLoggedIn::class]);

        Identity::createUser([
            'name' => 'Login Fail User',
            'email' => 'login-fail@example.com',
            'password' => bcrypt('password123'),
        ]);

        $data = LoginData::fromArray([
            'auth_method' => 'email_password',
            'email' => 'login-fail@example.com',
            'password' => 'wrong-password',
        ]);

        $this->expectException(InvalidCredentialsException::class);

        try {
            app(LoginUserAction::class)->execute($data, 'api');
        } finally {
            Event::assertNotDispatched(UserLoggedIn::class);
        }
    }

    public function test_initiate_login_otp_action_returns_delivery_payload_and_dispatches_failed_login_recorded(): void
    {
        Event::fake([FailedLoginRecorded::class]);

        $user = Identity::createUser([
            'name' => 'Otp Init User',
            'email' => 'otp-init@example.com',
            'password' => bcrypt('password123'),
        ]);

        $verificationCodeService = Mockery::mock(VerificationCodeService::class);
        $verificationCodeService->shouldReceive('sendCode')
            ->once()
            ->with($user->id, 'email', 'api', true, false, 'login')
            ->andReturn([
                'status' => 'sent',
                'channel' => 'email',
                'destination' => 'otp-init@example.com',
                'expires_at' => now()->addMinutes(10)->toIso8601String(),
            ]);

        $action = new InitiateLoginOtpAction($verificationCodeService, app(FailedLoginService::class));

        $result = $action->execute(LoginData::fromArray([
            'auth_method' => 'email_otp',
            'email' => 'otp-init@example.com',
        ]), 'api');

        $this->assertSame('sent', $result['status']);
        $this->assertSame('email', $result['channel']);
        $this->assertSame('otp-init@example.com', $result['destination']);
        $this->assertSame('api', $result['source']);
        Event::assertDispatched(FailedLoginRecorded::class);
    }

    public function test_initiate_login_otp_action_throws_when_user_is_missing(): void
    {
        Event::fake([FailedLoginRecorded::class]);

        $verificationCodeService = Mockery::mock(VerificationCodeService::class);
        $verificationCodeService->shouldNotReceive('sendCode');

        $action = new InitiateLoginOtpAction($verificationCodeService, app(FailedLoginService::class));

        $this->expectException(InvalidCredentialsException::class);

        try {
            $action->execute(LoginData::fromArray([
                'auth_method' => 'email_otp',
                'email' => 'missing@example.com',
            ]), 'api');
        } finally {
            Event::assertNotDispatched(FailedLoginRecorded::class);
        }
    }

    public function test_verify_login_otp_action_returns_payload_and_dispatches_user_logged_in(): void
    {
        Event::fake([UserLoggedIn::class]);

        $user = Identity::createUser([
            'name' => 'Verify Otp User',
            'email' => 'verify-otp@example.com',
            'password' => bcrypt('password123'),
        ]);

        $verificationCodeService = Mockery::mock(VerificationCodeService::class);
        $verificationCodeService->shouldReceive('verifyCode')
            ->once()
            ->with($user->id, 'email', '123456', 'api', 'login')
            ->andReturnTrue();

        $tokenService = Mockery::mock(TokenService::class);
        $tokenService->shouldReceive('issueForLogin')
            ->once()
            ->andReturn(['token' => 'api-token', 'expires_at' => now()->addHour()->toIso8601String()]);

        $action = new VerifyLoginOtpAction(
            $verificationCodeService,
            app(FailedLoginService::class),
            $tokenService,
        );

        $result = $action->execute(
            LoginData::fromArray([
                'auth_method' => 'email_otp',
                'email' => 'verify-otp@example.com',
            ]),
            '123456',
            'api'
        );

        $this->assertTrue($result['success']);
        $this->assertSame('api-token', $result['token']);
        $this->assertSame('email', $result['channel']);
        Event::assertDispatched(UserLoggedIn::class);
    }

    public function test_verify_login_otp_action_does_not_dispatch_user_logged_in_on_invalid_code(): void
    {
        Event::fake([UserLoggedIn::class]);

        $user = Identity::createUser([
            'name' => 'Bad Otp User',
            'email' => 'bad-otp@example.com',
            'password' => bcrypt('password123'),
        ]);

        $verificationCodeService = Mockery::mock(VerificationCodeService::class);
        $verificationCodeService->shouldReceive('verifyCode')
            ->once()
            ->andReturnFalse();

        $action = new VerifyLoginOtpAction(
            $verificationCodeService,
            app(FailedLoginService::class),
            app(TokenService::class),
        );

        $this->expectException(InvalidCredentialsException::class);

        try {
            $action->execute(
                LoginData::fromArray([
                    'auth_method' => 'email_otp',
                    'email' => 'bad-otp@example.com',
                ]),
                '000000',
                'api'
            );
        } finally {
            Event::assertNotDispatched(UserLoggedIn::class);
        }
    }

    public function test_resend_login_otp_action_delegates_to_verification_infrastructure(): void
    {
        $user = Identity::createUser([
            'name' => 'Resend Otp User',
            'email' => 'resend-otp@example.com',
            'password' => bcrypt('password123'),
        ]);

        $verificationCodeService = Mockery::mock(VerificationCodeService::class);
        $verificationCodeService->shouldReceive('resendCode')
            ->once()
            ->with($user->id, 'email', 'api', 'login')
            ->andReturn(['status' => 'sent', 'channel' => 'email']);

        $action = new ResendLoginOtpAction(app(FailedLoginService::class), $verificationCodeService);

        $result = $action->execute(LoginData::fromArray([
            'auth_method' => 'email_otp',
            'email' => 'resend-otp@example.com',
        ]), 'api');

        $this->assertSame('sent', $result['status']);
        $this->assertSame('email', $result['channel']);
        $this->assertSame($user->id, $result['user']->id);
    }

    public function test_send_login_verification_code_action_resends_for_unverified_email_user(): void
    {
        $user = Identity::createUser([
            'name' => 'Login Verify Send',
            'email' => 'login-verify-send@example.com',
            'password' => bcrypt('password123'),
        ]);

        $resendVerificationCode = Mockery::mock(ResendVerificationCode::class);
        $resendVerificationCode->shouldReceive('execute')
            ->once()
            ->with($user->id, 'email', 'web');

        $action = new SendLoginVerificationCodeAction($resendVerificationCode);
        $action->execute($user, 'web');
    }

    public function test_send_login_verification_code_action_skips_when_email_already_verified(): void
    {
        $user = Identity::createUser([
            'name' => 'Verified Login User',
            'email' => 'verified-login@example.com',
            'password' => bcrypt('password123'),
            'email_verified_at' => now(),
        ]);

        $resendVerificationCode = Mockery::mock(ResendVerificationCode::class);
        $resendVerificationCode->shouldNotReceive('execute');

        $action = new SendLoginVerificationCodeAction($resendVerificationCode);
        $action->execute($user, 'web');
    }
}
