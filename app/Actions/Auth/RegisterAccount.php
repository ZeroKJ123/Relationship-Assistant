<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Data\Auth\RegistrationData;
use App\Enums\Currency;
use App\Models\User;
use App\Services\Hearts\Ledger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class RegisterAccount
{
    private const RECOVERY_CODE_COUNT = 8;

    private const RECOVERY_CODE_LENGTH = 24;

    public function __construct(private readonly Ledger $ledger) {}

    /** @return array{user: User, recovery_codes: list<string>} */
    public function execute(RegistrationData $data): array
    {
        Log::debug('registration.started');
        $codes = [];
        for ($index = 0; $index < self::RECOVERY_CODE_COUNT; $index++) {
            $codes[] = Str::random(self::RECOVERY_CODE_LENGTH);
        }
        $user = new User;
        $user->forceFill([
            'login' => $data->login, 'name' => $data->name, 'password' => $data->password,
            'gender' => $data->gender, 'locale' => 'ru', 'currency' => Currency::Byn,
            'is_admin' => false, 'recovery_codes' => array_map(fn (string $code): string => Hash::make($code), $codes),
        ]);
        $user = DB::transaction(function () use ($user): User {
            $created = $user->replicate();
            $created->save();
            $this->ledger->getWallet($created);

            return $created;
        }, attempts: 3);
        Log::info('registration.completed', ['user_id' => $user->id]);

        return ['user' => $user, 'recovery_codes' => $codes];
    }
}
