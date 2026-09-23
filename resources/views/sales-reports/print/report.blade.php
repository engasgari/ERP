@extends('management-reports.print.layout')

@section('content')
    @if($forPdf ?? false)
        @include('components.pdf-report-shell')
    @else
        @include('sales-reports.partials.report-shell', ['printMode' => true, 'forPrint' => true])
    @endif
@endsection
