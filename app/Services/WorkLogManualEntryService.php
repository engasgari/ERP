<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Project;
use App\Models\User;
use App\Models\WorkLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Manual work-log entry from the list page popup (single or bulk rows).
 */
class WorkLogManualEntryService
{
    public const SOURCE = 'manual';

    /**
     * @param  list<array{employee_id?:mixed,work_date_fa?:mixed,start_time?:mixed,end_time?:mixed,project_id?:mixed}>  $rows
     * @return list<WorkLog>
     *
     * @throws ValidationException
     */
    public function createMany(array $rows, User $actor): array
    {
        if (! $actor->isAdmin() && ! $actor->hasPermission('worklogs.manage')) {
            throw ValidationException::withMessages([
                'rows' => 'اجازه ثبت کارکرد دستی را ندارید.',
            ]);
        }

        $normalized = [];
        foreach (array_values($rows) as $index => $row) {
            $line = $this->normalizeRow($row, $index + 1, $actor);
            if ($line !== null) {
                $normalized[] = $line;
            }
        }

        if ($normalized === []) {
            throw ValidationException::withMessages([
                'rows' => 'حداقل یک ردیف کامل (کارمند، تاریخ، ورود و خروج) لازم است.',
            ]);
        }

        return DB::transaction(function () use ($normalized) {
            $created = [];
            foreach ($normalized as $payload) {
                $created[] = WorkLog::query()->create($payload);
            }

            return $created;
        });
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>|null
     */
    private function normalizeRow(array $row, int $lineNumber, User $actor): ?array
    {
        $employeeId = (int) ($row['employee_id'] ?? 0);
        $dateFa = trim((string) ($row['work_date_fa'] ?? ''));
        $startRaw = trim((string) ($row['start_time'] ?? ''));
        $endRaw = trim((string) ($row['end_time'] ?? ''));
        $projectRaw = $row['project_id'] ?? '';

        $isBlank = $employeeId <= 0 && $dateFa === '' && $startRaw === '' && $endRaw === '';
        if ($isBlank) {
            return null;
        }

        if ($employeeId <= 0) {
            throw ValidationException::withMessages([
                "rows.{$lineNumber}" => "ردیف {$lineNumber}: کارمند را انتخاب کنید.",
            ]);
        }

        if (! $actor->canAccessEmployee($employeeId, 'worklogs.manage')) {
            throw ValidationException::withMessages([
                "rows.{$lineNumber}" => "ردیف {$lineNumber}: به این کارمند دسترسی ندارید.",
            ]);
        }

        $employee = Employee::query()->find($employeeId);
        if (! $employee || ! $employee->is_active) {
            throw ValidationException::withMessages([
                "rows.{$lineNumber}" => "ردیف {$lineNumber}: کارمند معتبر نیست.",
            ]);
        }

        $workDate = function_exists('jalaliToGregorianDate')
            ? jalaliToGregorianDate($dateFa)
            : null;

        if (! $workDate) {
            throw ValidationException::withMessages([
                "rows.{$lineNumber}" => "ردیف {$lineNumber}: تاریخ معتبر نیست.",
            ]);
        }

        $start = $this->normalizeTime($startRaw);
        $end = $this->normalizeTime($endRaw);

        if ($start === null || $end === null) {
            throw ValidationException::withMessages([
                "rows.{$lineNumber}" => "ردیف {$lineNumber}: ساعت ورود و خروج را به‌صورت HH:MM وارد کنید.",
            ]);
        }

        $startAt = Carbon::createFromFormat('H:i', $start);
        $endAt = Carbon::createFromFormat('H:i', $end);
        if ($endAt->lessThan($startAt)) {
            $endAt->addDay();
        }

        $hours = round($startAt->diffInMinutes($endAt) / 60, 2);
        if ($hours <= 0) {
            throw ValidationException::withMessages([
                "rows.{$lineNumber}" => "ردیف {$lineNumber}: مدت کارکرد باید بیشتر از صفر باشد.",
            ]);
        }

        $projectId = null;
        if ($projectRaw !== '' && $projectRaw !== null) {
            $projectId = (int) $projectRaw;
            if ($projectId <= 0 || ! Project::query()->whereKey($projectId)->exists()) {
                throw ValidationException::withMessages([
                    "rows.{$lineNumber}" => "ردیف {$lineNumber}: پروژه معتبر نیست.",
                ]);
            }
        }

        $hourlyRate = (float) ($employee->hourly_rate ?: 0);

        return [
            'employee_id' => $employee->id,
            'project_id' => $projectId,
            'work_date' => $workDate,
            'start_time' => $start,
            'end_time' => $end,
            'check_in_time' => $start,
            'check_out_time' => $end,
            'hours' => $hours,
            'is_incomplete' => false,
            'hourly_rate' => $hourlyRate,
            'total_amount' => round($hours * $hourlyRate, 2),
            'description' => 'ثبت دستی',
            'attendance_source' => self::SOURCE,
        ];
    }

    private function normalizeTime(string $value): ?string
    {
        $value = function_exists('normalizePersianDigits')
            ? (string) normalizePersianDigits(trim($value))
            : trim($value);

        if ($value === '') {
            return null;
        }

        if (preg_match('/^(\d{1,2}):(\d{2})(?::\d{2})?$/', $value, $matches)) {
            $hour = (int) $matches[1];
            $minute = (int) $matches[2];
            if ($hour >= 0 && $hour <= 23 && $minute >= 0 && $minute <= 59) {
                return sprintf('%02d:%02d', $hour, $minute);
            }
        }

        return null;
    }
}
