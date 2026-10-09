<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class RecoverAccount
{
    private const REMEMBER_TOKEN_LENGTH = 60;

    public function execute(string $login, string $code, string $password): void
    {
        Log::debug('recovery.started');
        $userId = DB::transaction(function () use ($login, $code, $password): int {
            $user = User::query()->where('login', $login)->lockForUpdate()->first();
            if ($user === null) {
                $this->rejectRecovery();
            }
            $codes = $user->recovery_codes ?? [];
            $matched = $this->findCode($codes, $code);
            if ($matched === null) {
                $this->rejectRecovery();
            }
            unset($codes[$matched]);
            $user->forceFill(['password' => $password, 'recovery_codes' => array_values($codes), 'remember_token' => Str::random(self::REMEMBER_TOKEN_LENGTH)])->save();
            DB::table('sessions')->where('user_id', $user->id)->delete();

            return $user->id;
        });
        Log::info('recovery.completed', ['user_id' => $userId]);
    }

    /** @param list<string> $hashes */
    private function findCode(array $hashes, string $code): ?int
    {
        foreach ($hashes as $index => $hash) {
            if (Hash::check($code, $hash)) {
                return $index;
            }
        }

        return null;
    }

    private function rejectRecovery(): never
    {
        Log::warning('recovery.failed');
        throw ValidationException::withMessages(['recovery_code' => __('Не удалось восстановить доступ. Проверьте логин и резервный код.')]);
    }
}
