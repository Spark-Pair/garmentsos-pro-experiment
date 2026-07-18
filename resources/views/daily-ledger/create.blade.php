@extends('app')
@section('title', 'Daily Ledger Deposit | ' . $client_company->name)
@section('content')
@php
    $isEdit = isset($ledgerEntry);
    $dailyLedgerType = $entryType ?? Auth::user()->daily_ledger_type;
    $formAction = $isEdit
        ? route('daily-ledger.update', ['daily_ledger' => $ledgerEntry->id, 'type' => $dailyLedgerType])
        : route('daily-ledger.store');
@endphp

    <div class="switch-btn-container flex absolute top-3 md:top-17 left-3 md:left-5 z-4 {{ $isEdit ? 'pointer-events-none opacity-70' : '' }}">
        <div class="switch-btn relative flex border-3 border-[var(--secondary-bg-color)] bg-[var(--secondary-bg-color)] rounded-2xl overflow-hidden">
            <!-- Highlight rectangle -->
            <div id="highlight" class="absolute h-full rounded-xl bg-[var(--bg-color)] transition-all duration-300 ease-in-out z-0"></div>

            <!-- Buttons -->
            <button id="depositBtn" type="button" class="relative z-10 px-3.5 md:px-5 py-1.5 md:py-2 cursor-pointer rounded-xl transition-colors duration-300">
                <div class="hidden md:block">Deposit</div>
                <div class="block md:hidden"><i class="fas fa-cart-shopping text-xs"></i></div>
            </button>
            <button id="useBtn" type="button" class="relative z-10 px-3.5 md:px-5 py-1.5 md:py-2 cursor-pointer rounded-xl transition-colors duration-300">
                <div class="hidden md:block">Use</div>
                <div class="block md:hidden"><i class="fas fa-box-open text-xs"></i></div>
            </button>
        </div>
    </div>

    <div class="max-w-3xl mx-auto mt-10">
        <x-search-header heading="{{ $isEdit ? 'Edit Daily Ledger' : 'Daily Ledger' }}" link linkText="Show Ledger" linkHref="{{ route('daily-ledger.index') }}" />
    </div>

    <div class="row max-w-3xl mx-auto flex gap-4 mt-2">
        @if ($dailyLedgerType === 'deposit')
            <form id="form" action="{{ $formAction }}" method="post"
                class="bg-[var(--secondary-bg-color)] text-sm rounded-xl shadow-lg p-8 border border-[var(--glass-border-color)]/20 pt-14 grow relative overflow-hidden">
                @csrf
                @if ($isEdit)
                    @method('PUT')
                    <input type="hidden" name="ledger_type" value="deposit">
                @endif
                <x-form-title-bar title="{{ $isEdit ? 'Edit Daily Ledger Deposit' : 'Daily Ledger Deposit' }}" />

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <x-input label="Date" name="date" id="date" type="date" validateMax max="{{ now()->toDateString() }}" required value="{{ old('date', $isEdit ? $ledgerEntry->date->toDateString() : '') }}" />

                    <x-select
                        label="Method"
                        name="method"
                        id="method"
                        :options="$method_options"
                        value="{{ old('method', $isEdit ? $ledgerEntry->method : '') }}"
                        required
                        showDefault
                    />

                    <x-input label="Amount" name="amount" id="amount" type="amount" placeholder="Enter amount" required dataValidate="required|amount" value="{{ old('amount', $isEdit ? $ledgerEntry->amount : '') }}" />

                    <x-input label="Reff. No" name="reff_no" id="reff_no" placeholder="Enter reference no (optional)" value="{{ old('reff_no', $isEdit ? $ledgerEntry->reff_no : '') }}" />
                </div>

                <div class="w-full flex justify-end mt-4">
                    <button type="submit"
                        class="px-6 py-1 bg-[var(--bg-success)] border border-[var(--bg-success)] text-[var(--text-success)] font-medium text-nowrap rounded-lg hover:bg-[var(--h-bg-success)] transition-all 0.3s ease-in-out cursor-pointer">
                        <i class='fas fa-save mr-1'></i> {{ $isEdit ? 'Update Deposit' : 'Save Deposit' }}
                    </button>
                </div>
            </form>
        @else
            <form id="form" action="{{ $formAction }}" method="post"
                class="bg-[var(--secondary-bg-color)] text-sm rounded-xl shadow-lg p-8 border border-[var(--glass-border-color)]/20 pt-14 grow relative overflow-hidden">
                @csrf
                @if ($isEdit)
                    @method('PUT')
                    <input type="hidden" name="ledger_type" value="use">
                @endif
                <x-form-title-bar title="{{ $isEdit ? 'Edit Daily Ledger Use' : 'Daily Ledger Use' }}" />

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <x-input label="Date" name="date" id="date" type="date" validateMax max="{{ now()->toDateString() }}" required value="{{ old('date', $isEdit ? $ledgerEntry->date->toDateString() : '') }}" />

                    <x-select
                        label="Case"
                        name="case"
                        id="case"
                        :options="$case_options"
                        value="{{ old('case', $isEdit ? $ledgerEntry->case : '') }}"
                        required
                        showDefault
                    />

                    <x-input label="Amount" name="amount" id="amount" type="amount" placeholder="Enter amount" required dataValidate="required|amount" value="{{ old('amount', $isEdit ? $ledgerEntry->amount : '') }}" />

                    <x-input label="Remarks" name="remarks" id="remarks" placeholder="Enter remarks (optional)" value="{{ old('remarks', $isEdit ? $ledgerEntry->remarks : '') }}" />
                </div>

                <div class="w-full flex justify-end mt-4">
                    <button type="submit"
                        class="px-6 py-1 bg-[var(--bg-success)] border border-[var(--bg-success)] text-[var(--text-success)] font-medium text-nowrap rounded-lg hover:bg-[var(--h-bg-success)] transition-all 0.3s ease-in-out cursor-pointer">
                        <i class='fas fa-save mr-1'></i> {{ $isEdit ? 'Update Use' : 'Save Use' }}
                    </button>
                </div>
            </form>
        @endif
    </div>

@endsection

@push('page-scripts')
<script defer src="{{ asset('js/pages/daily-ledger-create.js') }}"></script>
<script>
        window.__dailyLedgerCreate = {
            dailyLedgerType: @json($dailyLedgerType),
            csrfToken: @json(csrf_token()),
            isEdit: @json($isEdit),
        };
    </script>
@endpush
