<?php

namespace App\Http\Controllers;

use App\Models\ChartAccount;
use Illuminate\Http\Request;
use Throwable;

class ChartAccountController extends Controller
{
    public function index(Request $request)
    {
        return view('chart-accounts.index');
    }

    public function create()
    {
        return view('chart-accounts.create', [
            'account' => new ChartAccount(['level' => 'detail', 'nature' => 'neutral']),
            'parents' => ChartAccount::orderBy('code')->get(),
        ]);
    }

    public function store(Request $request)
    {
        ChartAccount::create($this->validated($request) + ['is_active' => true]);

        return redirect()->route('chart-accounts.index')->with('success', 'سرفصل مالی ثبت شد.');
    }

    public function edit(ChartAccount $chartAccount)
    {
        return view('chart-accounts.create', [
            'account' => $chartAccount,
            'parents' => ChartAccount::where('id', '!=', $chartAccount->id)->orderBy('code')->get(),
        ]);
    }

    public function update(Request $request, ChartAccount $chartAccount)
    {
        $chartAccount->update($this->validated($request, $chartAccount));

        return redirect()->route('chart-accounts.index')->with('success', 'سرفصل مالی ویرایش شد.');
    }

    public function destroy(ChartAccount $chartAccount)
    {
        if ($chartAccount->is_system || $chartAccount->children()->exists() || $chartAccount->lines()->exists()) {
            $children = $chartAccount->children()->limit(5)->pluck('code')->implode('، ');
            $details = collect([
                $chartAccount->is_system ? 'این حساب سیستمی است.' : null,
                $chartAccount->children()->exists() ? 'زیرمجموعه‌ها: ' . $chartAccount->children()->count() . ($children ? ' - نمونه کدها: ' . $children : '') : null,
                $chartAccount->lines()->exists() ? 'ردیف‌های سند حسابداری: ' . $chartAccount->lines()->count() : null,
            ])->filter()->values()->all();

            return redirect()->route('chart-accounts.index')
                ->with('error', 'این سرفصل قابل حذف نیست چون حساب سیستمی است یا گردش/زیرمجموعه دارد.')
                ->with('error_details', $details);
        }

        try {
            $chartAccount->delete();
        } catch (Throwable) {
            return redirect()->route('chart-accounts.index')
                ->with('error', 'امکان حذف این سرفصل مالی وجود ندارد.')
                ->with('error_details', ['ممکن است سند حسابداری یا زیرمجموعه‌ای به این حساب وصل باشد.']);
        }

        return redirect()->route('chart-accounts.index')->with('success', 'سرفصل مالی حذف شد.');
    }

    private function validated(Request $request, ?ChartAccount $account = null): array
    {
        $ignore = $account ? ',' . $account->id : '';

        return $request->validate([
            'parent_id' => 'nullable|exists:chart_accounts,id',
            'level' => 'required|in:group,ledger,subsidiary,detail',
            'code' => 'required|string|max:255|unique:chart_accounts,code' . $ignore,
            'title' => 'required|string|max:255',
            'nature' => 'required|in:debit,credit,neutral',
        ]);
    }
}
