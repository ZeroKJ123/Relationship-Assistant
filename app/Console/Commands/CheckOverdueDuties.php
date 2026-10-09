<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Duties\CheckOverdueDuty;
use App\Enums\CoupleStatus;
use App\Enums\DutyStatus;
use App\Models\Duty;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

final class CheckOverdueDuties extends Command
{
    private const CHUNK_SIZE = 100;

    protected $signature = 'duties:check-overdue';

    protected $description = 'Record overdue duties once without penalizing reports awaiting verification';

    public function handle(CheckOverdueDuty $check): int
    {
        Duty::query()->where('status', DutyStatus::Pending)->where('due_at', '<=', now())->whereRelation('couple', 'status', CoupleStatus::Active)->with('couple')->chunkById(self::CHUNK_SIZE, function (Collection $duties) use ($check): void {
            foreach ($duties as $duty) {
                try {
                    $check->execute($duty);
                } catch (AuthorizationException|ValidationException) {
                    Log::warning('duties.overdue_check.skipped', ['duty_id' => $duty->id, 'reason' => 'couple_changed']);

                    continue;
                }
            }
        });

        return self::SUCCESS;
    }
}
