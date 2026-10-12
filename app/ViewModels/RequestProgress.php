<?php

declare(strict_types=1);

namespace App\ViewModels;

use App\Enums\DutyStatus;
use App\Enums\RequestStatus;
use App\Models\PartnerRequest;
use App\Models\User;
use Illuminate\Support\Collection;

final class RequestProgress
{
    private const GROUP_AWAITING = 'awaiting';

    private const GROUP_ACTIVE = 'active';

    private const GROUP_FINISHED = 'finished';

    /**
     * @param  Collection<int,PartnerRequest>  $requests
     * @return list<array{label:string,isAlert:bool,requests:Collection<int,PartnerRequest>}>
     */
    public function group(Collection $requests): array
    {
        $labels = [self::GROUP_AWAITING => __('Ждут ответа'), self::GROUP_ACTIVE => __('В работе'), self::GROUP_FINISHED => __('Завершённые')];
        $grouped = $requests->groupBy(fn (PartnerRequest $request): string => $this->getGroup($request));
        $groups = [];
        foreach ($labels as $key => $label) {
            $items = $grouped->get($key);
            if ($items !== null) {
                $groups[] = ['label' => $label, 'isAlert' => $key === self::GROUP_AWAITING, 'requests' => $items->values()];
            }
        }

        return $groups;
    }

    public function isAwaitingAnswerFrom(PartnerRequest $request, User $viewer): bool
    {
        return match ($request->status) {
            RequestStatus::Pending => $request->recipient_id === $viewer->id,
            RequestStatus::Conditional => $request->author_id === $viewer->id,
            default => false,
        };
    }

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

    private function getGroup(PartnerRequest $request): string
    {
        if (in_array($request->status, [RequestStatus::Pending, RequestStatus::Conditional], true)) {
            return self::GROUP_AWAITING;
        }
        if ($request->status === RequestStatus::Accepted && $this->getTone($request) !== 'success') {
            return self::GROUP_ACTIVE;
        }

        return self::GROUP_FINISHED;
    }
}
