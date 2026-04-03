@extends('app')
@section('title', 'Add Utility Bill | ' . $client_company->name)
@section('content')
    <!-- Main Content -->
    <div class="max-w-3xl mx-auto">
        <x-search-header heading="Add Utility Bill" link linkText="Show Utility Bills" linkHref="{{ route('utility-bills.index') }}"/>
    </div>

    <!-- Form -->
    <form id="form" action="{{ route('utility-bills.store') }}" method="post"
        class="bg-[var(--secondary-bg-color)] text-sm rounded-xl shadow-lg p-8 border border-[var(--glass-border-color)]/20 pt-14 max-w-3xl mx-auto  relative overflow-hidden">
        @csrf
        <x-form-title-bar title="Add Utility Bill" />

        <div class="grid grid-cols-2 gap-4">
            {{-- bill_type --}}
            <x-select
                label="Bill Type"
                name="bill_type_id"
                id="bill_type"
                :options="$bill_type_options"
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
                onchange="trackLocation(this)"
                required
                showDefault
                disabled
            />

            {{-- account --}}
            <x-select
                label="Account"
                name="account_id"
                id="account"
                :options="[]"
                onchange="trackAccount(this)"
                required
                showDefault
            />

            {{-- month --}}
            <x-input label="Month" name="month" id="month" type="month" required disabled />

            {{-- units --}}
            <x-input label="Units" name="units" id="units" type="number" placeholder="Enter Units" disabled />

            {{-- amount --}}
            <x-input label="Amount" name="amount" id="amount" type="amount" required dataValidate="required|amount" placeholder="Enter Amount" disabled />

            <div class="col-span-full">
                {{-- due_date --}}
                <x-input label="Due Date" name="due_date" id="due_date" type="date" required disabled />
            </div>
        </div>
        <div class="w-full flex justify-end mt-4">
            <button type="submit"
                class="px-6 py-1 bg-[var(--bg-success)] border border-[var(--bg-success)] text-[var(--text-success)] font-medium text-nowrap rounded-lg hover:bg-[var(--h-bg-success)] transition-all duration-300 ease-in-out cursor-pointer">
                <i class='fas fa-save mr-1'></i> Save
            </button>
        </div>
    </form>

@endsection

@push('page-scripts')
<script defer src="{{ asset('js/pages/utility-bills-add.js') }}"></script>
@endpush
