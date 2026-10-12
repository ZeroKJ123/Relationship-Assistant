<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Actions\Requests\CreatePartnerRequest;
use App\Data\Requests\NewRequestData;
use App\Enums\CoupleStatus;
use App\Enums\RequestType;
use App\Models\AdminAudit;
use App\Models\Couple;
use App\Models\PartnerRequest;
use App\Models\User;
use App\Services\Admin\PanelAccess;
use Carbon\CarbonImmutable;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

final class CreateSystemRequest
{
    public function __construct(private readonly CreatePartnerRequest $requests, private readonly PanelAccess $access) {}

    public function execute(User $admin, User $recipient, string $description, string $operationKey): PartnerRequest
    {
        abort_unless($this->access->isOwner($admin), Response::HTTP_FORBIDDEN);

        return DB::transaction(function () use ($admin, $recipient, $description, $operationKey): PartnerRequest {
            $couple = Couple::query()->where('status', CoupleStatus::Active)->whereRelation('members', 'active_user_id', $recipient->id)->firstOrFail();
            $authorId = $couple->members()->where('user_id', '!=', $recipient->id)->sole()->user_id;
            $author = User::query()->findOrFail($authorId);
            $existing = PartnerRequest::query()->where('author_id', $author->id)->where('operation_key', $operationKey)->first();
            $request = $this->requests->execute($author, $couple, new NewRequestData(RequestType::Service, $description, $existing->due_at ?? CarbonImmutable::now()->addDay(), operationKey: $operationKey));
            abort_if($request->created_by_admin_id !== null && $request->created_by_admin_id !== $admin->id, Response::HTTP_FORBIDDEN);
            if ($request->created_by_admin_id === null) {
                abort_unless($request->wasRecentlyCreated, Response::HTTP_CONFLICT);
                $request->forceFill(['created_by_admin_id' => $admin->id])->save();
                $audit = new AdminAudit;
                $audit->forceFill(['admin_id' => $admin->id, 'target_user_id' => $recipient->id, 'operation' => 'panel.system_request'])->save();
            }

            return $request;
        }, attempts: 3);
    }
}
