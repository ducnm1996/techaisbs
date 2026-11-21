<?php

namespace App\Services;

use RuntimeException;

class PhoneIndex
{
    public function hash(string $phone): string
    {
        $key = base64_decode((string) config('history.index_key'), true);
        if ($key === false || strlen($key) < 32) {
            throw new RuntimeException('HISTORY_INDEX_KEY must be a separate base64 key of at least 32 bytes.');
        }

        return hash_hmac('sha256', preg_replace('/\s+/', '', $phone), $key);
    }
}
