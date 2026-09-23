<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\WorkLog;
use Carbon\Carbon;
use Hekmatinasser\Verta\Verta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;
use RuntimeException;
use ZipArchive;

class WorkLogExcelController extends Controller
{
    public function importView()
    {
        return view('worklog.import');
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

            $rows = $this->readImportRows($uploaded->getRealPath(), $extension);

            if (! $this->isAttendanceDeviceFile($rows)) {
                return back()->with('error', 'این صفحه فقط خروجی دستگاه تردد (Piofy) را می‌پذیرد.');
            }

            [$importedCount, $errors] = $this->importAttendanceRows($rows);
            $message = $importedCount . ' رکورد کارکرد وارد شد. پروژه‌ها خالی‌اند؛ ترددناقص‌ها قرمز نمایش داده می‌شوند.';
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

    private function importAttendanceRows(array $rows): array
    {
        $errors = [];
        $grouped = [];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;
            $employeeKey = trim((string) ($row['employee_code'] ?? $row['card_number'] ?? $row['user_id'] ?? ''));
            $employeeName = trim((string) ($row['user_name'] ?? ''));
            $dateTime = $this->convertAttendanceDateTime(
                (string) ($row['gregorian_datetime'] ?? ''),
                (string) ($row['jalali_datetime'] ?? '')
            );

            if ($employeeKey === '' && $employeeName === '') {
                $errors[] = sprintf('ردیف %d: شناسه یا نام کاربر ندارد.', $rowNumber);
                continue;
            }

            if (! $dateTime) {
                $errors[] = sprintf('ردیف %d: زمان تردد معتبر نیست.', $rowNumber);
                continue;
            }

            $employee = $this->findEmployee($employeeKey, $employeeName);
            if (! $employee) {
                $errors[] = sprintf('ردیف %d: پرسنل \'%s %s\' پیدا نشد.', $rowNumber, $employeeKey, $employeeName);
                continue;
            }

            $workDate = $dateTime->format('Y-m-d');
            $groupKey = $employee->id . '|' . $workDate;
            $grouped[$groupKey]['employee'] = $employee;
            $grouped[$groupKey]['date'] = $workDate;
            $grouped[$groupKey]['times'][] = $dateTime;
        }

        $importedCount = 0;

        foreach ($grouped as $group) {
            $times = collect($group['times'])->sortBy(fn (Carbon $time) => $time->timestamp)->values();

            if ($times->isEmpty()) {
                continue;
            }

            $isIncomplete = $times->count() < 2;
            $start = $times->first();
            $end = $isIncomplete ? null : $times->last();
            $hours = $isIncomplete ? 0 : round($start->diffInMinutes($end) / 60, 2);

            if (! $isIncomplete && $hours <= 0) {
                $errors[] = sprintf('%s در تاریخ %s: زمان کار معتبر نیست.', $group['employee']->full_name, $group['date']);
                continue;
            }

            if (WorkLog::where('employee_id', $group['employee']->id)->where('work_date', $group['date'])->exists()) {
                $errors[] = sprintf('%s در تاریخ %s: قبلا کارکرد ثبت شده است.', $group['employee']->full_name, $group['date']);
                continue;
            }

            $hourlyRate = (float) ($group['employee']->hourly_rate ?: 0);

            $this->createWorkLog([
                'employee_id' => $group['employee']->id,
                'project_id' => null,
                'work_date' => $group['date'],
                'start_time' => $start->format('H:i'),
                'end_time' => $end?->format('H:i'),
                'check_in_time' => $start->format('H:i'),
                'check_out_time' => $end?->format('H:i'),
                'hours' => $hours,
                'is_incomplete' => $isIncomplete,
                'description' => $isIncomplete ? 'تردد ناقص' : 'ورود از دستگاه تردد',
                'hourly_rate' => $hourlyRate,
                'total_amount' => $hours * $hourlyRate,
                'attendance_source' => 'device_import',
            ]);

            $importedCount++;
        }

        return [$importedCount, $errors];
    }

    private function createWorkLog(array $attributes): WorkLog
    {
        return WorkLog::create($attributes);
    }

    private function readImportRows(string $path, string $extension): array
    {
        if (in_array($extension, ['xls', 'xlsx'], true)) {
            $content = file_get_contents($path) ?: '';

            if ($extension === 'xlsx' || str_starts_with($content, "PK\x03\x04")) {
                return $this->readXlsxRows($path);
            }

            throw new RuntimeException('این فایل اکسل قابل خواندن نیست.');
        }

        return $this->readCsvRows($path);
    }

    private function readCsvRows(string $filePath): array
    {
        $rows = [];
        $separator = $this->detectSeparator($filePath);

        if (($handle = fopen($filePath, 'r')) !== false) {
            $headerRow = fgetcsv($handle, 0, $separator) ?: [];
            $firstHeader = preg_replace('/^\xEF\xBB\xBF/', '', (string) ($headerRow[0] ?? ''));
            if ($firstHeader !== '' && str_starts_with(strtolower(trim($firstHeader)), 'sep=')) {
                $headerRow = fgetcsv($handle, 0, $separator) ?: [];
            }

            $headers = $this->cleanHeaders($headerRow);

            while (($data = fgetcsv($handle, 0, $separator)) !== false) {
                if (! empty(array_filter($data, fn ($value) => trim((string) $value) !== ''))) {
                    $rows[] = $this->combineRow($headers, $data);
                }
            }

            fclose($handle);
        }

        return $rows;
    }

    private function readXlsxRows(string $path): array
    {
        $zip = new ZipArchive();

        if ($zip->open($path) !== true) {
            throw new RuntimeException('امکان باز کردن فایل xlsx وجود ندارد.');
        }

        $sharedStrings = $this->readXlsxSharedStrings($zip);
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');

        if ($sheetXml === false) {
            $zip->close();
            throw new RuntimeException('ساختار فایل xlsx معتبر نیست.');
        }

        $zip->close();

        $sheetXml = preg_replace('/xmlns(:[a-z0-9]+)?="[^"]*"/i', '', $sheetXml) ?? $sheetXml;
        $xml = @simplexml_load_string($sheetXml);
        if (! $xml) {
            return [];
        }

        $rawRows = [];

        foreach ($xml->sheetData->row ?? [] as $rowNode) {
            $cells = [];
            foreach ($rowNode->c ?? [] as $cellNode) {
                $attributes = $cellNode->attributes();
                $reference = (string) ($attributes['r'] ?? '');
                $index = $reference !== '' ? $this->xlsxColumnIndex($reference) : count($cells);

                while (count($cells) < $index) {
                    $cells[] = '';
                }

                $cells[] = $this->xlsxCellValue($cellNode, $sharedStrings);
            }

            if (! empty(array_filter($cells, fn ($value) => trim((string) $value) !== ''))) {
                $rawRows[] = $cells;
            }
        }

        return $this->rowsFromRawRows($rawRows);
    }

    private function readXlsxSharedStrings(ZipArchive $zip): array
    {
        $content = $zip->getFromName('xl/sharedStrings.xml');
        if ($content === false) {
            return [];
        }

        $content = preg_replace('/xmlns(:[a-z0-9]+)?="[^"]*"/i', '', $content) ?? $content;
        $xml = @simplexml_load_string($content);
        if (! $xml) {
            return [];
        }

        $strings = [];

        foreach ($xml->si ?? [] as $node) {
            $parts = [];
            if (isset($node->t)) {
                $parts[] = (string) $node->t;
            }
            foreach ($node->r ?? [] as $run) {
                if (isset($run->t)) {
                    $parts[] = (string) $run->t;
                }
            }
            $strings[] = implode('', $parts);
        }

        return $strings;
    }

    private function xlsxCellValue(\SimpleXMLElement $cellNode, array $sharedStrings): string
    {
        $attributes = $cellNode->attributes();
        $type = (string) ($attributes['t'] ?? '');

        if ($type === 'inlineStr') {
            $texts = [];
            if (isset($cellNode->is->t)) {
                $texts[] = (string) $cellNode->is->t;
            }
            foreach ($cellNode->is->r ?? [] as $run) {
                if (isset($run->t)) {
                    $texts[] = (string) $run->t;
                }
            }

            return trim(implode('', $texts));
        }

        $value = isset($cellNode->v) ? trim((string) $cellNode->v) : '';

        if ($type === 's') {
            return $sharedStrings[(int) $value] ?? '';
        }

        return $value;
    }

    private function xlsxColumnIndex(string $cellReference): int
    {
        preg_match('/^([A-Z]+)/i', $cellReference, $matches);
        $letters = strtoupper($matches[1] ?? 'A');
        $index = 0;

        foreach (str_split($letters) as $letter) {
            $index = ($index * 26) + (ord($letter) - 64);
        }

        return max(0, $index - 1);
    }

    private function rowsFromRawRows(array $rawRows): array
    {
        if ($rawRows === []) {
            return [];
        }

        $headers = $this->cleanHeaders(array_shift($rawRows));

        return collect($rawRows)
            ->map(fn ($row) => $this->combineRow($headers, $row))
            ->filter(fn ($row) => ! empty(array_filter($row, fn ($value) => trim((string) $value) !== '')))
            ->values()
            ->all();
    }

    private function combineRow(array $headers, array $row): array
    {
        $row = array_slice(array_pad($row, count($headers), null), 0, count($headers));

        return array_combine($headers, $row) ?: [];
    }

    private function detectSeparator(string $filePath): string
    {
        $handle = fopen($filePath, 'r');
        $firstLine = (string) fgets($handle);
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
            $normalized = $this->normalizeHeaderLabel((string) $header);

            return match (true) {
                in_array($normalized, ['userid', 'user_id'], true)
                    || (str_contains($normalized, 'شناسه') && str_contains($normalized, 'کاربر')) => 'user_id',
                in_array($normalized, ['cardnumber', 'card_number', 'card_no', 'attendancecardnumber', 'attendance_card_number'], true)
                    || str_contains($normalized, 'کارت') => 'card_number',
                in_array($normalized, ['employeecode', 'employee_code', 'personnelcode', 'personnel_code', 'personnelnumber', 'personnel_number'], true)
                    || str_contains($normalized, 'پرسنل') => 'employee_code',
                in_array($normalized, ['username', 'user_name', 'employee'], true)
                    || (str_contains($normalized, 'نام') && str_contains($normalized, 'کاربر')) => 'user_name',
                in_array($normalized, ['jalalidatetime', 'jalali_datetime'], true)
                    || str_contains($normalized, 'شمسی') => 'jalali_datetime',
                in_array($normalized, ['gregoriandatetime', 'gregorian_datetime'], true)
                    || str_contains($normalized, 'میلادی') => 'gregorian_datetime',
                in_array($normalized, ['date', 'workdate', 'work_date'], true)
                    || str_contains($normalized, 'تاریخ') => 'work_date',
                in_array($normalized, ['project', 'projectname', 'project_name'], true)
                    || str_contains($normalized, 'پروژه') => 'project_name',
                in_array($normalized, ['projectid', 'project_id'], true) => 'project_id',
                in_array($normalized, ['hours', 'workhours', 'work_hours'], true)
                    || str_contains($normalized, 'ساعتکار') => 'hours',
                in_array($normalized, ['time', 'worktime', 'work_time'], true)
                    || str_contains($normalized, 'زمانکار') => 'work_time',
                str_contains($normalized, 'عنوانکلید') || in_array($normalized, ['operationtitle', 'operation_title'], true) => 'operation_title',
                str_contains($normalized, 'کلیدعملیاتی') || in_array($normalized, ['operationkey', 'operation_key'], true) => 'operation_key',
                default => $normalized,
            };
        }, $headers);
    }

    private function normalizeHeaderLabel(string $header): string
    {
        $header = preg_replace('/^\xEF\xBB\xBF/', '', $header) ?? $header;
        $header = str_replace(["\u{200C}", "\u{200F}", "\u{200E}"], '', $header);
        $header = str_replace(["\u{064A}", "\u{0643}"], ["\u{06CC}", "\u{06A9}"], $header);
        $header = preg_replace('/[\s\-\/\\\\_()（）\[\]【】]+/u', '', $header) ?? $header;

        return mb_strtolower(trim($header));
    }

    private function isAttendanceDeviceFile(array $rows): bool
    {
        $firstRow = $rows[0] ?? [];

        $hasDateTime = array_key_exists('gregorian_datetime', $firstRow) || array_key_exists('jalali_datetime', $firstRow);
        $hasEmployeeIdentifier = array_key_exists('user_id', $firstRow)
            || array_key_exists('employee_code', $firstRow)
            || array_key_exists('card_number', $firstRow)
            || array_key_exists('user_name', $firstRow);

        return $hasDateTime && $hasEmployeeIdentifier;
    }

    private function findEmployee(string $value = '', string $name = ''): ?Employee
    {
        $value = trim($value);
        $name = trim($name);

        if ($value !== '') {
            $byIdentifier = Employee::query()
                ->where(function ($query) use ($value) {
                    $query->where('id', $value)
                        ->orWhere('employee_code', $value)
                        ->orWhere('personnel_code', $value)
                        ->orWhere('personnel_number', $value)
                        ->orWhere('attendance_card_number', $value)
                        ->orWhere('national_code', $value);
                })
                ->first();

            if ($byIdentifier) {
                return $byIdentifier;
            }
        }

        if ($name === '') {
            return null;
        }

        return Employee::query()
            ->where(function ($query) use ($name) {
                $query->whereRaw("CONCAT(first_name, ' ', last_name) = ?", [$name])
                    ->orWhereRaw("CONCAT(TRIM(first_name), ' ', TRIM(last_name)) = ?", [$name])
                    ->orWhereHas('party', fn ($party) => $party->where('name', $name));
            })
            ->first();
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
}