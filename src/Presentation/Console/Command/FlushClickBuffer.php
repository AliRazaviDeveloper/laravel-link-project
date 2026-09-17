<?php

declare(strict_types=1);

namespace Shortwave\Presentation\Console\Command;

use Illuminate\Console\Command;
use Shortwave\Infrastructure\Queue\BufferedClickRecorder;

/**
 * Drains the click buffer even when it never reaches the batch size.
 *
 * Without this, a quiet account's last few clicks would sit in Redis until enough
 * traffic arrived to fill a batch — which for a low-volume link could be days.
 */
final class FlushClickBuffer extends Command
{
    protected $signature = 'shortwave:flush-buffer {--max-batches=20}';

    protected $description = 'Dispatch any clicks waiting in the analytics buffer';

    public function handle(BufferedClickRecorder $recorder): int
    {
        $batches = max(1, (int) $this->option('max-batches'));
        $total = 0;

        for ($i = 0; $i < $batches; $i++) {
            $flushed = $recorder->flush();

            if ($flushed === 0) {
                break;
            }

            $total += $flushed;
        }

        $this->components->info(sprintf('Queued %d buffered clicks.', $total));

        return self::SUCCESS;
    }
}
