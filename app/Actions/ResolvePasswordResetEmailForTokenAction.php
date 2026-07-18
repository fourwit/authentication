<?php

namespace Modules\Authentication\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ResolvePasswordResetEmailForTokenAction
{
    public function execute(string $token): ?string
    {
        $table = config('auth.passwords.users.table', 'password_reset_tokens');
        $expire = config('auth.passwords.users.expire', 60);

        $records = DB::table($table)->get(['email', 'token', 'created_at']);

        foreach ($records as $record) {
            if (Hash::check($token, $record->token)) {
                if (now()->diffInMinutes($record->created_at) <= $expire) {
                    return $record->email;
                }

                return null;
            }
        }

        return null;
    }
}
