<?php

declare(strict_types=1);

namespace App\Data\Auth;

use App\Enums\Gender;

final readonly class RegistrationData
{
    public function __construct(public string $name, public string $login, public string $password, public Gender $gender) {}
}
