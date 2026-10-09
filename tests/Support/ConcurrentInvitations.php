<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\User;
use RuntimeException;
use Symfony\Component\Process\Process;

final class ConcurrentInvitations
{
    /**
     * @param  list<array{actor: User, token: string}>  $attempts
     * @return list<string>
     */
    public function run(array $attempts): array
    {
        $barrier = tempnam(sys_get_temp_dir(), 'couple-race-');
        if ($barrier === false) {
            throw new RuntimeException('Could not create a concurrency barrier.');
        }
        unlink($barrier);
        $processes = [];
        try {
            foreach ($attempts as $attempt) {
                // Raw child stdout is the barrier protocol, not a test report.
                $process = new Process([PHP_BINARY, base_path('tests/Support/accept-invitation-worker.php'), (string) $attempt['actor']->id, $attempt['token'], $barrier], base_path(), [...(new MariaDbEnvironment)->getVariables(), 'PAO_DISABLE' => 'true'], timeout: 15);
                $process->start();
                $processes[] = $process;
            }
            foreach ($processes as $process) {
                while (! str_contains($process->getOutput(), 'ready')) {
                    if (! $process->isRunning()) {
                        throw new RuntimeException(sprintf('Worker failed before readiness: %s', sprintf('%s%s', $process->getErrorOutput(), $process->getOutput())));
                    }
                    usleep(10000); // Read accumulated output; readiness may precede polling.
                }
            }
            file_put_contents($barrier, 'start');
            $results = [];
            foreach ($processes as $process) {
                $process->wait();
                if (! $process->isSuccessful()) {
                    throw new RuntimeException(sprintf('Concurrent worker failed: %s', sprintf('%s%s', $process->getErrorOutput(), $process->getOutput())));
                }
                $results[] = str_contains($process->getOutput(), 'accepted') ? 'accepted' : 'rejected';
            }

            return $results;
        } finally {
            foreach ($processes as $process) {
                $process->stop();
            }
            if (file_exists($barrier)) {
                unlink($barrier);
            }
        }
    }
}
