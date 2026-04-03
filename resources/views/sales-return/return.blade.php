@php
    $authUser = Auth::user();
@endphp

@extends('app')
@section('title', 'Sales Return | ' . $client_company->name)
@section('content')
    <div class="mb-5 max-w-3xl mx-auto fade-in">
        <x-search-header heading="Sales Return" link linkText="Show Returns" linkHref="{{ route('sales-returns.index') }}"/>
    </div>

    <!-- Form -->
    <form id="form" action="{{ route('sales-returns.store') }}" method="post" enctype="multipart/form-data"
        class="bg-[var(--secondary-bg-color)] rounded-xl shadow-lg p-8 border border-[var(--h-bg-color)] pt-12 max-w-3xl mx-auto relative overflow-hidden">
        @csrf
        <x-form-title-bar title="Sales Return" />
        <!-- Step 1: Basic Information -->
        <div class="step1 space-y-6 ">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                {{-- Customer --}}
                <x-select label="Customer" name="customer_id" id="customer" :options="$customerOptions" showDefault onchange="onCustomerSelect(this)" />

                {{-- Article --}}
                <x-select label="Article" name="article_id" id="article" :options="[]" showDefault disabled onchange="onArticleSelect(this)" />

                {{-- Invoice --}}
                <div class="col-span-2">
                    <x-select label="Invoice" name="invoice_id" id="invoice" :options="[]" showDefault disabled onchange="onInvoiceSelect(this)" />
                </div>

                <div class="grid grid-cols-3 col-span-full gap-4">
                    {{-- Date --}}
                    <x-input label="Date" name="date" id="date" type="date" max="{{ now()->toDateString() }}" required disabled />

                    {{-- Quantity --}}
                    <x-input label="Quantity" name="quantity" id="quantity" type="number" placeholder="Enter quantity" oninput="onQuantityInput(this)" required disabled dataValidate="required|numeric" />

                    {{-- Amount --}}
                    <x-input label="Amount" name="amount" id="amount" type="amount" placeholder="Amount" readonly dataValidate="required|amount" />
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
<script defer src="{{ asset('js/pages/sales-return-return.js') }}"></script>
<script>
        window.__salesReturnReturn = {
            detailsUrl: @json(route('sales-returns.get-details')),
            csrfToken: @json(csrf_token()),
        };
    </script>
@endpush
