<?php

namespace Modules\Authentication\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Modules\Authentication\Actions\VerifyEmailVerificationCodeAction;
use Modules\Authentication\Actions\VerifyRegistrationOtpAction;
use Modules\Authentication\Events\EmailVerified;
use Modules\Authentication\Repositories\VerificationCodeRepository;
use Modules\Authentication\Services\RegistrationFollowUpService;
use Modules\Identity\Facades\Identity;
use Tests\TestCase;

class EmailVerifiedEventTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('authentication.verification.enabled', true);
        config()->set('authentication.verification.method', 'code');
        config()->set('authentication.verification.channel', 'email');
        config()->set('authentication.otp.length', 6);
        config()->set('authentication.otp.expires_minutes', 10);
        config()->set('authentication.otp.max_attempts', 5);
        config()->set('authentication.after_otp_registration.prompt_for_password', true);
        config()->set('authentication.after_otp_registration.password_required', false);
        config()->set('authentication.registration.post_verification_profile_completion', false);
    }

    public function test_email_code_verification_dispatches_email_verified_exactly_once(): void
    {
        Event::fake([EmailVerified::class]);

        $user = Identity::createUser([
            'name' => 'Email Code Verify',
            'email' => 'email-code-verify@example.com',
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
        Event::assertDispatchedTimes(EmailVerified::class, 1);
        Event::assertDispatched(EmailVerified::class, function (EmailVerified $event) use ($user) {
            return (int) $event->payload->userId === (int) $user->id
                && $event->payload->source === 'api';
        });
    }

    public function test_registration_otp_verification_dispatches_email_verified_exactly_once(): void
    {
        Event::fake([EmailVerified::class]);

        $user = Identity::createUser([
            'name' => 'Registration Otp Verify',
            'email' => 'registration-otp-event@example.com',
            'password' => bcrypt(str()->random(40)),
        ]);

        Identity::setMetadata($user, RegistrationFollowUpService::META_AUTH_METHOD, 'email_otp');
        Identity::setMetadata($user, RegistrationFollowUpService::META_PASSWORD_PENDING, true);
        Identity::setMetadata($user, RegistrationFollowUpService::META_PASSWORD_MISSING, true);

        $codeData = app(VerificationCodeRepository::class)
            ->createCode($user->id, 'email', $user->email, 'register', 6, 10);

        $result = app(VerifyRegistrationOtpAction::class)->execute([
            'auth_method' => 'email_otp',
            'email' => 'registration-otp-event@example.com',
            'code' => $codeData['plain_code'],
        ], 'api');

        $this->assertSame('verified', $result['status']);
        Event::assertDispatchedTimes(EmailVerified::class, 1);
        Event::assertDispatched(EmailVerified::class, function (EmailVerified $event) use ($user) {
            return (int) $event->payload->userId === (int) $user->id
                && $event->payload->source === 'api';
        });
    }

    public function test_failed_verification_does_not_dispatch_email_verified(): void
    {
        Event::fake([EmailVerified::class]);

        $user = Identity::createUser([
            'name' => 'Failed Verify',
            'email' => 'failed-verify@example.com',
            'password' => bcrypt('password123'),
        ]);

        app(VerificationCodeRepository::class)
            ->createCode($user->id, 'email', $user->email, 6, 10);

        $result = app(VerifyEmailVerificationCodeAction::class)->execute(
            (int) $user->id,
            'email',
            '000000',
            'api'
        );

        $this->assertSame('failed', $result['status']);
        Event::assertNotDispatched(EmailVerified::class);
    }

    public function test_failed_registration_otp_verification_does_not_dispatch_email_verified(): void
    {
        Event::fake([EmailVerified::class]);

        $user = Identity::createUser([
            'name' => 'Failed Registration Otp',
            'email' => 'failed-registration-otp@example.com',
            'password' => bcrypt(str()->random(40)),
        ]);

        Identity::setMetadata($user, RegistrationFollowUpService::META_AUTH_METHOD, 'email_otp');

        app(VerificationCodeRepository::class)
            ->createCode($user->id, 'email', $user->email, 'register', 6, 10);

        $this->expectException(\Modules\Authentication\Exceptions\InvalidCredentialsException::class);

        try {
            app(VerifyRegistrationOtpAction::class)->execute([
                'auth_method' => 'email_otp',
                'email' => 'failed-registration-otp@example.com',
                'code' => '000000',
            ], 'api');
        } finally {
            Event::assertNotDispatched(EmailVerified::class);
        }
    }
}
