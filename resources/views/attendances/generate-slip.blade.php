@extends('app')
@section('title', 'Generate Slip | ' . $client_company->name)
@section('content')
    <!-- Main Content -->
    <!-- Progress Bar -->
    <div class="mb-5 max-w-4xl mx-auto">
        <x-search-header heading="Generate Slip" link linkText="Manage Salary" linkHref="{{ route('attendances.manage-salary') }}"/>
        <x-progress-bar :steps="['Generate Slip', 'Preview']" :currentStep="1" />
    </div>

    <!-- Form -->
    <form id="form" action="{{ route('attendances.generate-slip-post') }}" method="post" enctype="multipart/form-data"
        class="bg-[var(--secondary-bg-color)] text-sm rounded-xl shadow-lg p-8 border border-[var(--glass-border-color)]/20 pt-14 max-w-4xl mx-auto relative overflow-hidden">
        @csrf
        <x-form-title-bar title="Generate Slip" />

        <!-- Step 1: Generate shipment -->
        <div class="step1 space-y-4 ">
            <div class="">
                {{-- month --}}
                <x-input label="Month" name="month" id="month" type="month" required />
            </div>
        </div>

        <!-- Step 2: view shipment -->
        <div class="step2 hidden space-y-4 text-black h-[35rem] overflow-y-auto my-scrollbar-2 bg-white rounded-md">
            <div id="preview-container" class="w-[297mm] h-[210mm] mx-auto overflow-hidden relative">
                <div id="preview" class="preview flex flex-col h-full">
                    <h1 class="text-[var(--border-error)] font-medium text-center mt-5">No Preview avalaible.</h1>
                </div>
            </div>
        </div>
    </form>

@endsection

@push('page-scripts')
<script defer src="{{ asset('js/pages/attendances-generate-slip.js') }}"></script>
<script>
        window.__attendancesGenerateSlip = {
            companyName: @json($client_company->name),
        };
    </script>
@endpush
