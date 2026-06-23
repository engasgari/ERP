<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FinancialReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('financial.reports.view')
            || $this->user()?->hasPermission('reports.view')
            || false;
    }

    protected function prepareForValidation(): void
    {
        $filters = $this->all();

        foreach (['date_from', 'date_to'] as $key) {
            if (! empty($filters[$key])) {
                $filters[$key] = jalaliToGregorianDate($filters[$key]) ?: $filters[$key];
            }
        }

        if (! empty($filters['fiscal_year_id'])) {
            $filters['fiscal_year_id'] = (int) $filters['fiscal_year_id'];
        }

        if (! empty($filters['branch_id'])) {
            $filters['branch_id'] = (int) $filters['branch_id'];
        }

        if (! empty($filters['company_id'])) {
            $filters['company_id'] = (int) $filters['company_id'];
        }

        if (! empty($filters['project_id'])) {
            $filters['project_id'] = (int) $filters['project_id'];
        }

        if (! empty($filters['party_id'])) {
            $filters['party_id'] = (int) $filters['party_id'];
        }

        if (! empty($filters['account_id'])) {
            $filters['account_id'] = (int) $filters['account_id'];
        }

        if (! empty($filters['bank_account_id'])) {
            $filters['bank_account_id'] = (int) $filters['bank_account_id'];
        }

        if (! empty($filters['cost_center'])) {
            $filters['cost_center'] = trim((string) $filters['cost_center']);
        }

        if (! empty($filters['comparison_scope'])) {
            $filters['comparison_scope'] = trim((string) $filters['comparison_scope']);
        }

        $this->replace($filters);
    }

    public function rules(): array
    {
        return [
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'fiscal_year_id' => ['nullable', 'integer', 'exists:fiscal_years,id'],
            'company_id' => ['nullable', 'integer'],
            'branch_id' => ['nullable', 'integer'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'party_id' => ['nullable', 'integer', 'exists:parties,id'],
            'account_id' => ['nullable', 'integer', 'exists:chart_accounts,id'],
            'bank_account_id' => ['nullable', 'integer', 'exists:bank_accounts,id'],
            'cost_center' => ['nullable', 'string', 'max:255'],
            'comparison_scope' => ['nullable', 'in:monthly,quarterly,annual'],
            'search' => ['nullable', 'string', 'max:255'],
            'sort' => ['nullable', 'string', 'max:100'],
            'direction' => ['nullable', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:200'],
            'report' => ['nullable', 'string', 'max:100'],
            'format' => ['nullable', 'in:html,pdf,excel,print'],
        ];
    }
}
