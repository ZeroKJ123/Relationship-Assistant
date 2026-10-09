<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Actions\Requests\CreatePartnerRequest;
use App\Actions\Requests\RespondToRequest;
use App\Data\Requests\NewRequestData;
use App\Data\Requests\RequestResponseData;
use App\Enums\RequestResponse;
use App\Enums\RequestType;
use App\Models\Duty;
use App\Models\PartnerRequest;
use Carbon\CarbonImmutable;

final readonly class DutyScenario
{
    public function __construct(public AgreementScenario $pair, public PartnerRequest $request, public Duty $duty) {}

    public static function create(RequestType $type = RequestType::Service): self
    {
        return self::forPair(AgreementScenario::create(), $type);
    }

    public static function forPair(AgreementScenario $pair, RequestType $type = RequestType::Service): self
    {
        $request = app(CreatePartnerRequest::class)->execute($pair->first, $pair->couple, new NewRequestData($type, 'Тестовое дело', CarbonImmutable::now()->addDays(3)));
        app(RespondToRequest::class)->execute($pair->second, $pair->couple, $request, new RequestResponseData(RequestResponse::Agree, 0));

        return new self($pair, $request, $request->duties()->sole());
    }
}
