<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\FinancialTransaction;
use App\Models\Payment;
use App\Models\Project;
use App\Models\Salary;
use App\Models\WorkLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $cards = collect();
        $shortcuts = collect();

        if ($user->hasPermission('financial.view')) {
            $income = (float) FinancialTransaction::where('type', 'income')->sum('amount');
            $expense = (float) FinancialTransaction::where('type', 'expense')->sum('amount');
            $cards = $cards->merge([
                $this->moneyCard('درآمد ثبت شده', $income, 'مالی'),
                $this->moneyCard('هزینه ثبت شده', $expense, 'مالی'),
                $this->moneyCard('سود / زیان', $income - $expense, 'مالی'),
                $this->numberCard('تعداد اسناد مالی', FinancialTransaction::count(), 'مالی'),
            ]);
            $shortcuts = $shortcuts->merge([
                $this->shortcut('لیست اسناد مالی', 'financial-transactions.index'),
                $this->shortcut('ثبت سند دریافت', 'financial-transactions.create', ['type' => 'income'], 'financial.manage'),
                $this->shortcut('ثبت سند پرداخت', 'financial-transactions.create', ['type' => 'expense'], 'financial.manage'),
                $this->shortcut('گزارش مالی پروژه', 'projects.index'),
            ]);
        }

        if ($user->hasPermission('salaries.view')) {
            $salaryQuery = Salary::query();
            $paymentQuery = Payment::query();
            if (!$user->isAdmin()) {
                $employeeIds = $user->accessibleEmployeeIds('salaries.view');
                $salaryQuery->whereIn('employee_id', $employeeIds);
                $paymentQuery->whereIn('employee_id', $employeeIds);
            }

            $finalSalary = (float) (clone $salaryQuery)->sum('final_salary');
            $paid = (float) (clone $paymentQuery)->sum('amount');
            $cards = $cards->merge([
                $this->numberCard('فیش‌های حقوقی', (clone $salaryQuery)->count(), 'حقوق'),
                $this->moneyCard('حقوق نهایی', $finalSalary, 'حقوق'),
                $this->moneyCard('پرداخت شده', $paid, 'حقوق'),
                $this->moneyCard('مانده پرداخت', $finalSalary - $paid, 'حقوق'),
            ]);
            $shortcuts = $shortcuts->merge([
                $this->shortcut('لیست حقوق', 'salaries.index'),
                $this->shortcut('محاسبه حقوق جدید', 'salaries.create', [], 'salaries.manage'),
                $this->shortcut('گزارش مالی پرسنل', 'salaries.financial-report'),
                $this->shortcut('حذف گروهی حقوق', 'salaries.bulk-delete', [], 'salaries.manage'),
            ]);
        }

        if ($user->hasPermission('worklogs.view')) {
            $workLogQuery = WorkLog::query();
            if (!$user->isAdmin()) {
                $workLogQuery->whereIn('employee_id', $user->accessibleEmployeeIds('worklogs.view'));
            }

            $cards = $cards->merge([
                $this->numberCard('تعداد کارکردها', (clone $workLogQuery)->count(), 'کارکرد'),
                $this->numberCard('ساعت کار', (float) (clone $workLogQuery)->sum('hours'), 'کارکرد'),
                $this->moneyCard('مبلغ کارکرد', (float) (clone $workLogQuery)->sum('total_amount'), 'کارکرد'),
                $this->numberCard('پرسنل دارای کارکرد', (clone $workLogQuery)->distinct('employee_id')->count('employee_id'), 'کارکرد'),
            ]);
            $shortcuts = $shortcuts->merge([
                $this->shortcut('لیست کارکرد', 'work-logs.index'),
                $this->shortcut('ثبت کارکرد جدید', 'work-logs.create', [], 'worklogs.manage'),
                $this->shortcut('ورود اکسل کارکرد', 'worklog.import.view', [], 'worklogs.manage'),
                $this->shortcut('خروجی اکسل کارکرد', 'worklog.export', [], 'worklogs.manage'),
            ]);
        }

        if ($user->hasPermission('warehouse.view')) {
            $warehouseIn = (float) DB::table('inventory_document_lines as l')
                ->join('inventory_documents as d', 'd.id', '=', 'l.inventory_document_id')
                ->where('d.status', 'confirmed')
                ->where('d.type', 'receipt')
                ->sum('l.line_total');
            $warehouseOut = (float) DB::table('inventory_document_lines as l')
                ->join('inventory_documents as d', 'd.id', '=', 'l.inventory_document_id')
                ->where('d.status', 'confirmed')
                ->whereIn('d.type', ['issue', 'consumption'])
                ->sum('l.line_total');
            $warehouseDocuments = (int) DB::table('inventory_documents')->count();
            $cards = $cards->merge([
                $this->moneyCard('ورودی انبار', $warehouseIn, 'انبار'),
                $this->moneyCard('خروجی انبار', $warehouseOut, 'انبار'),
                $this->moneyCard('ارزش مانده انبار', $warehouseIn - $warehouseOut, 'انبار'),
                $this->numberCard('اسناد انبار', $warehouseDocuments, 'انبار'),
            ]);
            $shortcuts = $shortcuts->merge([
                $this->shortcut('اسناد انبار', 'inventory-documents.index'),
                $this->shortcut('ثبت سند انبار', 'inventory-documents.create', ['type' => 'receipt'], 'warehouse.manage'),
                $this->shortcut('انبارها', 'warehouses.index'),
                $this->shortcut('گزارش موجودی انبار', 'management-reports.warehouse-inventory', [], 'reports.view'),
            ]);
        }

        if ($user->hasPermission('projects.view')) {
            $cards = $cards->merge([
                $this->numberCard('پروژه‌ها', Project::count(), 'پروژه'),
                $this->numberCard('پروژه‌های فعال', Project::where('status', 'active')->count(), 'پروژه'),
            ]);
            $shortcuts = $shortcuts->merge([
                $this->shortcut('لیست پروژه‌ها', 'projects.index'),
                $this->shortcut('پروژه جدید', 'projects.create', [], 'projects.manage'),
            ]);
        }

        if ($user->hasPermission('employees.view')) {
            $cards = $cards->merge([
                $this->numberCard('پرسنل', Employee::count(), 'پرسنل'),
                $this->numberCard('پرسنل فعال', Employee::where('is_active', true)->count(), 'پرسنل'),
            ]);
            $shortcuts = $shortcuts->merge([
                $this->shortcut('لیست پرسنل', 'employees.index'),
                $this->shortcut('پرسنل جدید', 'employees.create', [], 'employees.manage'),
            ]);
        }

        if ($user->hasPermission('reports.view')) {
            $shortcuts = $shortcuts->merge([
                $this->shortcut('مرکز گزارش‌ها', 'management-reports.index'),
                $this->shortcut('تراز آزمایشی', 'management-reports.trial-balance'),
                $this->shortcut('کاردکس انبار', 'management-reports.warehouse-cardex'),
            ]);
        }

        if ($user->hasPermission('users.manage')) {
            $shortcuts = $shortcuts->merge([
                $this->shortcut('مدیریت کاربران', 'access.users.index'),
                $this->shortcut('کاربر جدید', 'access.users.create'),
                $this->shortcut('مدیریت نقش‌ها', 'access.roles.index'),
            ]);
        }

        return view('dashboard', [
            'cards' => $cards->take(4)->values(),
            'shortcuts' => $shortcuts
                ->filter(fn ($shortcut) => !$shortcut['permission'] || $user->hasPermission($shortcut['permission']))
                ->unique(fn ($shortcut) => $shortcut['route'] . serialize($shortcut['params']))
                ->map(function ($shortcut) {
                    $shortcut['id'] = md5($shortcut['route'] . serialize($shortcut['params']));
                    $shortcut['url'] = route($shortcut['route'], $shortcut['params']);
                    $shortcut['iconSvg'] = $this->shortcutIconSvg($shortcut['route'], $shortcut['label']);

                    return $shortcut;
                })
                ->values(),
            'shortcutStorageKey' => 'erp.dashboard.shortcuts.' . $user->id,
        ]);
    }

    private function moneyCard(string $title, float $value, string $group): array
    {
        return [
            'title' => $title,
            'value' => number_format($value) . ' ریال',
            'group' => $group,
        ];
    }

    private function numberCard(string $title, float|int $value, string $group): array
    {
        return [
            'title' => $title,
            'value' => number_format($value),
            'group' => $group,
        ];
    }

    private function shortcut(string $label, string $route, array $params = [], ?string $permission = null): array
    {
        return compact('label', 'route', 'params', 'permission');
    }

    private function shortcutIconSvg(string $route, string $label): string
    {
        $icon = match (true) {
            str_contains($route, 'financial') || str_contains($label, 'سند') => 'money',
            str_contains($route, 'salaries') => 'wallet',
            str_contains($route, 'work-logs') || str_contains($route, 'worklog') => 'clock',
            str_contains($route, 'warehouse') || str_contains($route, 'warehouses') => 'box',
            str_contains($route, 'projects') => 'folder',
            str_contains($route, 'employees') => 'users',
            str_contains($route, 'management-reports') || str_contains($label, 'گزارش') => 'chart',
            str_contains($route, 'access') => 'shield',
            str_contains($label, 'جدید') || str_contains($label, 'ثبت') => 'plus',
            default => 'link',
        };

        $paths = [
            'money' => '<path d="M4 7h16v10H4z"/><path d="M8 11h.01M16 13h.01"/><path d="M12 10a2 2 0 1 0 0 4 2 2 0 0 0 0-4z"/>',
            'wallet' => '<path d="M4 7h14a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H4z"/><path d="M4 7V5a2 2 0 0 1 2-2h12v4"/><path d="M16 13h.01"/>',
            'clock' => '<circle cx="12" cy="12" r="8"/><path d="M12 8v5l3 2"/>',
            'box' => '<path d="M4 8l8-4 8 4-8 4z"/><path d="M4 8v8l8 4 8-4V8"/><path d="M12 12v8"/>',
            'folder' => '<path d="M3 6h7l2 2h9v11H3z"/><path d="M3 10h18"/>',
            'users' => '<circle cx="9" cy="8" r="3"/><path d="M3 19a6 6 0 0 1 12 0"/><circle cx="17" cy="9" r="2"/><path d="M15 16a4 4 0 0 1 6 3"/>',
            'chart' => '<path d="M4 19V5"/><path d="M4 19h17"/><path d="M8 16v-5"/><path d="M13 16V8"/><path d="M18 16v-9"/>',
            'shield' => '<path d="M12 3l7 3v5c0 5-3 8-7 10-4-2-7-5-7-10V6z"/><path d="M9 12l2 2 4-5"/>',
            'plus' => '<path d="M12 5v14"/><path d="M5 12h14"/>',
            'link' => '<path d="M10 13a5 5 0 0 0 7 0l2-2a5 5 0 0 0-7-7l-1 1"/><path d="M14 11a5 5 0 0 0-7 0l-2 2a5 5 0 0 0 7 7l1-1"/>',
        ];

        return '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $paths[$icon] . '</svg>';
    }
}
