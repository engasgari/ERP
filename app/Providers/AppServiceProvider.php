<?php

namespace App\Providers;

use App\Models\Permission;
use App\Policies\FinancialReportPolicy;
use App\Support\FinancialReportContext;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::before(fn ($user) => $user->isAdmin() ? true : null);
        Gate::policy(FinancialReportContext::class, FinancialReportPolicy::class);

        foreach ($this->permissionKeys() as $permissionKey) {
            Gate::define($permissionKey, fn ($user) => $user->hasPermission($permissionKey));
        }
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
            'accounting.view',
            'accounting.manage',
            'inventory.view',
            'inventory.manage',
            'settings.manage',
            'fiscal-years.manage',
            'projects.view',
            'projects.manage',
            'employees.view',
            'employees.manage',
            'hr.view',
            'hr.manage',
            'employment-orders.view',
            'employment-orders.manage',
            'employment-orders.approve',
            'contracts.view',
            'contracts.manage',
            'attendance.view',
            'attendance.manage',
            'attendance.import',
            'payroll.view',
            'payroll.manage',
            'payroll.approve',
            'payroll.post',
            'salary-payments.view',
            'salary-payments.manage',
            'worklogs.view',
            'worklogs.manage',
            'salaries.view',
            'salaries.manage',
            'warehouse.view',
            'warehouse.manage',
            'financial.view',
            'financial.manage',
            'reports.view',
            'financial.reports.view',
            'financial.reports.export',
        ];
    }
}
