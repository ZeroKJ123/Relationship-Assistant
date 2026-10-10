<?php

declare(strict_types=1);

namespace App\Services\Requests;

use App\Enums\CoupleStatus;
use App\Enums\NotificationKind;
use App\Models\Couple;
use App\Models\CoupleMember;
use App\Models\PartnerRequest;
use App\Models\RequestEvent;
use App\Models\User;
use App\Services\Couples\CoupleLock;
use App\Services\Notifications\NotificationOutbox;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

final class RequestContext
{
    private const PARTNER_COUNT = 2;

    public function __construct(private readonly CoupleLock $lock, private readonly NotificationOutbox $outbox) {}

    public function lockCouple(User $actor, Couple $couple): Couple
    {
        Gate::forUser($actor)->authorize('update', $couple);
        $ids = $couple->members()->orderBy('user_id')->get()->map(fn (CoupleMember $member): int => $member->user_id)->all();
        $this->lock->lockUsers(array_values($ids));
        $current = $this->lock->lockCouple($couple->id);
        $members = $current->members()->orderBy('user_id')->lockForUpdate()->get();
        Gate::forUser($actor)->authorize('update', $current);
        if ($current->status !== CoupleStatus::Active || $members->count() !== self::PARTNER_COUNT || $members->map(fn (CoupleMember $member): int => $member->user_id)->all() !== $ids) {
            $this->deny('inactive_couple', coupleId: $current->id);
        }

        return $current;
    }

    public function lockRequest(User $actor, Couple $couple, PartnerRequest $request, string $ability): PartnerRequest
    {
        $currentCouple = $this->lockCouple($actor, $couple);
        $current = PartnerRequest::query()->where('couple_id', $currentCouple->id)->whereKey($request->id)->lockForUpdate()->firstOrFail();
        $current->setRelation('couple', $currentCouple);
        Gate::forUser($actor)->authorize($ability, $current);

        return $current;
    }

    public function requireVersion(PartnerRequest $request, int $version): void
    {
        Log::debug('request.version_checked', ['request_id' => $request->id, 'expected_version' => $version, 'current_version' => $request->version]);
        if ($request->version !== $version) {
            $this->deny('stale_version', requestId: $request->id);
        }
    }

    public function deny(string $reason, ?int $requestId = null, ?int $coupleId = null): never
    {
        Log::warning('request.transition_denied', ['reason' => $reason, 'request_id' => $requestId, 'couple_id' => $coupleId]);
        throw ValidationException::withMessages(['partner_request' => __('Просьба изменилась или действие недоступно. Обновите страницу.')]);
    }

    public function record(User $actor, PartnerRequest $request, string $type): RequestEvent
    {
        $event = new RequestEvent;
        $event->forceFill(['partner_request_id' => $request->id, 'actor_id' => $actor->id, 'type' => $type, 'status' => $request->status, 'version' => $request->version])->save();

        $recipientId = $actor->id === $request->author_id ? $request->recipient_id : $request->author_id;
        $this->outbox->record($request->couple_id, $recipientId, NotificationKind::RequestEvent, $event->id);

        return $event;
    }

    public function logEvent(RequestEvent $event): void
    {
        Log::info('request.transitioned', ['request_id' => $event->partner_request_id, 'actor_id' => $event->actor_id, 'event_id' => $event->id, 'type' => $event->type, 'status' => $event->status->value, 'version' => $event->version]);
    }
}
