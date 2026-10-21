@extends('layouts.embed')

@section('content')
    @include('parties.partials.form', ['embedded' => true])
@endsection
