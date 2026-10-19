@extends('layouts.embed')

@section('content')
    @include('invoices.partials.show', [
        'embedded' => true,
        'crmContext' => true,
        'routes' => [
            'show' => route('crm.proforma.show', $invoice),
            'print' => route('crm.proforma.print', $invoice),
            'pdf' => route('crm.proforma.pdf', $invoice),
            'excel' => route('crm.proforma.excel', $invoice),
        ],
    ])
@endsection
