@extends('app')
@section('title', 'Add Rates | ' . $client_company->name)
@section('content')
    <!-- Main Content -->

    <div class="max-w-2xl mx-auto">
        <x-search-header heading="Add Setup" link linkText="Show Rates" linkHref="{{ route('rates.index') }}" />
        <x-progress-bar :steps="['Select Type', 'Enter Rates']" :currentStep="1" />
    </div>

    <!-- Form -->
    <form id="form" action="{{ route('rates.store') }}" method="post"
        class="bg-[var(--secondary-bg-color)] rounded-xl shadow-lg p-8 border border-[var(--h-bg-color)] pt-12 max-w-2xl mx-auto relative overflow-hidden">
        @csrf
        <x-form-title-bar title="Add Rates" />

        <!-- Step 1: Basic Information -->
        <div class="step1 space-y-4 ">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- type -->
                <x-select label="Type" name="type_id" id="type" :options="$type_options" showDefault
                    onchange="trackTypeStatus(this)" />

                <!-- effective_date -->
                <x-input label="Effective Date" name="effective_date" id="effective_date" type="date" validateMin
                    min="2024-01-01" required onchange="trackEffectiveDateState(this)" disabled />
            </div>
        </div>

        <!-- Step 2: Basic Information -->
        <div class="step2 space-y-4 hidden">
            <div class="inputsWrapper grid grid-cols-1 md:grid-cols-1 gap-4">
            </div>
        </div>
    </form>
    @php
        $cuttingFieldsHtml = view('components.input', [
            'label' => 'Selet Categories',
            'id' => 'select_categories',
            'required' => true,
            'placeholder' => 'Select Categories',
            'readonly' => true,
            'onclick' => "generateSelectCredentialsModal('categories')",
        ])->render();
        $cuttingFieldsHtml .= view('components.input', [
            'label' => 'Selet Seasons',
            'id' => 'select_seasons',
            'required' => true,
            'placeholder' => 'Select Seasons',
            'readonly' => true,
            'onclick' => "generateSelectCredentialsModal('seasons')",
        ])->render();
        $cuttingFieldsHtml .= view('components.input', [
            'label' => 'Selet Sizes',
            'id' => 'select_sizes',
            'required' => true,
            'placeholder' => 'Select Sizes',
            'readonly' => true,
            'onclick' => "generateSelectCredentialsModal('sizes')",
        ])->render();
        $cuttingFieldsHtml .= '<input type="hidden" name="categories" />';
        $cuttingFieldsHtml .= '<input type="hidden" name="seasons" />';
        $cuttingFieldsHtml .= '<input type="hidden" name="sizes" />';
        $cuttingFieldsHtml .= '<div class="grid grid-cols-1 md:grid-cols-2 gap-4">';
        $cuttingFieldsHtml .= view('components.input', [
            'label' => 'Title',
            'name' => 'title',
            'id' => 'title',
            'placeholder' => 'Enter title',
            'required' => true,
        ])->render();
        $cuttingFieldsHtml .= view('components.input', [
            'label' => 'Rate',
            'name' => 'rate',
            'id' => 'rate',
            'type' => 'number',
            'placeholder' => 'Enter rate',
            'required' => true,
        ])->render();
        $cuttingFieldsHtml .= '</div>';
    @endphp

@endsection

@push('page-scripts')
<script defer src="{{ asset('js/pages/rates-add.js') }}"></script>
<script>
        window.__ratesAdd = {
            articleDetails: @json(app('article')),
            cuttingFieldsHtml: @json($cuttingFieldsHtml),
        };
    </script>
@endpush
