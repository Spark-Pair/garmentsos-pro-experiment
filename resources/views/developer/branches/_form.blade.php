@php
    $input = 'w-full rounded-lg border border-[var(--h-bg-color)] bg-[var(--h-bg-color)] px-3 py-2 text-sm text-[var(--text-color)] outline-none focus:border-[var(--primary-color)]';
    $button = 'px-4 py-2 bg-[var(--primary-color)] text-[var(--text-color)] font-medium text-nowrap rounded-lg hover:bg-[var(--h-primary-color)] hover:scale-95 transition-all duration-300 ease-in-out cursor-pointer';
    $branch = $branch ?? null;
@endphp

@if ($errors->any())
    <div class="rounded-lg border border-[var(--border-error)] bg-[var(--bg-error)] p-4 text-sm text-[var(--text-error)]">
        <div class="font-semibold">Please fix the highlighted fields before continuing.</div>
        <ul class="mt-2 list-disc space-y-1 pl-5">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form method="POST" action="{{ $branch ? route('developer.branches.update', $branch) : route('developer.branches.store') }}" enctype="multipart/form-data" class="grid grid-cols-1 gap-4 md:grid-cols-3">
    @csrf
    @if ($branch)
        @method('PUT')
    @endif

    <input name="name" class="{{ $input }}" value="{{ old('name', $branch?->name) }}" placeholder="Branch name" required>
    <input name="code" class="{{ $input }}" value="{{ old('code', $branch?->code) }}" placeholder="Code optional" @readonly($branch?->is_main)>
    <input name="prefix" class="{{ $input }}" value="{{ old('prefix', $branch?->prefix ?? ($branch?->is_main ? 'MAIN' : '')) }}" placeholder="Document prefix e.g. PZ" required>
    <select name="status" class="{{ $input }}" @disabled($branch?->is_main)>
        <option value="active" @selected(old('status', $branch?->status ?? 'active') === 'active')>Active</option>
        <option value="inactive" @selected(old('status', $branch?->status) === 'inactive')>Inactive</option>
    </select>
    @if ($branch?->is_main)
        <input type="hidden" name="status" value="active">
    @endif

    <input name="display_name" class="{{ $input }}" value="{{ old('display_name', $branch?->display_name) }}" placeholder="Business/company display name">
    <input name="owner_name" class="{{ $input }}" value="{{ old('owner_name', $branch?->owner_name) }}" placeholder="Owner name">
    <input name="phone" class="{{ $input }}" value="{{ old('phone', $branch?->phone) }}" placeholder="Phone">
    <input name="email" type="email" class="{{ $input }}" value="{{ old('email', $branch?->email) }}" placeholder="Email">
    <input name="city" class="{{ $input }}" value="{{ old('city', $branch?->city) }}" placeholder="City">
    <input name="province" class="{{ $input }}" value="{{ old('province', $branch?->province) }}" placeholder="Province">
    <input name="ntn_cnic" class="{{ $input }}" value="{{ old('ntn_cnic', $branch?->ntn_cnic) }}" placeholder="NTN / CNIC">
    <input name="strn_sntn" class="{{ $input }}" value="{{ old('strn_sntn', $branch?->strn_sntn) }}" placeholder="STRN / SNTN">
    <input name="header_text" class="{{ $input }}" value="{{ old('header_text', $branch?->header_text) }}" placeholder="Invoice/report header text">
    <input name="footer_text" class="{{ $input }}" value="{{ old('footer_text', $branch?->footer_text) }}" placeholder="Footer text">
    <input name="logo" type="file" accept="image/*" class="{{ $input }}">
    <textarea name="address" class="{{ $input }} md:col-span-3" placeholder="Address">{{ old('address', $branch?->address) }}</textarea>
    <textarea name="terms_text" class="{{ $input }} md:col-span-3" placeholder="Terms / notes">{{ old('terms_text', $branch?->terms_text) }}</textarea>

    <div class="md:col-span-3">
        <button type="submit" class="{{ $button }}">{{ $branch ? 'Save Branch' : 'Create Branch' }}</button>
    </div>
</form>
