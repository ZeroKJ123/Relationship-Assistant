<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Data\Couples\CoupleSetupData;
use App\Models\Couple;
use Carbon\CarbonImmutable;
use DateTimeZone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class InvitePartnerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        $timezone = $this->input('timezone');
        $validTimezone = is_string($timezone) && in_array($timezone, DateTimeZone::listIdentifiers(), true);
        $zone = $validTimezone ? $timezone : Couple::DEFAULT_TIMEZONE;

        return [
            'started_on' => ['required', 'date_format:Y-m-d', sprintf('before_or_equal:%s', now($zone)->toDateString())],
            'timezone' => ['required', 'string', Rule::in(DateTimeZone::listIdentifiers())],
        ];
    }

    public function getData(): CoupleSetupData
    {
        $zone = $this->string('timezone')->toString();

        return new CoupleSetupData(CarbonImmutable::parse($this->string('started_on')->toString(), $zone), $zone);
    }
}
