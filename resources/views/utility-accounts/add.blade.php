@extends('app')
@php($isEdit = isset($utilityAccount))
@section('title', ($isEdit ? 'Edit' : 'Add') . ' Utility Account | ' . $client_company->name)
@section('content')
    <!-- Main Content -->
    <div class="max-w-3xl mx-auto">
        <x-search-header :heading="($isEdit ? 'Edit' : 'Add') . ' Utility Account'" link linkText="Show Utility Accounts" linkHref="{{ route('utility-accounts.index') }}"/>
    </div>

    <!-- Form -->
    <form id="form" action="{{ $isEdit ? route('utility-accounts.update', $utilityAccount) : route('utility-accounts.store') }}" method="post"
        class="bg-[var(--secondary-bg-color)] text-sm rounded-xl shadow-lg p-8 border border-[var(--glass-border-color)]/20 pt-14 max-w-3xl mx-auto  relative overflow-hidden">
        @csrf
        @if($isEdit)
            @method('PUT')
        @endif
        <x-form-title-bar :title="($isEdit ? 'Edit' : 'Add') . ' Utility Account'" />

        <div class="grid grid-cols-2 gap-4">
            {{-- bill_type --}}
            <x-select
                label="Bill Type"
                name="bill_type_id"
                id="bill_type"
                :options="$bill_type_options"
                :value="$utilityAccount->bill_type_id ?? ''"
                onchange="trackBillType(this)"
                required
                showDefault
            />

            {{-- location --}}
            <x-select
                label="Location"
                name="location_id"
                id="location"
                :options="$location_options"
                :value="$utilityAccount->location_id ?? ''"
                onchange="trackLocation(this)"
                required
                showDefault
            />

            {{-- account_title --}}
            <x-input label="Account Title" name="account_title" id="account_title" type="text" :value="$utilityAccount->account_title ?? ''" required dataValidate="required|friendly" capitalized placeholder="Enter account title" />

            {{-- account_no --}}
            <x-input label="Account No." name="account_no" id="account_no" type="text" :value="$utilityAccount->account_no ?? ''" required dataValidate="required|friendly" capitalized placeholder="Enter account no." />
        </div>
        <div class="w-full flex justify-end mt-4">
            <button type="submit"
                class="px-6 py-1 bg-[var(--bg-success)] border border-[var(--bg-success)] text-[var(--text-success)] font-medium text-nowrap rounded-lg hover:bg-[var(--h-bg-success)] transition-all duration-300 ease-in-out cursor-pointer">
                <i class='fas fa-save mr-1'></i> {{ $isEdit ? 'Update' : 'Save' }}
            </button>
        </div>
    </form>

@endsection

@push('page-scripts')
<script defer src="{{ asset('js/pages/utility-accounts-add.js') }}"></script>
@endpush
