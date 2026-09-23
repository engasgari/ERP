<?php

namespace App\Http\Requests;

use App\Rules\WithinActiveFiscalPeriod;
use App\Services\FiscalPeriodService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePartnerCurrentAccountTransferRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $date = $this->input('transaction_date');

        $this->merge([
            'transaction_date' => jalaliToGregorianDate($date) ?: $date,
            'amount' => normalizeMoneyValue($this->input('amount')),
        ]);
    }

    public function authorize(): bool
    {
        return $this->user()?->hasPermission('treasury.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'party_id' => ['required', 'exists:parties,id'],
            'bank_account_id' => ['required', 'exists:bank_accounts,id'],
            'direction' => ['required', Rule::in(['deposit', 'withdraw'])],
            'transaction_date' => ['required', 'date', new WithinActiveFiscalPeriod(app(FiscalPeriodService::class))],
            'amount' => ['required', 'numeric', 'gt:0'],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function attributes(): array
    {
        return [
            'party_id' => 'شریک/سهامدار',
            'bank_account_id' => 'حساب بانکی',
            'direction' => 'نوع انتقال',
            'transaction_date' => 'تاریخ',
            'amount' => 'مبلغ',
            'description' => 'شرح',
        ];
    }
}
