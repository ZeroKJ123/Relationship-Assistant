<?php

declare(strict_types=1);

namespace App\Actions\Money;

use App\Actions\Requests\CreatePartnerRequest;
use App\Data\Money\MoneyRequestData;
use App\Data\Requests\NewRequestData;
use App\Enums\RequestType;
use App\Models\Couple;
use App\Models\PartnerRequest;
use App\Models\User;

final class CreateMoneyRequest
{
    public function __construct(private readonly CreatePartnerRequest $requests) {}

    public function execute(User $actor, Couple $couple, MoneyRequestData $data): PartnerRequest
    {
        return $this->requests->execute($actor, $couple, new NewRequestData(type: RequestType::Money, description: $data->purpose ?? __('Помощь деньгами'), dueAt: $data->dueAt, operationKey: $data->operationKey, expenseCategoryId: $data->expenseCategoryId, templateId: $data->templateId, moneyAmount: $data->amount, moneyCurrency: $data->currency));
    }
}
