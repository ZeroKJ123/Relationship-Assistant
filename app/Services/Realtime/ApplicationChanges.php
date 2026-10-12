<?php

declare(strict_types=1);

namespace App\Services\Realtime;

use App\Events\ApplicationUpdated;
use Illuminate\Database\Events\QueryExecuted;

final class ApplicationChanges
{
    private const TECHNICAL_TABLES = ['sessions', 'jobs', 'job_batches', 'failed_jobs', 'cache', 'cache_locks', 'migrations', 'telegram_update_receipts', 'telegram_callback_actions', 'telegram_login_attempts', 'telegram_link_codes', 'recovery_codes', 'admin_audits'];

    public function handle(QueryExecuted $query): void
    {
        if (config('realtime.enabled') !== true || preg_match('/^\s*(?:insert(?:\s+or\s+ignore|\s+ignore)?\s+into|update|delete\s+from)\s+[`"\[]?([a-z_]+)/i', $query->sql, $matches) !== 1) {
            return;
        }
        if (in_array(strtolower($matches[1]), self::TECHNICAL_TABLES, true)) {
            return;
        }
        if ($query->connection->transactionLevel() > 0) {
            $query->connection->afterCommit(fn () => ApplicationUpdated::dispatch());

            return;
        }
        ApplicationUpdated::dispatch();
    }
}
