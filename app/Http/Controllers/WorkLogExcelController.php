<?php

namespace App\Http\Controllers;

use App\Models\WorkLog;
use App\Services\Attendance\PiofyAttendanceSyncService;
use App\Services\Attendance\WorkLogAttendanceImportService;
use Hekmatinasser\Verta\Verta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;

class WorkLogExcelController extends Controller
{
    public function __construct(
        private WorkLogAttendanceImportService $importService,
        private PiofyAttendanceSyncService $syncService,
    ) {
    }

    public function importView()
    {
        return view('worklog.import', [
            'piofyEnabled' => (bool) config('piofy.enabled'),
            'piofyBaseUrl' => (string) config('piofy.base_url'),
            'lastSyncAt' => cache('piofy.last_sync_at'),
        ]);
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|max:20480',
        ], [
            'file.required' => 'لطفا فایل خروجی دستگاه را انتخاب کنید.',
            'file.max' => 'حجم فایل نباید بیشتر از ۲۰ مگابایت باشد.',
        ]);

        try {
            $uploaded = $request->file('file');
            $extension = strtolower($uploaded->getClientOriginalExtension() ?: '');

            if (! in_array($extension, ['csv', 'txt', 'xlsx', 'xls'], true)) {
                return back()->with('error', 'فرمت فایل باید csv یا xlsx باشد.');
            }

            $rows = $this->importService->readImportRows($uploaded->getRealPath(), $extension);

            if (! $this->importService->isAttendanceDeviceFile($rows)) {
                return back()->with('error', 'این صفحه فقط خروجی دستگاه تردد (Piofy) را می‌پذیرد.');
            }

            [$importedCount, $errors] = $this->importService->importAttendanceRows($rows, 'device_import');

            return back()->with('success', $this->formatImportMessage($importedCount, $errors));
        } catch (\Throwable $e) {
            Log::error('WorkLog import error: '.$e->getMessage());

            return back()->with('error', 'خطا در پردازش فایل: '.$e->getMessage());
        }
    }

    public function syncFromDevice(Request $request)
    {
        abort_unless((bool) config('piofy.enabled'), 404);

        $validated = $request->validate([
            'since_date' => ['nullable', 'string', 'max:20'],
        ]);

        try {
            $since = null;
            if (! empty($validated['since_date'])) {
                $gregorian = jalaliToGregorianDate($validated['since_date']);
                if (! $gregorian) {
                    return back()->with('error', 'تاریخ شروع همسان‌سازی نامعتبر است.');
                }
                $since = \Carbon\Carbon::parse($gregorian)->startOfDay();
            }

            $result = $this->syncService->sync($since);
            $message = sprintf(
                'همسان‌سازی دستگاه انجام شد. خوانده‌شده: %d — ثبت‌شده: %d — از تاریخ: %s',
                $result['fetched'],
                $result['imported'],
                $result['since'] ? formatJalaliDateSafe($result['since']) : '-'
            );

            if ($result['errors']) {
                $message .= ' | '.implode(' | ', array_slice($result['errors'], 0, 5));
                if (count($result['errors']) > 5) {
                    $message .= ' و '.(count($result['errors']) - 5).' مورد دیگر';
                }
            }

            return back()->with($result['imported'] > 0 || $result['errors'] === [] ? 'success' : 'error', $message);
        } catch (\Throwable $e) {
            Log::error('Piofy sync error: '.$e->getMessage());

            return back()->with('error', 'خطا در همسان‌سازی با دستگاه: '.$e->getMessage());
        }
    }

    public function export()
    {
        $workLogs = WorkLog::with(['employee', 'project'])
            ->orderBy('work_date', 'desc')
            ->orderBy('start_time')
            ->get();

        $headers = [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="worklogs_'.Verta::now()->format('Y-m-d').'.csv"',
        ];

        $callback = function () use ($workLogs) {
            $file = fopen('php://output', 'w');
            fwrite($file, "\xEF\xBB\xBF");
            fwrite($file, "sep=,\r\n");
            fputcsv($file, ['work_date', 'employee_name', 'project_name', 'hours', 'work_time']);

            foreach ($workLogs as $workLog) {
                fputcsv($file, [
                    gregorianToJalaliDate($workLog->work_date),
                    $workLog->employee?->full_name,
                    $workLog->project?->name,
                    $workLog->hours,
                    $workLog->time_range,
                ]);
            }

            fclose($file);
        };

        return Response::stream($callback, 200, $headers);
    }

    public function downloadTemplate()
    {
        $headers = [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="piofy_attendance_template.csv"',
        ];

        $callback = function () {
            $file = fopen('php://output', 'w');
            fwrite($file, "\xEF\xBB\xBF");
            fwrite($file, "sep=,\r\n");
            fputcsv($file, ['شناسه کاربر', 'نام کاربر', 'شماره کارت', 'زمان تردد (شمسی)', 'زمان تردد (میلادی)', 'کلید عملیاتی', 'عنوان کلید عملیاتی']);
            fputcsv($file, ['1', 'Employee Name', '1001', '1405/06/25 08:00:00', '2026/09/16 08:00:00', '', '']);
            fputcsv($file, ['1', 'Employee Name', '1001', '1405/06/25 16:00:00', '2026/09/16 16:00:00', '', '']);
            fclose($file);
        };

        return Response::stream($callback, 200, $headers);
    }

    /**
     * @param  list<string>  $errors
     */
    private function formatImportMessage(int $importedCount, array $errors): string
    {
        $message = $importedCount.' رکورد کارکرد وارد شد. پروژه‌ها خالی‌اند؛ ترددناقص‌ها قرمز نمایش داده می‌شوند.';
        if ($errors) {
            $message .= ' خطاها: '.implode(' | ', array_slice($errors, 0, 5));
            if (count($errors) > 5) {
                $message .= ' و '.(count($errors) - 5).' خطای دیگر';
            }
        }

        return $message;
    }
}
