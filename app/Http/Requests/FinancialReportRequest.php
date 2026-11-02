<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class FinancialReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('financial.reports.view')
            || $this->user()?->hasPermission('reports.view')
            || $this->user()?->hasPermission('financial.manage')
            || false;
    }

    protected function prepareForValidation(): void
    {
        $filters = $this->all();

        foreach (['date_from', 'date_to'] as $key) {
            if (! empty($filters[$key])) {
                $converted = jalaliToGregorianDate($filters[$key]);
                if ($converted) {
                    $filters[$key] = $converted;
                } else {
                    // Drop unparseable dates so GET filter pages do not redirect-loop.
                    unset($filters[$key]);
                }
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

        if (! empty($filters['cashbox_id'])) {
            $filters['cashbox_id'] = (int) $filters['cashbox_id'];
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
            'cashbox_id' => ['nullable', 'integer', 'exists:cashboxes,id'],
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

    /**
     * GET report filters must not use redirect()->back() with the same invalid
     * query string — that creates ERR_TOO_MANY_REDIRECTS.
     */
    protected function failedValidation(Validator $validator): void
    {
        if (! $this->isMethod('GET')) {
            parent::failedValidation($validator);

            return;
        }

        $route = $this->route();
        $name = $route?->getName();
        $parameters = [];

        foreach ($route?->parameters() ?? [] as $key => $value) {
            $parameters[$key] = is_object($value) && method_exists($value, 'getRouteKey')
                ? $value->getRouteKey()
                : $value;
        }

        $target = ($name && $parameters !== [])
            ? route($name, $parameters)
            : ($name ? route($name) : $this->path());

        throw new HttpResponseException(
            redirect()
                ->to($target)
                ->withErrors($validator)
                ->with('error', 'فیلتر گزارش نامعتبر بود و پاک شد.')
        );
    }
}
