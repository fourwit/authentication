<?php

namespace Modules\Authentication\Repositories;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Modules\Authentication\Models\VerificationCode; // assume model or use query

class VerificationCodeRepository
{
    protected string $table = 'authentication_otps';

    public function createCode(?int $userId, string $channel, string $identifier, string|int $purposeOrLength = 'register', int $lengthOrExpires = 6, ?int $expiresMinutes = 10): array
    {
        [$purpose, $length, $expiresMinutes] = $this->normalizeCreateArguments($purposeOrLength, $lengthOrExpires, $expiresMinutes);
        $plainCode = $this->generateCode($length);
        $codeHash = Hash::make($plainCode);
        $expiresAt = now()->addMinutes($expiresMinutes);

        \DB::table($this->table)->insert([
            'user_id' => $userId,
            'identifier' => $identifier,
            'channel' => $channel,
            'purpose' => $purpose,
            'code_hash' => $codeHash,
            'expires_at' => $expiresAt,
            'sent_at' => now(),
            'attempts' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'plain_code' => $plainCode,
            'expires_at' => $expiresAt,
        ];
    }

    public function findActiveCode(?int $userId, string $channel, string $identifier, string|int $purpose = 'register'): ?object
    {
        $purpose = is_int($purpose) ? 'register' : $purpose;
        return \DB::table($this->table)
            ->when(! is_null($userId), fn ($query) => $query->where('user_id', $userId))
            ->where('identifier', $identifier)
            ->where('channel', $channel)
            ->where('purpose', $purpose)
            ->whereNull('verified_at')
            ->where('expires_at', '>', now())
            ->orderBy('created_at', 'desc')
            ->first();
    }

    public function verifyCode(?int $userId, string $channel, string $identifier, string $plainCode, string|int $purpose = 'register'): bool
    {
        $purpose = is_int($purpose) ? 'register' : $purpose;
        $record = $this->findActiveCode($userId, $channel, $identifier, $purpose);

        if (! $record) {
            return false;
        }

        if ($record->attempts >= config('authentication.otp.max_attempts', 5)) {
            return false;
        }

        // increment attempts
        \DB::table($this->table)->where('id', $record->id)->increment('attempts');

        if (Hash::check($plainCode, $record->code_hash)) {
            \DB::table($this->table)->where('id', $record->id)->update([
                'verified_at' => now(),
                'updated_at' => now(),
            ]);
            return true;
        }

        return false;
    }

    public function incrementAttempts(int $id): void
    {
        \DB::table($this->table)->where('id', $id)->increment('attempts');
    }

    public function hasActiveCode(int $userId, string $channel, string $destination, string $purpose = 'register'): bool
    {
        return (bool) \DB::table($this->table)
            ->where('user_id', $userId)
            ->where('identifier', $destination)
            ->where('channel', $channel)
            ->where('purpose', $purpose)
            ->whereNull('verified_at')
            ->where('expires_at', '>', now())
            ->exists();
    }

    public function markAllAsVerified(int $userId, string $channel, string $identifier, string $purpose = 'register'): void
    {
        \DB::table($this->table)
            ->where('user_id', $userId)
            ->where('identifier', $identifier)
            ->where('channel', $channel)
            ->where('purpose', $purpose)
            ->whereNull('verified_at')
            ->update(['verified_at' => now(), 'updated_at' => now()]);
    }

    /**
     * Return the created_at of the most recent code (active or not) for cooldown/rate calculations.
     */
    public function getLastCodeCreatedAt(?int $userId, string $channel, string $identifier, string $purpose = 'register'): ?\Carbon\Carbon
    {
        $row = \DB::table($this->table)
            ->when(! is_null($userId), fn ($query) => $query->where('user_id', $userId))
            ->where('identifier', $identifier)
            ->where('channel', $channel)
            ->where('purpose', $purpose)
            ->orderBy('created_at', 'desc')
            ->first(['created_at']);

        return $row ? \Carbon\Carbon::parse($row->created_at) : null;
    }

    /**
     * Count codes created for this user/channel/dest in the last 60 minutes (for max_resend_attempts per hour).
     */
    public function countCodesInLastHour(?int $userId, string $channel, string $identifier, string $purpose = 'register'): int
    {
        return \DB::table($this->table)
            ->when(! is_null($userId), fn ($query) => $query->where('user_id', $userId))
            ->where('identifier', $identifier)
            ->where('channel', $channel)
            ->where('purpose', $purpose)
            ->where('created_at', '>', now()->subHour())
            ->count();
    }

    protected function generateCode(int $length): string
    {
        return str_pad((string) random_int(0, (10 ** $length) - 1), $length, '0', STR_PAD_LEFT);
    }

    /**
     * Backward-compatible argument normalization.
     *
     * Old calls were createCode($userId, $channel, $destination, $length, $expiresMinutes).
     * New calls are createCode($userId, $channel, $identifier, $purpose, $length, $expiresMinutes).
     *
     * @return array{0:string,1:int,2:int}
     */
    protected function normalizeCreateArguments(string|int $purposeOrLength, int $lengthOrExpires, ?int $expiresMinutes): array
    {
        if (is_int($purposeOrLength)) {
            return ['register', $purposeOrLength, $lengthOrExpires];
        }

        return [
            $purposeOrLength,
            $lengthOrExpires,
            $expiresMinutes ?? 10,
        ];
    }
}
