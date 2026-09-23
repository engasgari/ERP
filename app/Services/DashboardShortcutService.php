<?php

namespace App\Services;

use App\Models\User;

class DashboardShortcutService
{
    /**
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    public function forUser(User $user): \Illuminate\Support\Collection
    {
        $shortcuts = collect();

        if ($user->hasPermission('financial.view')) {
            $shortcuts = $shortcuts->merge([
                $this->shortcut('لیست اسناد مالی', 'financial-transactions.index'),
                $this->shortcut('ثبت سند دریافت', 'financial-transactions.create', ['type' => 'income'], 'financial.manage'),
                $this->shortcut('ثبت سند پرداخت', 'financial-transactions.create', ['type' => 'expense'], 'financial.manage'),
                $this->shortcut('گزارش مالی پروژه', 'projects.index'),
            ]);
        }

        if ($user->hasPermission('reports.view')) {
            $shortcuts = $shortcuts->merge([
                $this->shortcut('مرکز گزارش‌ها', 'management-reports.index'),
                $this->shortcut('تراز آزمایشی', 'management-reports.trial-balance'),
            ]);
        }

        if ($user->hasPermission('reports.sales.view')) {
            $shortcuts = $shortcuts->merge([
                $this->shortcut('داشبورد فروش', 'sales.intelligence'),
            ]);
        }

        if ($user->hasPermission('financial.reports.view')) {
            $shortcuts = $shortcuts->merge([
                $this->shortcut('گزارش‌های مالی', 'financial-reports.index'),
            ]);
        }

        if ($user->hasPermission('commerce.view')) {
            $shortcuts = $shortcuts->merge([
                $this->shortcut('فاکتورها', 'invoices.index'),
            ]);
        }

        if ($user->hasPermission('projects.view')) {
            $shortcuts = $shortcuts->merge([
                $this->shortcut('لیست پروژه‌ها', 'projects.index'),
            ]);
        }

        if ($user->hasPermission('users.manage')) {
            $shortcuts = $shortcuts->merge([
                $this->shortcut('مدیریت کاربران', 'access.users.index'),
            ]);
        }

        return $shortcuts
            ->filter(fn ($shortcut) => ! $shortcut['permission'] || $user->hasPermission($shortcut['permission']))
            ->unique(fn ($shortcut) => $shortcut['route'].serialize($shortcut['params']))
            ->map(function ($shortcut) {
                $shortcut['id'] = md5($shortcut['route'].serialize($shortcut['params']));
                $shortcut['url'] = route($shortcut['route'], $shortcut['params']);

                return $shortcut;
            })
            ->values();
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function shortcut(string $label, string $route, array $params = [], ?string $permission = null): array
    {
        return compact('label', 'route', 'params', 'permission');
    }
}
