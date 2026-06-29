<?php

namespace App\Console\Commands;

use App\Services\SystemLoadMetrics;
use Illuminate\Console\Command;

class RecordSystemMetricSnapshotCommand extends Command
{
    protected $signature = 'shop:system-metrics-record';

    protected $description = 'Record one minute-level system load metric snapshot.';

    public function handle(SystemLoadMetrics $metrics): int
    {
        $metrics->record();
        $this->info('System metric snapshot recorded.');

        return self::SUCCESS;
    }
}
