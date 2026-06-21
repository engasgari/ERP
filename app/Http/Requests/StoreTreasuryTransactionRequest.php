<?php

namespace App\Http\Requests;

use App\Rules\WithinActiveFiscalPeriod;
use App\Services\FiscalPeriodService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTreasuryTransactionRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $date = $this->input('transaction_date');

        $this->merge([
            'transaction_date' => jalaliToGregorianDate($date) ?: $date,
        ]);
    }

    public function authorize(): bool
    {
        return $this->user()?->hasPermission('treasury.manage')
            || $this->user()?->hasPermission('accounting.manage')
            || false;
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $fromType = $this->input('from_treasury_type');
            $fromId = $this->input('from_treasury_id');
            $toType = $this->input('to_treasury_type');
            $toId = $this->input('to_treasury_id');

            if (blank($fromType) && in_array($this->input('type'), ['withdrawal', 'cash_payment', 'bank_payment', 'transfer'], true)) {
                $validator->errors()->add('from_treasury_type', 'نوع حساب مبدا الزامی است.');
            }

            if (blank($toType) && in_array($this->input('type'), ['deposit', 'cash_receipt', 'bank_receipt', 'transfer'], true)) {
                $validator->errors()->add('to_treasury_type', 'نوع حساب مقصد الزامی است.');
            }

            if ($fromType === \App\Models\BankAccount::class && $fromId && ! \App\Models\BankAccount::whereKey($fromId)->exists()) {
                $validator->errors()->add('from_treasury_id', 'حساب بانکی مبدا معتبر نیست.');
            }

            if ($fromType === \App\Models\Cashbox::class && $fromId && ! \App\Models\Cashbox::whereKey($fromId)->exists()) {
                $validator->errors()->add('from_treasury_id', 'صندوق مبدا معتبر نیست.');
            }

            if ($toType === \App\Models\BankAccount::class && $toId && ! \App\Models\BankAccount::whereKey($toId)->exists()) {
                $validator->errors()->add('to_treasury_id', 'حساب بانکی مقصد معتبر نیست.');
            }

            if ($toType === \App\Models\Cashbox::class && $toId && ! \App\Models\Cashbox::whereKey($toId)->exists()) {
                $validator->errors()->add('to_treasury_id', 'صندوق مقصد معتبر نیست.');
            }
        });
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['deposit', 'withdrawal', 'transfer', 'cash_receipt', 'cash_payment', 'bank_receipt', 'bank_payment'])],
            'transaction_date' => ['required', 'date', new WithinActiveFiscalPeriod(app(FiscalPeriodService::class))],
            'amount' => ['required', 'numeric', 'gt:0'],
            'currency' => ['nullable', 'string', 'max:10'],
            'from_treasury_type' => ['nullable', Rule::in([\App\Models\BankAccount::class, \App\Models\Cashbox::class]), 'required_if:type,withdrawal,cash_payment,bank_payment,transfer'],
            'from_treasury_id' => ['nullable', 'integer', 'required_if:type,withdrawal,cash_payment,bank_payment,transfer'],
            'to_treasury_type' => ['nullable', Rule::in([\App\Models\BankAccount::class, \App\Models\Cashbox::class]), 'required_if:type,deposit,cash_receipt,bank_receipt,transfer'],
            'to_treasury_id' => ['nullable', 'integer', 'required_if:type,deposit,cash_receipt,bank_receipt,transfer'],
            'party_id' => ['nullable', 'exists:parties,id'],
            'project_id' => ['nullable', 'exists:projects,id'],
            'expense_account_id' => ['nullable', 'exists:chart_accounts,id'],
            'income_account_id' => ['nullable', 'exists:chart_accounts,id'],
            'description' => ['nullable', 'string'],
        ];
    }
}
