<?php

namespace Database\Seeders;

use App\Models\ChartAccount;
use App\Models\Party;
use App\Models\PartyType;
use Illuminate\Database\Seeder;

class PartnerShareholdersSeeder extends Seeder
{
    /** @var list<array{party_code: string, name: string, chart_code: string, chart_title: string, withdraw_title: string, deposit_title: string}> */
    private array $partners = [
        [
            'party_code' => 'PT-1404-MA',
            'name' => 'مهدی عسگری',
            'chart_code' => '3201',
            'chart_title' => 'حساب جاری مهدی عسگری',
            'withdraw_title' => 'برداشت‌های مهدی عسگری',
            'deposit_title' => 'واریزهای مهدی عسگری',
        ],
        [
            'party_code' => 'PT-1404-MR',
            'name' => 'میلاد رحیمی',
            'chart_code' => '3202',
            'chart_title' => 'حساب جاری میلاد رحیمی',
            'withdraw_title' => 'برداشت‌های میلاد رحیمی',
            'deposit_title' => 'واریزهای میلاد رحیمی',
        ],
    ];

    public function run(): void
    {
        PartyType::ensureDefaults();
        $shareholderType = PartyType::shareholder();

        if (! $shareholderType) {
            $this->command?->error('نوع سهامدار/شریک در سیستم یافت نشد.');

            return;
        }

        $this->seedChartAccounts();

        foreach ($this->partners as $partner) {
            $this->seedPartnerParty($partner, $shareholderType);
        }

        $this->command?->info('حساب‌های جاری شرکا و اشخاص مهدی عسگری و میلاد رحیمی آماده شد.');
    }

    private function seedChartAccounts(): void
    {
        $this->upsertAccount('3', 'حقوق مالکانه', 'group', 'credit', null);
        $this->upsertAccount('32', 'حساب‌های جاری شرکا', 'ledger', 'credit', '3');

        foreach ($this->partners as $partner) {
            $subsidiary = $this->upsertAccount(
                $partner['chart_code'],
                $partner['chart_title'],
                'subsidiary',
                'credit',
                '32'
            );

            $this->upsertAccount(
                $partner['chart_code'] . '01',
                $partner['withdraw_title'],
                'detail',
                'credit',
                $partner['chart_code']
            );

            $this->upsertAccount(
                $partner['chart_code'] . '02',
                $partner['deposit_title'],
                'detail',
                'credit',
                $partner['chart_code']
            );
        }
    }

    private function upsertAccount(
        string $code,
        string $title,
        string $level,
        string $nature,
        ?string $parentCode
    ): ChartAccount {
        return ChartAccount::updateOrCreate(
            ['code' => $code],
            [
                'parent_id' => $parentCode ? ChartAccount::where('code', $parentCode)->value('id') : null,
                'level' => $level,
                'title' => $title,
                'nature' => $nature,
                'is_active' => true,
                'is_system' => true,
            ]
        );
    }

    private function seedPartnerParty(array $partner, PartyType $shareholderType): void
    {
        $party = Party::query()
            ->where('code', $partner['party_code'])
            ->orWhere('name', $partner['name'])
            ->first();

        if (! $party) {
            $party = Party::create([
                'code' => $partner['party_code'],
                'detail_code' => $partner['chart_code'],
                'kind' => 'person',
                'name' => $partner['name'],
                'is_active' => true,
            ]);
        } else {
            $party->update([
                'name' => $partner['name'],
                'detail_code' => $partner['chart_code'],
                'is_active' => true,
            ]);
        }

        $party->types()->syncWithoutDetaching([$shareholderType->id]);
    }
}
