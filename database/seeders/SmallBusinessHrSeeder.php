<?php

namespace Database\Seeders;

use App\Models\Job;
use App\Models\OrganizationUnit;
use App\Models\Position;
use Illuminate\Database\Seeder;

class SmallBusinessHrSeeder extends Seeder
{
    public function run(): void
    {
        $units = [
            ['code' => 'ORG-001', 'title' => 'مدیریت عامل', 'type' => 'company', 'cost_center_code' => 'CC-100', 'description' => 'مدیریت کل شرکت'],
            ['code' => 'ORG-010', 'title' => 'منابع انسانی و اداری', 'type' => 'department', 'cost_center_code' => 'CC-110', 'description' => 'امور پرسنلی، اداری و پشتیبانی'],
            ['code' => 'ORG-020', 'title' => 'مالی و حسابداری', 'type' => 'department', 'cost_center_code' => 'CC-120', 'description' => 'حسابداری، خزانه و گزارش‌های مالی'],
            ['code' => 'ORG-030', 'title' => 'فروش و بازاریابی', 'type' => 'department', 'cost_center_code' => 'CC-130', 'description' => 'فروش، ارتباط با مشتری و توسعه بازار'],
            ['code' => 'ORG-040', 'title' => 'عملیات و تولید', 'type' => 'department', 'cost_center_code' => 'CC-140', 'description' => 'برنامه‌ریزی و کنترل عملیات، تولید و ارائه خدمات'],
            ['code' => 'ORG-041', 'title' => 'دپارتمان پروژه', 'type' => 'department', 'cost_center_code' => 'CC-141', 'description' => 'مدیریت پروژه‌ها، برنامه‌ریزی، کنترل پیشرفت و هماهنگی اجرا'],
            ['code' => 'ORG-042', 'title' => 'طراحی الکترونیک', 'type' => 'department', 'cost_center_code' => 'CC-142', 'description' => 'طراحی مدار، PCB، firmware و تست الکترونیک'],
            ['code' => 'ORG-043', 'title' => 'طراحی مکانیک', 'type' => 'department', 'cost_center_code' => 'CC-143', 'description' => 'طراحی مکانیکی، مدل‌سازی سه‌بعدی، نقشه ساخت و مستندات فنی'],
            ['code' => 'ORG-044', 'title' => 'تولید و مونتاژ', 'type' => 'department', 'cost_center_code' => 'CC-144', 'description' => 'تولید، مونتاژ، کنترل کیفیت و آماده‌سازی محصول'],
            ['code' => 'ORG-050', 'title' => 'انبار و تدارکات', 'type' => 'department', 'cost_center_code' => 'CC-150', 'description' => 'انبار، خرید و تامین کالا'],
            ['code' => 'ORG-060', 'title' => 'فناوری اطلاعات', 'type' => 'department', 'cost_center_code' => 'CC-160', 'description' => 'زیرساخت، نرم‌افزار و پشتیبانی سیستم‌ها'],
        ];

        foreach ($units as $unit) {
            OrganizationUnit::updateOrCreate(
                ['code' => $unit['code']],
                $unit + ['parent_id' => null, 'is_active' => true]
            );
        }

        $root = OrganizationUnit::where('code', 'ORG-001')->first();
        OrganizationUnit::where('code', '!=', 'ORG-001')->update(['parent_id' => $root?->id]);

        $jobs = [
            ['code' => 'JOB-001', 'title' => 'مدیرعامل', 'description' => 'راهبری و تصمیم‌گیری کلان شرکت'],
            ['code' => 'JOB-010', 'title' => 'مدیر منابع انسانی', 'description' => 'مدیریت جذب، نگهداشت و امور پرسنلی'],
            ['code' => 'JOB-011', 'title' => 'کارشناس اداری و منابع انسانی', 'description' => 'ثبت پرونده پرسنلی، حضور و غیاب و امور اداری'],
            ['code' => 'JOB-020', 'title' => 'مدیر مالی', 'description' => 'مدیریت مالی، حسابداری و کنترل نقدینگی'],
            ['code' => 'JOB-021', 'title' => 'حسابدار', 'description' => 'ثبت اسناد، کنترل حساب‌ها و گزارش‌های مالی'],
            ['code' => 'JOB-030', 'title' => 'مدیر فروش', 'description' => 'مدیریت تیم فروش و ارتباط با مشتریان کلیدی'],
            ['code' => 'JOB-031', 'title' => 'کارشناس فروش', 'description' => 'پیگیری فروش، صدور پیش‌فاکتور و ارتباط با مشتری'],
            ['code' => 'JOB-040', 'title' => 'مدیر عملیات', 'description' => 'برنامه‌ریزی و کنترل عملیات روزانه'],
            ['code' => 'JOB-041', 'title' => 'مدیر پروژه', 'description' => 'مدیریت زمان، هزینه، محدوده و تحویل پروژه‌ها'],
            ['code' => 'JOB-042', 'title' => 'کارشناس کنترل پروژه', 'description' => 'برنامه‌ریزی، کنترل پیشرفت، گزارش‌دهی و پیگیری اقدامات پروژه'],
            ['code' => 'JOB-043', 'title' => 'هماهنگ‌کننده پروژه', 'description' => 'هماهنگی بین طراحی، خرید، تولید و مشتری در پروژه‌ها'],
            ['code' => 'JOB-050', 'title' => 'مدیر طراحی الکترونیک', 'description' => 'هدایت تیم طراحی الکترونیک و تایید راهکارهای فنی'],
            ['code' => 'JOB-051', 'title' => 'مهندس الکترونیک', 'description' => 'طراحی مدار، انتخاب قطعات، تحلیل شماتیک و عیب‌یابی سخت‌افزار'],
            ['code' => 'JOB-052', 'title' => 'طراح PCB', 'description' => 'طراحی برد مدار چاپی، رعایت اصول EMC و آماده‌سازی فایل تولید'],
            ['code' => 'JOB-053', 'title' => 'مهندس firmware', 'description' => 'توسعه نرم‌افزار embedded، راه‌اندازی سخت‌افزار و تست عملکرد'],
            ['code' => 'JOB-054', 'title' => 'تکنسین تست الکترونیک', 'description' => 'تست بردها، ثبت نتایج آزمون و عیب‌یابی اولیه'],
            ['code' => 'JOB-060', 'title' => 'مدیر طراحی مکانیک', 'description' => 'هدایت طراحی مکانیکی، تایید نقشه‌ها و هماهنگی ساخت'],
            ['code' => 'JOB-061', 'title' => 'مهندس مکانیک', 'description' => 'طراحی قطعات، انتخاب مواد، تحلیل مونتاژ و مستندات فنی'],
            ['code' => 'JOB-062', 'title' => 'طراح صنعتی', 'description' => 'طراحی ظاهر محصول، ارگونومی و آماده‌سازی مدل مفهومی'],
            ['code' => 'JOB-063', 'title' => 'نقشه‌کش صنعتی', 'description' => 'تهیه نقشه ساخت، BOM مکانیکی و مستندات تولید'],
            ['code' => 'JOB-070', 'title' => 'مدیر تولید و مونتاژ', 'description' => 'مدیریت خط تولید، ظرفیت، زمان‌بندی و تحویل محصول'],
            ['code' => 'JOB-071', 'title' => 'سرپرست تولید', 'description' => 'نظارت بر اجرای برنامه تولید و کنترل عملکرد اپراتورها'],
            ['code' => 'JOB-072', 'title' => 'تکنسین مونتاژ الکترونیک', 'description' => 'مونتاژ برد، لحیم‌کاری، کابل‌کشی و آماده‌سازی الکترونیک'],
            ['code' => 'JOB-073', 'title' => 'تکنسین مونتاژ مکانیک', 'description' => 'مونتاژ قطعات مکانیکی، تنظیمات و کنترل نهایی مکانیکی'],
            ['code' => 'JOB-074', 'title' => 'کارشناس کنترل کیفیت', 'description' => 'کنترل کیفیت ورودی، حین تولید و محصول نهایی'],
            ['code' => 'JOB-075', 'title' => 'اپراتور تولید', 'description' => 'اجرای عملیات تولید، بسته‌بندی و ثبت کارکرد روزانه'],
            ['code' => 'JOB-080', 'title' => 'مسئول انبار', 'description' => 'کنترل موجودی، رسید و حواله انبار'],
            ['code' => 'JOB-081', 'title' => 'کارشناس خرید و تدارکات', 'description' => 'تامین کالا و پیگیری سفارش خرید'],
            ['code' => 'JOB-090', 'title' => 'کارشناس فناوری اطلاعات', 'description' => 'پشتیبانی سیستم‌ها، شبکه و نرم‌افزارها'],
        ];

        foreach ($jobs as $job) {
            Job::updateOrCreate(['code' => $job['code']], $job + ['is_active' => true]);
        }

        $unitId = fn (string $code) => OrganizationUnit::where('code', $code)->value('id');
        $jobId = fn (string $code) => Job::where('code', $code)->value('id');

        $positions = [
            ['code' => 'POS-001', 'title' => 'مدیرعامل', 'job' => 'JOB-001', 'unit' => 'ORG-001', 'capacity' => 1],
            ['code' => 'POS-010', 'title' => 'مدیر منابع انسانی و اداری', 'job' => 'JOB-010', 'unit' => 'ORG-010', 'capacity' => 1, 'supervisor' => 'POS-001'],
            ['code' => 'POS-011', 'title' => 'کارشناس اداری و منابع انسانی', 'job' => 'JOB-011', 'unit' => 'ORG-010', 'capacity' => 2, 'supervisor' => 'POS-010'],
            ['code' => 'POS-020', 'title' => 'مدیر مالی', 'job' => 'JOB-020', 'unit' => 'ORG-020', 'capacity' => 1, 'supervisor' => 'POS-001'],
            ['code' => 'POS-021', 'title' => 'حسابدار', 'job' => 'JOB-021', 'unit' => 'ORG-020', 'capacity' => 3, 'supervisor' => 'POS-020'],
            ['code' => 'POS-030', 'title' => 'مدیر فروش', 'job' => 'JOB-030', 'unit' => 'ORG-030', 'capacity' => 1, 'supervisor' => 'POS-001'],
            ['code' => 'POS-031', 'title' => 'کارشناس فروش', 'job' => 'JOB-031', 'unit' => 'ORG-030', 'capacity' => 5, 'supervisor' => 'POS-030'],
            ['code' => 'POS-040', 'title' => 'مدیر عملیات', 'job' => 'JOB-040', 'unit' => 'ORG-040', 'capacity' => 1, 'supervisor' => 'POS-001'],

            ['code' => 'POS-041', 'title' => 'مدیر پروژه', 'job' => 'JOB-041', 'unit' => 'ORG-041', 'capacity' => 1, 'supervisor' => 'POS-001'],
            ['code' => 'POS-042', 'title' => 'کارشناس کنترل پروژه', 'job' => 'JOB-042', 'unit' => 'ORG-041', 'capacity' => 2, 'supervisor' => 'POS-041'],
            ['code' => 'POS-043', 'title' => 'هماهنگ‌کننده پروژه', 'job' => 'JOB-043', 'unit' => 'ORG-041', 'capacity' => 2, 'supervisor' => 'POS-041'],

            ['code' => 'POS-050', 'title' => 'مدیر طراحی الکترونیک', 'job' => 'JOB-050', 'unit' => 'ORG-042', 'capacity' => 1, 'supervisor' => 'POS-040'],
            ['code' => 'POS-051', 'title' => 'مهندس الکترونیک', 'job' => 'JOB-051', 'unit' => 'ORG-042', 'capacity' => 3, 'supervisor' => 'POS-050'],
            ['code' => 'POS-052', 'title' => 'طراح PCB', 'job' => 'JOB-052', 'unit' => 'ORG-042', 'capacity' => 2, 'supervisor' => 'POS-050'],
            ['code' => 'POS-053', 'title' => 'مهندس firmware', 'job' => 'JOB-053', 'unit' => 'ORG-042', 'capacity' => 2, 'supervisor' => 'POS-050'],
            ['code' => 'POS-054', 'title' => 'تکنسین تست الکترونیک', 'job' => 'JOB-054', 'unit' => 'ORG-042', 'capacity' => 2, 'supervisor' => 'POS-050'],

            ['code' => 'POS-060', 'title' => 'مدیر طراحی مکانیک', 'job' => 'JOB-060', 'unit' => 'ORG-043', 'capacity' => 1, 'supervisor' => 'POS-040'],
            ['code' => 'POS-061', 'title' => 'مهندس مکانیک', 'job' => 'JOB-061', 'unit' => 'ORG-043', 'capacity' => 3, 'supervisor' => 'POS-060'],
            ['code' => 'POS-062', 'title' => 'طراح صنعتی', 'job' => 'JOB-062', 'unit' => 'ORG-043', 'capacity' => 1, 'supervisor' => 'POS-060'],
            ['code' => 'POS-063', 'title' => 'نقشه‌کش صنعتی', 'job' => 'JOB-063', 'unit' => 'ORG-043', 'capacity' => 2, 'supervisor' => 'POS-060'],

            ['code' => 'POS-070', 'title' => 'مدیر تولید و مونتاژ', 'job' => 'JOB-070', 'unit' => 'ORG-044', 'capacity' => 1, 'supervisor' => 'POS-040'],
            ['code' => 'POS-071', 'title' => 'سرپرست تولید', 'job' => 'JOB-071', 'unit' => 'ORG-044', 'capacity' => 2, 'supervisor' => 'POS-070'],
            ['code' => 'POS-072', 'title' => 'تکنسین مونتاژ الکترونیک', 'job' => 'JOB-072', 'unit' => 'ORG-044', 'capacity' => 6, 'supervisor' => 'POS-071'],
            ['code' => 'POS-073', 'title' => 'تکنسین مونتاژ مکانیک', 'job' => 'JOB-073', 'unit' => 'ORG-044', 'capacity' => 5, 'supervisor' => 'POS-071'],
            ['code' => 'POS-074', 'title' => 'کارشناس کنترل کیفیت', 'job' => 'JOB-074', 'unit' => 'ORG-044', 'capacity' => 2, 'supervisor' => 'POS-070'],
            ['code' => 'POS-075', 'title' => 'اپراتور تولید', 'job' => 'JOB-075', 'unit' => 'ORG-044', 'capacity' => 10, 'supervisor' => 'POS-071'],

            ['code' => 'POS-080', 'title' => 'مسئول انبار', 'job' => 'JOB-080', 'unit' => 'ORG-050', 'capacity' => 2, 'supervisor' => 'POS-040'],
            ['code' => 'POS-081', 'title' => 'کارشناس خرید و تدارکات', 'job' => 'JOB-081', 'unit' => 'ORG-050', 'capacity' => 2, 'supervisor' => 'POS-080'],
            ['code' => 'POS-090', 'title' => 'کارشناس فناوری اطلاعات', 'job' => 'JOB-090', 'unit' => 'ORG-060', 'capacity' => 2, 'supervisor' => 'POS-001'],
        ];

        foreach ($positions as $position) {
            Position::updateOrCreate(
                ['code' => $position['code']],
                [
                    'title' => $position['title'],
                    'job_id' => $jobId($position['job']),
                    'organization_unit_id' => $unitId($position['unit']),
                    'supervisor_position_id' => null,
                    'capacity' => $position['capacity'],
                    'is_active' => true,
                    'description' => 'پست پیشنهادی قابل ویرایش برای ساختار منابع انسانی شرکت',
                ]
            );
        }

        foreach ($positions as $position) {
            if (! isset($position['supervisor'])) {
                continue;
            }

            Position::where('code', $position['code'])->update([
                'supervisor_position_id' => Position::where('code', $position['supervisor'])->value('id'),
            ]);
        }
    }
}
