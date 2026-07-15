@extends('app')
@section('title', 'Generate Voucher | ' . $client_company->name)
@section('content')

@php
    $voucherType = Auth::user()->voucher_type;
    $self_accounts_options = $self_accounts_options ?? [];

    $steps = [
        $voucherType == 'supplier' ? 'Select Supplier' : 'Select Date',
        'Enter Payment',
        'Preview',
    ];

    $method_options = [
        'cash' => ['text' => 'Cash'],
        'cheque' => ['text' => 'Cheque'],
        'slip' => ['text' => 'Slip'],
    ];

    if ($voucherType == 'supplier') {
        // Insert 'program' at 3rd position (index 3)
        $method_options = array_slice($method_options, 0, 3, true)
            + ['program' => ['text' => 'Payment Program']]
            + array_slice($method_options, 3, null, true);
        $method_options = array_slice($method_options, 0, 3, true)
            + ['purchase_return' => ['text' => 'Purchase Return']]
            + array_slice($method_options, 3, null, true);
    }

    // Add remaining methods
    $method_options += [
        'self_cheque' => ['text' => 'Self Cheque'],
        'atm' => ['text' => 'ATM'],
        'adjustment' => ['text' => 'Adjustment'],
    ];
@endphp

    <div class="switch-btn-container flex absolute top-3 md:top-17 left-3 md:left-5 z-4">
        <div class="switch-btn relative flex border-3 border-[var(--secondary-bg-color)] bg-[var(--secondary-bg-color)] rounded-2xl overflow-hidden">
            <!-- Highlight rectangle -->
            <div id="highlight" class="absolute h-full rounded-xl bg-[var(--bg-color)] transition-all duration-300 ease-in-out z-0"></div>

            <!-- Buttons -->
            <button id="supplierBtn" type="button" class="relative z-10 px-3.5 md:px-5 py-1.5 md:py-2 cursor-pointer rounded-xl transition-colors duration-300">
                <div class="hidden md:block">Supplier</div>
                <div class="block md:hidden"><i class="fas fa-cart-shopping text-xs"></i></div>
            </button>
            <button id="selfAccountBtn" type="button" class="relative z-10 px-3.5 md:px-5 py-1.5 md:py-2 cursor-pointer rounded-xl transition-colors duration-300">
                <div class="hidden md:block">Self Account</div>
                <div class="block md:hidden"><i class="fas fa-box-open text-xs"></i></div>
            </button>
        </div>
    </div>

    

    <!-- Progress Bar -->
    <div class="mb-5 max-w-4xl mx-auto">
        <x-search-header heading="Generate Voucher" link linkText="Show Vouchers"
            linkHref="{{ route('vouchers.index') }}" />
        <x-progress-bar :steps="$steps" :currentStep="1" />
    </div>

    <!-- Form -->
    <form id="form" action="{{ route('vouchers.store') }}" method="post"
        class="bg-[var(--secondary-bg-color)] text-sm rounded-xl shadow-lg p-8 border border-[var(--glass-border-color)]/20 pt-14 max-w-4xl mx-auto  relative overflow-hidden">
        @csrf
        <x-form-title-bar title="Generate Voucher" />

        <div class="step1 space-y-4 ">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @if ($voucherType == 'supplier')
                    {{-- supplier --}}
                    <x-select class="col-span-2" label="Supplier" name="supplier_id" id="supplier_id" :options="$suppliers_options" required showDefault
                        onchange="trackSupplierState()" />

                    {{-- balance --}}
                    <x-input label="Balance" placeholder="Select supplier first" name="balance" id="balance" disabled />

                    {{-- date --}}
                    <x-input label="Date" name="date" id="date" type="date" required disabled
                        onchange="trackDateState(this)" />
                @else
                    <div class="col-span-full">
                        {{-- date --}}
                        <x-input label="Date" name="date" id="date" type="date" required
                            onchange="trackDateState(this)" />
                    </div>
                @endif
            </div>
        </div>

        <div class="step2 space-y-4 hidden">
            <div class="flex flex-col space-y-4 gap-4">
                {{-- method --}}
                <x-select label="Method" id="method" :options="$method_options" required showDefault
                    onchange="trackMethodState(this)" withButton btnId="enterDetailsBtn" btnText="Enter Details"
                    btnOnclick="trackMethodState(this.previousElementSibling)" />
            </div>
            {{-- payment showing --}}
            <div id="payment-table" class="w-full text-left text-sm">
                <div class="flex justify-between items-center bg-[var(--h-bg-color)] rounded-lg py-2 px-4 mb-4">
                    <div class="w-[5%]">S.No</div>
                    @if ($voucherType == 'self_account')
                        <div class="w-1/3">To Account</div>
                    @endif
                    <div class="w-1/5">Method</div>
                    <div class="w-1/3">{{ $voucherType == 'self_account' ? 'From Account / Beneficiary' : 'Customer/Self Acc.' }}</div>
                    <div class="w-1/5">Reff. No.</div>
                    <div class="w-1/6">Remarks</div>
                    <div class="w-[15%]">Amount</div>
                    <div class="w-[8%] text-center">Action</div>
                </div>
                <div id="payment-list" class="h-[20rem] overflow-y-auto my-scrollbar-2">
                    <div class="text-center bg-[var(--h-bg-color)] rounded-lg py-2 px-4">No Payment Added</div>
                </div>
                <input type="hidden" name="payment_details_array" id="payment_details_array">
            </div>

            <div class="flex w-full text-sm mt-5 text-nowrap">
                <div
                    class="total-payment flex justify-between items-center border border-gray-600 rounded-lg py-2 px-4 w-full">
                    <div class="grow">Total Payment - Rs.</div>
                    <div id="finalTotalPayment">0</div>
                </div>
            </div>
        </div>

        <div class="step3 hidden space-y-4 text-black h-[35rem] overflow-y-auto my-scrollbar-2 bg-white rounded-md">
            <div id="preview-container" class="w-[210mm] h-[297mm] mx-auto overflow-hidden relative">
                <div id="preview" class="preview flex flex-col h-full">
                    <h1 class="text-[var(--border-error)] font-medium text-center mt-5">No Preview avalaible.</h1>
                </div>
            </div>
        </div>
    </form>

@endsection

@php
    $voucherTemplates = [
        'selfAccountSelect' => view('components.select', [
            'label' => '__LABEL__',
            'name' => '__NAME__',
            'id' => '__ID__',
            'options' => $self_accounts_options,
            'showDefault' => true,
            'onchange' => '__ONCHANGE__',
        ])->render(),
        'emptySelect' => view('components.select', [
            'label' => '__LABEL__',
            'name' => '__NAME__',
            'id' => '__ID__',
            'options' => [],
            'showDefault' => true,
            'onchange' => '__ONCHANGE__',
        ])->render(),
    ];
@endphp


@push('page-scripts')
<script defer src="{{ asset('js/pages/vouchers-create.js') }}"></script>
<script>
        window.__vouchersCreate = {
            voucherType: @json($voucherType),
            csrfToken: @json(csrf_token()),
            lastVoucher: @json($last_voucher),
            nextVoucherNo: @json($nextVoucherNo ?? $last_voucher?->voucher_no ?? null),
            companyData: @json($client_company),
            branchBranding: @json($branchBranding ?? null),
            companyLogoUrl: @json(asset('images/' . $client_company->logo)),
            companyLogoBase: @json(asset('images')),
            templates: @json($voucherTemplates),
        };
    </script>
@endpush
