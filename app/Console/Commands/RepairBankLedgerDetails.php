<?php

namespace App\Console\Commands;

use App\Services\BankAccountCodingService;
use Illuminate\Console\Command;

class RepairBankLedgerDetails extends Command
{
    protected $signature = 'accounting:repair-bank-details {--dry-run : فقط گزارش تعداد تغییرات را نشان بده، بدون ذخیره}';

    protected $description = 'تفصیل بانک‌ها را بازسازی می‌کند و سطرهای قدیمی سندهای بانکی را به تفصیل بانک وصل می‌کند';

    public function handle(BankAccountCodingService $coding): int
    {
        $this->info('بازسازی تفصیل بانک‌ها شروع شد...');

        $banks = $coding->syncAllBankDetailAccounts();
        $this->line("بانک‌های پردازش‌شده: {$banks}");

        if ($this->option('dry-run')) {
            $this->warn('dry-run فعال است؛ اصلاح سطرهای قدیمی انجام نشد.');

            return self::SUCCESS;
        }

        $result = $coding->backfillHistoricalBankDetails();

        $this->info('اصلاح سطرهای قدیمی انجام شد.');
        $this->table(['مورد', 'تعداد'], [
            ['سطرهای سند حسابداری', $result['accounting_document_lines']],
            ['تراکنش‌های مالی', $result['financial_transactions']],
            ['سطرهای سند مبتنی بر منبع', $result['document_source_lines']],
        ]);

        return self::SUCCESS;
    }
}
