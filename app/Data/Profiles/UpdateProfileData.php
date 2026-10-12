<?php

declare(strict_types=1);

namespace App\Data\Profiles;

use App\Enums\Currency;
use App\Enums\Gender;

final readonly class UpdateProfileData
{
    public function __construct(public ?string $name = null, public ?Gender $gender = null, public ?string $paymentDetails = null, public ?Currency $currency = null, public ?string $bio = null, public bool $settingsOnly = false, public bool $paymentProvided = true, public bool $bioProvided = true) {}
}
