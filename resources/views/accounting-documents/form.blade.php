<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl">ثبت سند حسابداری</h2></x-slot>

    <form method="post" action="{{ $document->exists ? route('accounting-documents.update', $document) : route('accounting-documents.store') }}" class="bg-white rounded-lg shadow-md p-6 space-y-4">
        @csrf
        @if($document->exists) @method('PUT') @endif

        <div class="grid md:grid-cols-4 gap-4">
            <label>شماره سند
                <input name="number" value="{{ old('number', $document->number) }}" placeholder="ثبت خودکار در صورت خالی بودن" class="w-full">
            </label>
            <label>تاریخ سند
                <input type="text" name="document_date" inputmode="numeric" dir="ltr" placeholder="1405/03/19" value="{{ old('document_date') ? jalaliDateInputValue(old('document_date')) : jalaliDateInputValue(null, $document->document_date ?: now()) }}" required class="w-full">
            </label>
            <label>وضعیت
                <select name="status" class="w-full">
                    <option value="draft" @selected(old('status', $document->status ?: 'draft') === 'draft')>پیش‌نویس</option>
                    <option value="posted" @selected(old('status', $document->status) === 'posted')>ثبت قطعی</option>
                </select>
            </label>
            <label>ارز
                <input name="currency" value="{{ old('currency', $document->currency ?: 'IRR') }}" class="w-full">
            </label>
        </div>

        <label>شرح سند
            <textarea name="description" rows="2" class="w-full">{{ old('description', $document->description) }}</textarea>
        </label>

        @php
            $lines = old('lines', $document->lines->toArray() ?: [
                ['debit' => 0, 'credit' => 0],
                ['debit' => 0, 'credit' => 0],
            ]);
        @endphp

        <div class="overflow-x-auto">
            <table class="erp-ui-data-table">
                <thead><tr><th>حساب</th><th>شخص/شرکت</th><th>پروژه</th><th>شرح ردیف</th><th>بدهکار (ریال)</th><th>بستانکار (ریال)</th></tr></thead>
                <tbody>
                @for($i = 0; $i < max(8, count($lines)); $i++)
                    @php($line = $lines[$i] ?? [])
                    <tr>
                        <td>
                            <select name="lines[{{ $i }}][chart_account_id]" class="w-full">
                                <option value="">انتخاب حساب</option>
                                @foreach($accounts as $account)
                                    <option value="{{ $account->id }}" @selected(($line['chart_account_id'] ?? null) == $account->id)>{{ $account->code }} - {{ $account->title }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td>
                            <select name="lines[{{ $i }}][party_id]" class="w-full">
                                <option value="">بدون شخص</option>
                                @foreach($parties as $party)
                                    <option value="{{ $party->id }}" @selected(($line['party_id'] ?? null) == $party->id)>{{ $party->name }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td>
                            <select name="lines[{{ $i }}][project_id]" class="w-full">
                                <option value="">بدون پروژه</option>
                                @foreach($projects as $project)
                                    <option value="{{ $project->id }}" @selected(($line['project_id'] ?? null) == $project->id)>{{ $project->name }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td><input name="lines[{{ $i }}][description]" value="{{ $line['description'] ?? '' }}" class="w-full"></td>
                        <td><input type="number" step="0.01" name="lines[{{ $i }}][debit]" value="{{ $line['debit'] ?? 0 }}" class="w-full"></td>
                        <td><input type="number" step="0.01" name="lines[{{ $i }}][credit]" value="{{ $line['credit'] ?? 0 }}" class="w-full"></td>
                    </tr>
                @endfor
                </tbody>
            </table>
        </div>

        <label>یادداشت
            <textarea name="notes" rows="2" class="w-full">{{ old('notes', $document->notes) }}</textarea>
        </label>

        <button class="bg-blue-600 text-white px-4 py-2 rounded">ذخیره سند</button>
    </form>
</x-app-layout>
