<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Data\Auth\RegistrationData;
use App\Enums\Currency;
use App\Enums\Locale;
use App\Models\User;
use App\Services\Auth\RecoveryCodes;
use App\Services\Hearts\Ledger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class RegisterAccount
{
    public function __construct(private readonly Ledger $ledger, private readonly RecoveryCodes $recoveryCodes) {}

    /** @return array{user: User, recovery_codes: list<string>} */
    public function execute(RegistrationData $data): array
    {
        Log::debug('registration.started');
        $codes = $this->recoveryCodes->generate();
        $user = new User;
        $user->forceFill([
            'login' => $data->login, 'name' => $data->name, 'password' => $data->password,
            'gender' => $data->gender, 'locale' => Locale::current(), 'currency' => Currency::Byn,
            'is_admin' => false, 'recovery_codes' => $this->recoveryCodes->hash($codes),
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
