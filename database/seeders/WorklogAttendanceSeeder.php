<?php

namespace Database\Seeders;

use App\Models\AttendanceRawLog;
use App\Models\Employee;
use App\Models\EmploymentOrder;
use App\Models\Party;
use App\Models\Project;
use App\Models\WorkCalendar;
use App\Models\WorkGroup;
use App\Models\WorkLog;
use App\Models\WorkShift;
use App\Support\WorkCalendarDefaults;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class WorklogAttendanceSeeder extends Seeder
{
    private const START_JALALI = [1404, 7, 1];

    private const END_JALALI = [1404, 12, 29];

    private const IMPORT_BATCH = 'WORKLOG-SEED-1404-H2';

    private const ATTENDANCE_SOURCE = 'worklog-seed-1404';

    public function run(): void
    {
        $this->call(WorkCalendarSeeder::class);

        DB::transaction(function (): void {
            $calendar = $this->upsertCalendar();
            $shift = $this->upsertShift();
            $group = $this->upsertWorkGroup($shift, $calendar);
            $project = $this->upsertProject();
            $range = $this->workDateRange();

            $employees = collect([
                $this->upsertEmployee([
                    'employee_code' => 'EMP-1404-MA',
                    'personnel_code' => 'P-1404-MA',
                    'personnel_number' => 'PN-1404-MA',
                    'attendance_card_number' => 'CARD-1404-MA',
                    'party_code' => 'PT-1404-MA',
                    'detail_code' => 'DT-1404-MA',
                    'first_name' => 'مهدی',
                    'last_name' => 'عسگری',
                    'national_code' => '1404000001',
                    'phone' => '09150000001',
                    'email' => 'mehdi.asgari.seed@example.test',
                    'position' => 'کارمند اداری',
                    'department' => 'اداری - داخلی',
                    'base_salary' => 220000000,
                    'hourly_rate' => 1000000,
                    'salary' => 75000000,
                    'notes' => 'Seeded worklog demo data for 1404',
                ], $project),
                $this->upsertEmployee([
                    'employee_code' => 'EMP-1404-MR',
                    'personnel_code' => 'P-1404-MR',
                    'personnel_number' => 'PN-1404-MR',
                    'attendance_card_number' => 'CARD-1404-MR',
                    'party_code' => 'PT-1404-MR',
                    'detail_code' => 'DT-1404-MR',
                    'first_name' => 'میلاد',
                    'last_name' => 'رحیمی',
                    'national_code' => '1404000002',
                    'phone' => '09150000002',
                    'email' => 'milad.rahimi.seed@example.test',
                    'position' => 'کارمند اداری',
                    'department' => 'اداری - داخلی',
                    'base_salary' => 235000000,
                    'hourly_rate' => 1068181.82,
                    'salary' => 82000000,
                    'notes' => 'Seeded worklog demo data for 1404',
                ], $project),
            ]);

            foreach ($employees as $employee) {
                $this->upsertEmploymentOrder($employee, $project, $range);
                $this->upsertGroupAssignment($employee, $group, $range);
                $this->clearSeededAttendance($employee, $range);
                $this->seedWorkdays($employee, $project, $calendar, $range);
            }
        });

        $this->command?->info('سیدر تردد مهر تا اسفند 1404 برای مهدی عسگری و میلاد رحیمی اجرا شد.');
    }

    private function upsertCalendar(): WorkCalendar
    {
        $defaults = WorkCalendarDefaults::defaultCalendar(1404);

        return WorkCalendar::updateOrCreate(
            ['code' => 'CAL-1404'],
            [
                'name' => 'تقویم کاری 1404',
                'jalali_year' => 1404,
                'working_days' => $defaults['working_days'],
                'weekend_days' => $defaults['weekend_days'],
                'holidays' => $defaults['holidays'],
                'is_default' => true,
                'is_active' => true,
                'description' => 'تقویم کاری استاندارد 1404 برای داده‌های تردد آزمایشی',
            ]
        );
    }

    private function upsertShift(): WorkShift
    {
        return WorkShift::updateOrCreate(
            ['code' => 'SHIFT-1404-DAY-08-17'],
            [
                'name' => 'شیفت روزانه 08 تا 17',
                'start_time' => '08:00:00',
                'end_time' => '17:00:00',
                'break_minutes' => 60,
                'daily_work_hours' => 8,
                'overtime_multiplier' => 1.4,
                'late_tolerance_minutes' => 0,
                'early_leave_tolerance_minutes' => 0,
                'is_active' => true,
                'description' => 'شیفت روزانه مخصوص سید تردد مهر تا اسفند 1404',
            ]
        );
    }

    private function upsertWorkGroup(WorkShift $shift, WorkCalendar $calendar): WorkGroup
    {
        return WorkGroup::updateOrCreate(
            ['code' => 'WG-1404-ATTENDANCE-SEED'],
            [
                'name' => 'گروه کاری تردد 1404',
                'work_shift_id' => $shift->id,
                'work_calendar_id' => $calendar->id,
                'payroll_rules' => [
                    'start_time' => '08:00:00',
                    'end_time' => '17:00:00',
                    'break_minutes' => 60,
                ],
                'is_active' => true,
                'description' => 'گروه کاری آزمایشی برای ثبت تردد دو کارمند نمونه',
            ]
        );
    }

    private function upsertProject(): Project
    {
        return Project::updateOrCreate(
            ['project_number' => 'PRJ-ATT-1404'],
            [
                'name' => 'تردد داخلی 1404',
                'description' => 'پروژه آزمایشی برای ثبت work log های مهر تا اسفند 1404',
                'status' => 'active',
                'start_date' => $this->workDateRange()['start']->toDateString(),
                'end_date' => $this->workDateRange()['end']->toDateString(),
                'budget' => 0,
                'cost_center_code' => 'CC-ATT-1404',
            ]
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private function upsertEmployee(array $data, Project $project): Employee
    {
        $party = Party::updateOrCreate(
            ['code' => $data['party_code']],
            [
                'detail_code' => $data['detail_code'],
                'kind' => 'person',
                'name' => trim($data['first_name'] . ' ' . $data['last_name']),
                'national_id' => $data['national_code'],
                'phone' => $data['phone'],
                'mobile' => $data['phone'],
                'email' => $data['email'],
                'is_active' => true,
                'notes' => $data['notes'],
            ]
        );

        return Employee::updateOrCreate(
            ['employee_code' => $data['employee_code']],
            [
                'party_id' => $party->id,
                'personnel_code' => $data['personnel_code'],
                'personnel_number' => $data['personnel_number'],
                'attendance_card_number' => $data['attendance_card_number'],
                'hire_date' => $this->workDateRange()['start']->toDateString(),
                'termination_date' => null,
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'national_code' => $data['national_code'],
                'phone' => $data['phone'],
                'email' => $data['email'],
                'department' => $data['department'],
                'position' => $data['position'],
                'default_project_id' => $project->id,
                'employment_type' => 'full_time',
                'salary_type' => 'monthly',
                'salary' => $data['salary'],
                'base_salary' => $data['base_salary'],
                'hourly_rate' => $data['hourly_rate'],
                'overtime_rate' => round($data['hourly_rate'] * 1.4, 2),
                'start_date' => $this->workDateRange()['start']->toDateString(),
                'end_date' => null,
                'is_active' => true,
                'status' => 'active',
                'notes' => $data['notes'],
            ]
        );
    }

    private function upsertEmploymentOrder(Employee $employee, Project $project, array $range): EmploymentOrder
    {
        return EmploymentOrder::updateOrCreate(
            ['number' => 'EO-1404-' . $employee->employee_code],
            [
                'employee_id' => $employee->id,
                'position_id' => $employee->position_id,
                'job_id' => null,
                'organization_unit_id' => $employee->organization_unit_id,
                'default_project_id' => $project->id,
                'order_type' => 'hire',
                'employment_type' => 'monthly_contract',
                'insurance_status' => 'insured',
                'effective_date' => $range['start']->toDateString(),
                'end_date' => null,
                'base_salary' => (float) $employee->base_salary,
                'hourly_rate' => (float) $employee->hourly_rate,
                'status' => 'approved',
                'approved_at' => now(),
                'notes' => 'سند استخدام آزمایشی برای سید تردد 1404',
            ]
        );
    }

    /**
     * @param array{start: Carbon, end: Carbon} $range
     */
    private function upsertGroupAssignment(Employee $employee, WorkGroup $group, array $range): void
    {
        DB::table('work_group_employee')->updateOrInsert(
            [
                'employee_id' => $employee->id,
                'work_group_id' => $group->id,
            ],
            [
                'start_date' => $range['start']->toDateString(),
                'end_date' => $range['end']->toDateString(),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    /**
     * @param array{start: Carbon, end: Carbon} $range
     */
    private function clearSeededAttendance(Employee $employee, array $range): void
    {
        WorkLog::query()
            ->where('employee_id', $employee->id)
            ->where('import_batch', self::IMPORT_BATCH)
            ->whereBetween('work_date', [$range['start']->toDateString(), $range['end']->toDateString()])
            ->delete();

        AttendanceRawLog::query()
            ->where('employee_id', $employee->id)
            ->where('source', self::ATTENDANCE_SOURCE)
            ->whereBetween('logged_at', [$range['start']->copy()->startOfDay(), $range['end']->copy()->endOfDay()])
            ->delete();
    }

    /**
     * @param array{start: Carbon, end: Carbon} $range
     */
    private function seedWorkdays(Employee $employee, Project $project, WorkCalendar $calendar, array $range): void
    {
        $workingDays = collect($calendar->working_days ?? [6, 0, 1, 2, 3]);
        $holidays = collect($calendar->holidays ?? []);
        $period = CarbonPeriod::create($range['start']->copy()->startOfDay(), '1 day', $range['end']->copy()->endOfDay());

        foreach ($period as $date) {
            if (! $this->isWorkingDay($date, $workingDays, $holidays)) {
                continue;
            }

            $checkIn = Carbon::parse($date->toDateString() . ' 08:00:00');
            $checkOut = Carbon::parse($date->toDateString() . ' 17:00:00');
            $hours = 9.0;
            $hourlyRate = (float) $employee->hourly_rate;

            WorkLog::create([
                'employee_id' => $employee->id,
                'project_id' => $project->id,
                'cost_center' => $project->cost_center_code,
                'work_date' => $date->toDateString(),
                'check_in_time' => $checkIn->format('H:i:s'),
                'check_out_time' => $checkOut->format('H:i:s'),
                'start_time' => $checkIn->format('H:i:s'),
                'end_time' => $checkOut->format('H:i:s'),
                'hours' => $hours,
                'overtime_hours' => 0,
                'delay_hours' => 0,
                'early_leave_hours' => 0,
                'mission_hours' => 0,
                'leave_hours' => 0,
                'absence_hours' => 0,
                'description' => 'تردد آزمایشی سید 1404 - ' . formatJalaliDateSafe($date),
                'hourly_rate' => $hourlyRate,
                'total_amount' => round($hours * $hourlyRate, 2),
                'attendance_source' => self::ATTENDANCE_SOURCE,
                'import_batch' => self::IMPORT_BATCH,
            ]);

            AttendanceRawLog::create([
                'employee_id' => $employee->id,
                'attendance_card_number' => $employee->attendance_card_number,
                'logged_at' => $checkIn,
                'direction' => 'in',
                'device_code' => 'SEED-ATT-01',
                'source' => self::ATTENDANCE_SOURCE,
                'payload' => [
                    'kind' => 'seeded-attendance',
                    'work_date' => $date->toDateString(),
                    'shift_code' => 'SHIFT-1404-DAY-08-17',
                    'event' => 'check_in',
                ],
            ]);

            AttendanceRawLog::create([
                'employee_id' => $employee->id,
                'attendance_card_number' => $employee->attendance_card_number,
                'logged_at' => $checkOut,
                'direction' => 'out',
                'device_code' => 'SEED-ATT-01',
                'source' => self::ATTENDANCE_SOURCE,
                'payload' => [
                    'kind' => 'seeded-attendance',
                    'work_date' => $date->toDateString(),
                    'shift_code' => 'SHIFT-1404-DAY-08-17',
                    'event' => 'check_out',
                ],
            ]);
        }
    }

    /**
     * @return array{start: Carbon, end: Carbon}
     */
    private function workDateRange(): array
    {
        $start = Carbon::parse(jalaliToGregorianDateSafe(...self::START_JALALI));
        $end = Carbon::parse(jalaliToGregorianDateSafe(...self::END_JALALI));

        return [
            'start' => $start->startOfDay(),
            'end' => $end->endOfDay(),
        ];
    }

    private function isWorkingDay(Carbon $date, \Illuminate\Support\Collection $workingDays, \Illuminate\Support\Collection $holidays): bool
    {
        if ($holidays->contains($date->toDateString()) || $holidays->contains(formatJalaliDateSafe($date))) {
            return false;
        }

        return $workingDays->contains((int) $date->dayOfWeek);
    }
}
