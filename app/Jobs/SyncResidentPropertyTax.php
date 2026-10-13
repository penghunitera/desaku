<?php

namespace App\Jobs;

use App\Models\PropertyTaxSnapshot;
use App\Models\Resident;
use App\Services\PropertyTaxSyncService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Throwable;

class SyncResidentPropertyTax implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $uniqueFor = 3600;

    public int $timeout = 25;

    public int $tries = 0;

    // Only identifiers are serialized into the queue; never plaintext NIK.
    public function __construct(public int $residentId, public int $villageId) {}

    public function uniqueId(): string
    {
        return (string) $this->residentId;
    }

    public function middleware(): array
    {
        return [new RateLimited('property-tax'), (new WithoutOverlapping('pbb-'.$this->residentId))->releaseAfter(5)->expireAfter(60)];
    }

    public function retryUntil(): \DateTimeInterface
    {
        return now()->addHours(2);
    }

    public function handle(PropertyTaxSyncService $sync): void
    {
        $resident = Resident::where('village_id', $this->villageId)->whereIn('resident_status', ['tetap', 'sementara'])->find($this->residentId);
        if (! $resident) {
            PropertyTaxSnapshot::where('resident_id', $this->residentId)->update(['sync_status' => 'failed', 'error_code' => 'resident_inactive']);

            return;
        }
        $sync->sync($resident);
    }

    public function failed(?Throwable $exception): void
    {
        PropertyTaxSnapshot::where('resident_id', $this->residentId)->update(['sync_status' => 'failed', 'error_code' => 'worker_failed']);
    }
}
