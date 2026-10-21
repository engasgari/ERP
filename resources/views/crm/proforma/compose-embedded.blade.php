@extends('layouts.embed')

@section('content')
    @if(! empty($linkExistingUrl))
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2 rounded-lg border border-teal-200 bg-teal-50 px-3 py-2 text-sm text-teal-900">
            <span>اگر پیش‌فاکتور را قبلاً در ERP زده‌اید، از فهرست انتخاب و به این فرصت وصل کنید.</span>
            <a href="{{ $linkExistingUrl }}" class="erp-action-btn erp-action-edit">فهرست پیش‌فاکتورها</a>
        </div>
    @endif

    @include('invoices.partials.editor', [
        'embedded' => true,
        'formAction' => $formAction,
        'draftDefaults' => $draftDefaults ?? [],
    ])
@endsection
