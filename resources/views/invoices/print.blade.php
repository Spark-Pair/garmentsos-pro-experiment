@extends('app')
@section('title', 'Generate Invoice | ' . $client_company->name)
@section('content')
    <div id="invoice-container" class="hidden"></div>

@endsection

@push('page-scripts')
<script defer src="{{ asset('js/pages/invoices-print.js') }}"></script>
<script>
        window.__invoicesPrint = {
            invoices: @json($invoices),
            companyData: @json($client_company),
            companyLogoBase: '{{ asset("images") }}',
        };
    </script>
@endpush
