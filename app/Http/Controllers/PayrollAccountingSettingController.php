<?php

namespace App\Http\Controllers;

use App\Models\ChartAccount;
use App\Models\PayrollAccountingSetting;
use Illuminate\Http\Request;

class PayrollAccountingSettingController extends Controller
{
    public function index()
    {
        return view('payroll-settings.accounting', [
            'settings' => PayrollAccountingSetting::with('account')->orderBy('id')->get(),
            'accounts' => ChartAccount::where('is_active', true)->orderBy('code')->get(),
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'settings' => 'required|array',
            'settings.*.chart_account_id' => 'required|exists:chart_accounts,id',
        ]);

        foreach ($validated['settings'] as $id => $setting) {
            $account = ChartAccount::findOrFail($setting['chart_account_id']);
            PayrollAccountingSetting::whereKey($id)->update([
                'chart_account_id' => $account->id,
                'account_code' => $account->code,
            ]);
        }

        return redirect()->route('payroll.accounting-settings.index')->with('success', 'تنظیمات حسابداری حقوق ذخیره شد.');
    }
}
