<?php

namespace App\Services;

use App\Models\AttendanceLeave;
use App\Models\Employee;
use App\Models\LeaveBalance;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class LeaveManagementService
{
    public function request(array $data, ?int $userId = null): AttendanceLeave
    {
        return DB::transaction(function () use ($data, $userId): AttendanceLeave {
            $payload = $this->normalizePayload($data);

            return AttendanceLeave::create($payload + [
                'requested_by' => $userId,
                'status' => $payload['status'] ?? 'pending',
            ]);
        });
    }

    public function approve(AttendanceLeave $leave, int $userId): AttendanceLeave
    {
        return DB::transaction(function () use ($leave, $userId): AttendanceLeave {
            $leave->update([
                'status' => 'approved',
                'approved_by' => $userId,
                'approved_at' => now(),
                'rejection_reason' => null,
            ]);

            $year = (int) formatJalaliDateSafe($leave->start_date ?: $leave->leave_date, '0');
            if ($year > 0) {
                $this->recalculateBalance($leave->employee, $year);
            }

            return $leave->refresh();
        });
    }

    public function reject(AttendanceLeave $leave, int $userId, ?string $reason = null): AttendanceLeave
    {
        $leave->update([
            'status' => 'rejected',
            'approved_by' => $userId,
            'approved_at' => now(),
            'rejection_reason' => $reason,
        ]);

        return $leave->refresh();
    }

    public function recalculateBalance(Employee $employee, int $year): LeaveBalance
    {
        $range = [
            jalaliToGregorianDateSafe($year, 1, 1),
            jalaliToGregorianDateSafe($year + 1, 1, 1),
        ];

        $usedDays = AttendanceLeave::query()
            ->where('employee_id', $employee->id)
            ->where('status', 'approved')
            ->where('start_date', '>=', $range[0])
            ->where('start_date', '<', $range[1])
            ->get()
            ->sum(fn (AttendanceLeave $leave): float => $this->leaveUsedDays($leave));

        $earnedDays = 26.0;

        return LeaveBalance::updateOrCreate(
            ['employee_id' => $employee->id, 'year' => $year],
            [
                'earned_days' => $earnedDays,
                'used_days' => $usedDays,
                'remaining_days' => $earnedDays - $usedDays,
            ]
        );
    }

    private function normalizePayload(array $data): array
    {
        $type = $data['request_type'] ?? 'hourly';
        $startDate = $data['start_date'] ?? $data['leave_date'] ?? null;
        $endDate = $data['end_date'] ?? $startDate;
        $durationMinutes = (int) ($data['duration_minutes'] ?? 0);

        if ($durationMinutes === 0 && ! empty($data['hours'])) {
            $durationMinutes = (int) round((float) $data['hours'] * 60);
        }

        if ($durationMinutes === 0 && ! empty($data['start_time']) && ! empty($data['end_time']) && $startDate) {
            $durationMinutes = Carbon::parse($startDate . ' ' . $data['end_time'])
                ->diffInMinutes(Carbon::parse($startDate . ' ' . $data['start_time']));
        }

        $totalDays = (float) ($data['total_days'] ?? 0);
        if ($type === 'daily' && $totalDays <= 0 && $startDate && $endDate) {
            $totalDays = Carbon::parse($startDate)->diffInDays(Carbon::parse($endDate)) + 1;
        }

        return $data + [
            'request_type' => $type,
            'leave_date' => $data['leave_date'] ?? $startDate,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'duration_minutes' => $durationMinutes,
            'hours' => $data['hours'] ?? round($durationMinutes / 60, 2),
            'total_days' => $totalDays,
            'reason' => $data['reason'] ?? ($data['description'] ?? null),
        ];
    }

    private function leaveUsedDays(AttendanceLeave $leave): float
    {
        if ($leave->request_type === 'daily') {
            return (float) ($leave->total_days ?: 1);
        }

        return round(((int) $leave->duration_minutes ?: (float) $leave->hours * 60) / 480, 2);
    }
}
