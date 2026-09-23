@extends('layouts.embed')

@section('content')
    @include('items.partials.form', ['embedded' => true])
@endsection
