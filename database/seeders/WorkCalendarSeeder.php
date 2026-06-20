<?php

namespace Database\Seeders;

use App\Models\WorkCalendar;
use App\Support\WorkCalendarDefaults;
use Illuminate\Database\Seeder;

class WorkCalendarSeeder extends Seeder
{
    public function run(): void
    {
        $calendars = [
            1404 => [
                'code' => 'CAL-1404',
                'name' => 'تقویم کاری ۱۴۰۴',
            ],
            1405 => [
                'code' => 'CAL-1405',
                'name' => 'تقویم کاری ۱۴۰۵',
            ],
        ];

        foreach ($calendars as $year => $calendar) {
            $defaults = WorkCalendarDefaults::defaultCalendar($year);

            WorkCalendar::updateOrCreate(
                ['code' => $calendar['code']],
                [
                    'name' => $calendar['name'],
                    'jalali_year' => $year,
                    'working_days' => $defaults['working_days'],
                    'weekend_days' => $defaults['weekend_days'],
                    'holidays' => $defaults['holidays'],
                    'is_default' => $year === 1405,
                    'is_active' => true,
                    'description' => 'تقویم نمونه' . ' ' . $year,
                ]
            );
        }
    }
}
