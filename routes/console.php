<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;
use Shortwave\Presentation\Console\Command\FlushClickBuffer;
use Shortwave\Presentation\Console\Command\FlushClickCounters;
use Shortwave\Presentation\Console\Command\PruneClickEvents;

/*
|--------------------------------------------------------------------------
| Scheduled work
|--------------------------------------------------------------------------
|
| Two of these three are load-bearing rather than housekeeping: the click counters
| and the analytics buffer both hold state that only these commands move into a
| durable store. If the scheduler stops, click totals freeze and reports go stale —
| so both run every minute and neither is allowed to overlap itself.
|
*/

Schedule::command(FlushClickCounters::class)
    ->everyMinute()
    ->withoutOverlapping(5)
    ->runInBackground();

Schedule::command(FlushClickBuffer::class)
    ->everyMinute()
    ->withoutOverlapping(5)
    ->runInBackground();

// Housekeeping. The TTL index does most of this on its own; the nightly run is what
// catches up after the retention window is shortened.
Schedule::command(PruneClickEvents::class)
    ->dailyAt('03:30')
    ->withoutOverlapping(30);
