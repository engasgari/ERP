@extends('layouts.embed')

@section('content')
    @include('invoices.partials.editor', [
        'embedded' => true,
        'formAction' => $formAction,
        'draftDefaults' => $draftDefaults ?? [],
    ])
@endsection
