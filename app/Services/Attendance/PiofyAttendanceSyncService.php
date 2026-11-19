<?php

namespace App\Services\Attendance;

use App\Models\WorkLog;
use Carbon\Carbon;
use Hekmatinasser\Verta\Verta;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PiofyAttendanceSyncService
{
    public function __construct(
        private PiofyDeviceClient $deviceClient,
        private WorkLogAttendanceImportService $importService,
    ) {
    }

    /**
     * @return array{imported:int,skipped:int,errors:list<string>,fetched:int,since:?string}
     */
    public function sync(?Carbon $since = null): array
    {
        $since ??= $this->defaultSinceDate();
        $rows = $this->deviceClient->fetchAttendanceRows();
        $filtered = $this->filterRowsSince($rows, $since);

        [$imported, $errors] = $this->importService->importAttendanceRows($filtered, 'device_sync');

        Cache::put('piofy.last_sync_at', now()->toDateTimeString(), now()->addYear());

        Log::info('Piofy attendance sync finished.', [
            'fetched' => count($rows),
            'filtered' => count($filtered),
            'imported' => $imported,
            'errors' => count($errors),
            'since' => $since->toDateString(),
        ]);

        return [
            'imported' => $imported,
            'skipped' => max(0, count($filtered) - $imported),
            'errors' => $errors,
            'fetched' => count($rows),
            'since' => $since->toDateString(),
        ];
    }

    private function defaultSinceDate(): Carbon
    {
        $lastWorkDate = WorkLog::query()
            ->whereIn('attendance_source', ['device_import', 'device_sync'])
            ->max('work_date');

        if ($lastWorkDate) {
            return Carbon::parse($lastWorkDate)->startOfDay();
        }

        return now()->subDays(30)->startOfDay();
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function filterRowsSince(array $rows, Carbon $since): array
    {
        $sinceStart = $since->copy()->startOfDay();

        return collect($rows)
            ->filter(function (array $row) use ($sinceStart) {
                $dateTime = $this->rowDateTime($row);

                return ! $dateTime || $dateTime->greaterThanOrEqualTo($sinceStart);
            })
            ->values()
            ->all();
    }

    private function rowDateTime(array $row): ?Carbon
    {
        $gregorian = trim((string) ($row['gregorian_datetime'] ?? ''));
        $jalali = trim((string) ($row['jalali_datetime'] ?? ''));

        if ($gregorian !== '') {
            try {
                return Carbon::parse($gregorian);
            } catch (\Throwable) {
            }
        }

        if ($jalali !== '') {
            try {
                return Carbon::instance(Verta::parse(str_replace('/', '-', $jalali))->datetime());
            } catch (\Throwable) {
            }
        }

        return null;
    }
}
