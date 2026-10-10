<?php

declare(strict_types=1);

namespace App\Services\Auth;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final class RecoveryCodes
{
    private const COUNT = 8;

    private const LENGTH = 24;

    /** @return list<string> */
    public function generate(): array
    {
        $codes = [];
        for ($index = 0; $index < self::COUNT; $index++) {
            $codes[] = Str::random(self::LENGTH);
        }

        return $codes;
    }

    /** @param list<string> $codes
     * @return list<string>
     */
    public function hash(array $codes): array
    {
        return array_map(fn (string $code): string => Hash::make($code), $codes);
    }
}
