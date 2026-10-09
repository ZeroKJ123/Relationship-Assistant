<?php

declare(strict_types=1);

namespace Tests\Support;

use RuntimeException;
use Symfony\Component\Process\Process;

final class ConcurrentOperations
{
    private const PROCESS_TIMEOUT_SECONDS = 15;

    private const POLL_INTERVAL_MICROSECONDS = 10000;

    /**
     * @param  list<list<string>>  $arguments
     * @return list<string>
     */
    public function run(string $worker, array $arguments): array
    {
        $barrier = tempnam(sys_get_temp_dir(), 'homeassist-race-');
        if ($barrier === false) {
            throw new RuntimeException('Could not create a concurrency barrier.');
        }
        unlink($barrier);
        $processes = [];
        try {
            foreach ($arguments as $values) {
                // Raw stdout carries the synchronization protocol, not a test report.
                $command = [PHP_BINARY, base_path(sprintf('tests/Support/%s', $worker)), ...$values, $barrier];
                $process = new Process($command, base_path(), [...(new MariaDbEnvironment)->getVariables(), 'PAO_DISABLE' => 'true'], timeout: self::PROCESS_TIMEOUT_SECONDS);
                $process->start();
                $processes[] = $process;
            }
            foreach ($processes as $process) {
                while (! str_contains($process->getOutput(), 'ready')) {
                    $process->checkTimeout();
                    if (! $process->isRunning()) {
                        throw new RuntimeException(sprintf('Worker failed before readiness: %s%s', $process->getErrorOutput(), $process->getOutput()));
                    }
                    usleep(self::POLL_INTERVAL_MICROSECONDS);
                }
            }
            file_put_contents($barrier, 'start');
            $results = [];
            foreach ($processes as $process) {
                $process->wait();
                if (! $process->isSuccessful()) {
                    throw new RuntimeException(sprintf('Concurrent worker failed: %s%s', $process->getErrorOutput(), $process->getOutput()));
                }
                $lines = explode("\n", trim($process->getOutput()));
                $result = trim($lines[count($lines) - 1]);
                if (! in_array($result, ['accepted', 'rejected'], true)) {
                    throw new RuntimeException(sprintf('Unexpected worker response: %s', $process->getOutput()));
                }
                $results[] = $result;
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
