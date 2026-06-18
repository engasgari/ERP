<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl">تنظیمات حسابداری حقوق</h2></x-slot>
    <form method="POST" action="{{ route('payroll.accounting-settings.update') }}" class="bg-white rounded-lg shadow-md p-6 space-y-4">
        @csrf
        @method('PUT')
        <div class="overflow-x-auto">
            <table class="erp-ui-data-table min-w-full">
                <thead><tr><th>عنوان</th><th>حساب متصل</th><th>کد فعلی</th></tr></thead>
                <tbody>
                @foreach($settings as $setting)
                    <tr>
                        <td>{{ $setting->title }}</td>
                        <td>
                            <select name="settings[{{ $setting->id }}][chart_account_id]" class="w-full">
                                @foreach($accounts as $account)
                                    <option value="{{ $account->id }}" @selected($setting->chart_account_id == $account->id)>{{ $account->code }} - {{ $account->title }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td>{{ $setting->account_code }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="flex justify-end"><button class="erp-action-btn erp-action-edit">ذخیره تنظیمات</button></div>
    </form>
</x-app-layout>
