@extends('layouts.embed')

@section('content')
    @include('invoices.partials.editor', [
        'embedded' => true,
        'formAction' => $formAction ?? route('crm.proforma.update', $invoice),
    ])
@endsection
