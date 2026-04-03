<?php

namespace App\Jobs;

use App\Services\Ordonnances\OrdonnanceVerificationProcessor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessOrdonnanceVerificationJob implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public int $ordonnanceId) {}

    public function handle(OrdonnanceVerificationProcessor $processor): void
    {
        $processor->process($this->ordonnanceId);
    }
}
