@extends('app')
@section('title', 'Edit Customer Payment | ' . $client_company->name)
@section('content')
    @php
        $method_options = [
            'cash' => ['text' => 'Cash'],
            'cheque' => ['text' => 'Cheque'],
            'slip' => ['text' => 'Slip'],
            'adjustment' => ['text' => 'Adjustment'],
        ];
        $type_options = [
            'normal' => ['text' => 'Normal'],
            'payment_program' => ['text' => 'Payment Program'],
            'recovery' => ['text' => 'Recovery'],
        ]
    @endphp
    <!-- Progress Bar -->
    <div class="mb-5 max-w-3xl mx-auto">
        <x-search-header heading="Edit Customer Payment" link linkText="Show Payments" linkHref="{{ route('customer-payments.index') }}"/>
    </div>

    <div class="row max-w-3xl mx-auto flex gap-4">
        <!-- Form -->
        <form id="form" action="{{ route('customer-payments.update', ['customer_payment' => $customerPayment->id]) }}" method="post"
            class="bg-[var(--secondary-bg-color)] text-sm rounded-xl shadow-lg p-7 border border-[var(--h-bg-color)] pt-12 w-full mx-auto relative overflow-hidden">
            @csrf
            @method('PUT')
            <x-form-title-bar title="Edit Customer Payment" />

            <div class="step space-y-4 overflow-y-auto max-h-[65vh] p-1 my-scrollbar-2">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    {{-- customer --}}
                    <x-input
                        label="Customer"
                        value="{{ $customerPayment->customer->customer_name }}"
                        disabled
                    />

                    {{-- balance --}}
                    <x-input label="Balance" value="{{ \App\Support\Money::format($customerPayment->customer->balance) }}" disabled />

                    {{-- date --}}
                    <x-input label="Date" name="date" id="date" type="date" required value="{{ $customerPayment->date->format('Y-m-d') }}" readonly onchange="trackDateState(this)"/>

                    {{-- type --}}
                    <x-select
                        label="Type"
                        name="type"
                        id="type"
                        :options="$type_options"
                        required
                        showDefault
                        onchange="trackTypeState(this)"
                    />

                    <div class="col-span-full">
                        <div id="details-inputs-container" class="grid grid-cols-1 md:grid-cols-2 gap-4 col-span-full">
                        </div>
                        {{-- method --}}
                        <x-select
                            label="Method"
                            name="method"
                            id="method"
                            :options="$method_options"
                            required
                            showDefault
                            onchange="trackMethodState(this)"
                        />

                        <hr class="border-gray-600 my-3">

                        <div id="details" class="grid grid-cols-1 md:grid-cols-3 gap-4">

                        </div>
                    </div>
                </div>
            </div>
            <div class="w-full flex justify-end mt-4">
                <button type="submit"
                    class="px-10 py-2 bg-[var(--bg-success)] border border-[var(--bg-success)] text-[var(--text-success)] font-medium text-nowrap rounded-lg hover:bg-[var(--h-bg-success)] hover:border-[var(--border-success)] hover:scale-90 transition-all duration-300 ease-in-out cursor-pointer">
                    <i class='fas fa-save mr-1'></i> Save
                </button>
            </div>
        </form>
    </div>

    @php
        $programSelectHtml = view('components.select', [
            'label' => 'Payment Programs',
            'name' => 'program_id',
            'id' => 'payment_programs',
            'required' => true,
            'showDefault' => true,
            'options' => [],
            'onchange' => 'trackProgramState(this)',
        ])->render();

        $bankSelectHtml = view('components.select', [
            'label' => 'Bank',
            'name' => 'bank_id',
            'id' => 'bank',
            'required' => true,
            'showDefault' => true,
            'options' => $banks_options,
        ])->render();

        $bankAccountsSelectHtml = view('components.select', [
            'label' => 'Bank Accounts',
            'name' => 'bank_account_id',
            'id' => 'bank_accounts',
            'required' => true,
            'showDefault' => true,
            'options' => [],
        ])->render();

        $templates = [
            'cash' =>
                view('components.input', [
                    'label' => 'Amount',
                    'type' => 'amount',
                    'placeholder' => 'Enter amount',
                    'name' => 'amount',
                    'id' => 'amount',
                    'value' => '__AMOUNT__',
                    'dataValidate' => 'required|amount',
                    'oninput' => 'validateInput(this)',
                    'required' => true,
                ])->render() .
                view('components.input', [
                    'label' => 'Remarks',
                    'placeholder' => 'Remarks',
                    'name' => 'remarks',
                    'id' => 'remarks',
                    'value' => '__REMARKS__',
                    'dataValidate' => 'friendly',
                    'oninput' => 'validateInput(this)',
                ])->render(),
            'cheque' =>
                $bankSelectHtml .
                view('components.input', [
                    'label' => 'Amount',
                    'type' => 'amount',
                    'placeholder' => 'Enter amount',
                    'name' => 'amount',
                    'id' => 'amount',
                    'value' => '__AMOUNT__',
                    'dataValidate' => 'required|amount',
                    'oninput' => 'validateInput(this)',
                    'required' => true,
                ])->render() .
                view('components.input', [
                    'label' => 'Cheque Date',
                    'type' => 'date',
                    'name' => 'cheque_date',
                    'id' => 'cheque_date',
                    'value' => '__CHEQUE_DATE__',
                    'required' => true,
                ])->render() .
                view('components.input', [
                    'label' => 'Cheque No',
                    'placeholder' => 'Enter cheque no',
                    'name' => 'cheque_no',
                    'id' => 'cheque_no',
                    'value' => '__CHEQUE_NO__',
                    'required' => true,
                    'dataValidate' => 'required|friendly|unique:chequeNo',
                    'oninput' => 'validateInput(this)',
                ])->render() .
                view('components.input', [
                    'label' => 'Remarks',
                    'placeholder' => 'Remarks',
                    'name' => 'remarks',
                    'id' => 'remarks',
                    'value' => '__REMARKS__',
                    'dataValidate' => 'friendly',
                    'oninput' => 'validateInput(this)',
                ])->render() .
                view('components.input', [
                    'label' => 'Clear Date',
                    'type' => 'date',
                    'name' => 'clear_date',
                    'id' => 'clear_date',
                    'value' => '__CLEAR_DATE__',
                ])->render(),
            'slip' =>
                view('components.input', [
                    'label' => 'Customer',
                    'placeholder' => 'Enter Customer',
                    'name' => 'customer',
                    'id' => 'customer',
                    'value' => '__CUSTOMER_NAME__',
                    'disabled' => true,
                    'required' => true,
                ])->render() .
                view('components.input', [
                    'label' => 'Amount',
                    'type' => 'amount',
                    'placeholder' => 'Enter amount',
                    'name' => 'amount',
                    'id' => 'amount',
                    'value' => '__AMOUNT__',
                    'dataValidate' => 'required|amount',
                    'oninput' => 'validateInput(this)',
                    'required' => true,
                ])->render() .
                view('components.input', [
                    'label' => 'Slip Date',
                    'type' => 'date',
                    'name' => 'slip_date',
                    'id' => 'slip_date',
                    'value' => '__SLIP_DATE__',
                    'required' => true,
                ])->render() .
                view('components.input', [
                    'label' => 'Slip No',
                    'placeholder' => 'Enter slip no',
                    'name' => 'slip_no',
                    'id' => 'slip_no',
                    'value' => '__SLIP_NO__',
                    'required' => true,
                    'dataValidate' => 'required|friendly|unique:slipNo',
                    'oninput' => 'validateInput(this)',
                ])->render() .
                view('components.input', [
                    'label' => 'Remarks',
                    'placeholder' => 'Remarks',
                    'name' => 'remarks',
                    'id' => 'remarks',
                    'value' => '__REMARKS__',
                    'dataValidate' => 'friendly',
                    'oninput' => 'validateInput(this)',
                ])->render() .
                view('components.input', [
                    'label' => 'Clear Date',
                    'type' => 'date',
                    'name' => 'clear_date',
                    'id' => 'clear_date',
                    'value' => '__CLEAR_DATE__',
                ])->render(),
            'adjustment' =>
                view('components.input', [
                    'label' => 'Amount',
                    'type' => 'amount',
                    'placeholder' => 'Enter amount',
                    'name' => 'amount',
                    'id' => 'amount',
                    'value' => '__AMOUNT__',
                    'dataValidate' => 'required|amount',
                    'oninput' => 'validateInput(this)',
                    'required' => true,
                ])->render() .
                view('components.input', [
                    'label' => 'Remarks',
                    'placeholder' => 'Remarks',
                    'name' => 'remarks',
                    'id' => 'remarks',
                    'value' => '__REMARKS__',
                    'dataValidate' => 'friendly',
                    'oninput' => 'validateInput(this)',
                ])->render(),
            'program' =>
                view('components.input', [
                    'label' => 'Category',
                    'value' => '__PROGRAM_CATEGORY__',
                    'disabled' => true,
                ])->render() .
                view('components.input', [
                    'label' => 'Beneficiary',
                    'value' => '__BENEFICIARY__',
                    'disabled' => true,
                ])->render() .
                view('components.input', [
                    'label' => 'Program Date',
                    'value' => '__PROGRAM_DATE__',
                    'disabled' => true,
                ])->render() .
                view('components.input', [
                    'label' => 'Program Balance',
                    'type' => 'number',
                    'value' => '__PROGRAM_BALANCE__',
                    'disabled' => true,
                ])->render() .
                view('components.input', [
                    'label' => 'Amount',
                    'type' => 'amount',
                    'placeholder' => 'Enter amount',
                    'name' => 'amount',
                    'id' => 'amount',
                    'value' => '__AMOUNT__',
                    'dataValidate' => 'required|amount',
                    'oninput' => 'validateInput(this)',
                    'required' => true,
                ])->render() .
                $bankAccountsSelectHtml .
                view('components.input', [
                    'label' => 'Transaction Id',
                    'name' => 'transaction_id',
                    'id' => 'transaction_id',
                    'placeholder' => 'Enter Transaction Id',
                    'required' => true,
                    'value' => '__TRANSACTION_ID__',
                    'dataValidate' => 'required|alphanumeric',
                    'oninput' => 'validateInput(this)',
                ])->render() .
                view('components.input', [
                    'label' => 'Remarks',
                    'placeholder' => 'Remarks',
                    'name' => 'remarks',
                    'id' => 'remarks',
                    'value' => '__REMARKS__',
                    'dataValidate' => 'friendly',
                    'oninput' => 'validateInput(this)',
                ])->render(),
        ];
    @endphp

@endsection

@push('page-scripts')
<script defer src="{{ asset('js/pages/employee-payments-edit.js') }}"></script>
<script>
        window.__employeePaymentsEdit = {
            chequeNos: @json($cheque_nos ?? ''),
            slipNos: @json($slip_nos ?? ''),
            customerPayment: @json($customerPayment),
            programSelectHtml: @json('<div class="col-span-full">' . $programSelectHtml . '</div>'),
            templates: @json($templates),
        };
    </script>
@endpush
