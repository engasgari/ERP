<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SalesReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('reports.sales.view')
            || $this->user()?->hasPermission('reports.view')
            || $this->user()?->hasPermission('commerce.view')
            || false;
    }

    protected function prepareForValidation(): void
    {
        $this->replace(\App\Support\SalesReportFilters::normalize($this->all()));
    }

    public function rules(): array
    {
        return [
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'date_preset' => ['nullable', 'string', 'max:50'],
            'fiscal_year_id' => ['nullable', 'integer', 'exists:fiscal_years,id'],
            'party_id' => ['nullable', 'integer', 'exists:parties,id'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'item_id' => ['nullable', 'integer', 'exists:items,id'],
            'category' => ['nullable', 'string', 'max:255'],
            'created_by' => ['nullable', 'integer', 'exists:users,id'],
            'status' => ['nullable', 'in:draft,confirmed,cancelled'],
            'payment_status' => ['nullable', 'in:settled,unsettled'],
            'search' => ['nullable', 'string', 'max:255'],
            'sort' => ['nullable', 'string', 'max:100'],
            'direction' => ['nullable', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:200'],
            'report' => ['nullable', 'string', 'max:100'],
            'tab' => ['nullable', 'in:returns,discounts'],
            'group_by' => ['nullable', 'in:invoice,customer,product,category,salesperson,project'],
            'format' => ['nullable', 'in:html,pdf,excel,print'],
        ];
    }
}
