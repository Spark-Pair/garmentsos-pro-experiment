@php
    $authUser = Auth::user();
@endphp

@extends('app')
@section('title', 'Sales Return | ' . $client_company->name)
@section('content')
    <div class="mb-5 max-w-4xl mx-auto fade-in">
        <x-search-header heading="Sales Return" link linkText="Show Returns" linkHref="{{ route('sales-returns.index') }}"/>
    </div>

    <!-- Form -->
    <form id="form" action="{{ route('sales-returns.store') }}" method="post" enctype="multipart/form-data"
        class="bg-[var(--secondary-bg-color)] text-sm rounded-xl shadow-lg p-8 border border-[var(--h-bg-color)] pt-12 max-w-4xl mx-auto relative overflow-hidden">
        @csrf
        <x-form-title-bar title="Sales Return" />
        <!-- Step 1: Basic Information -->
        <div class="step1 space-y-4 ">
            <div class="grid grid-cols-1 md:grid-cols-[1fr_12rem_auto] gap-4 items-end">
                {{-- Customer --}}
                <x-select label="Customer" name="customer_id" id="customer" :options="$customerOptions" showDefault onchange="onCustomerSelect(this)" />

                {{-- Date --}}
                <x-input label="Date" name="date" id="date" type="date" max="{{ now()->toDateString() }}" required disabled />

                <button id="selectReturnArticlesBtn" type="button" onclick="openReturnLinesModal()" disabled
                    class="h-[2.45rem] px-4 bg-[var(--primary-color)] rounded-lg hover:bg-[var(--h-primary-color)] transition-all duration-300 ease-in-out cursor-pointer text-sm font-semibold disabled:opacity-50 disabled:cursor-not-allowed">
                    Select Articles
                </button>
            </div>

            <div id="article-table" class="w-full text-left text-sm">
                <div class="flex justify-between items-center text-center bg-[var(--h-bg-color)] rounded-lg py-2 px-2 mb-4">
                    <div class="w-[3%]">#</div>
                    <div class="w-[9%]">Invoice</div>
                    <div class="w-[11%]">Date</div>
                    <div class="w-[12%]">Article</div>
                    <div class="grow">Desc.</div>
                    <div class="w-[8%] ">Max Pcs</div>
                    <div class="w-[11%] ">Return Pcs</div>
                    <div class="w-[9%] ">Rate</div>
                    <div class="w-[5%] ">Disc.</div>
                    <div class="w-[10%] ">Amount</div>
                    <div class="w-[3%] text-center"></div>
                </div>
                <div id="article-list" class="h-[20rem] overflow-y-auto my-scrollbar-2">
                    <div class="text-center bg-[var(--h-bg-color)] rounded-lg py-3 px-4">Select a customer to load invoices</div>
                </div>
            </div>

            <input type="hidden" name="returns_data" id="returns_data" value="">

            <div class="flex w-full grid grid-cols-1 md:grid-cols-3 gap-3 text-sm text-nowrap">
                <div class="total-lines flex justify-between items-center border border-gray-600 cursor-not-allowed rounded-lg py-2 px-4 w-full">
                    <div class="grow">Selected Lines</div>
                    <div id="selectedLinesInForm">0</div>
                </div>
                <div class="total-qty flex justify-between items-center border border-gray-600 cursor-not-allowed rounded-lg py-2 px-4 w-full">
                    <div class="grow">Total Quantity - Pcs</div>
                    <div id="totalQuantityInForm">0</div>
                </div>
                <div class="final flex justify-between items-center border border-gray-600 cursor-not-allowed rounded-lg py-2 px-4 w-full">
                    <div class="grow">Total Amount - Rs.</div>
                    <div id="totalAmountInForm">0.0</div>
                </div>
            </div>
        </div>
        <div class="w-full flex justify-end mt-4">
            <button type="submit"
                class="px-6 py-1 bg-[var(--bg-success)] border border-[var(--bg-success)] text-[var(--text-success)] font-medium text-nowrap rounded-lg hover:bg-[var(--h-bg-success)] transition-all 0.3s ease-in-out cursor-pointer">
                <i class='fas fa-save mr-1'></i> Save
            </button>
        </div>
    </form>

@endsection

@push('page-scripts')
<script defer src="{{ asset('js/pages/sales-return-return.js') }}?v={{ @filemtime(public_path('js/pages/sales-return-return.js')) }}"></script>
<script>
        window.__salesReturnReturn = {
            detailsUrl: @json(route('sales-returns.get-details')),
            csrfToken: @json(csrf_token()),
        };
    </script>
@endpush
