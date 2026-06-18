<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\EmploymentOrder;
use App\Models\PayrollPeriod;
use App\Models\Project;
use App\Models\Salary;
use App\Models\WorkLog;
use App\Services\PayrollCalculationService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class LeilaPayrollTestSeeder extends Seeder
{
    private const BATCH = 'leila-1405-03-payroll-test';

    public function run(): void
    {
        $employee = Employee::with('party')
            ->whereHas('party', fn ($query) => $query->where('name', 'like', '%لیلا%'))
            ->orWhere('first_name', 'like', '%لیلا%')
            ->firstOrFail();

        $project = Project::firstOrCreate(
            ['name' => 'پروژه تست حقوق و کارکرد'],
            ['description' => 'پروژه تستی برای محاسبه کارکرد، ماموریت و حقوق']
        );

        $monthlyBase = 166255500;
        $hourlyRate = round($monthlyBase / 220, 2);

        $employee->update([
            'salary_type' => 'monthly',
            'base_salary' => $monthlyBase,
            'hourly_rate' => $hourlyRate,
            'overtime_rate' => round($hourlyRate * 1.4, 2),
            'default_project_id' => $project->id,
            'is_active' => true,
            'status' => 'active',
        ]);

        EmploymentOrder::updateOrCreate(
            ['number' => 'EO-LEILA-1405-03-TEST'],
            [
                'employee_id' => $employee->id,
                'position_id' => $employee->position_id,
                'organization_unit_id' => $employee->organization_unit_id,
                'default_project_id' => $project->id,
                'order_type' => 'salary_adjustment',
                'employment_type' => $employee->employment_type ?: 'full_time',
                'insurance_status' => 'insured',
                'effective_date' => jalaliToGregorianDateSafe(1405, 3, 31),
                'end_date' => null,
                'base_salary' => $monthlyBase,
                'hourly_rate' => $hourlyRate,
                'housing_allowance' => 30000000,
                'food_allowance' => 22000000,
                'child_allowance' => 0,
                'transportation_allowance' => 6000000,
                'fixed_benefits' => ['پاداش عملکرد تستی' => 7500000],
                'fixed_deductions' => ['قسط وام تستی' => 2500000],
                'status' => 'approved',
                'approved_at' => now(),
                'notes' => 'حکم تستی برای اجرای کامل کارکرد و حقوق خرداد ۱۴۰۵',
            ]
        );

        WorkLog::where('employee_id', $employee->id)
            ->where('import_batch', self::BATCH)
            ->delete();

        $start = Carbon::parse(jalaliToGregorianDateSafe(1405, 3, 1));
        $daysInMonth = 31;

        for ($day = 1; $day <= $daysInMonth; $day++) {
            $date = $start->copy()->addDays($day - 1);

            if ((int) $date->dayOfWeek === Carbon::FRIDAY) {
                continue;
            }

            $data = $this->dayData($day);
            WorkLog::create([
                'employee_id' => $employee->id,
                'project_id' => $project->id,
                'cost_center' => 'HR-TEST',
                'work_date' => $date->toDateString(),
                'check_in_time' => $data['start'] ?: '00:00:00',
                'check_out_time' => $data['end'] ?: '00:00:00',
                'start_time' => $data['start'] ?: '00:00:00',
                'end_time' => $data['end'] ?: '00:00:00',
                'hours' => $data['hours'],
                'overtime_hours' => $data['overtime'],
                'delay_hours' => $data['delay'],
                'early_leave_hours' => $data['early'],
                'mission_hours' => $data['mission'],
                'leave_hours' => $data['leave'],
                'absence_hours' => $data['absence'],
                'description' => $data['description'],
                'hourly_rate' => $hourlyRate,
                'total_amount' => (($data['hours'] + $data['mission']) * $hourlyRate) + ($data['overtime'] * $employee->overtime_rate),
                'attendance_source' => 'test_seed',
                'import_batch' => self::BATCH,
            ]);
        }

        $period = app(PayrollCalculationService::class)->createOrGetPeriod(1405, 3, null);
        app(PayrollCalculationService::class)->calculate($period, null);

        $salary = Salary::with('lines')
            ->where('employee_id', $employee->id)
            ->where('year', 1405)
            ->where('month', 3)
            ->firstOrFail();

        $this->command?->info('کارکرد و حقوق تستی لیلا ثبت شد.');
        $this->command?->info('شناسه فیش حقوقی: ' . $salary->id);
        $this->command?->info('مبلغ نهایی: ' . number_format((float) $salary->final_salary) . ' ریال');
    }

    private function dayData(int $day): array
    {
        $base = [
            'start' => '08:00:00',
            'end' => '17:00:00',
            'hours' => 8,
            'overtime' => 0,
            'delay' => 0,
            'early' => 0,
            'mission' => 0,
            'leave' => 0,
            'absence' => 0,
            'description' => 'کارکرد عادی تستی',
        ];

        return match ($day) {
            3 => array_merge($base, ['end' => '19:00:00', 'hours' => 10, 'overtime' => 2, 'description' => 'کارکرد با ۲ ساعت اضافه‌کاری']),
            4 => array_merge($base, ['start' => '08:45:00', 'hours' => 7.25, 'delay' => 0.75, 'description' => 'تاخیر ۴۵ دقیقه‌ای']),
            5 => array_merge($base, ['end' => '16:00:00', 'hours' => 7, 'early' => 1, 'description' => 'تعجیل خروج ۱ ساعت']),
            10 => array_merge($base, ['start' => null, 'end' => null, 'hours' => 0, 'mission' => 8, 'description' => 'ماموریت تمام‌روز']),
            12 => array_merge($base, ['start' => null, 'end' => null, 'hours' => 0, 'leave' => 8, 'description' => 'مرخصی روزانه']),
            16 => array_merge($base, ['start' => null, 'end' => null, 'hours' => 0, 'absence' => 8, 'description' => 'غیبت روزانه تستی']),
            17 => array_merge($base, ['end' => '18:30:00', 'hours' => 9.5, 'overtime' => 1.5, 'description' => 'کارکرد با ۱.۵ ساعت اضافه‌کاری']),
            19 => array_merge($base, ['start' => '08:30:00', 'end' => '16:30:00', 'hours' => 7, 'delay' => 0.5, 'early' => 0.5, 'description' => 'تاخیر و تعجیل هرکدام نیم ساعت']),
            23 => array_merge($base, ['end' => '12:00:00', 'hours' => 4, 'mission' => 4, 'description' => 'نیم‌روز کارکرد و نیم‌روز ماموریت']),
            25 => array_merge($base, ['end' => '20:00:00', 'hours' => 11, 'overtime' => 3, 'description' => 'کارکرد با ۳ ساعت اضافه‌کاری']),
            default => $base,
        };
    }
}
