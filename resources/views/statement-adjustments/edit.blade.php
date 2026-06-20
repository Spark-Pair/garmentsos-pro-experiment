@extends('app')
@section('title', 'Edit Balance Entry | ' . $client_company->name)
@section('content')
    <div class="mb-5 max-w-3xl mx-auto fade-in">
        <x-search-header heading="Edit Balance Entry" link linkText="Show Entries"
            linkHref="{{ route('statement-adjustments.index') }}" />
    </div>

    <form id="form" action="{{ route('statement-adjustments.update', $statementAdjustment) }}" method="post"
        class="bg-[var(--secondary-bg-color)] rounded-xl shadow-lg p-8 border border-[var(--h-bg-color)] pt-12 max-w-3xl mx-auto relative overflow-hidden">
        @csrf
        @method('PUT')
        <x-form-title-bar title="Edit Balance Entry" />

        <div class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <x-select label="Category" name="category" id="category" :options="$categoryOptions"
                    :value="old('category', $category)" showDefault onchange="onStatementAdjustmentCategoryChange(this)" />

                <x-select label="Name" name="adjustable_id" id="adjustable_id" :options="[]" showDefault disabled onchange="onStatementAdjustmentAdjustableChange(this)" />

                <x-select label="Entry Type" name="entry_type" id="entry_type" :options="$entryTypeOptions"
                    :value="old('entry_type', $statementAdjustment->entry_type)" showDefault onchange="onStatementAdjustmentEntryTypeChange(this)" />

                <x-input label="Date" name="date" id="date" type="date"
                    value="{{ old('date', $statementAdjustment->date?->format('Y-m-d')) }}" required readonly />

                <x-select label="Transaction" name="direction" id="direction" :options="$directionOptions"
                    :value="old('direction', $statementAdjustment->direction)" showDefault />

                <x-input label="Amount" name="amount" id="amount" type="amount"
                    value="{{ old('amount', $statementAdjustment->amount) }}" placeholder="Enter amount" required dataValidate="required|amount" />

                <div class="col-span-full">
                    <x-input label="Remarks" name="remarks" id="remarks"
                        value="{{ old('remarks', $statementAdjustment->remarks) }}" placeholder="Enter remarks" />
                </div>
            </div>
        </div>

        <div class="w-full flex justify-end mt-6">
            <button type="submit"
                class="px-6 py-1 bg-[var(--bg-success)] border border-[var(--bg-success)] text-[var(--text-success)] font-medium text-nowrap rounded-lg hover:bg-[var(--h-bg-success)] transition-all 0.3s ease-in-out cursor-pointer">
                <i class='fas fa-save mr-1'></i> Save
            </button>
        </div>
    </form>
@endsection

@push('page-scripts')
<script defer src="{{ asset('js/pages/statement-adjustments-create.js') }}"></script>
<script>
    window.__statementAdjustmentsCreate = {
        namesUrl: @json(route('reports.statement.get-names')),
        firstDateUrl: @json(route('statement-adjustments.first-transaction-date')),
        csrfToken: @json(csrf_token()),
        oldCategory: @json(old('category', $category)),
        oldAdjustableId: @json(old('adjustable_id', $statementAdjustment->adjustable_id)),
    };
</script>
@endpush
