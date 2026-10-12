<?php

declare(strict_types=1);

namespace App\ViewModels;

use App\Enums\DutyStatus;
use App\Enums\RequestStatus;
use App\Models\PartnerRequest;

final class RequestProgress
{
    public function getLabel(PartnerRequest $request): string
    {
        if ($request->status !== RequestStatus::Accepted) {
            return match ($request->status) {
                RequestStatus::Pending => __('Создано · ожидает ответа'),
                RequestStatus::Conditional => __('Ожидает принятия условия'),
                default => __($request->status->label()),
            };
        }
        $statuses = $request->duties->pluck('status');
        if ($request->rating !== null || ($statuses->isNotEmpty() && $statuses->every(fn (DutyStatus $status): bool => $status === DutyStatus::Confirmed))) {
            return __('Выполнено');
        }
        if ($statuses->contains(DutyStatus::Reported)) {
            return __('Ожидает подтверждения');
        }
        if ($statuses->contains(DutyStatus::Disputed)) {
            return __('Выполнение оспорено');
        }
        if ($statuses->contains(DutyStatus::Overdue)) {
            return __('В процессе исполнения · срок прошёл');
        }

        return __('В процессе исполнения');
    }

    public function getTone(PartnerRequest $request): string
    {
        if (in_array($request->status, [RequestStatus::Cancelled, RequestStatus::Declined], true) || $request->duties->contains('status', DutyStatus::Disputed)) {
            return 'danger';
        }
        if ($request->status !== RequestStatus::Accepted || $request->duties->contains('status', DutyStatus::Reported) || $request->duties->contains('status', DutyStatus::Overdue)) {
            return 'warning';
        }
        if ($request->rating !== null || ($request->duties->isNotEmpty() && $request->duties->every(fn ($duty): bool => $duty->status === DutyStatus::Confirmed))) {
            return 'success';
        }

        return 'neutral';
    }
}
