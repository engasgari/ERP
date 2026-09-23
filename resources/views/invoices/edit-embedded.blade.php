@extends('layouts.embed')

@section('content')
    @include('invoices.partials.editor', ['embedded' => true])
@endsection
