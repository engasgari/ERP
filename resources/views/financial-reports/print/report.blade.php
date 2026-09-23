@extends('management-reports.print.layout')

@section('content')
    @if($forPdf ?? false)
        @include('components.pdf-report-shell')
    @else
        @include('financial-reports.partials.report-shell', ['printMode' => true])
    @endif
@endsection
