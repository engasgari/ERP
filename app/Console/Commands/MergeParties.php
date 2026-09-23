<?php

namespace App\Console\Commands;

use App\Models\AccountingDocumentLine;
use App\Models\Invoice;
use App\Models\Party;
use App\Models\PartyType;
use App\Models\PaymentVoucher;
use App\Models\Project;
use App\Models\ReceiptVoucher;
use App\Models\TreasuryTransaction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MergeParties extends Command
{
    protected $signature = 'parties:merge
        {--from= : شناسه طرف حساب مبدأ (حذف می‌شود)}
        {--into= : شناسه طرف حساب مقصد}
        {--dry-run : فقط گزارش بدون ذخیره}';

    protected $description = 'ادغام دو طرف حساب؛ انتقال همه ارجاعات به مقصد و حذف مبدأ';

    public function handle(): int
    {
        $fromId = (int) $this->option('from');
        $intoId = (int) $this->option('into');
        $dryRun = (bool) $this->option('dry-run');

        if ($fromId <= 0 || $intoId <= 0) {
            $this->error('شناسه مبدأ (--from) و مقصد (--into) الزامی است.');

            return self::FAILURE;
        }

        if ($fromId === $intoId) {
            $this->error('مبدأ و مقصد نمی‌توانند یکسان باشند.');

            return self::FAILURE;
        }

        $fromParty = Party::with('types')->find($fromId);
        $intoParty = Party::with('types')->find($intoId);

        if (! $fromParty || ! $intoParty) {
            $this->error('یکی از طرف حساب‌ها یافت نشد.');

            return self::FAILURE;
        }

        $counts = $this->referenceCounts($fromId);
        $this->info("ادغام «{$fromParty->name}» (#{$fromId}) → «{$intoParty->name}» (#{$intoId})");
        $this->line(sprintf(
            'فاکتور:%d | خط سند:%d | خزانه:%d | پرداخت:%d | دریافت:%d | پروژه:%d',
            $counts['invoices'],
            $counts['accounting_lines'],
            $counts['treasury'],
            $counts['payments'],
            $counts['receipts'],
            $counts['projects'],
        ));

        if ($dryRun) {
            $this->warn('dry-run فعال است؛ تغییری ذخیره نشد.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($fromParty, $intoParty, $fromId, $intoId): void {
            Invoice::where('party_id', $fromId)->update(['party_id' => $intoId]);
            AccountingDocumentLine::where('party_id', $fromId)->update(['party_id' => $intoId]);
            TreasuryTransaction::withTrashed()->where('party_id', $fromId)->update(['party_id' => $intoId]);
            PaymentVoucher::where('party_id', $fromId)->update(['party_id' => $intoId]);
            ReceiptVoucher::where('party_id', $fromId)->update(['party_id' => $intoId]);
            Project::where('party_id', $fromId)->update(['party_id' => $intoId]);

            $typeIds = collect([
                ...$intoParty->types->pluck('id')->all(),
                ...$fromParty->types->pluck('id')->all(),
            ])->filter()->unique()->values()->all();

            if ($typeIds !== []) {
                PartyType::ensureDefaults();
                $intoParty->types()->sync($typeIds);
            }

            $fromParty->delete();
        });

        $this->info('ادغام طرف حساب انجام شد.');

        return self::SUCCESS;
    }

    /**
     * @return array{invoices:int,accounting_lines:int,treasury:int,payments:int,receipts:int,projects:int}
     */
    private function referenceCounts(int $partyId): array
    {
        return [
            'invoices' => Invoice::where('party_id', $partyId)->count(),
            'accounting_lines' => AccountingDocumentLine::where('party_id', $partyId)->count(),
            'treasury' => TreasuryTransaction::withTrashed()->where('party_id', $partyId)->count(),
            'payments' => PaymentVoucher::where('party_id', $partyId)->count(),
            'receipts' => ReceiptVoucher::where('party_id', $partyId)->count(),
            'projects' => Project::where('party_id', $partyId)->count(),
        ];
    }
}
