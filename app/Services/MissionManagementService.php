<?php

namespace App\Services;

use App\Models\AttendanceMission;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class MissionManagementService
{
    public function __construct(private readonly FiscalPeriodService $periods)
    {
    }

    public function request(array $data, ?int $userId = null): AttendanceMission
    {
        return DB::transaction(function () use ($data, $userId): AttendanceMission {
            $payload = $this->normalizePayload($data);
            $this->periods->ensureDateIsAllowed($payload['mission_date'] ?? $payload['start_date'] ?? null);

            return AttendanceMission::create($payload + [
                'requested_by' => $userId,
                'status' => $payload['status'] ?? 'pending',
            ]);
        });
    }

    public function approve(AttendanceMission $mission, int $userId): AttendanceMission
    {
        $this->periods->ensureDateIsAllowed($mission->mission_date ?? $mission->start_date ?? null);

        $mission->update([
            'status' => 'approved',
            'approved_by' => $userId,
            'approved_at' => now(),
            'rejection_reason' => null,
        ]);

        return $mission->refresh();
    }

    public function reject(AttendanceMission $mission, int $userId, ?string $reason = null): AttendanceMission
    {
        $this->periods->ensureDateIsAllowed($mission->mission_date ?? $mission->start_date ?? null);

        $mission->update([
            'status' => 'rejected',
            'approved_by' => $userId,
            'approved_at' => now(),
            'rejection_reason' => $reason,
        ]);

        return $mission->refresh();
    }

    private function normalizePayload(array $data): array
    {
        $type = $data['request_type'] ?? 'hourly';
        $startDate = $data['start_date'] ?? $data['mission_date'] ?? null;
        $endDate = $data['end_date'] ?? $startDate;
        $durationMinutes = (int) ($data['duration_minutes'] ?? 0);

        if ($durationMinutes === 0 && ! empty($data['hours'])) {
            $durationMinutes = (int) round((float) $data['hours'] * 60);
        }

        if ($durationMinutes === 0 && ! empty($data['start_time']) && ! empty($data['end_time']) && $startDate) {
            $durationMinutes = Carbon::parse($startDate . ' ' . $data['end_time'])
                ->diffInMinutes(Carbon::parse($startDate . ' ' . $data['start_time']));
        }

        $totalDays = isset($data['total_days']) && $data['total_days'] !== ''
            ? (float) $data['total_days']
            : 0.0;
        if ($type === 'daily' && $totalDays <= 0 && $startDate && $endDate) {
            $totalDays = Carbon::parse($startDate)->diffInDays(Carbon::parse($endDate)) + 1;
        }

        if ($type === 'daily' && $durationMinutes <= 0 && $totalDays > 0) {
            $durationMinutes = (int) round($totalDays * 480);
        }

        return array_merge($data, [
            'request_type' => $type,
            'mission_date' => $data['mission_date'] ?? $startDate,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'duration_minutes' => $durationMinutes,
            'hours' => isset($data['hours']) && $data['hours'] !== ''
                ? $data['hours']
                : round($durationMinutes / 60, 2),
            'total_days' => $totalDays,
        ]);
    }
}
