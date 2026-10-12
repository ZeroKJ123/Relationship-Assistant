<?php

declare(strict_types=1);

namespace App\Actions\Duties;

use App\Actions\Requests\CreatePartnerRequest;
use App\Actions\Requests\RespondToRequest;
use App\Data\Requests\NewRequestData;
use App\Data\Requests\RequestResponseData;
use App\Enums\NotificationKind;
use App\Enums\RequestResponse;
use App\Enums\RequestType;
use App\Enums\SelectionStatus;
use App\Models\ChoreDraw;
use App\Models\Couple;
use App\Models\User;
use App\Services\Notifications\NotificationOutbox;
use App\Services\Realtime\SelectionPresence;
use App\Services\Requests\RequestContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

final class ManageChoreDraw
{
    public function __construct(private readonly RequestContext $context, private readonly NotificationOutbox $outbox, private readonly CreatePartnerRequest $requests, private readonly RespondToRequest $responses, private readonly SelectionPresence $presence) {}

    public function create(User $actor, Couple $couple, string $description, CarbonImmutable $dueAt, string $operationKey): ChoreDraw
    {
        $description = trim($description);
        $dueAt = $dueAt->setMicrosecond(0)->utc();
        Validator::make(['description' => $description, 'due_at' => $dueAt, 'key' => $operationKey], ['description' => ['required', 'string', 'max:2000'], 'due_at' => ['date', 'after:now'], 'key' => ['uuid']])->validate();

        return DB::transaction(function () use ($actor, $couple, $description, $dueAt, $operationKey): ChoreDraw {
            $current = $this->context->lockCouple($actor, $couple);
            $existing = ChoreDraw::query()->where('first_user_id', $actor->id)->where('operation_key', $operationKey)->lockForUpdate()->first();
            if ($existing !== null) {
                if ($existing->couple_id !== $current->id || $existing->description !== $description || ! $existing->due_at->equalTo($dueAt)) {
                    $this->context->deny('changed_draw_payload');
                }

                return $existing;
            }
            $partner = $current->members()->where('user_id', '!=', $actor->id)->sole();
            $draw = new ChoreDraw;
            $draw->forceFill(['couple_id' => $current->id, 'first_user_id' => $actor->id, 'second_user_id' => $partner->user_id, 'description' => $description, 'due_at' => $dueAt, 'operation_key' => $operationKey, 'first_ready' => false, 'second_ready' => false, 'status' => SelectionStatus::Waiting])->save();
            $this->outbox->record($current->id, $partner->user_id, NotificationKind::ChoreDraw, $draw->id, expiresAt: $dueAt);
            DB::afterCommit(fn () => Log::info('chore.draw_created', ['draw_id' => $draw->id, 'actor_id' => $actor->id]));

            return $draw;
        }, attempts: 3);
    }

    public function answer(User $actor, Couple $couple, ChoreDraw $draw, bool $accepted): void
    {
        DB::transaction(function () use ($actor, $couple, $draw, $accepted): void {
            $currentCouple = $this->context->lockCouple($actor, $couple);
            $current = ChoreDraw::query()->whereKey($draw->id)->where('couple_id', $currentCouple->id)->lockForUpdate()->firstOrFail();
            if ($current->status !== SelectionStatus::Waiting) {
                return;
            }
            if (! $accepted) {
                $current->forceFill(['status' => SelectionStatus::Declined])->save();

                return;
            }
            if (! $current->due_at->isFuture()) {
                $this->context->deny('expired_deadline');
            }
            $current->setAttribute($actor->id === $current->first_user_id ? 'first_ready' : 'second_ready', true);
            $current->save();
            DB::afterCommit(fn () => Log::info('chore.draw_answered', ['draw_id' => $current->id, 'actor_id' => $actor->id, 'status' => $current->status->value, 'executor_id' => $current->executor_id]));
        }, attempts: 3);
    }

    public function start(User $actor, Couple $couple, ChoreDraw $draw): void
    {
        DB::transaction(function () use ($actor, $couple, $draw): void {
            $currentCouple = $this->context->lockCouple($actor, $couple);
            $current = ChoreDraw::query()->whereKey($draw->id)->where('couple_id', $currentCouple->id)->lockForUpdate()->firstOrFail();
            if ($current->status !== SelectionStatus::Waiting || ! $current->first_ready || ! $current->second_ready || ! $this->presence->areBothPresent($current)) {
                return;
            }
            if (! $current->due_at->isFuture()) {
                $this->context->deny('expired_deadline');
            }
            $this->finish($currentCouple, $current);
            $current->spin_started_at = CarbonImmutable::now()->addSeconds(SelectionPresence::START_DELAY_SECONDS);
            $current->save();
            DB::afterCommit(fn () => Log::info('chore.draw_started', ['draw_id' => $current->id, 'executor_id' => $current->executor_id]));
        }, attempts: 3);
    }

    private function finish(Couple $couple, ChoreDraw $draw): void
    {
        $executorId = random_int(0, 1) === 0 ? $draw->first_user_id : $draw->second_user_id;
        $reviewerId = $executorId === $draw->first_user_id ? $draw->second_user_id : $draw->first_user_id;
        $executor = User::query()->findOrFail($executorId);
        $reviewer = User::query()->findOrFail($reviewerId);
        $request = $this->requests->execute($reviewer, $couple, new NewRequestData(RequestType::Service, $draw->description, $draw->due_at, operationKey: $draw->operation_key));
        $this->responses->execute($executor, $couple, $request, new RequestResponseData(RequestResponse::Agree, $request->version));
        $draw->forceFill(['executor_id' => $executorId, 'partner_request_id' => $request->id, 'status' => SelectionStatus::Matched]);
    }
}
