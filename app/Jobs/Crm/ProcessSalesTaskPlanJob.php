<?php

namespace App\Jobs\Crm;

use App\Models\Crm\SalesTaskPlan;
use App\Services\Crm\SalesTaskPlannerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ProcessSalesTaskPlanJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $uniqueFor = 900;

    public function __construct(public readonly int $planId)
    {
        $this->timeout = (int) config('crm_ai.timeout_seconds', 60)
            * max(1, (int) config('crm_ai.retry.max_attempts', 2))
            + 120;
    }

    public function uniqueId(): string
    {
        return 'sales-task-plan:'.$this->planId;
    }

    public function handle(SalesTaskPlannerService $service): void
    {
        $service->process($this->planId);
    }

    public function failed(?Throwable $exception): void
    {
        SalesTaskPlan::query()
            ->whereKey($this->planId)
            ->where('status', SalesTaskPlan::STATUS_PENDING)
            ->update([
                'status' => SalesTaskPlan::STATUS_FAILED,
                'failure_reason' => 'پردازش در صف با خطا متوقف شد.',
            ]);
    }
}
