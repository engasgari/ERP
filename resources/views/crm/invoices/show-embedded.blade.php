@extends('layouts.embed')

@section('content')
    @include('invoices.partials.show', [
        'embedded' => true,
        'crmContext' => true,
        'routes' => [
            'show' => route('crm.invoices.show', $invoice),
            'print' => route('crm.invoices.print', $invoice),
            'pdf' => route('crm.invoices.pdf', $invoice),
            'excel' => route('crm.invoices.excel', $invoice),
        ],
    ])
@endsection
