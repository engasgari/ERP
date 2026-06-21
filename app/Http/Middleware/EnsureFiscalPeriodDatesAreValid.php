<?php

namespace App\Http\Middleware;

use App\Services\FiscalPeriodService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class EnsureFiscalPeriodDatesAreValid
{
    private const DATE_KEYS = [
        'date',
        'date_from',
        'date_to',
        'start_date',
        'end_date',
        'document_date',
        'transaction_date',
        'invoice_date',
        'work_date',
        'effective_date',
        'planned_start_date',
        'planned_end_date',
        'actual_start_date',
        'actual_end_date',
        'leave_date',
        'mission_date',
        'payment_date',
        'hire_date',
        'termination_date',
    ];

    public function __construct(private readonly FiscalPeriodService $periods)
    {
    }

    public function handle(Request $request, Closure $next)
    {
        if (in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            foreach ($this->collectDates($request) as $value) {
                $this->assertAllowed($value);
            }
        }

        return $next($request);
    }

    private function collectDates(Request $request): array
    {
        $values = [];
        $payload = $request->all();

        foreach (self::DATE_KEYS as $key) {
            if (! array_key_exists($key, $payload)) {
                continue;
            }

            $value = $payload[$key];
            if (is_array($value)) {
                foreach ($value as $nested) {
                    if (is_string($nested) || $nested instanceof Carbon) {
                        $values[] = $nested;
                    }
                }
                continue;
            }

            if (is_string($value) || $value instanceof Carbon) {
                $values[] = $value;
            }
        }

        foreach ($request->route()?->parameters() ?? [] as $parameter) {
            if (! $parameter instanceof Model) {
                continue;
            }

            foreach (self::DATE_KEYS as $key) {
                $value = $parameter->getAttribute($key);
                if (is_string($value) || $value instanceof Carbon) {
                    $values[] = $value;
                }
            }
        }

        return $values;
    }

    private function assertAllowed(Carbon|string $value): void
    {
        try {
            $this->periods->ensureDateIsAllowed($value);
        } catch (\Throwable $exception) {
            throw ValidationException::withMessages([
                'date' => 'تاریخ انتخاب‌شده خارج از بازه مالی فعال است.',
            ]);
        }
    }
}
