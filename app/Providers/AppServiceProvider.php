<?php

namespace App\Providers;

use App\Models\Permission;
use App\Policies\FinancialReportPolicy;
use App\Services\Ai\Contracts\AiGateway;
use App\Services\Ai\Gateways\FakeAiGateway;
use App\Services\Ai\Gateways\NullAiGateway;
use App\Services\Ai\Gateways\OpenAiCompatibleGateway;
use App\Services\Ai\WebSearch\Contracts\WebSearchProvider;
use App\Services\Ai\WebSearch\NullWebSearchProvider;
use App\Services\NavigationBreadcrumbService;
use App\Support\FinancialReportContext;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(AiGateway::class, fn () => match (config('crm_ai.driver')) {
            'fake' => new FakeAiGateway,
            'openai' => OpenAiCompatibleGateway::fromConfig(),
            default => new NullAiGateway,
        });

        $this->app->singleton(WebSearchProvider::class, fn () => match (config('crm_ai.web_search.driver')) {
            default => new NullWebSearchProvider,
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        App::setLocale('fa');

        Gate::before(fn ($user) => $user->isAdmin() ? true : null);
        Gate::policy(FinancialReportContext::class, FinancialReportPolicy::class);

        foreach ($this->permissionKeys() as $permissionKey) {
            Gate::define($permissionKey, fn ($user) => $user->hasPermission($permissionKey));
        }

        View::composer('layouts.app', function ($view): void {
            if (($view->getData()['hideBreadcrumb'] ?? false) === true) {
                return;
            }

            if (! empty($view->getData()['breadcrumbTrail'])) {
                return;
            }

            $service = app(NavigationBreadcrumbService::class);
            $view->with('breadcrumbTrail', $service->trailForRequest(request()));
            $view->with('breadcrumbBack', $service->backLink(request()));
        });
    }

    private function permissionKeys(): array
    {
        try {
            if (class_exists(Permission::class) && \Illuminate\Support\Facades\Schema::hasTable('permissions')) {
                return Permission::pluck('key')->all();
            }
        } catch (\Throwable) {
            //
        }

        return [
            'users.manage',
            'base-info.view',
            'base-info.manage',
            'commerce.view',
            'commerce.manage',
            'commerce.invoices.create',
            'commerce.invoices.update',
            'commerce.invoices.delete',
            'commerce.invoices.cancel',
            'commerce.invoices.confirm',
            'commerce.invoices.settle',
            'commerce.invoices.convert',
            'commerce.pricing.manage',
            'accounting.view',
            'accounting.manage',
            'inventory.view',
            'inventory.manage',
            'inventory.documents.create',
            'inventory.documents.update',
            'inventory.documents.delete',
            'inventory.documents.confirm',
            'settings.manage',
            'fiscal-years.manage',
            'projects.view',
            'projects.manage',
            'projects.create',
            'projects.update',
            'projects.delete',
            'employees.view',
            'employees.manage',
            'employees.create',
            'employees.update',
            'employees.delete',
            'hr.view',
            'hr.manage',
            'hr.organization.manage',
            'hr.documents.manage',
            'employment-orders.view',
            'employment-orders.manage',
            'employment-orders.create',
            'employment-orders.update',
            'employment-orders.delete',
            'employment-orders.approve',
            'contracts.view',
            'contracts.manage',
            'contracts.create',
            'contracts.update',
            'contracts.delete',
            'attendance.view',
            'attendance.manage',
            'attendance.import',
            'attendance.calculate',
            'attendance.daily.edit',
            'attendance.leaves.create',
            'attendance.leaves.approve',
            'attendance.missions.create',
            'attendance.missions.approve',
            'attendance.summaries.rebuild',
            'payroll.view',
            'payroll.manage',
            'payroll.periods.create',
            'payroll.calculate',
            'payroll.approve',
            'payroll.post',
            'payroll.periods.delete',
            'payroll.periods.close',
            'payroll.adjust',
            'payroll.settings.manage',
            'salary-payments.view',
            'salary-payments.manage',
            'worklogs.view',
            'worklogs.manage',
            'worklogs.create',
            'worklogs.update',
            'worklogs.delete',
            'worklogs.setup.manage',
            'salaries.view',
            'salaries.manage',
            'insurance.view',
            'insurance.manage',
            'insurance.payment',
            'warehouse.view',
            'warehouse.manage',
            'warehouse.create',
            'warehouse.update',
            'warehouse.delete',
            'treasury.view',
            'treasury.manage',
            'treasury.create',
            'treasury.update',
            'treasury.delete',
            'treasury.partners.manage',
            'financial.view',
            'financial.manage',
            'reports.view',
            'financial.reports.view',
            'financial.reports.export',
        ];
    }
}
