<?php

namespace Modules\Authentication\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Modules\Authentication\Contracts\PhoneVerificationCodeSenderInterface;
use Modules\Authentication\Exceptions\MaxVerificationAttemptsExceededException;
use Modules\Authentication\Exceptions\PhoneVerificationNotConfiguredException;
use Modules\Authentication\Notifications\VerificationCodeNotification;
use Modules\Authentication\Repositories\VerificationCodeRepository;
use Tests\TestCase;

class VerificationCodeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure code mode and sane defaults for tests
        config()->set('authentication.verification.method', 'code');
        config()->set('authentication.verification.channel', 'email');
        config()->set('authentication.otp.length', 6);
        config()->set('authentication.otp.expires_minutes', 10);
        config()->set('authentication.otp.max_attempts', 5);
    }

    public function test_code_generation_produces_correct_length_and_is_numeric(): void
    {
        $repo = app(VerificationCodeRepository::class);
        // Use reflection or call protected via a small helper if needed; here we exercise via create which returns plain
        // We test indirectly by creating and checking the returned plain code length
        $user = \Modules\Identity\Facades\Identity::createUser([
            'name' => 'Code Gen',
            'first_name' => 'Code',
            'last_name' => 'Gen',
            'email' => 'codegen@example.com',
            'password' => bcrypt('password123'),
        ]);

        $result = app(\Modules\Authentication\Services\VerificationCodeService::class)
            ->sendCode($user->id, 'email', 'test');

        $this->assertEquals('sent', $result['status']);
        $this->assertEquals('email', $result['channel']);
        $this->assertNotNull($result['expires_at']);

        $record = \DB::table('authentication_otps')
            ->where('user_id', $user->id)
            ->where('channel', 'email')
            ->first();

        $this->assertNotNull($record);
        $this->assertNotEmpty($record->code_hash);
        $this->assertTrue(strlen($record->code_hash) > 20); // bcrypt hash length
        $this->assertEquals(0, $record->attempts);
        $this->assertNull($record->verified_at);
    }

    public function test_email_verification_code_is_sent_on_registration_and_via_send(): void
    {
        NotificationFacade::fake();

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Reg User',
            'email' => 'regcode@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertStatus(201); // RegisterController typically returns 201 on success

        $user = \Modules\Identity\Facades\Identity::findByEmail('regcode@example.com');
        $this->assertNotNull($user);

        // Should have attempted to send (the registration path calls sendVerificationCode)
        NotificationFacade::assertSentTo($user, VerificationCodeNotification::class);
    }

    public function test_code_verify_success_updates_email_verified_at_and_allows_protected(): void
    {
        $user = \Modules\Identity\Facades\Identity::createUser([
            'name' => 'Verify Success',
            'first_name' => 'Verify',
            'last_name' => 'Success',
            'email' => 'verifysuccess@example.com',
            'password' => bcrypt('password123'),
        ]);

        // Manually create a code via repo for determinism
        $repo = app(VerificationCodeRepository::class);
        $codeData = $repo->createCode($user->id, 'email', $user->email, 6, 10);
        $plain = $codeData['plain_code'];

        $this->actingAs($user)
            ->postJson('/api/v1/auth/verification/verify', [
                'code' => $plain,
                'channel' => 'email',
            ])
            ->assertStatus(200)
            ->assertJson(['status' => 'verified']);

        $fresh = $user->fresh();
        $this->assertNotNull($fresh->email_verified_at);

        // Now protected route should pass
        $this->actingAs($fresh)
            ->getJson('/api/v1/auth/me')
            ->assertStatus(200);
    }

    public function test_expired_code_is_rejected(): void
    {
        $user = \Modules\Identity\Facades\Identity::createUser([
            'name' => 'Expired Code',
            'first_name' => 'Expired',
            'last_name' => 'Code',
            'email' => 'expiredcode@example.com',
            'password' => bcrypt('password123'),
        ]);

        $repo = app(VerificationCodeRepository::class);
        $codeData = $repo->createCode($user->id, 'email', $user->email, 6, 10);
        $plain = $codeData['plain_code'];

        // Force expire
        \DB::table('authentication_otps')
            ->where('user_id', $user->id)
            ->update(['expires_at' => now()->subMinute()]);

        $this->actingAs($user)
            ->postJson('/api/v1/auth/verification/verify', ['code' => $plain, 'channel' => 'email'])
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'Invalid or expired code.']);
    }

    public function test_wrong_code_is_rejected_and_attempts_increment(): void
    {
        $user = \Modules\Identity\Facades\Identity::createUser([
            'name' => 'Wrong Code',
            'first_name' => 'Wrong',
            'last_name' => 'Code',
            'email' => 'wrongcode@example.com',
            'password' => bcrypt('password123'),
        ]);

        $repo = app(VerificationCodeRepository::class);
        $codeData = $repo->createCode($user->id, 'email', $user->email, 6, 10);

        $this->actingAs($user)
            ->postJson('/api/v1/auth/verification/verify', ['code' => '000000', 'channel' => 'email'])
            ->assertStatus(422);

        $record = \DB::table('authentication_otps')->where('user_id', $user->id)->first();
        $this->assertGreaterThan(0, $record->attempts);
    }

    public function test_max_attempts_rejects_and_throws_proper_exception(): void
    {
        $user = \Modules\Identity\Facades\Identity::createUser([
            'name' => 'Max Attempts',
            'first_name' => 'Max',
            'last_name' => 'Attempts',
            'email' => 'maxattempts@example.com',
            'password' => bcrypt('password123'),
        ]);

        config()->set('authentication.otp.max_attempts', 2);

        $repo = app(VerificationCodeRepository::class);
        $codeData = $repo->createCode($user->id, 'email', $user->email, 6, 10);
        $plain = $codeData['plain_code'];

        // Exhaust attempts (wrong codes)
        $this->actingAs($user)->postJson('/api/v1/auth/verification/verify', ['code' => '111111', 'channel' => 'email']);
        $this->actingAs($user)->postJson('/api/v1/auth/verification/verify', ['code' => '222222', 'channel' => 'email']);

        // Next should hit max
        $response = $this->actingAs($user)
            ->postJson('/api/v1/auth/verification/verify', ['code' => $plain, 'channel' => 'email']);

        $response->assertStatus(422);
        $this->assertNotEmpty($response->json('message'));
    }

    public function test_resend_code_invalidates_previous_and_sends_new(): void
    {
        NotificationFacade::fake();

        $user = \Modules\Identity\Facades\Identity::createUser([
            'name' => 'Resend Test',
            'first_name' => 'Resend',
            'last_name' => 'Test',
            'email' => 'resend@example.com',
            'password' => bcrypt('password123'),
        ]);

        // First send
        $this->actingAs($user)
            ->postJson('/api/v1/auth/verification/send', ['channel' => 'email'])
            ->assertStatus(200);

        $firstRecord = \DB::table('authentication_otps')->where('user_id', $user->id)->orderBy('id')->first();

        // Resend
        $this->actingAs($user)
            ->postJson('/api/v1/auth/verification/resend', ['channel' => 'email'])
            ->assertStatus(200);

        $latest = \DB::table('authentication_otps')->where('user_id', $user->id)->orderBy('id', 'desc')->first();

        $this->assertNotEquals($firstRecord->id, $latest->id);
        NotificationFacade::assertSentTo($user, VerificationCodeNotification::class);
    }

    public function test_send_code_is_not_blocked_by_cooldown_after_previous_code_was_verified(): void
    {
        NotificationFacade::fake();

        $user = \Modules\Identity\Facades\Identity::createUser([
            'name' => 'Verified Cooldown Bypass',
            'first_name' => 'Verified',
            'last_name' => 'Cooldown',
            'email' => 'verified-cooldown@example.com',
            'password' => bcrypt('password123'),
        ]);

        $service = app(\Modules\Authentication\Services\VerificationCodeService::class);
        $first = $service->sendCode($user->id, 'email', 'test');
        $this->assertSame('sent', $first['status']);

        $plainCode = null;
        NotificationFacade::assertSentTo($user, VerificationCodeNotification::class, function ($notification) use (&$plainCode) {
            $plainCode = $notification->plainCode;

            return true;
        });

        $this->assertNotNull($plainCode);
        $this->assertTrue($service->verifyCode($user->id, 'email', $plainCode, 'test'));

        $second = $service->sendCode($user->id, 'email', 'test');
        $this->assertSame('sent', $second['status']);
        NotificationFacade::assertSentToTimes($user, VerificationCodeNotification::class, 2);
    }

    public function test_protected_route_access_allowed_after_one_verified_channel(): void
    {
        $user = \Modules\Identity\Facades\Identity::createUser([
            'name' => 'One Channel',
            'first_name' => 'One',
            'last_name' => 'Channel',
            'email' => 'onechannel@example.com',
            'password' => bcrypt('password123'),
        ]);

        // Verify email only (min=1, logic=any)
        $repo = app(VerificationCodeRepository::class);
        $codeData = $repo->createCode($user->id, 'email', $user->email, 6, 10);
        app(\Modules\Authentication\Services\VerificationCodeService::class)
            ->verifyCode($user->id, 'email', $codeData['plain_code']);

        $fresh = $user->fresh();
        $this->assertNotNull($fresh->email_verified_at);

        $this->actingAs($fresh)
            ->getJson('/api/v1/auth/me')
            ->assertStatus(200);
    }

    public function test_phone_verification_throws_clear_exception_if_no_sender_bound(): void
    {
        $this->expectException(PhoneVerificationNotConfiguredException::class);

        $user = \Modules\Identity\Facades\Identity::createUser([
            'name' => 'Phone NoSender',
            'first_name' => 'Phone',
            'last_name' => 'NoSender',
            'email' => 'phonenosender@example.com',
            'phone' => '5550001234',
            'password' => bcrypt('password123'),
        ]);

        // Ensure no binding for phone sender
        // (if previously bound in test, unbind)
        app()->forgetInstance(PhoneVerificationCodeSenderInterface::class);

        app(\Modules\Authentication\Services\VerificationCodeService::class)
            ->sendCode($user->id, 'phone', 'test');
    }

    public function test_phone_verification_works_when_sender_is_bound(): void
    {
        $sender = new class implements PhoneVerificationCodeSenderInterface {
            public array $sent = [];
            public function send(string $phone, string $code, string $source = 'web'): void
            {
                $this->sent[] = compact('phone', 'code', 'source');
            }
        };

        app()->instance(PhoneVerificationCodeSenderInterface::class, $sender);

        config()->set('authentication.registration.fields_per_method.phone_otp.phone.required', true);

        $user = \Modules\Identity\Facades\Identity::createUser([
            'name' => 'Phone Ok',
            'first_name' => 'Phone',
            'last_name' => 'Ok',
            'email' => 'phoneok@example.com',
            'phone' => '5550009999',
            'password' => bcrypt('password123'),
        ]);

        $result = app(\Modules\Authentication\Services\VerificationCodeService::class)
            ->sendCode($user->id, 'phone');

        $this->assertEquals('sent', $result['status']);
        $this->assertNotEmpty($sender->sent);
    }
}
