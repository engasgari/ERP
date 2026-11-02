<?php

namespace App\Http\Controllers;

use App\Models\PayrollItem;

class PayrollSettingController extends Controller
{
    public function index()
    {
        $items = PayrollItem::query()
            ->orderBy('type')
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get()
            ->groupBy(function (PayrollItem $item): string {
                if ($item->calculation_type === 'reference') {
                    return 'reference';
                }

                return $item->type === 'earning' ? 'earning' : 'deduction';
            });

        return view('payroll-settings.items', [
            'itemGroups' => [
                'reference' => [
                    'label' => 'آیتم‌های مرجع / پایه',
                    'items' => $items->get('reference', collect()),
                ],
                'earning' => [
                    'label' => 'مزایا',
                    'items' => $items->get('earning', collect()),
                ],
                'deduction' => [
                    'label' => 'کسورات',
                    'items' => $items->get('deduction', collect()),
                ],
            ],
        ]);
    }
}
