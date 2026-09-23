<?php

namespace App\Services;

use App\Models\AttendanceSummary;
use App\Models\MonthlyAttendance;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class AttendanceAdjustmentService
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function update(MonthlyAttendance $attendance, array $payload, ?int $userId = null): MonthlyAttendance
    {
        return DB::transaction(function () use ($attendance, $payload, $userId) {
            $hours = $this->normalizedHours($payload);

            if (($payload['recalculate_payable'] ?? false) === true) {
                $requiredHours = array_key_exists('required_hours', $payload)
                    ? round((float) $payload['required_hours'], 2)
                    : (float) $attendance->required_hours;
                $hours = $this->recalculatePayableHours($hours, $requiredHours);
            }

            $meta = $attendance->meta ?? [];
            $meta['manually_adjusted'] = true;
            $meta['adjusted_at'] = now()->toDateTimeString();
            $meta['adjusted_by'] = $userId;
            $meta['system_values_before_adjustment'] = $meta['system_values_before_adjustment'] ?? [
                'work_days' => (float) ($attendance->work_days ?? $attendance->present_days ?? 0),
                'present_days' => (float) ($attendance->present_days ?? 0),
                'absence_days' => (float) ($attendance->absence_days ?? 0),
                'normal_hours' => (float) $attendance->normal_hours,
                'worked_hours' => (float) $attendance->worked_hours,
                'overtime_hours' => (float) $attendance->overtime_hours,
                'delay_hours' => (float) $attendance->delay_hours,
                'early_leave_hours' => (float) $attendance->early_leave_hours,
                'absence_hours' => (float) $attendance->absence_hours,
                'leave_hours' => (float) $attendance->leave_hours,
                'mission_hours' => (float) $attendance->mission_hours,
                'night_hours' => (float) $attendance->night_hours,
                'holiday_hours' => (float) $attendance->holiday_hours,
                'payable_hours' => (float) $attendance->payable_hours,
                'net_payable_hours' => (float) $attendance->net_payable_hours,
            ];

            $workDays = array_key_exists('work_days', $payload)
                ? round((float) $payload['work_days'], 2)
                : (float) ($attendance->work_days ?? $attendance->present_days ?? 0);
            $absenceDays = array_key_exists('absence_days', $payload)
                ? round((float) $payload['absence_days'], 2)
                : (float) ($attendance->absence_days ?? 0);

            if ($workDays < 0 || $absenceDays < 0) {
                throw ValidationException::withMessages([
                    'work_days' => 'روز کارکرد و روز غیبت نمی‌توانند منفی باشند.',
                ]);
            }

            $attributes = [
                'work_days' => $workDays,
                'present_days' => (int) round($workDays),
                'absence_days' => $absenceDays,
                'normal_hours' => $hours['normal_hours'],
                'worked_hours' => $hours['normal_hours'],
                'overtime_hours' => $hours['overtime_hours'],
                'delay_hours' => $hours['delay_hours'],
                'early_leave_hours' => $hours['early_leave_hours'],
                'absence_hours' => $hours['absence_hours'],
                'leave_hours' => $hours['leave_hours'],
                'mission_hours' => $hours['mission_hours'],
                'night_hours' => $hours['night_hours'],
                'holiday_hours' => $hours['holiday_hours'],
                'payable_hours' => $hours['payable_hours'],
                'net_payable_hours' => $hours['net_payable_hours'],
                'delay_minutes' => (int) round($hours['delay_hours'] * 60),
                'early_leave_minutes' => (int) round($hours['early_leave_hours'] * 60),
                'status' => 'adjusted',
                'meta' => $meta,
            ];

            if (Schema::hasColumn('monthly_attendances', 'absence_minutes')) {
                $attributes['absence_minutes'] = (int) round($hours['absence_hours'] * 60);
            }

            $attendance->update($attributes);

            $this->syncSummary($attendance->fresh(), $hours, $workDays, $absenceDays, $userId);

            return $attendance->fresh(['employee', 'period']);
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{
     *     normal_hours: float,
     *     overtime_hours: float,
     *     delay_hours: float,
     *     early_leave_hours: float,
     *     absence_hours: float,
     *     leave_hours: float,
     *     mission_hours: float,
     *     night_hours: float,
     *     holiday_hours: float,
     *     payable_hours: float,
     *     net_payable_hours: float
     * }
     */
    private function normalizedHours(array $payload): array
    {
        $fields = [
            'normal_hours',
            'overtime_hours',
            'delay_hours',
            'early_leave_hours',
            'absence_hours',
            'leave_hours',
            'mission_hours',
            'night_hours',
            'holiday_hours',
            'payable_hours',
            'net_payable_hours',
        ];

        $hours = [];
        foreach ($fields as $field) {
            if (! array_key_exists($field, $payload)) {
                throw ValidationException::withMessages([
                    $field => "فیلد {$field} الزامی است.",
                ]);
            }

            $value = round((float) $payload[$field], 2);
            if ($value < 0) {
                throw ValidationException::withMessages([
                    $field => 'مقادیر کارکرد نمی‌توانند منفی باشند.',
                ]);
            }

            $hours[$field] = $value;
        }

        return $hours;
    }

    /**
     * @param  array<string, float>  $hours
     * @return array<string, float>
     */
    private function recalculatePayableHours(array $hours, float $requiredHours): array
    {
        $deduction = min(
            max(0, $requiredHours),
            max($hours['absence_hours'], $hours['delay_hours'] + $hours['early_leave_hours'])
        );

        $hours['net_payable_hours'] = round(max(0, $requiredHours - $deduction), 2);
        $hours['payable_hours'] = round($hours['net_payable_hours'] + $hours['overtime_hours'] + $hours['holiday_hours'], 2);

        return $hours;
    }

    /**
     * @param  array<string, float>  $hours
     */
    private function syncSummary(
        MonthlyAttendance $attendance,
        array $hours,
        float $workDays,
        float $absenceDays,
        ?int $userId
    ): void
    {
        $summaryId = $attendance->meta['attendance_summary_id'] ?? null;
        $summary = $summaryId
            ? AttendanceSummary::query()->find($summaryId)
            : AttendanceSummary::query()
                ->where('employee_id', $attendance->employee_id)
                ->where('payroll_period_id', $attendance->payroll_period_id)
                ->first();

        if (! $summary) {
            return;
        }

        $meta = $summary->meta ?? [];
        $meta['manually_adjusted'] = true;
        $meta['adjusted_at'] = now()->toDateTimeString();
        $meta['adjusted_by'] = $userId;

        $payload = [
            'worked_days' => $workDays,
            'worked_hours' => $hours['normal_hours'],
            'worked_minutes' => (int) round($hours['normal_hours'] * 60),
            'overtime_hours' => $hours['overtime_hours'],
            'overtime_minutes' => (int) round($hours['overtime_hours'] * 60),
            'delay_minutes' => (int) round($hours['delay_hours'] * 60),
            'early_leave_minutes' => (int) round($hours['early_leave_hours'] * 60),
            'absence_hours' => $hours['absence_hours'],
            'absence_minutes' => (int) round($hours['absence_hours'] * 60),
            'leave_hours' => $hours['leave_hours'],
            'hourly_leave_minutes' => (int) round($hours['leave_hours'] * 60),
            'mission_hours' => $hours['mission_hours'],
            'hourly_mission_minutes' => (int) round($hours['mission_hours'] * 60),
            'holiday_minutes' => (int) round($hours['holiday_hours'] * 60),
            'net_payable_hours' => $hours['net_payable_hours'],
            'status' => 'adjusted',
            'meta' => $meta,
        ];

        if (Schema::hasColumn('attendance_summaries', 'absence_days')) {
            $payload['absence_days'] = $absenceDays;
        }

        $summary->update($payload);
    }
}
