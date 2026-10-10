<?php

declare(strict_types=1);

namespace App\Services\Requests;

use App\Data\Requests\GuestVisitData;
use App\Data\Requests\NewRequestData;
use App\Enums\RequestType;
use App\Models\DateInvitation;
use App\Models\Friend;
use App\Models\GuestVisit;
use App\Models\PartnerRequest;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class SocialInvitations
{
    public function getEndsAt(GuestVisitData $data): CarbonImmutable
    {
        Validator::make(['duration_minutes' => $data->durationMinutes], ['duration_minutes' => ['integer', sprintf('between:%d,%d', GuestVisit::MINIMUM_DURATION_MINUTES, GuestVisit::MAXIMUM_DURATION_MINUTES)]])->validate();

        return $data->startsAt->setMicrosecond(0)->utc()->addMinutes($data->durationMinutes);
    }

    public function isSame(PartnerRequest $request, NewRequestData $data): bool
    {
        $guest = $request->guestVisit()->lockForUpdate()->first();
        $date = $request->dateInvitation()->lockForUpdate()->first();
        if (($guest === null) !== ($data->guestVisit === null) || ($date === null) !== ($data->dateInvitation === null)) {
            return false;
        }
        if ($guest !== null && $data->guestVisit !== null) {
            $selections = collect($guest->guests_snapshot)->map(fn (array $item): array => ['id' => $item['id'], 'version' => $item['version']])->all();

            return $selections === $this->getSelections($data->guestVisit) && $guest->starts_at->equalTo($data->guestVisit->startsAt->setMicrosecond(0)) && $guest->duration_minutes === $data->guestVisit->durationMinutes && $guest->reason === $data->guestVisit->reason;
        }
        if ($date !== null && $data->dateInvitation !== null) {
            return $date->kind === $data->dateInvitation->kind && $date->title === $data->dateInvitation->title && $date->starts_at->equalTo($data->dateInvitation->startsAt->setMicrosecond(0)) && $date->description === $data->dateInvitation->description && $date->dress_code === $data->dateInvitation->dressCode;
        }

        return true;
    }

    public function attach(User $actor, PartnerRequest $request, NewRequestData $data): void
    {
        if ($data->guestVisit === null && $data->dateInvitation === null) {
            return;
        }
        if ($data->dishId !== null || $data->moneyAmount !== null || $data->moneyCurrency !== null || $data->heartPrice !== 0 || $data->isVeryWanted || ($data->guestVisit !== null && $data->dateInvitation !== null)) {
            $this->deny('conflicting_attachments');
        }
        if ($data->guestVisit !== null) {
            if ($data->type !== RequestType::Guests || ! $data->guestVisit->startsAt->isFuture()) {
                $this->deny('invalid_visit_time_or_type');
            }
            $endsAt = $this->getEndsAt($data->guestVisit);
            if (! $data->dueAt->setMicrosecond(0)->equalTo($endsAt) || $data->description !== $data->guestVisit->reason) {
                $this->deny('inconsistent_visit_details');
            }
            $snapshot = $this->getGuests($actor, $data->guestVisit);
            $visit = new GuestVisit;
            $visit->forceFill(['partner_request_id' => $request->id, 'guests_snapshot' => $snapshot, 'starts_at' => $data->guestVisit->startsAt->setMicrosecond(0)->utc(), 'ends_at' => $endsAt, 'duration_minutes' => $data->guestVisit->durationMinutes, 'reason' => $data->guestVisit->reason])->save();
            DB::afterCommit(fn () => Log::info('guest_visit.created', ['visit_id' => $visit->id, 'request_id' => $request->id, 'guest_count' => count($snapshot)]));

            return;
        }
        $date = $data->dateInvitation;
        if ($data->type !== RequestType::Event || ! $date->startsAt->isFuture()) {
            $this->deny('invalid_event_time_or_type');
        }
        if (! $data->dueAt->setMicrosecond(0)->equalTo($date->startsAt->setMicrosecond(0)) || $data->description !== ($date->description ?? $date->title)) {
            $this->deny('inconsistent_event_details');
        }
        Validator::make(['title' => $date->title, 'description' => $date->description, 'dress_code' => $date->dressCode], ['title' => ['required', 'string', 'max:120'], 'description' => ['nullable', 'string', 'max:2000'], 'dress_code' => ['nullable', 'string', 'max:500']])->validate();
        $invitation = new DateInvitation;
        $invitation->forceFill(['partner_request_id' => $request->id, 'kind' => $date->kind, 'title' => $date->title, 'starts_at' => $date->startsAt->setMicrosecond(0)->utc(), 'description' => $date->description, 'dress_code' => $date->dressCode])->save();
        DB::afterCommit(fn () => Log::info('date_invitation.created', ['invitation_id' => $invitation->id, 'request_id' => $request->id, 'kind' => $date->kind->value]));
    }

    /** @return list<array{id:int,version:int}> */
    private function getSelections(GuestVisitData $data): array
    {
        Validator::make(['friends' => $data->friends], ['friends' => ['required', 'array', 'list', sprintf('max:%d', GuestVisit::MAXIMUM_GUESTS)], 'friends.*' => ['required', 'array:id,version'], 'friends.*.id' => ['required', 'integer', 'min:1', 'distinct:strict'], 'friends.*.version' => ['required', 'integer', 'min:0']])->validate();

        return array_values(collect($data->friends)->sortBy('id')->all());
    }

    /** @return list<array{id:int,version:int,name:string}> */
    private function getGuests(User $actor, GuestVisitData $data): array
    {
        $selections = $this->getSelections($data);
        $friends = Friend::query()->where('user_id', $actor->id)->whereNull('archived_at')->whereIn('id', array_column($selections, 'id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        $snapshot = [];
        foreach ($selections as $selection) {
            $friend = $friends->get($selection['id']);
            if ($friend === null || $friend->version !== $selection['version']) {
                $this->deny('changed_or_foreign_friend');
            }
            $snapshot[] = ['id' => $friend->id, 'version' => $friend->version, 'name' => $friend->name];
        }

        return $snapshot;
    }

    private function deny(string $reason): never
    {
        Log::warning('social_invitation.denied', ['reason' => $reason]);
        throw ValidationException::withMessages(['friends' => __('Проверьте выбранных друзей, время и параметры приглашения. Обновите страницу.')]);
    }
}
