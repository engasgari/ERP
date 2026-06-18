@extends('management-reports.print.layout')

@section('content')
    @include('financial-reports.partials.report-shell', ['printMode' => true])
@endsection
