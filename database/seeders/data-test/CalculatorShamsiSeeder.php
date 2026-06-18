<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\EmploymentOrder;
use App\Models\Job;
use App\Models\OrganizationUnit;
use App\Models\Party;
use App\Models\Position;
use App\Models\Project;
use App\Models\WorkCalendar;
use App\Models\WorkGroup;
use App\Models\WorkLog;
use App\Models\WorkShift;
use App\Services\PayrollCalculationService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CalculatorShamsiSeeder extends Seeder
{
    private const JALALI_YEAR = 1405;

    private const JALALI_MONTH = 3;

    private const IMPORT_BATCH = 'calculator-shamsi-1405-03';

    public function run(): void
    {
        $this->call(StandardPayrollSeeder::class);

        DB::transaction(function (): void {
            $calendar = $this->seedCalendar();
            $shift = $this->seedShift();
            $group = $this->seedGroup($calendar, $shift);
            $project = $this->seedProject();
            $employee = $this->seedEmployee($group, $project);

            $this->seedWorkLogs($employee, $project, $shift);

            $period = app(PayrollCalculationService::class)->createOrGetPeriod(
                self::JALALI_YEAR,
                self::JALALI_MONTH
            );

            app(PayrollCalculationService::class)->calculate($period);
        });
    }

    private function seedCalendar(): WorkCalendar
    {
        return WorkCalendar::updateOrCreate(
            ['code' => 'CAL-SHAMSI-1405'],
            [
                'name' => 'تقویم کاری شمسی ۱۴۰۵',
                'jalali_year' => self::JALALI_YEAR,
                'working_days' => [0, 1, 2, 3, 4],
                'weekend_days' => [5, 6],
                'holidays' => [],
                'is_default' => true,
                'is_active' => true,
                'description' => 'تقویم demo برای تست محاسبه حقوق و حضور و غیاب شمسی',
            ]
        );
    }

    private function seedShift(): WorkShift
    {
        return WorkShift::updateOrCreate(
            ['code' => 'SHIFT-SHAMSI-DAY'],
            [
                'name' => 'شیفت روزانه شمسی',
                'start_time' => '08:00:00',
                'end_time' => '17:00:00',
                'break_minutes' => 60,
                'daily_work_hours' => 8,
                'overtime_multiplier' => 1.4,
                'late_tolerance_minutes' => 15,
                'early_leave_tolerance_minutes' => 15,
                'is_active' => true,
                'description' => 'شیفت نمونه برای سناریوی محاسبه حقوق شمسی',
            ]
        );
    }

    private function seedGroup(WorkCalendar $calendar, WorkShift $shift): WorkGroup
    {
        return WorkGroup::updateOrCreate(
            ['code' => 'WG-SHAMSI-1405'],
            [
                'name' => 'گروه کاری شمسی ۱۴۰۵',
                'work_shift_id' => $shift->id,
                'work_calendar_id' => $calendar->id,
                'payroll_rules' => [
                    'overtime_multiplier' => 1.4,
                    'late_tolerance_minutes' => 15,
                    'early_leave_tolerance_minutes' => 15,
                ],
                'is_active' => true,
                'description' => 'گروه نمونه متصل به تقویم و شیفت شمسی',
            ]
        );
    }

    private function seedProject(): Project
    {
        return Project::updateOrCreate(
            ['project_number' => 'PRJ-SHAMSI-1405-03'],
            [
                'name' => 'پروژه دمو محاسبه شمسی',
                'description' => 'پروژه نمونه برای ثبت کارکردهای حقوقی شمسی',
                'status' => 'active',
                'start_date' => jalaliToGregorianDateSafe(self::JALALI_YEAR, self::JALALI_MONTH, 1),
                'end_date' => jalaliToGregorianDateSafe(self::JALALI_YEAR, self::JALALI_MONTH, 31),
            ]
        );
    }

    private function seedEmployee(WorkGroup $group, Project $project): Employee
    {
        $unit = OrganizationUnit::updateOrCreate(
            ['code' => 'ORG-SHAMSI-HR'],
            [
                'title' => 'واحد دمو منابع انسانی',
                'type' => 'department',
                'is_active' => true,
                'description' => 'واحد نمونه برای سناریوی محاسبه حقوق شمسی',
            ]
        );

        $job = Job::updateOrCreate(
            ['code' => 'JOB-SHAMSI-001'],
            [
                'title' => 'کارشناس حقوق و دستمزد',
                'description' => 'شغل نمونه برای اجرای seeder محاسبه شمسی',
                'is_active' => true,
            ]
        );

        $position = Position::updateOrCreate(
            ['code' => 'POS-SHAMSI-001'],
            [
                'title' => 'کارشناس حقوق و دستمزد',
                'job_id' => $job->id,
                'organization_unit_id' => $unit->id,
                'capacity' => 1,
                'is_active' => true,
                'description' => 'پست نمونه برای سناریوی demo',
            ]
        );

        $party = Party::updateOrCreate(
            ['code' => 'PTY-SHAMSI-001'],
            [
                'detail_code' => 'DL-SH-0001',
                'kind' => 'person',
                'name' => 'لیلا احمدی',
                'national_id' => '0012345678',
                'mobile' => '09120000001',
                'is_active' => true,
                'notes' => 'پرسنل demo برای محاسبه حقوق شمسی',
            ]
        );

        $employee = Employee::updateOrCreate(
            ['personnel_code' => 'EMP-SHAMSI-001'],
            [
                'employee_code' => 'EMP-SHAMSI-001',
                'party_id' => $party->id,
                'personnel_number' => 'PN-SH-0001',
                'attendance_card_number' => 'CARD-SH-0001',
                'hire_date' => jalaliToGregorianDateSafe(1404, 1, 1),
                'first_name' => 'لیلا',
                'last_name' => 'احمدی',
                'national_code' => '0012345678',
                'phone' => '09120000001',
                'department' => $unit->title,
                'position' => $position->title,
                'position_id' => $position->id,
                'organization_unit_id' => $unit->id,
                'employment_type' => 'full_time',
                'salary_type' => 'monthly',
                'salary' => 0,
                'base_salary' => 166255500,
                'hourly_rate' => 755707.95,
                'overtime_rate' => 1057980.73,
                'default_cost_center' => 'CC-SH-001',
                'default_project_id' => $project->id,
                'insurance_number' => 'INS-SH-0001',
                'start_date' => jalaliToGregorianDateSafe(1404, 1, 1),
                'is_active' => true,
                'status' => 'active',
                'notes' => 'پرسنل نمونه برای seeder calculator-shamsi',
            ]
        );

        EmploymentOrder::updateOrCreate(
            ['number' => 'EO-SHAMSI-1405-03-001'],
            [
                'employee_id' => $employee->id,
                'position_id' => $position->id,
                'job_id' => $job->id,
                'organization_unit_id' => $unit->id,
                'cost_center_code' => 'CC-SH-001',
                'default_project_id' => $project->id,
                'order_type' => 'hire',
                'employment_type' => 'full_time',
                'insurance_status' => 'insured',
                'effective_date' => jalaliToGregorianDateSafe(self::JALALI_YEAR, self::JALALI_MONTH, 1),
                'base_salary' => 166255500,
                'hourly_rate' => 755707.95,
                'housing_allowance' => 30000000,
                'food_allowance' => 22000000,
                'child_allowance' => 16625550,
                'transportation_allowance' => 6000000,
                'fixed_benefits' => [
                    'bonus' => 5000000,
                ],
                'fixed_deductions' => [
                    'loan' => 2000000,
                ],
                'status' => 'approved',
                'approved_at' => now(),
                'notes' => 'حکم دمو برای سناریوی محاسبه شمسی',
            ]
        );

        $employee->workGroups()->syncWithoutDetaching([$group->id]);

        return $employee;
    }

    private function seedWorkLogs(Employee $employee, Project $project, WorkShift $shift): void
    {
        $hoursByDay = [
            1 => ['hours' => 8, 'overtime' => 0, 'delay' => 0, 'early' => 0, 'mission' => 0, 'leave' => 0, 'absence' => 0],
            2 => ['hours' => 9.5, 'overtime' => 1.5, 'delay' => 0, 'early' => 0, 'mission' => 0, 'leave' => 0, 'absence' => 0],
            3 => ['hours' => 7.5, 'overtime' => 0, 'delay' => 0.5, 'early' => 0, 'mission' => 0, 'leave' => 0, 'absence' => 0],
            4 => ['hours' => 8, 'overtime' => 0, 'delay' => 0, 'early' => 0.5, 'mission' => 0, 'leave' => 0, 'absence' => 0],
            5 => ['hours' => 4, 'overtime' => 0, 'delay' => 0, 'early' => 0, 'mission' => 4, 'leave' => 0, 'absence' => 0],
            6 => ['hours' => 8, 'overtime' => 0, 'delay' => 0, 'early' => 0, 'mission' => 0, 'leave' => 8, 'absence' => 0],
        ];

        foreach ($hoursByDay as $day => $data) {
            $date = jalaliToGregorianDateSafe(self::JALALI_YEAR, self::JALALI_MONTH, $day);

            WorkLog::updateOrCreate(
                [
                    'employee_id' => $employee->id,
                    'project_id' => $project->id,
                    'work_date' => $date,
                    'import_batch' => self::IMPORT_BATCH,
                ],
                [
                    'cost_center' => 'CC-SH-001',
                    'check_in_time' => $shift->start_time,
                    'check_out_time' => $shift->end_time,
                    'start_time' => $shift->start_time,
                    'end_time' => $shift->end_time,
                    'hours' => $data['hours'],
                    'overtime_hours' => $data['overtime'],
                    'delay_hours' => $data['delay'],
                    'early_leave_hours' => $data['early'],
                    'mission_hours' => $data['mission'],
                    'leave_hours' => $data['leave'],
                    'absence_hours' => $data['absence'],
                    'description' => 'کارکرد دمو برای seeder calculator-shamsi',
                    'hourly_rate' => 755707.95,
                    'total_amount' => (($data['hours'] + $data['mission']) * 755707.95) + ($data['overtime'] * 1057980.73),
                    'attendance_source' => 'calculator_shamsi_seed',
                ]
            );
        }
    }
}
