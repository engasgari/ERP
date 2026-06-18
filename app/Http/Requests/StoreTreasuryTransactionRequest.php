<?php

namespace App\Http\Requests;

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

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['deposit', 'withdrawal', 'transfer', 'cash_receipt', 'cash_payment', 'bank_receipt', 'bank_payment'])],
            'transaction_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'currency' => ['nullable', 'string', 'max:10'],
            'from_treasury_type' => ['nullable', Rule::in([\App\Models\BankAccount::class, \App\Models\Cashbox::class])],
            'from_treasury_id' => ['nullable', 'integer'],
            'to_treasury_type' => ['nullable', Rule::in([\App\Models\BankAccount::class, \App\Models\Cashbox::class])],
            'to_treasury_id' => ['nullable', 'integer'],
            'party_id' => ['nullable', 'exists:parties,id'],
            'project_id' => ['nullable', 'exists:projects,id'],
            'expense_account_id' => ['nullable', 'exists:chart_accounts,id'],
            'income_account_id' => ['nullable', 'exists:chart_accounts,id'],
            'description' => ['nullable', 'string'],
        ];
    }
}
