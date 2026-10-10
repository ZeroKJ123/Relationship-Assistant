<?php

declare(strict_types=1);

namespace App\Actions\Requests;

use App\Data\Requests\ActRatingData;
use App\Enums\RequestStatus;
use App\Enums\RequestType;
use App\Models\Couple;
use App\Models\PartnerRequest;
use App\Models\User;
use App\Services\Requests\RequestContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

final class RatePartnerAct
{
    public function __construct(private readonly RequestContext $context) {}

    public function execute(User $actor, Couple $couple, PartnerRequest $request, ActRatingData $data): void
    {
        $version = $data->expectedVersion;
        $rating = $data->rating;
        $comment = $data->comment;
        Validator::make(['rating' => $rating, 'comment' => $comment], ['rating' => ['integer', 'between:1,5'], 'comment' => ['nullable', 'string', 'max:1000']])->validate();
        DB::transaction(function () use ($actor, $couple, $request, $version, $rating, $comment): void {
            $current = $this->context->lockRequest($actor, $couple, $request, 'respond');
            if ($current->type !== RequestType::Rating) {
                $this->context->deny('not_rating_request', requestId: $current->id);
            }
            if ($current->version === $version + 1 && $current->rating === $rating && $current->rating_comment === $comment) {
                return;
            }
            $this->context->requireVersion($current, $version);
            if ($current->status !== RequestStatus::Pending || ! $current->due_at->isFuture()) {
                $this->context->deny('rating_unavailable', requestId: $current->id);
            }
            $current->forceFill(['rating' => $rating, 'rating_comment' => $comment, 'status' => RequestStatus::Accepted, 'accepted_at' => now(), 'version' => $current->version + 1])->save();
            $event = $this->context->record($actor, $current, 'rated');
            DB::afterCommit(fn () => $this->context->logEvent($event));
        }, attempts: 3);
    }
}
