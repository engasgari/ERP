<?php

namespace App\Services;

use App\Models\AccountingAudit;
use App\Models\AccountingDocument;
use App\Models\ChartAccount;
use App\Models\InventoryDocument;
use App\Models\Invoice;
use App\Models\PayrollAccountingSetting;
use App\Models\PaymentVoucher;
use App\Models\ReceiptVoucher;
use App\Models\Salary;
use App\Models\TreasuryTransaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AccountingPostingService
{
    public function __construct(
        private NumberingService $numbering,
        private FiscalPeriodService $periods
    ) {
    }

    public function createManual(array $data, array $lines, ?int $userId = null): AccountingDocument
    {
        return DB::transaction(function () use ($data, $lines, $userId) {
            $date = $data['document_date'];
            $this->periods->assertOpen($date);
            $this->assertBalanced($lines);

            $period = $this->periods->periodForDate($date);
            $year = $period?->fiscalYear ?: $this->periods->fiscalYearForDate($date);

            $document = AccountingDocument::create([
                'fiscal_year_id' => $year?->id,
                'fiscal_period_id' => $period?->id,
                'number' => $data['number'] ?? $this->numbering->next('accounting_document', 'ACC-'),
                'document_date' => $date,
                'type' => $data['type'] ?? 'manual',
                'status' => $data['status'] ?? 'draft',
                'currency' => $data['currency'] ?? 'IRR',
                'description' => $data['description'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $userId,
                'posted_at' => (($data['status'] ?? 'draft') === 'posted') ? now() : null,
                'posted_by' => (($data['status'] ?? 'draft') === 'posted') ? $userId : null,
            ]);

            $this->replaceLines($document, $lines);
            $this->audit($document, 'create', null, $document->load('lines')->toArray(), $userId);

            return $document;
        });
    }

    public function updateManual(AccountingDocument $document, array $data, array $lines, ?int $userId = null): AccountingDocument
    {
        return DB::transaction(function () use ($document, $data, $lines, $userId) {
            $this->periods->assertOpen($data['document_date'] ?? $document->document_date);
            $this->assertBalanced($lines);

            $old = $document->load('lines')->toArray();
            $document->update([
                'document_date' => $data['document_date'] ?? $document->document_date,
                'description' => $data['description'] ?? $document->description,
                'notes' => $data['notes'] ?? $document->notes,
                'currency' => $data['currency'] ?? $document->currency,
            ]);
            $this->replaceLines($document, $lines);
            $this->audit($document, 'edit', $old, $document->refresh()->load('lines')->toArray(), $userId);

            return $document;
        });
    }

    public function post(AccountingDocument $document, ?int $userId = null): AccountingDocument
    {
        return DB::transaction(function () use ($document, $userId) {
            $document->load('lines');
            $this->periods->assertOpen($document->document_date);
            $this->assertBalanced($document->lines->toArray());

            $old = $document->toArray();
            $document->update([
                'status' => 'posted',
                'posted_at' => now(),
                'posted_by' => $userId,
            ]);
            $this->audit($document, 'post', $old, $document->toArray(), $userId);

            return $document;
        });
    }

    public function unpost(AccountingDocument $document, ?int $userId = null): AccountingDocument
    {
        return DB::transaction(function () use ($document, $userId) {
            $this->periods->assertOpen($document->document_date);

            $old = $document->toArray();
            $document->update([
                'status' => 'draft',
                'posted_at' => null,
                'posted_by' => null,
            ]);
            $this->audit($document, 'unpost', $old, $document->toArray(), $userId);

            return $document;
        });
    }

    public function fromInvoice(Invoice $invoice, ?int $userId = null): AccountingDocument
    {
        $invoice->loadMissing('lines.item', 'party');

        if ($invoice->accounting_document_id) {
            return $invoice->accountingDocument;
        }

        $lines = [];
        $net = (float) $invoice->subtotal - (float) $invoice->discount_amount;

        if ($invoice->direction === 'sale') {
            $lines[] = $this->line('1101', $invoice->total_amount, 0, 'حساب دریافتنی مشتری', partyId: $invoice->party_id);
            $lines[] = $this->line('4101', 0, $net, 'درآمد فروش');
            if ((float) $invoice->tax_amount > 0) {
                $lines[] = $this->line('2102', 0, $invoice->tax_amount, 'مالیات ارزش افزوده فروش');
            }
        } else {
            $lines[] = $this->line('1103', $net, 0, 'خرید کالا/خدمات');
            if ((float) $invoice->tax_amount > 0) {
                $lines[] = $this->line('1102', $invoice->tax_amount, 0, 'اعتبار مالیات خرید');
            }
            $lines[] = $this->line('2101', 0, $invoice->total_amount, 'حساب پرداختنی فروشنده', partyId: $invoice->party_id);
        }

        $document = $this->automatic(
            source: $invoice,
            type: $invoice->direction === 'sale' ? 'sale_invoice' : 'purchase_invoice',
            date: $invoice->invoice_date->toDateString(),
            description: 'ثبت خودکار فاکتور ' . $invoice->number,
            lines: $lines,
            userId: $userId ?? $invoice->created_by
        );

        $invoice->update(['accounting_document_id' => $document->id]);

        return $document;
    }

    public function fromInventoryDocument(InventoryDocument $inventoryDocument, ?int $userId = null): ?AccountingDocument
    {
        $inventoryDocument->loadMissing('lines');
        $amount = (float) $inventoryDocument->lines->sum('line_total');

        if ($amount <= 0 || $inventoryDocument->accounting_document_id) {
            return $inventoryDocument->accountingDocument;
        }

        $lines = match ($inventoryDocument->type) {
            'receipt' => [
                $this->line('1103', $amount, 0, 'رسید انبار'),
                $this->line('5102', 0, $amount, 'تعدیل موجودی انبار'),
            ],
            'issue' => [
                $this->line('5103', $amount, 0, 'بهای تمام‌شده کالای فروش‌رفته'),
                $this->line('1103', 0, $amount, 'حواله انبار'),
            ],
            'consumption' => [
                $this->line(
                    $inventoryDocument->project_id ? '1106' : '5201',
                    $amount,
                    0,
                    $inventoryDocument->project_id ? 'مصرف مستقیم پروژه' : 'مصرف داخلی سازمان',
                    projectId: $inventoryDocument->project_id
                ),
                $this->line('1103', 0, $amount, 'حواله مصرف انبار'),
            ],
            default => [
                $this->line('1103', $amount, 0, 'انتقال ورودی انبار'),
                $this->line('1103', 0, $amount, 'انتقال خروجی انبار'),
            ],
        };

        $document = $this->automatic(
            source: $inventoryDocument,
            type: 'inventory',
            date: $inventoryDocument->document_date->toDateString(),
            description: 'ثبت خودکار سند انبار ' . $inventoryDocument->number,
            lines: $lines,
            userId: $userId ?? $inventoryDocument->created_by
        );

        $inventoryDocument->update(['accounting_document_id' => $document->id]);

        return $document;
    }

    public function fromReceiptVoucher(ReceiptVoucher $voucher, ?int $userId = null): AccountingDocument
    {
        $treasuryCode = $voucher->treasury_type === \App\Models\Cashbox::class ? '1201' : '1202';
        $creditCode = $voucher->type === 'customer' ? '1101' : (optional($voucher->incomeAccount)->code ?: '4102');

        return $this->automatic(
            source: $voucher,
            type: 'receipt',
            date: $voucher->voucher_date->toDateString(),
            description: 'رسید دریافت ' . $voucher->number,
            lines: [
                $this->line($treasuryCode, $voucher->amount, 0, 'دریافت خزانه'),
                $this->line($creditCode, 0, $voucher->amount, 'طرف حساب دریافت', partyId: $voucher->party_id),
            ],
            userId: $userId ?? $voucher->created_by
        );
    }

    public function fromPaymentVoucher(PaymentVoucher $voucher, ?int $userId = null): AccountingDocument
    {
        $treasuryCode = $voucher->treasury_type === \App\Models\Cashbox::class ? '1201' : '1202';
        $debitCode = $voucher->type === 'supplier' ? '2101' : (optional($voucher->expenseAccount)->code ?: '5201');

        return $this->automatic(
            source: $voucher,
            type: 'payment',
            date: $voucher->voucher_date->toDateString(),
            description: 'رسید پرداخت ' . $voucher->number,
            lines: [
                $this->line($debitCode, $voucher->amount, 0, 'طرف حساب پرداخت', partyId: $voucher->party_id),
                $this->line($treasuryCode, 0, $voucher->amount, 'پرداخت خزانه'),
            ],
            userId: $userId ?? $voucher->created_by
        );
    }

    public function fromTreasuryTransaction(TreasuryTransaction $transaction, ?int $userId = null): AccountingDocument
    {
        $amount = (float) $transaction->amount;
        $bankOrCashTo = $transaction->to_treasury_type === \App\Models\Cashbox::class ? '1201' : '1202';
        $bankOrCashFrom = $transaction->from_treasury_type === \App\Models\Cashbox::class ? '1201' : '1202';

        $lines = match ($transaction->type) {
            'deposit', 'cash_receipt', 'bank_receipt' => [
                $this->line($bankOrCashTo, $amount, 0, 'دریافت خزانه'),
                $this->line($transaction->party_id ? '1101' : '4102', 0, $amount, 'طرف حساب دریافت', partyId: $transaction->party_id),
            ],
            'withdrawal', 'cash_payment', 'bank_payment' => [
                $this->line($transaction->party_id ? '2101' : '5201', $amount, 0, 'طرف حساب پرداخت', partyId: $transaction->party_id),
                $this->line($bankOrCashFrom, 0, $amount, 'پرداخت خزانه'),
            ],
            default => [
                $this->line($bankOrCashTo, $amount, 0, 'انتقال ورودی خزانه'),
                $this->line($bankOrCashFrom, 0, $amount, 'انتقال خروجی خزانه'),
            ],
        };

        return $this->automatic(
            source: $transaction,
            type: $transaction->type === 'transfer' ? 'payment' : 'receipt',
            date: $transaction->transaction_date->toDateString(),
            description: 'تراکنش خزانه ' . $transaction->number,
            lines: $lines,
            userId: $userId ?? $transaction->created_by
        );
    }

    public function fromSalary(Salary $salary, ?int $userId = null): AccountingDocument
    {
        $salary->loadMissing('employee', 'payments');

        if ($salary->accounting_document_id) {
            return $salary->accountingDocument;
        }

        $gross = (float) ($salary->gross_salary ?: ($salary->base_salary + $salary->overtime_salary + $salary->bonus + $salary->benefits));
        $payable = (float) $salary->final_salary;
        $deductions = max(0, $gross - $payable);

        if ($gross <= 0 || $payable < 0) {
            throw new RuntimeException('مبلغ حقوق برای ثبت سند حسابداری معتبر نیست.');
        }

        $period = getPersianMonthName($salary->month) . ' ' . $salary->year;
        $employeeName = $salary->employee?->full_name ?: 'پرسنل #' . $salary->employee_id;

        $lines = [
            $this->line(
                $this->payrollAccountCode('salary_expense', '5202'),
                $gross,
                0,
                'هزینه حقوق و دستمزد ' . $employeeName . ' - ' . $period,
                projectId: $salary->employee?->default_project_id,
            ),
            $this->line(
                $this->payrollAccountCode('salary_payable', '2104'),
                0,
                $payable,
                'حقوق پرداختنی ' . $employeeName . ' - ' . $period,
                projectId: $salary->employee?->default_project_id,
            ),
        ];

        if ($deductions > 0) {
            $lines[] = $this->line(
                $this->payrollAccountCode('deduction_payable', '2104'),
                0,
                $deductions,
                'کسورات، مساعده و تعهدات حقوق ' . $employeeName . ' - ' . $period,
                projectId: $salary->employee?->default_project_id,
            );
        }

        $document = $this->automatic(
            source: $salary,
            type: 'manual',
            date: now()->toDateString(),
            description: 'ثبت خودکار حقوق و دستمزد ' . $employeeName . ' - ' . $period,
            lines: $lines,
            userId: $userId
        );

        $salary->update([
            'accounting_document_id' => $document->id,
            'posted_at' => now(),
        ]);

        return $document;
    }

    private function automatic(Model $source, string $type, string $date, string $description, array $lines, ?int $userId = null): AccountingDocument
    {
        return DB::transaction(function () use ($source, $type, $date, $description, $lines, $userId) {
            $document = $this->createManual([
                'document_date' => $date,
                'type' => $type,
                'status' => 'posted',
                'description' => $description,
            ], $lines, $userId);

            $document->update([
                'source_type' => $source::class,
                'source_id' => $source->id,
            ]);

            return $document;
        });
    }

    public function line(string $accountCode, float $debit, float $credit, ?string $description = null, ?int $partyId = null, ?int $projectId = null): array
    {
        $account = ChartAccount::where('code', $accountCode)->first()
            ?? throw new RuntimeException("حساب با کد {$accountCode} پیدا نشد.");

        return [
            'chart_account_id' => $account->id,
            'party_id' => $partyId,
            'project_id' => $projectId,
            'description' => $description,
            'debit' => $debit,
            'credit' => $credit,
        ];
    }

    private function payrollAccountCode(string $key, string $fallback): string
    {
        return PayrollAccountingSetting::where('key', $key)->where('is_active', true)->value('account_code') ?: $fallback;
    }

    private function replaceLines(AccountingDocument $document, array $lines): void
    {
        $document->lines()->delete();

        foreach ($lines as $line) {
            $document->lines()->create([
                'chart_account_id' => $line['chart_account_id'],
                'detail_account_id' => $line['detail_account_id'] ?? null,
                'party_id' => $line['party_id'] ?? null,
                'project_id' => $line['project_id'] ?? null,
                'cost_center' => $line['cost_center'] ?? null,
                'description' => $line['description'] ?? null,
                'debit' => (float) ($line['debit'] ?? 0),
                'credit' => (float) ($line['credit'] ?? 0),
                'currency' => $line['currency'] ?? $document->currency ?? 'IRR',
                'exchange_rate' => $line['exchange_rate'] ?? 1,
                'bank_account_id' => $line['bank_account_id'] ?? null,
                'cashbox_id' => $line['cashbox_id'] ?? null,
            ]);
        }
    }

    private function assertBalanced(array $lines): void
    {
        $debit = collect($lines)->sum(fn ($line) => (float) ($line['debit'] ?? 0));
        $credit = collect($lines)->sum(fn ($line) => (float) ($line['credit'] ?? 0));

        if ($debit <= 0 || $credit <= 0 || abs($debit - $credit) >= 0.01) {
            throw new RuntimeException('جمع بدهکار و بستانکار سند حسابداری باید برابر باشد.');
        }
    }

    private function audit(Model $model, string $event, ?array $old, ?array $new, ?int $userId): void
    {
        AccountingAudit::create([
            'auditable_type' => $model::class,
            'auditable_id' => $model->id,
            'event' => $event,
            'user_id' => $userId,
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => request()?->ip(),
        ]);
    }
}
