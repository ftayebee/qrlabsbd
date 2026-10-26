<?php

namespace App\Jobs;

use App\Services\Analytics\ScanContext;
use App\Services\Analytics\ScanRecorder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RecordScan implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly ScanContext $context)
    {
        $this->onQueue('analytics');
    }

    public function handle(ScanRecorder $recorder): void
    {
        $recorder->record($this->context);
    }
}
