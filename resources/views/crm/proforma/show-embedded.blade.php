@extends('layouts.embed')

@section('content')
    @include('invoices.partials.show', [
        'embedded' => true,
        'crmContext' => true,
        'routes' => [
            'excel' => route('crm.proforma.excel', $invoice),
        ],
    ])
@endsection
