<?php

namespace App\Http\Requests;

use App\Rules\WithinActiveFiscalPeriod;
use App\Services\FiscalPeriodService;
use Illuminate\Foundation\Http\FormRequest;

class StoreAccountingDocumentRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $documentDate = $this->input('document_date');
        $lines = collect($this->input('lines', []))
            ->filter(fn ($line) => !empty($line['chart_account_id']) || (float) ($line['debit'] ?? 0) > 0 || (float) ($line['credit'] ?? 0) > 0)
            ->values()
            ->all();

        $this->merge([
            'document_date' => jalaliToGregorianDate($documentDate) ?: $documentDate,
            'lines' => $lines,
        ]);
    }

    public function authorize(): bool
    {
        return $this->user()?->hasPermission('accounting.documents.create')
            || $this->user()?->hasPermission('accounting.manage')
            || false;
    }

    public function rules(): array
    {
        return [
            'number' => ['nullable', 'string', 'max:255'],
            'document_date' => ['required', 'date', new WithinActiveFiscalPeriod(app(FiscalPeriodService::class))],
            'description' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'status' => ['required', 'in:draft,posted'],
            'currency' => ['nullable', 'string', 'max:10'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.chart_account_id' => ['required', 'exists:chart_accounts,id'],
            'lines.*.detail_account_id' => ['nullable', 'exists:chart_accounts,id'],
            'lines.*.party_id' => ['nullable', 'exists:parties,id'],
            'lines.*.project_id' => ['nullable', 'exists:projects,id'],
            'lines.*.cost_center' => ['nullable', 'string', 'max:255'],
            'lines.*.description' => ['nullable', 'string'],
            'lines.*.debit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.credit' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
