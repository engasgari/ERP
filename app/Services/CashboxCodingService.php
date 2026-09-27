<?php

namespace App\Services;

use App\Models\Cashbox;
use App\Models\ChartAccount;
use Illuminate\Support\Facades\DB;

class CashboxCodingService
{
    public function syncDetailAccount(Cashbox $cashbox): Cashbox
    {
        return DB::transaction(function () use ($cashbox) {
            $cashbox->loadMissing('account', 'detailAccount');

            $parent = $cashbox->account
                ?: ChartAccount::where('code', '1201')->first();

            if (! $parent) {
                return $cashbox;
            }

            $attributes = [
                'parent_id' => $parent->id,
                'level' => 'detail',
                'code' => $parent->code.'-'.$cashbox->code,
                'title' => trim((string) $cashbox->name) ?: $cashbox->code,
                'nature' => 'debit',
                'is_active' => (bool) $cashbox->is_active,
                'is_system' => true,
            ];

            $detail = $cashbox->detailAccount;

            if ($detail) {
                $detail->update($attributes);
            } else {
                $detail = ChartAccount::updateOrCreate(['code' => $attributes['code']], $attributes);
            }

            Cashbox::withoutEvents(fn () => $cashbox->forceFill([
                'chart_account_id' => $cashbox->chart_account_id ?: $parent->id,
                'detail_account_id' => $detail->id,
            ])->save());

            return $cashbox->refresh()->load('account', 'detailAccount');
        });
    }

    public function syncAllCashboxDetailAccounts(): int
    {
        $count = 0;

        Cashbox::withTrashed()
            ->orderBy('id')
            ->chunkById(100, function ($cashboxes) use (&$count): void {
                foreach ($cashboxes as $cashbox) {
                    $this->syncDetailAccount($cashbox);
                    $count++;
                }
            });

        return $count;
    }
}
