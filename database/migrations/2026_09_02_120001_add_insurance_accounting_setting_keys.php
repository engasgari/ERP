<?php

use App\Models\ChartAccount;
use App\Models\PayrollAccountingSetting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $settings = [
            ['key' => 'insurance_expense', 'title' => 'هزینه بیمه سهم کارفرما', 'account_code' => '520206'],
            ['key' => 'insurance_penalty_expense', 'title' => 'هزینه جرائم بیمه', 'account_code' => '520115'],
            ['key' => 'insurance_other_expense', 'title' => 'سایر هزینه‌های بیمه', 'account_code' => '520115'],
        ];

        foreach ($settings as $setting) {
            PayrollAccountingSetting::query()->updateOrCreate(
                ['key' => $setting['key']],
                $setting + [
                    'chart_account_id' => ChartAccount::where('code', $setting['account_code'])->value('id'),
                    'is_active' => true,
                ]
            );
        }
    }

    public function down(): void
    {
        PayrollAccountingSetting::query()
            ->whereIn('key', ['insurance_expense', 'insurance_penalty_expense', 'insurance_other_expense'])
            ->delete();
    }
};
