@extends('app')
@section('title', 'Add Inventory | ' . $client_company->name)
@section('content')
    @php
        $units = collect(app('defaults')->units)->mapWithKeys(fn ($unit) => [$unit => ['text' => $unit]])->all();
        $types = [
            'material' => ['text' => 'Material'],
            'fabric' => ['text' => 'Fabric'],
            'tag' => ['text' => 'Tag'],
            'accessory' => ['text' => 'Accessory'],
            'other' => ['text' => 'Other'],
        ];
        $paymentMethods = [
            'cash' => ['text' => 'Cash'],
            'supplier_credit' => ['text' => 'Supplier Credit'],
            'bank' => ['text' => 'Bank'],
        ];
    @endphp

    <div class="mb-5 max-w-4xl mx-auto">
        <x-search-header heading="Add Inventory" link linkText="Show Inventory" linkHref="{{ route('inventory.index') }}" />
        <x-progress-bar
            :steps="['Item Details', 'Stock In']"
            :currentStep="1"
        />
    </div>

    <div class="row max-w-4xl mx-auto flex gap-4">
        <form id="form" action="{{ route('inventory.store') }}" method="post" enctype="multipart/form-data"
            class="bg-[var(--secondary-bg-color)] text-sm rounded-xl shadow-lg p-8 border border-[var(--glass-border-color)]/20 pt-14 grow relative overflow-hidden">
            @csrf
            <x-form-title-bar title="Inventory Purchase / Stock In" />

            <div class="step1 space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <x-input label="Date" name="date" id="date" type="date" value="{{ now()->toDateString() }}" required />
                    <x-input label="Item Name" name="name" id="name" placeholder="Enter item name" required />
                    <x-select label="Type" name="type" id="type" :options="$types" showDefault required onchange="trackInventoryType(this)" />
                    <x-select label="Fabric" name="fabric_id" id="fabric_id" :options="$fabricOptions" showDefault />
                    <x-input label="Tag" name="tag" id="tag" placeholder="Optional tag / batch no." />
                    <x-input label="Color" name="color" id="color" placeholder="Optional color" />
                </div>
            </div>

            <div class="step2 hidden space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <x-select label="Unit" name="unit" id="unit" :options="$units" showDefault required />
                    <x-input label="Quantity" name="quantity" id="quantity" type="number" step="0.001" placeholder="Enter quantity" required oninput="calculateInventoryAmount()" />
                    <x-input label="Unit Price" name="unit_price" id="unit_price" type="number" step="0.01" placeholder="Enter unit price" oninput="calculateInventoryAmount()" />
                    <x-input label="Amount" name="amount" id="amount" type="number" step="0.01" placeholder="Amount" readonly />
                    <x-select label="Supplier" name="supplier_id" id="supplier_id" :options="$supplierOptions" showDefault />
                    <x-select label="Payment Method" name="payment_method" id="payment_method" :options="$paymentMethods" showDefault />
                    <x-input label="Reference No." name="reference_no" id="reference_no" placeholder="Bill / reference no." />
                    <x-input label="Remarks" name="remarks" id="remarks" placeholder="Optional remarks" />
                </div>
            </div>
        </form>
    </div>
@endsection

@push('page-scripts')
<script defer src="{{ asset('js/pages/inventory-create.js') }}"></script>
@endpush
