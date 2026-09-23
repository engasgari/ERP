@extends('layouts.embed')

@section('content')
    @include('invoices.partials.show', [
        'embedded' => true,
        'crmContext' => $crmContext ?? false,
    ])
@endsection
