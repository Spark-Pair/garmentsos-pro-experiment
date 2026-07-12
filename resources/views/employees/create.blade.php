@extends('app')
@section('title', 'Add Employee | ' . $client_company->name)
@section('content')
@php
    $categories_options = [
        'staff' => ['text' => 'Staff'],
        'worker' => ['text' => 'Worker'],
    ]
@endphp
    <!-- Progress Bar -->
    <div class="mb-5 max-w-3xl mx-auto">
        <x-search-header heading="Add Employee" link linkText="Show Employees" linkHref="{{ route('employees.index') }}"/>
        <x-progress-bar :steps="['Enter Details', 'Upload Image']" :currentStep="1" />
    </div>

    <!-- Form -->
    <form id="form" action="{{ route('employees.store') }}" method="post" enctype="multipart/form-data"
        class="bg-[var(--secondary-bg-color)] text-sm rounded-xl shadow-lg p-8 border border-[var(--glass-border-color)]/20 pt-14 max-w-3xl mx-auto  relative overflow-hidden">
        @csrf
        <x-form-title-bar title="Add Employee" />

        <!-- Step1 : Basic Information -->
        <div class="step1 space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                {{-- employee_category --}}
                <x-select
                    label="Category"
                    name="category"
                    id="category"
                    :options="$categories_options"
                    onchange="trackCategoryChange()"
                    required
                    showDefault
                />

                {{-- employee_type --}}
                <x-select
                    label="Type"
                    name="type_id"
                    id="type"
                    required
                />

                <!-- employee_name -->
                <x-input
                    label="Employee Name"
                    name="employee_name"
                    id="employee_name"
                    placeholder="Enter employee name"
                    required
                    capitalized
                    dataValidate="required|letters"
                />

                <!-- urdu_title -->
                <x-input
                    label="Urdu Title"
                    name="urdu_title"
                    id="urdu_title"
                    placeholder="Enter urdu title"
                />

                {{-- employee_phone_number --}}
                <x-input
                    label="Phone Number"
                    name="phone_number"
                    id="phone_number"
                    placeholder="Enter phone number"
                    required
                    dataValidate="required|phone"
                />

                {{-- employee_joining_date --}}
                <x-input
                    label="Joining Date"
                    name="joining_date"
                    id="joining_date"
                    min="2024-01-01"
                    validateMin
                    max="{{ now()->toDateString() }}"
                    validateMax
                    type="date"
                    required
                />

                {{-- employee_cnic --}}
                <x-input
                    label="C.N.I.C No."
                    name="cnic_no"
                    id="cnic_no"
                    placeholder="Enter C.N.I.C No."
                    capitalized
                />

                {{-- employee_salary --}}
                <x-input
                    label="Salary"
                    name="salary"
                    id="salary"
                    placeholder="Enter salary"
                    type="amount"
                    dataValidate="amount"
                    disabled
                    capitalized
                />
            </div>
        </div>

        <!-- Step 2: Production Details -->
        <div class="step2 hidden space-y-6 ">
            <x-file-upload id="profile_picture" name="profile_picture" placeholder="{{ asset('images/image_icon.png') }}"
                uploadText="Upload Profile Picture" />
        </div>
    </form>

@endsection

@push('left-actions-after')
    <x-module-branch-selector module-key="employees" />
@endpush

@push('page-scripts')
<script defer src="{{ asset('js/pages/employees-create.js') }}"></script>
<script>
        window.__employeesCreate = {
            allTypes: @json($all_types),
        };
    </script>
@endpush
