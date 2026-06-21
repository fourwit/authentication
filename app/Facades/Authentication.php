<?php

namespace Modules\Authentication\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static mixed login(array $data, string $source = 'web')
 * @method static mixed logout($user = null, string $source = 'web')
 * @method static mixed register(array $data, string $source = 'web')
 * @method static mixed user($request = null)
 * @method static mixed sendPasswordReset(array $data, string $source = 'web')
 * @method static mixed resetPassword(array $data, string $source = 'web')
 * @method static mixed verifyPasswordResetOtp(array $data, string $source = 'web')
 * @method static mixed sendEmailVerification(array $data, string $source = 'web')
 * @method static mixed verifyEmail(array $data, string $source = 'web')
 * @method static mixed sendVerificationCode(array $data, string $source = 'web')
 * @method static mixed verifyCode(array $data, string $source = 'web')
 * @method static mixed resendVerificationCode(array $data, string $source = 'web')
 * @method static mixed verifyRegistrationOtp(array $data, string $source = 'web')
 * @method static mixed resendRegistrationOtp(array $data, string $source = 'web')
 * @method static mixed setRegistrationPassword(array $data, string $source = 'web')
 * @method static mixed skipRegistrationPassword(array $data, string $source = 'web')
 * @method static mixed verifyLoginOtp(array $data, string $source = 'web')
 * @method static mixed resendLoginOtp(array $data, string $source = 'web')
 */
class Authentication extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'authentication';
    }
}
