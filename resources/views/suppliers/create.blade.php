@extends('app')
@section('title', 'Add Suppliers | ' . $client_company->name)
@section('content')
    <!-- Progress Bar -->
    <div class="mb-5 max-w-3xl mx-auto">
        <x-search-header heading="Add Supplier" link linkText="Show Suppliers" linkHref="{{ route('suppliers.index') }}"/>
        <x-progress-bar
            :steps="['Enter Details', 'Upload Image']"
            :currentStep="1"
        />
    </div>

    <!-- Form -->
    <form id="form" action="{{ route('suppliers.store') }}" method="post" enctype="multipart/form-data"
        class="bg-[var(--secondary-bg-color)] text-sm rounded-xl shadow-lg p-8 border border-[var(--glass-border-color)]/20 pt-14 max-w-3xl mx-auto  relative overflow-hidden">
        @csrf
        <x-form-title-bar title="Add Supplier" />
        <!-- Step 1: Basic Information -->
        <div class="step1 space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- supplier_name -->
                <x-input
                    label="Supplier Name"
                    name="supplier_name"
                    id="supplier_name"
                    placeholder="Enter supplire name"
                    required
                    capitalized
                    dataValidate="required|friendly"
                />

                <!-- urdu_title -->
                <x-input
                    label="Urdu Title"
                    name="urdu_title"
                    id="urdu_title"
                    placeholder="Enter urdu title"
                    required
                    dataValidate="required|urdu"
                />

                {{-- person name --}}
                <x-input
                    label="Person Name"
                    name="person_name"
                    id="person_name"
                    placeholder="Enter person name"
                    required
                    capitalized
                    dataValidate="required|friendly"
                />

                {{-- supplier_phone_number --}}
                <x-input
                    label="Phone Number"
                    name="phone_number"
                    id="phone_number"
                    placeholder="Enter phone number"
                    required
                    dataValidate="required|phone"
                />

                {{-- supplier_username --}}
                <x-input
                    label="Username"
                    name="username"
                    id="username"
                    type="username"
                    placeholder="Enter username"
                    required
                    data-validate="required|alphanumeric|lowercase|unique:username"
                    data-clean="lowercase|alphanumeric|no-space"
                />

                {{-- supplier_password --}}
                <x-input
                    label="Password"
                    name="password"
                    id="password"
                    type="password"
                    placeholder="Enter password"
                    required
                    dataValidate="required|min:4|alphanumeric|lowercase"
                />

                {{-- supplier_registration_date --}}
                <x-input
                    label="Date"
                    name="date"
                    id="date"
                    min="2024-01-01"
                    validateMin
                    max="{{ now()->toDateString() }}"
                    validateMax
                    type="date"
                    required
                />

                {{-- supplier_category --}}
                <x-select
                    label="Category"
                    id="category_select"
                    :options="$categories_options"
                    required
                    showDefault
                    onchange="trackStateOfCategoryBtn(this)"
                    class="grow"
                    withButton
                    btnId="addCategoryBtn"
                />

                <input type="hidden" name="categories_array" id="categories_array" value="">

                <hr class="col-span-2 border-gray-600">

                <div class="chipsContainer col-span-2">
                    <div id="chips" class="w-full flex gap-2">
                        <div class="chip border border-gray-600 text-gray-300 text-xs rounded-xl py-2 px-4 inline-flex items-center gap-2 mx-auto fade-in">
                            <div class="text tracking-wide text-[var(--secondary-text)]">Please add category</div>
                        </div>
                    </div>
                    <div id="category-error" class="text-[var(--border-error)] text-xs mt-1 hidden transition-all duration-300 ease-in-out"></div>
                </div>
            </div>
        </div>

        <!-- Step 2: Production Details -->
        <div class="step2 hidden space-y-4">
            <x-file-upload
                id="profile_picture"
                name="profile_picture"
                placeholder="{{ asset('images/image_icon.png') }}"
                uploadText="Upload Supplier's Picture"
            />
        </div>
    </form>

@endsection


@push('page-scripts')
<script defer src="{{ asset('js/pages/suppliers-create.js') }}"></script>
<script>
        window.__suppliersCreate = {
            usernames: @json($usernames),
        };
    </script>
@endpush
