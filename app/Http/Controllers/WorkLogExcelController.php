<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Project;
use App\Models\WorkLog;
use Carbon\Carbon;
use Hekmatinasser\Verta\Verta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;

class WorkLogExcelController extends Controller
{
    private const DEFAULT_PROJECT_NAME = 'اداری - داخل سازمانی';
    private const LEGACY_DEFAULT_PROJECT_NAME = 'اداری-داخل سازمانی';

    public function importView()
    {
        return view('worklog.import');
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:csv,txt|max:10240',
        ]);

        try {
            $rows = $this->readRows($request->file('file')->getRealPath());

            if (! $this->isAttendanceDeviceFile($rows)) {
                return back()->with('error', 'این صفحه فقط فایل نمونه حضور و غیاب را می‌پذیرد. فایل شما باید ستون‌های user_id یا employee_code و jalali_datetime یا gregorian_datetime داشته باشد.');
            }

            [$importedCount, $errors] = $this->importAttendanceRows($rows);
            $message = "{$importedCount} رکورد کارکرد با موفقیت وارد شد.";
            if ($errors) {
                $message .= ' خطاها: ' . implode(' | ', array_slice($errors, 0, 5));
                if (count($errors) > 5) {
                    $message .= ' و ' . (count($errors) - 5) . ' خطای دیگر';
                }
            }

            return back()->with('success', $message);
        } catch (\Throwable $e) {
            Log::error('WorkLog import error: ' . $e->getMessage());

            return back()->with('error', 'خطا در پردازش فایل: ' . $e->getMessage());
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
            'Content-Disposition' => 'attachment; filename="worklogs_' . Verta::now()->format('Y-m-d') . '.csv"',
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
            'Content-Disposition' => 'attachment; filename="attendance_device_template.csv"',
        ];

        $callback = function () {
            $file = fopen('php://output', 'w');
            fwrite($file, "\xEF\xBB\xBF");
            fwrite($file, "sep=,\r\n");
            fputcsv($file, ['user_id', 'employee_code', 'user_name', 'project_id', 'project_name', 'jalali_datetime', 'gregorian_datetime']);
            fputcsv($file, ['1', 'EMP-00001', 'Employee Name', '1', '����� - ���� �������', '1403/03/17 08:00', '2024-06-06 08:00']);
            fputcsv($file, ['1', 'EMP-00001', 'Employee Name', '1', '����� - ���� �������', '1403/03/17 16:00', '2024-06-06 16:00']);
            fclose($file);
        };

        return Response::stream($callback, 200, $headers);
    }

    private function importAttendanceRows(array $rows): array
    {
        $errors = [];
        $grouped = [];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;
            $employeeKey = trim((string) ($row['employee_code'] ?? $row['user_id'] ?? ''));
            $employeeName = trim((string) ($row['user_name'] ?? ''));
            $projectId = trim((string) ($row['project_id'] ?? ''));
            $projectName = trim((string) ($row['project_name'] ?? ''));
            $dateTime = $this->convertAttendanceDateTime(
                (string) ($row['gregorian_datetime'] ?? ''),
                (string) ($row['jalali_datetime'] ?? '')
            );

            if (!$employeeKey && !$employeeName) {
                $errors[] = "ردیف {$rowNumber}: شناسه یا نام کاربر ندارد.";
                continue;
            }

            if (!$dateTime) {
                $errors[] = "ردیف {$rowNumber}: زمان تردد معتبر نیست.";
                continue;
            }

            $employee = $this->findEmployee($employeeKey, $employeeName);
            if (!$employee) {
                $errors[] = "ردیف {$rowNumber}: پرسنل '{$employeeKey} {$employeeName}' پیدا نشد.";
                continue;
            }

            $project = $this->resolveProjectFromRow($row) ?? $this->getDefaultProject();

            $workDate = $dateTime->format('Y-m-d');
            $groupKey = $employee->id . '|' . $workDate;
            $grouped[$groupKey]['employee'] = $employee;
            $grouped[$groupKey]['date'] = $workDate;
            $grouped[$groupKey]['times'][] = $dateTime;
            $grouped[$groupKey]['project'] = $project;
        }

        $importedCount = 0;

        foreach ($grouped as $group) {
            $times = collect($group['times'])->sortBy(fn (Carbon $time) => $time->timestamp)->values();

            if ($times->count() < 2) {
                $errors[] = "{$group['employee']->full_name} در تاریخ {$group['date']}: کمتر از دو تردد دارد.";
                continue;
            }

            $start = $times->first();
            $end = $times->last();
            $hours = round($start->diffInMinutes($end) / 60, 2);

            if ($hours <= 0) {
                $errors[] = "{$group['employee']->full_name} در تاریخ {$group['date']}: زمان کار معتبر نیست.";
                continue;
            }

            if (WorkLog::where('employee_id', $group['employee']->id)->where('work_date', $group['date'])->exists()) {
                $errors[] = "{$group['employee']->full_name} در تاریخ {$group['date']}: قبلا کارکرد ثبت شده است.";
                continue;
            }

            $hourlyRate = (float) ($group['employee']->hourly_rate ?: 0);

            $this->createWorkLog([
                'employee_id' => $group['employee']->id,
                'project_id' => $group['project']->id,
                'work_date' => $group['date'],
                'start_time' => $start->format('H:i'),
                'end_time' => $end->format('H:i'),
                'hours' => $hours,
                'description' => 'ورود از دستگاه تردد',
                'hourly_rate' => $hourlyRate,
                'total_amount' => $hours * $hourlyRate,
            ]);

            $importedCount++;
        }

        return [$importedCount, $errors];
    }

    private function createWorkLog(array $attributes): WorkLog
    {
        return WorkLog::create($attributes);
    }

    private function getDefaultProject(): Project
    {
        $project = Project::where('name', self::DEFAULT_PROJECT_NAME)->first();

        if ($project) {
            return $project;
        }

        $legacyProject = Project::where('name', self::LEGACY_DEFAULT_PROJECT_NAME)->first();

        if ($legacyProject) {
            $legacyProject->name = self::DEFAULT_PROJECT_NAME;
            $legacyProject->save();

            return $legacyProject;
        }

        $project = Project::create([
            'name' => self::DEFAULT_PROJECT_NAME,
            'description' => 'پروژه پیش فرض برای ورود اطلاعات دستگاه تردد',
            'status' => 'active',
        ]);

        return $project;
    }

    private function readRows(string $filePath): array
    {
        $rows = [];
        $separator = $this->detectSeparator($filePath);

        if (($handle = fopen($filePath, 'r')) !== false) {
            $headerRow = fgetcsv($handle, 1000, $separator) ?: [];
            $firstHeader = preg_replace('/^\xEF\xBB\xBF/', '', (string) ($headerRow[0] ?? ''));
            if ($firstHeader !== '' && str_starts_with(strtolower(trim($firstHeader)), 'sep=')) {
                $headerRow = fgetcsv($handle, 1000, $separator) ?: [];
            }

            $headers = $this->cleanHeaders($headerRow);

            while (($data = fgetcsv($handle, 1000, $separator)) !== false) {
                if (!empty(array_filter($data))) {
                    $rows[] = $this->combineRow($headers, $data);
                }
            }

            fclose($handle);
        }

        return $rows;
    }

    private function combineRow(array $headers, array $row): array
    {
        $row = array_slice(array_pad($row, count($headers), null), 0, count($headers));

        return array_combine($headers, $row);
    }

    private function detectSeparator(string $filePath): string
    {
        $handle = fopen($filePath, 'r');
        $firstLine = fgets($handle);
        fclose($handle);

        $detectedSeparator = ',';
        $maxCount = 0;

        foreach ([',', ';', "\t", '|'] as $separator) {
            $count = substr_count($firstLine, $separator);
            if ($count > $maxCount) {
                $maxCount = $count;
                $detectedSeparator = $separator;
            }
        }

        return $detectedSeparator;
    }

    private function cleanHeaders(array $headers): array
    {
        return array_map(function ($header) {
            $header = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header);
            $header = trim(str_replace([' ', '-', '‌'], '_', $header));

            return match ($header) {
                'شناسه_کاربر', 'user_id', 'userid' => 'user_id',
                'employee_code', 'personnel_code', 'personnel_number', 'attendance_card_number' => 'employee_code',
                'نام_کاربر', 'user_name', 'username', 'employee' => 'user_name',
                'زمان_تردد(شمسی)', 'زمان_تردد_شمسی', 'jalali_datetime' => 'jalali_datetime',
                'زمان_تردد(میلادی)', 'زمان_تردد_میلادی', 'gregorian_datetime' => 'gregorian_datetime',
                'تاریخ', 'date', 'work_date' => 'work_date',
                'پرسنل', 'employee_code' => 'employee_code',
                'پروژه', 'project', 'project_name' => 'project_name',
                'ساعت_کار', 'hours', 'work_hours' => 'hours',
                'زمان_کار', 'time', 'work_time' => 'work_time',
                default => strtolower($header),
            };
        }, $headers);
    }

    private function isAttendanceDeviceFile(array $rows): bool
    {
        $firstRow = $rows[0] ?? [];

        $hasDateTime = array_key_exists('gregorian_datetime', $firstRow) || array_key_exists('jalali_datetime', $firstRow);
        $hasEmployeeIdentifier = array_key_exists('user_id', $firstRow)
            || array_key_exists('employee_code', $firstRow)
            || array_key_exists('user_name', $firstRow);

        return $hasDateTime && $hasEmployeeIdentifier;
    }

    private function findEmployee(string $value = '', string $name = ''): ?Employee
    {
        $value = trim($value);
        $name = trim($name);

        return Employee::query()
            ->where(function ($query) use ($value, $name) {
                if ($value !== '') {
                    $query->where('id', $value)
                        ->orWhere('employee_code', $value)
                        ->orWhere('personnel_code', $value)
                        ->orWhere('personnel_number', $value)
                        ->orWhere('attendance_card_number', $value)
                        ->orWhere('national_code', $value);
                }

                if ($name !== '') {
                    $query->orWhereRaw("CONCAT(first_name, ' ', last_name) = ?", [$name])
                        ->orWhereHas('party', fn ($party) => $party->where('name', $name));
                }
            })
            ->first();
    }

    private function findProject(string $value): ?Project
    {
        $value = trim($value);

        return Project::query()
            ->where('id', $value)
            ->orWhere('name', $value)
            ->first();
    }

    private function resolveProjectFromRow(array $rowData): ?Project
    {
        $projectId = trim((string) ($rowData['project_id'] ?? ''));
        if ($projectId !== '') {
            $project = Project::find($projectId);
            if ($project) {
                return $project;
            }
        }

        $projectName = trim((string) ($rowData['project_name'] ?? ''));
        if ($projectName !== '') {
            return $this->findProject($projectName);
        }

        return null;
    }

    private function convertAttendanceDateTime(string $gregorian, string $jalali): ?Carbon
    {
        $gregorian = trim($gregorian);
        $jalali = trim($jalali);

        if ($gregorian !== '') {
            foreach (['Y-m-d H:i:s', 'Y-m-d H:i', 'Y/m/d H:i:s', 'Y/m/d H:i'] as $format) {
                try {
                    return Carbon::createFromFormat($format, $gregorian);
                } catch (\Throwable) {
                }
            }

            try {
                return Carbon::parse($gregorian);
            } catch (\Throwable) {
            }
        }

        if ($jalali !== '') {
            try {
                $normalized = str_replace('/', '-', $jalali);
                return Carbon::instance(Verta::parse($normalized)->datetime());
            } catch (\Throwable) {
            }
        }

        return null;
    }

    private function convertDate(string $dateString): ?string
    {
        $dateString = trim($dateString);

        foreach (['Y-m-d', 'Y/m/d', 'd-m-Y', 'd/m/Y', 'm-d-Y', 'm/d/Y'] as $format) {
            $date = \DateTime::createFromFormat($format, $dateString);
            if ($date !== false) {
                return $date->format('Y-m-d');
            }
        }

        return null;
    }

    private function convertTime(string $timeString): ?string
    {
        if (preg_match('/^(\d{1,2}):(\d{2})/', trim($timeString), $matches)) {
            $hour = (int) $matches[1];
            $minute = (int) $matches[2];

            if ($hour >= 0 && $hour <= 23 && $minute >= 0 && $minute <= 59) {
                return sprintf('%02d:%02d', $hour, $minute);
            }
        }

        return null;
    }

    private function convertTimeRange(string $timeRange): array
    {
        $parts = preg_split('/\s*(?:-|–|—|تا|الی|,|،)\s*/u', trim($timeRange));

        if (count($parts) < 2) {
            return [null, null];
        }

        return [$this->convertTime($parts[0]), $this->convertTime($parts[1])];
    }
}
