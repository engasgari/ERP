<?php

namespace App\Support\Hr;

/**
 * اقلام استاندارد حکم کارگزینی مطابق قانون کار ایران،
 * رویه‌های رایج لیست بیمه تامین اجتماعی و مالیات حقوق.
 */
class IranLaborEmploymentOrderCatalog
{
    public static function orderTypes(): array
    {
        return [
            'hire' => 'استخدام',
            'confirmation' => 'تایید وضعیت / تبدیل وضعیت',
            'salary_change' => 'اصلاح حقوق و مزایا',
            'grade_change' => 'تغییر گروه / رتبه / پایه',
            'position_change' => 'تغییر پست سازمانی',
            'transfer' => 'انتقال محل خدمت',
            'contract_renewal' => 'تمدید قرارداد',
            'termination' => 'خاتمه همکاری',
        ];
    }

    public static function employmentTypes(): array
    {
        return [
            'permanent' => 'رسمی',
            'fixed_term' => 'پیمانی / مدت معین',
            'monthly_contract' => 'قراردادی ماهانه',
            'hourly' => 'ساعتی',
            'project_based' => 'پروژه‌ای',
        ];
    }

    public static function insuranceStatuses(): array
    {
        return [
            'insured' => 'مشمول بیمه تامین اجتماعی',
            'not_insured' => 'غیرمشمول بیمه',
            'exempt' => 'معاف از بیمه',
        ];
    }

    public static function maritalStatuses(): array
    {
        return [
            'single' => 'مجرد',
            'married' => 'متاهل',
        ];
    }

    /**
     * @return array<string, array{title:string,insurable:bool,taxable:bool,field:string}>
     */
    public static function wageComponents(): array
    {
        return [
            'base_salary' => [
                'title' => 'حقوق پایه / مزد مبنا (ماهانه)',
                'insurable' => true,
                'taxable' => true,
                'field' => 'base_salary',
            ],
            'seniority_pay' => [
                'title' => 'پایه سنوات',
                'insurable' => true,
                'taxable' => true,
                'field' => 'seniority_pay',
            ],
            'housing_allowance' => [
                'title' => 'کمک‌هزینه مسکن',
                'insurable' => true,
                'taxable' => true,
                'field' => 'housing_allowance',
            ],
            'food_allowance' => [
                'title' => 'بن خواربار / اقلام مصرفی',
                'insurable' => true,
                'taxable' => true,
                'field' => 'food_allowance',
            ],
            'child_allowance' => [
                'title' => 'کمک‌هزینه اولاد (مبلغ ماهانه)',
                'insurable' => true,
                'taxable' => true,
                'field' => 'child_allowance',
            ],
            'marriage_allowance' => [
                'title' => 'کمک‌هزینه عائله‌مندی / حق تاهل',
                'insurable' => true,
                'taxable' => true,
                'field' => 'marriage_allowance',
            ],
            'job_allowance' => [
                'title' => 'فوق‌العاده شغل',
                'insurable' => true,
                'taxable' => true,
                'field' => 'job_allowance',
            ],
            'hardship_allowance' => [
                'title' => 'فوق‌العاده سختی کار',
                'insurable' => true,
                'taxable' => true,
                'field' => 'hardship_allowance',
            ],
            'shift_allowance' => [
                'title' => 'فوق‌العاده نوبت‌کاری (ثابت)',
                'insurable' => true,
                'taxable' => true,
                'field' => 'shift_allowance',
            ],
            'other_insurable_benefits' => [
                'title' => 'سایر مزایای ثابت مشمول بیمه',
                'insurable' => true,
                'taxable' => true,
                'field' => 'other_insurable_benefits',
            ],
            'transportation_allowance' => [
                'title' => 'ایاب و ذهاب',
                'insurable' => false,
                'taxable' => true,
                'field' => 'transportation_allowance',
            ],
            'other_non_insurable_benefits' => [
                'title' => 'سایر مزایای غیرمشمول بیمه',
                'insurable' => false,
                'taxable' => true,
                'field' => 'other_non_insurable_benefits',
            ],
        ];
    }

    public static function moneyFields(): array
    {
        return array_values(array_map(
            fn (array $component) => $component['field'],
            self::wageComponents()
        ));
    }
}
