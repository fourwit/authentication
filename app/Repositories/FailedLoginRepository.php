<?php

namespace Modules\Authentication\Repositories;

use Illuminate\Support\Facades\Cache;

class FailedLoginRepository
{
    public function increment(string $key, int $seconds): int
    {
        $count = (int) Cache::get($key, 0) + 1;
        Cache::put($key, $count, $seconds);

        return $count;
    }

    public function count(string $key): int
    {
        return (int) Cache::get($key, 0);
    }

    public function clear(string $key): void
    {
        Cache::forget($key);
    }
}
