@php($isEdit = isset($account) && $account->exists)

<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl">{{ $isEdit ? 'ویرایش سرفصل مالی' : 'تعریف سرفصل مالی' }}</h2></x-slot>
    <form method="POST" action="{{ $isEdit ? route('chart-accounts.update', $account) : route('chart-accounts.store') }}" class="bg-white rounded-lg shadow-md p-6 grid gap-4 md:grid-cols-2">
        @csrf
        @if($isEdit)
            @method('PUT')
        @endif
        <label>کد <input name="code" required class="w-full" value="{{ old('code', $account->code ?? '') }}"></label>
        <label>عنوان <input name="title" required class="w-full" value="{{ old('title', $account->title ?? '') }}"></label>
        <label>سطح <select name="level" class="w-full"><option value="group" @selected(old('level', $account->level ?? '') === 'group')>گروه</option><option value="ledger" @selected(old('level', $account->level ?? '') === 'ledger')>کل</option><option value="subsidiary" @selected(old('level', $account->level ?? '') === 'subsidiary')>معین</option><option value="detail" @selected(old('level', $account->level ?? 'detail') === 'detail')>تفصیل</option></select></label>
        <label>ماهیت <select name="nature" class="w-full"><option value="debit" @selected(old('nature', $account->nature ?? '') === 'debit')>بدهکار</option><option value="credit" @selected(old('nature', $account->nature ?? '') === 'credit')>بستانکار</option><option value="neutral" @selected(old('nature', $account->nature ?? 'neutral') === 'neutral')>خنثی</option></select></label>
        <label>والد <select name="parent_id" class="w-full"><option value="">بدون والد</option>@foreach($parents as $parent)<option value="{{ $parent->id }}" @selected(old('parent_id', $account->parent_id ?? null) == $parent->id)>{{ $parent->code }} - {{ $parent->title }}</option>@endforeach</select></label>
        <div class="md:col-span-2 flex gap-2">
            <button class="bg-blue-500 text-white px-4 py-2 rounded">{{ $isEdit ? 'ذخیره تغییرات' : 'ثبت' }}</button>
            <a href="{{ route('chart-accounts.index') }}" class="bg-gray-500 text-white px-4 py-2 rounded">بازگشت</a>
        </div>
    </form>
</x-app-layout>
