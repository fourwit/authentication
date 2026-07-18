<?php

namespace Modules\Authentication\Tests\Unit\Actions;

use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Mockery;
use Modules\Authentication\Actions\SendEmailVerificationCodeAction;
use Modules\Authentication\Actions\SendEmailVerificationLinkAction;
use Modules\Authentication\Actions\VerifyEmailVerificationCodeAction;
use Modules\Authentication\Actions\VerifyEmailVerificationLinkAction;
use Modules\Authentication\Actions\VerifyCode;
use Modules\Authentication\DTOs\EmailVerificationData;
use Modules\Authentication\Events\EmailVerificationSent;
use Modules\Authentication\Events\EmailVerified;
use Modules\Authentication\Exceptions\InvalidVerificationTokenException;
use Modules\Authentication\Repositories\VerificationCodeRepository;
use Modules\Authentication\Services\VerificationCodeService;
use Modules\Identity\Facades\Identity;
use Tests\TestCase;

class EmailVerificationActionsTest extends TestCase
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

        config()->set('authentication.verification.enabled', true);
        config()->set('authentication.verification.method', 'code');
        config()->set('authentication.verification.channel', 'email');

        if (! Route::has('verification.verify')) {
            Route::get('/email/verify/{id}/{hash}', fn () => 'ok')->name('verification.verify');
        }
    }

    public function test_send_email_verification_code_action_dispatches_email_verification_sent(): void
    {
        Event::fake([EmailVerificationSent::class]);

        $user = Identity::createUser([
            'name' => 'Send Code User',
            'email' => 'send-code@example.com',
            'password' => bcrypt('password123'),
        ]);

        $verificationCodeService = Mockery::mock(VerificationCodeService::class);
        $verificationCodeService->shouldReceive('sendCode')
            ->once()
            ->with($user->id, 'email', 'api');

        $action = new SendEmailVerificationCodeAction($verificationCodeService);

        $result = $action->execute(
            EmailVerificationData::fromArray(['email' => 'send-code@example.com']),
            'api'
        );

        $this->assertSame('sent', $result['status']);
        $this->assertSame($user->id, $result['user']->id);
        Event::assertDispatched(EmailVerificationSent::class);
    }

    public function test_send_email_verification_code_action_returns_disabled_without_sending_when_verification_disabled(): void
    {
        Event::fake([EmailVerificationSent::class]);
        config()->set('authentication.verification.enabled', false);

        $verificationCodeService = Mockery::mock(VerificationCodeService::class);
        $verificationCodeService->shouldNotReceive('sendCode');

        $action = new SendEmailVerificationCodeAction($verificationCodeService);

        $result = $action->execute(
            EmailVerificationData::fromArray(['email' => 'disabled@example.com']),
            'api'
        );

        $this->assertSame('disabled', $result['status']);
        Event::assertDispatched(EmailVerificationSent::class);
    }

    public function test_send_email_verification_link_action_dispatches_email_verification_sent(): void
    {
        Event::fake([EmailVerificationSent::class]);
        Notification::fake();

        Identity::createUser([
            'name' => 'Link Send User',
            'email' => 'link-send@example.com',
            'password' => bcrypt('password123'),
        ]);

        $result = app(SendEmailVerificationLinkAction::class)->execute(
            EmailVerificationData::fromArray(['email' => 'link-send@example.com']),
            'api'
        );

        $this->assertSame('sent', $result['status']);
        $this->assertSame('link-send@example.com', $result['user']->email);
        Event::assertDispatched(EmailVerificationSent::class);
    }

    public function test_send_email_verification_link_action_throws_for_unknown_user(): void
    {
        Event::fake([EmailVerificationSent::class]);

        $this->expectException(InvalidVerificationTokenException::class);

        try {
            app(SendEmailVerificationLinkAction::class)->execute(
                EmailVerificationData::fromArray(['email' => 'missing-link@example.com']),
                'api'
            );
        } finally {
            Event::assertNotDispatched(EmailVerificationSent::class);
        }
    }

    public function test_verify_email_verification_code_action_returns_verified_payload(): void
    {
        Event::fake([EmailVerified::class]);

        $user = Identity::createUser([
            'name' => 'Verify Code Action',
            'email' => 'verify-code-action@example.com',
            'password' => bcrypt('password123'),
        ]);

        $verifyCode = Mockery::mock(VerifyCode::class);
        $verifyCode->shouldReceive('execute')
            ->once()
            ->with($user->id, 'email', '123456', 'api')
            ->andReturnTrue();

        $action = new VerifyEmailVerificationCodeAction(
            $verifyCode,
            app(\Modules\Authentication\Services\RegistrationFollowUpService::class),
        );

        $result = $action->execute($user->id, 'email', '123456', 'api');

        $this->assertSame('verified', $result['status']);
        $this->assertSame('dashboard', $result['next_step']);
        $this->assertSame($user->id, $result['user']->id);
        Event::assertDispatched(EmailVerified::class);
    }

    public function test_verify_email_verification_code_action_returns_failed_without_dispatching_email_verified(): void
    {
        Event::fake([EmailVerified::class]);

        $user = Identity::createUser([
            'name' => 'Failed Code Action',
            'email' => 'failed-code-action@example.com',
            'password' => bcrypt('password123'),
        ]);

        $verifyCode = Mockery::mock(VerifyCode::class);
        $verifyCode->shouldReceive('execute')->once()->andReturnFalse();

        $action = new VerifyEmailVerificationCodeAction(
            $verifyCode,
            app(\Modules\Authentication\Services\RegistrationFollowUpService::class),
        );

        $result = $action->execute($user->id, 'email', '000000', 'api');

        $this->assertSame('failed', $result['status']);
        Event::assertNotDispatched(EmailVerified::class);
    }

    public function test_verify_email_verification_link_action_verifies_user_and_dispatches_email_verified(): void
    {
        Event::fake([EmailVerified::class, Verified::class]);

        $user = Identity::createUser([
            'name' => 'Verify Link Action',
            'email' => 'verify-link-action@example.com',
            'password' => bcrypt('password123'),
        ]);

        $result = app(VerifyEmailVerificationLinkAction::class)->execute(
            EmailVerificationData::fromArray(['email' => 'verify-link-action@example.com']),
            'api'
        );

        $this->assertSame('verified', $result['status']);
        $this->assertNotNull($result['user']->fresh()->email_verified_at);
        Event::assertDispatched(EmailVerified::class);
        Event::assertDispatched(Verified::class);
    }

    public function test_verify_email_verification_link_action_returns_disabled_when_verification_disabled(): void
    {
        Event::fake([EmailVerified::class]);
        config()->set('authentication.verification.enabled', false);

        $result = app(VerifyEmailVerificationLinkAction::class)->execute(
            EmailVerificationData::fromArray(['email' => 'disabled-link@example.com']),
            'api'
        );

        $this->assertSame('disabled', $result['status']);
        Event::assertNotDispatched(EmailVerified::class);
    }

    public function test_verify_email_verification_code_action_integration_with_repository(): void
    {
        Event::fake([EmailVerified::class]);

        $user = Identity::createUser([
            'name' => 'Repo Verify Code',
            'email' => 'repo-verify-code@example.com',
            'password' => bcrypt('password123'),
        ]);

        $codeData = app(VerificationCodeRepository::class)
            ->createCode($user->id, 'email', $user->email, 6, 10);

        $result = app(VerifyEmailVerificationCodeAction::class)->execute(
            (int) $user->id,
            'email',
            $codeData['plain_code'],
            'api'
        );

        $this->assertSame('verified', $result['status']);
        Event::assertDispatched(EmailVerified::class);
    }
}
