<?php

namespace App\Jobs\Crm;

use App\Models\Crm\CustomerIntelligence;
use App\Services\Crm\CustomerIntelligenceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Provider retries happen inside AiRunner with backoff; the job itself runs once so a queue retry
 * can never produce a second summary or a second provider charge.
 */
class ProcessCustomerIntelligenceJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $uniqueFor = 900;

    public function __construct(public readonly int $intelligenceId)
    {
        $this->timeout = (int) config('crm_ai.timeout_seconds', 60)
            * max(1, (int) config('crm_ai.retry.max_attempts', 2))
            + 120;
    }

    public function uniqueId(): string
    {
        return 'customer-intelligence:'.$this->intelligenceId;
    }

    public function handle(CustomerIntelligenceService $service): void
    {
        $service->process($this->intelligenceId);
    }

    public function failed(?Throwable $exception): void
    {
        CustomerIntelligence::query()
            ->whereKey($this->intelligenceId)
            ->where('status', CustomerIntelligence::STATUS_PENDING)
            ->update([
                'status' => CustomerIntelligence::STATUS_FAILED,
                'failure_reason' => 'پردازش در صف با خطا متوقف شد.',
            ]);
    }
}
