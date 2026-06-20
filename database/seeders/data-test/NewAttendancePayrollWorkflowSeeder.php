<?php

namespace Database\Seeders;

use App\Models\AttendanceLeave;
use App\Models\AttendanceMission;
use App\Models\AttendanceRawLog;
use App\Models\CostCenter;
use App\Models\Employee;
use App\Models\EmploymentOrder;
use App\Models\Job;
use App\Models\OrganizationUnit;
use App\Models\Party;
use App\Models\PayrollPeriod;
use App\Models\Position;
use App\Models\Project;
use App\Models\WorkCalendar;
use App\Models\WorkGroup;
use App\Models\WorkShift;
use App\Support\WorkCalendarDefaults;
use App\Services\NewPayrollEngineService;
use App\Services\PayrollCalculationService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class NewAttendancePayrollWorkflowSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(StandardPayrollSeeder::class);

        DB::transaction(function () {
            $units = collect(['مدیریت', 'مالی و اداری', 'فروش و بازرگانی', 'طراحی و مهندسی', 'تولید و مونتاژ'])
                ->map(fn ($name, $i) => OrganizationUnit::updateOrCreate(['code' => 'NEW-HR-' . ($i + 1)], ['title' => $name, 'type' => 'department', 'is_active' => true]));

            $jobs = collect(['مدیرعامل', 'مدیر مالی', 'کارشناس حسابداری', 'کارشناس منابع انسانی', 'کارشناس فروش', 'کارشناس خرید', 'مهندس الکترونیک', 'مهندس مکانیک', 'سرپرست تولید', 'تکنسین مونتاژ', 'کنترل کیفیت', 'انباردار', 'برنامه‌ریز تولید', 'پشتیبانی مشتری', 'کارشناس پروژه'])
                ->map(fn ($name, $i) => Job::updateOrCreate(['code' => 'NEW-JOB-' . ($i + 1)], ['title' => $name, 'is_active' => true]));

            $positions = $jobs->map(fn ($job, $i) => Position::updateOrCreate(
                ['code' => 'NEW-POS-' . ($i + 1)],
                ['title' => $job->title, 'job_id' => $job->id, 'organization_unit_id' => $units[$i % $units->count()]->id, 'is_active' => true]
            ));

            $projects = collect(['پروژه طراحی کنترلر صنعتی', 'پروژه مونتاژ تابلو برق', 'پروژه بهینه‌سازی خط تولید'])
                ->map(fn ($name, $i) => Project::firstOrCreate(['name' => $name], ['description' => 'پروژه تست موتور جدید حقوق و حضور']));

            $centers = collect([
                ['code' => 'CC-ADM', 'name' => 'مرکز هزینه اداری'],
                ['code' => 'CC-PRD', 'name' => 'مرکز هزینه تولید'],
            ])->map(fn ($row) => CostCenter::updateOrCreate(['code' => $row['code']], $row + ['is_active' => true]));

            $shifts = collect([
                ['code' => 'SHIFT-DAY', 'name' => 'شیفت روز', 'start_time' => '08:00:00', 'end_time' => '17:00:00', 'daily_work_hours' => 8],
                ['code' => 'SHIFT-EVENING', 'name' => 'شیفت عصر', 'start_time' => '14:00:00', 'end_time' => '22:00:00', 'daily_work_hours' => 8],
                ['code' => 'SHIFT-NIGHT', 'name' => 'شیفت شب', 'start_time' => '22:00:00', 'end_time' => '06:00:00', 'daily_work_hours' => 8],
            ])->map(fn ($row) => WorkShift::updateOrCreate(['code' => $row['code']], $row + ['break_minutes' => 60, 'overtime_multiplier' => 1.4, 'late_tolerance_minutes' => 10, 'early_leave_tolerance_minutes' => 10, 'is_active' => true]));

            $calendar = WorkCalendar::updateOrCreate(
                ['code' => 'CAL-1405-NEW'],
                ['name' => 'تقویم کاری ۱۴۰۵', 'jalali_year' => 1405] + WorkCalendarDefaults::defaultCalendar(1405) + ['is_default' => true, 'is_active' => true]
            );
            $groups = $shifts->map(fn ($shift, $i) => WorkGroup::updateOrCreate(['code' => 'WG-NEW-' . ($i + 1)], ['name' => 'گروه ' . $shift->name, 'work_shift_id' => $shift->id, 'work_calendar_id' => $calendar->id, 'is_active' => true]));

            // $firstNames = ['لیلا', 'مریم', 'زهرا', 'سارا', 'نگار', 'رضا', 'علی', 'محمد', 'حسین', 'امیر', 'نیما', 'کیان', 'ندا', 'الهام', 'فرهاد', 'آرمان', 'پویا', 'مهسا', 'سپیده', 'شهاب'];
            // $lastNames = ['جانی', 'احمدی', 'محمدی', 'کریمی', 'موسوی', 'رضایی', 'حسینی', 'نوری', 'کاظمی', 'اکبری'];
            // $start = Carbon::parse(jalaliToGregorianDateSafe(1405, 3, 1));

            // for ($i = 1; $i <= 50; $i++) {
            //     $firstName = $firstNames[$i % count($firstNames)];
            //     $lastName = $lastNames[$i % count($lastNames)];
            //     $fullName = $firstName . ' ' . $lastName;
            //     $party = Party::updateOrCreate(
            //         ['code' => 'EMP-NEW-PARTY-' . str_pad((string) $i, 3, '0', STR_PAD_LEFT)],
            //         ['detail_code' => 'DL-EMP-' . str_pad((string) $i, 4, '0', STR_PAD_LEFT), 'name' => $fullName, 'kind' => 'person', 'national_id' => '00' . str_pad((string) $i, 8, '0', STR_PAD_LEFT), 'mobile' => '0912' . str_pad((string) $i, 7, '0', STR_PAD_LEFT), 'is_active' => true]
            //     );
            //     $position = $positions[$i % $positions->count()];
            //     $hireDate = jalaliToGregorianDateSafe(1404, 1, min(28, $i));
            //     $employee = Employee::updateOrCreate(
            //         ['personnel_code' => 'NEW-P' . str_pad((string) $i, 4, '0', STR_PAD_LEFT)],
            //         [
            //             'party_id' => $party->id,
            //             'attendance_card_number' => 'CARD-' . str_pad((string) $i, 4, '0', STR_PAD_LEFT),
            //             'hire_date' => $hireDate,
            //             'first_name' => $firstName,
            //             'last_name' => $lastName,
            //             'national_code' => '00' . str_pad((string) $i, 8, '0', STR_PAD_LEFT),
            //             'phone' => '0912' . str_pad((string) $i, 7, '0', STR_PAD_LEFT),
            //             'position' => $position->title,
            //             'department' => $units[$i % $units->count()]->title,
            //             'position_id' => $position->id,
            //             'organization_unit_id' => $position->organization_unit_id,
            //             'employment_type' => 'full_time',
            //             'salary_type' => $i % 5 === 0 ? 'hourly' : 'monthly',
            //             'base_salary' => 166255500 + ($i * 1500000),
            //             'hourly_rate' => round((166255500 + ($i * 1500000)) / 220, 2),
            //             'overtime_rate' => round(((166255500 + ($i * 1500000)) / 220) * 1.4, 2),
            //             'start_date' => $hireDate,
            //             'is_active' => true,
            //             'status' => 'active',
            //         ]
            //     );
            //     $employee->workGroups()->syncWithoutDetaching([$groups[$i % $groups->count()]->id]);

            //     EmploymentOrder::updateOrCreate(
            //         ['number' => 'NEW-EO-1405-03-' . str_pad((string) $i, 4, '0', STR_PAD_LEFT)],
            //         [
            //             'employee_id' => $employee->id,
            //             'position_id' => $position->id,
            //             'job_id' => $position->job_id,
            //             'organization_unit_id' => $position->organization_unit_id,
            //             'default_project_id' => $projects[$i % $projects->count()]->id,
            //             'order_type' => 'hire',
            //             'employment_type' => 'full_time',
            //             'insurance_status' => 'insured',
            //             'effective_date' => jalaliToGregorianDateSafe(1405, 3, 1),
            //             'base_salary' => $employee->base_salary,
            //             'hourly_rate' => $employee->hourly_rate,
            //             'housing_allowance' => 30000000,
            //             'food_allowance' => 22000000,
            //             'transportation_allowance' => 4000000 + ($i % 4) * 500000,
            //             'fixed_benefits' => ['پاداش بهره‌وری' => ($i % 3) * 2000000],
            //             'fixed_deductions' => ['قسط وام' => ($i % 4) * 1000000],
            //             'status' => 'approved',
            //             'approved_at' => now(),
            //         ]
            //     );

            //     AttendanceRawLog::where('employee_id', $employee->id)->where('source', 'new-workflow-seed')->delete();
            //     AttendanceLeave::where('employee_id', $employee->id)->delete();
            //     AttendanceMission::where('employee_id', $employee->id)->delete();

            //     for ($day = 1; $day <= 31; $day++) {
            //         $date = $start->copy()->addDays($day - 1);
            //         if ((int) $date->dayOfWeek === Carbon::FRIDAY || ($day === 12 && $i % 6 === 0)) {
            //             continue;
            //         }
            //         $shift = $groups[$i % $groups->count()]->shift;
            //         $in = Carbon::parse($date->toDateString() . ' ' . $shift->start_time)->addMinutes(($day + $i) % 5 === 0 ? 35 : 0);
            //         $out = Carbon::parse($date->toDateString() . ' ' . $shift->end_time)->addMinutes(($day + $i) % 7 === 0 ? 120 : 0)->subMinutes(($day + $i) % 11 === 0 ? 45 : 0);
            //         if ($out->lte($in)) {
            //             $out->addDay();
            //         }
            //         AttendanceRawLog::create(['employee_id' => $employee->id, 'attendance_card_number' => $employee->attendance_card_number, 'logged_at' => $in, 'direction' => 'in', 'device_code' => 'DEV-01', 'source' => 'new-workflow-seed']);
            //         AttendanceRawLog::create(['employee_id' => $employee->id, 'attendance_card_number' => $employee->attendance_card_number, 'logged_at' => $out, 'direction' => 'out', 'device_code' => 'DEV-01', 'source' => 'new-workflow-seed']);
            //     }

            //     if ($i % 4 === 0) {
            //         AttendanceLeave::create(['employee_id' => $employee->id, 'leave_date' => jalaliToGregorianDateSafe(1405, 3, 18), 'hours' => 8, 'type' => 'استحقاقی', 'status' => 'approved', 'description' => 'مرخصی تستی']);
            //     }
            //     if ($i % 3 === 0) {
            //         AttendanceMission::create(['employee_id' => $employee->id, 'project_id' => $projects[$i % $projects->count()]->id, 'cost_center_id' => $centers[$i % $centers->count()]->id, 'mission_date' => jalaliToGregorianDateSafe(1405, 3, 22), 'hours' => 8, 'status' => 'approved', 'description' => 'ماموریت تستی']);
            //     }
            // }
        });

        $period = app(PayrollCalculationService::class)->createOrGetPeriod(1405, 3, null);
        $calculations = app(NewPayrollEngineService::class)->runFullLifecycle($period);
        $calculations->each(fn ($calculation) => app(NewPayrollEngineService::class)->pay($calculation));

        $this->command?->info('چرخه کامل موتور جدید حضور و حقوق اجرا شد.');
        $this->command?->info('تعداد پرسنل پردازش‌شده: ' . $calculations->count());
    }
}
