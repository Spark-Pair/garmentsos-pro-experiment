@extends('app')

@section('title', 'Branch Details | ' . $client_company->name)

@section('content')
    @php
        $panel = 'bg-[var(--secondary-bg-color)] text-sm rounded-xl shadow-lg p-7 border border-[var(--h-bg-color)] pt-12 relative overflow-hidden';
        $input = 'w-full rounded-lg border border-[var(--h-bg-color)] bg-[var(--h-bg-color)] px-3 py-2 text-sm text-[var(--text-color)] outline-none focus:border-[var(--primary-color)]';
        $button = 'px-4 py-2 bg-[var(--primary-color)] text-[var(--text-color)] font-medium text-nowrap rounded-lg hover:bg-[var(--h-primary-color)] hover:scale-95 transition-all duration-300 ease-in-out cursor-pointer';
        $secondary = 'px-3 py-1.5 bg-[var(--h-bg-color)] border border-gray-600 text-[var(--secondary-text)] font-medium text-nowrap rounded-lg hover:bg-[var(--secondary-bg-color)] transition-all duration-300 ease-in-out cursor-pointer';
        $badge = 'inline-flex items-center rounded-lg border px-2.5 py-1 text-xs font-semibold';
    @endphp

    <div class="max-w-6xl mx-auto w-full">
        <x-search-header heading="Branch Details" link linkText="Back to Branches" linkHref="{{ route('developer.branches.index') }}" />
    </div>

    <section class="text-center mx-auto">
        <div class="show-box mx-auto w-[80%] h-[70vh] bg-[var(--secondary-bg-color)] border border-[var(--glass-border-color)]/20 rounded-xl shadow pt-8.5 relative">
            <x-form-title-bar title="Branch Details" />
            <div class="details h-full z-40">
                <div class="container-parent h-full">
                    <div class="card_container px-4 h-full flex flex-col">
                        <div class="overflow-y-auto grow my-scrollbar-2 space-y-4 pb-24 pr-1 text-left">

        <section class="{{ $panel }}">
            <x-form-title-bar title="Business Identity" />
            <div class="flex flex-col gap-5 md:flex-row md:items-start md:justify-between">
                <div class="space-y-2">
                    <div class="flex items-center gap-3">
                        @if ($branch->logo_path)
                            <img src="{{ asset('storage/' . $branch->logo_path) }}" alt="{{ $branch->name }}" class="h-14 w-14 rounded-lg object-contain bg-white p-1">
                        @endif
                        <div>
                            <h2 class="text-lg font-semibold">{{ $branch->display_name ?: $branch->name }}</h2>
                            <p class="text-xs text-[var(--secondary-text)]">{{ $branch->code }} | Prefix {{ $branch->prefix ?: '-' }} | {{ $branch->city ?: '-' }}</p>
                        </div>
                    </div>
                    <p class="text-sm text-[var(--secondary-text)]">{{ $branch->address ?: 'No address saved.' }}</p>
                    <div class="grid grid-cols-1 gap-2 text-sm md:grid-cols-2">
                        <div>Phone: {{ $branch->phone ?: '-' }}</div>
                        <div>Email: {{ $branch->email ?: '-' }}</div>
                        <div>NTN/CNIC: {{ $branch->ntn_cnic ?: '-' }}</div>
                        <div>STRN/SNTN: {{ $branch->strn_sntn ?: '-' }}</div>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    <span class="{{ $badge }} {{ $branch->status === 'active' ? 'border-[var(--border-success)] bg-[var(--bg-success)] text-[var(--text-success)]' : 'border-gray-600 bg-[var(--h-bg-color)] text-[var(--secondary-text)]' }}">{{ $branch->is_main ? 'Main Branch' : ucfirst($branch->status) }}</span>
                    <a href="{{ route('developer.branches.edit', $branch) }}" class="{{ $secondary }}">Edit</a>
                    @if (!$branch->is_main)
                        <form method="POST" action="{{ route('developer.branches.status', $branch) }}">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="status" value="{{ $branch->status === 'active' ? 'inactive' : 'active' }}">
                            <button type="submit" class="{{ $secondary }}">{{ $branch->status === 'active' ? 'Deactivate' : 'Activate' }}</button>
                        </form>
                    @endif
                </div>
            </div>
        </section>

        <section class="{{ $panel }}">
            <x-form-title-bar title="Branch Module Settings" />
            <form method="POST" action="{{ route('developer.branches.modules') }}">
                @csrf
                <input type="hidden" name="branch_id" value="{{ $branch->id }}">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[108rem] text-sm">
                        <thead class="bg-[var(--h-bg-color)]">
                            <tr>
                                <th class="p-3 text-left">Module / Page</th>
                                <th class="p-3 text-left">Registry</th>
                                <th class="p-3 text-left">Group</th>
                                <th class="p-3 text-left">Route / Page</th>
                                <th class="p-3 text-left">Branch Enabled</th>
                                <th class="p-3 text-left">Branch Switching</th>
                                <th class="p-3 text-left">Multi Branch</th>
                                <th class="p-3 text-left">Record Filtering</th>
                                <th class="p-3 text-left">Branch Branding</th>
                                <th class="p-3 text-left">Serial Prefix</th>
                                <th class="p-3 text-left">Default Order Discount (%)</th>
                                <th class="p-3 text-left">Doc Identity Prefix</th>
                                <th class="p-3 text-left">Status</th>
                                <th class="p-3 text-left">Notes</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-600/40">
                            @foreach ($moduleRegistry as $moduleKey => $module)
                                @php
                                    $setting = $moduleSettings->get($moduleKey);
                                    $configurable = (bool) ($module['configurable_by_developer'] ?? true);
                                    $supportsSwitching = (bool) ($module['supports_branch_selector'] ?? false);
                                    $supportsFiltering = (bool) ($module['supports_record_filtering'] ?? $module['can_filter_records'] ?? false);
                                    $hasBranchIdSupport = (bool) ($module['has_branch_id_support'] ?? false);
                                    $canBrand = (bool) ($module['supports_branch_branding'] ?? $module['can_use_branch_branding'] ?? false);
                                    $canMulti = (bool) ($module['supports_multi_branch_selector'] ?? false);
                                    $canSerialPrefix = (bool) ($module['supports_serial_prefix'] ?? $module['supports_branch_serial_prefix'] ?? false);
                                    $canDocPrefix = (bool) ($module['supports_doc_identity_prefix'] ?? false);
                                    $docIdentityPrefix = $module['doc_identity_prefix'] ?? '';
                                    $isSystemModule = (bool) ($module['is_system_module'] ?? false);
                                    $isMainDefault = (bool) $branch->is_main;
                                    $enabled = $setting ? (bool) $setting->branch_enabled : $isMainDefault;
                                    $switching = $setting ? (bool) $setting->allow_user_switching : $isMainDefault;
                                    $metadata = is_array($setting?->metadata) ? $setting->metadata : [];
                                    $filtering = array_key_exists('record_filtering_enabled', $metadata)
                                        ? (bool) $metadata['record_filtering_enabled']
                                        : ($supportsFiltering && $hasBranchIdSupport);
                                    $defaultOrderDiscount = max(0, min(100, (int) ($metadata['default_order_discount_percent'] ?? 0)));
                                    $filterWarning = $supportsSwitching && ! $hasBranchIdSupport && ! $isSystemModule;
                                @endphp
                                <tr>
                                    <td class="p-3">
                                        <div class="font-semibold">{{ $module['label'] ?? $moduleKey }}</div>
                                        <div class="text-xs text-[var(--secondary-text)]">{{ $moduleKey }}</div>
                                        @if ($isMainDefault)
                                            <div class="mt-1"><span class="{{ $badge }} border-[var(--border-success)] bg-[var(--bg-success)] text-[var(--text-success)]">Main Branch default</span></div>
                                        @elseif ($isSystemModule)
                                            <div class="mt-1"><span class="{{ $badge }} border-gray-600 bg-[var(--h-bg-color)] text-[var(--secondary-text)]">System Page</span></div>
                                        @endif
                                    </td>
                                    <td class="p-3">
                                        <div class="flex flex-col gap-1">
                                            <span class="{{ $badge }} {{ ($module['configured'] ?? false) ? 'border-[var(--border-success)] bg-[var(--bg-success)] text-[var(--text-success)]' : 'border-[var(--border-warning)] bg-[var(--h-bg-color)] text-[var(--border-warning)]' }}">
                                                {{ ($module['configured'] ?? false) ? 'Configured' : 'Needs Configuration' }}
                                            </span>
                                            @if ($module['discovered'] ?? false)
                                                <span class="{{ $badge }} border-gray-600 bg-[var(--h-bg-color)] text-[var(--secondary-text)]">Detected</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="p-3 text-[var(--secondary-text)]">{{ $module['group'] ?? '-' }}</td>
                                    <td class="p-3 text-xs text-[var(--secondary-text)]">
                                        <div>{{ $module['page_reference'] ?? '-' }}</div>
                                        <div class="mt-1">{{ collect($module['route_prefixes'] ?? [])->join(', ') ?: '-' }}</div>
                                    </td>
                                    <td class="p-3">
                                        <input type="hidden" name="modules[{{ $moduleKey }}][branch_enabled]" value="0">
                                        <label class="inline-flex items-center gap-2">
                                            <input
                                                type="checkbox"
                                                name="modules[{{ $moduleKey }}][branch_enabled]"
                                                value="1"
                                                @checked($enabled)
                                            >
                                            <span class="text-xs {{ $configurable ? 'text-[var(--secondary-text)]' : 'text-[var(--border-warning)]' }}">{{ $configurable ? 'Developer controlled' : 'System page' }}</span>
                                        </label>
                                    </td>
                                    <td class="p-3">
                                        <input type="hidden" name="modules[{{ $moduleKey }}][allow_user_switching]" value="0">
                                        <label class="inline-flex items-center gap-2">
                                            <input type="checkbox" name="modules[{{ $moduleKey }}][allow_user_switching]" value="1" @checked($switching && $supportsSwitching)>
                                            <span class="text-xs {{ $supportsSwitching ? 'text-[var(--secondary-text)]' : 'text-[var(--border-warning)]' }}">{{ $supportsSwitching ? 'Switcher allowed' : 'No branch context' }}</span>
                                        </label>
                                    </td>
                                    <td class="p-3">
                                        <label class="inline-flex items-center gap-2 text-xs text-[var(--secondary-text)]">
                                            <input type="checkbox" disabled @checked($canMulti)>
                                            <span>{{ $canMulti ? 'Multi selector configured' : 'Single branch selector' }}</span>
                                        </label>
                                    </td>
                                    <td class="p-3">
                                        <input type="hidden" name="modules[{{ $moduleKey }}][record_filtering_enabled]" value="0">
                                        <label class="inline-flex items-center gap-2 text-xs {{ $supportsFiltering && $hasBranchIdSupport ? 'text-[var(--secondary-text)]' : 'text-[var(--border-warning)]' }}">
                                            <input
                                                type="checkbox"
                                                name="modules[{{ $moduleKey }}][record_filtering_enabled]"
                                                value="1"
                                                @disabled(!($supportsFiltering && $hasBranchIdSupport))
                                                @checked($filtering && $supportsFiltering && $hasBranchIdSupport)
                                            >
                                            <span>
                                                @if ($supportsFiltering && $hasBranchIdSupport)
                                                    {{ $filtering ? 'Filtering enabled' : 'Global records' }}
                                                @elseif ($supportsSwitching)
                                                    Needs branch_id review
                                                @else
                                                    System/global page
                                                @endif
                                            </span>
                                        </label>
                                    </td>
                                    <td class="p-3">
                                        <label class="inline-flex items-center gap-2 text-xs text-[var(--secondary-text)]">
                                            <input type="checkbox" disabled @checked($canBrand)>
                                            <span>{{ $canBrand ? 'Branch branding configured' : 'Uses default branding' }}</span>
                                        </label>
                                    </td>
                                    <td class="p-3">
                                        <label class="inline-flex items-center gap-2 text-xs text-[var(--secondary-text)]">
                                            <input type="checkbox" disabled @checked($canSerialPrefix)>
                                            <span>{{ $canSerialPrefix ? 'Serial prefix configured' : 'No serial prefix' }}</span>
                                        </label>
                                    </td>
                                    <td class="p-3">
                                        @if ($moduleKey === 'orders')
                                            <input
                                                type="number"
                                                min="0"
                                                max="100"
                                                step="1"
                                                name="modules[{{ $moduleKey }}][default_order_discount_percent]"
                                                value="{{ $defaultOrderDiscount }}"
                                                class="{{ $input }} min-w-[10rem] text-xs"
                                            >
                                        @else
                                            <span class="text-xs text-[var(--secondary-text)]">-</span>
                                        @endif
                                    </td>
                                    <td class="p-3">
                                        <input
                                            type="text"
                                            readonly
                                            value="{{ $canDocPrefix ? ($docIdentityPrefix ?: 'Configured') : 'Default document identity' }}"
                                            class="{{ $input }} min-w-[12rem] text-xs"
                                        >
                                    </td>
                                    <td class="p-3">
                                        <select name="modules[{{ $moduleKey }}][status]" class="{{ $input }}">
                                            <option value="active" @selected(($setting?->status ?? 'active') === 'active')>Active</option>
                                            <option value="inactive" @selected($setting?->status === 'inactive')>Inactive</option>
                                        </select>
                                    </td>
                                    <td class="p-3 text-xs text-[var(--secondary-text)]">
                                        <div>{{ $module['notes'] ?? '-' }}</div>
                                        @if ($filterWarning)
                                            <div class="mt-1 text-[var(--border-warning)]">Branch switching available, but record filtering requires branch_id support/review.</div>
                                        @endif
                                        @if (!empty($module['dependencies']))
                                            <div class="mt-1 text-[var(--border-warning)]">{{ $module['dependencies'] }}</div>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <button type="submit" class="{{ $button }} mt-4">Save Module Settings</button>
            </form>
        </section>

        <section class="{{ $panel }}">
            <x-form-title-bar title="Access / Permissions" />
            <div class="grid grid-cols-1 gap-5 xl:grid-cols-2">
                <form method="POST" action="{{ route('developer.branches.access') }}" class="space-y-4">
                    @csrf
                    <input type="hidden" name="branch_id" value="{{ $branch->id }}">
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                        <select name="role" class="{{ $input }}">
                            @foreach ($roleLabels as $role)
                                <option value="{{ $role }}">{{ str_replace('_', ' ', ucfirst($role)) }}</option>
                            @endforeach
                        </select>
                        <select name="user_id" class="{{ $input }}">
                            <option value="">Role-based access</option>
                            @foreach ($users as $user)
                                <option value="{{ $user->id }}">{{ $user->name ?? $user->username }} ({{ $user->role }})</option>
                            @endforeach
                        </select>
                        <select name="module_key" class="{{ $input }} md:col-span-2">
                            <option value="">All modules</option>
                            @foreach ($moduleLabels as $moduleKey => $label)
                                <option value="{{ $moduleKey }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-3 text-sm md:grid-cols-3">
                        @foreach (['can_view' => 'View', 'can_create' => 'Create', 'can_update' => 'Update', 'can_delete' => 'Delete', 'can_switch' => 'Switch', 'can_manage' => 'Manage'] as $field => $label)
                            <label class="flex items-center gap-2 rounded-lg border border-[var(--h-bg-color)] bg-[var(--h-bg-color)]/60 px-3 py-2">
                                <input type="checkbox" name="{{ $field }}" value="1" @checked(in_array($field, ['can_view', 'can_switch'], true))>
                                <span>{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                    <button type="submit" class="{{ $button }}">Save Access</button>
                </form>

                <div class="overflow-x-auto">
                    <table class="w-full min-w-[42rem] text-sm">
                        <thead class="bg-[var(--h-bg-color)]">
                            <tr>
                                <th class="p-3 text-left">Role/User</th>
                                <th class="p-3 text-left">Module</th>
                                <th class="p-3 text-left">Access</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-600/40">
                            @forelse ($accessRows as $row)
                                <tr>
                                    <td class="p-3">{{ $row->user ? (($row->user->name ?? $row->user->username) . ' (user)') : ($row->role ?: '-') }}</td>
                                    <td class="p-3">{{ $row->module_key ? ($moduleLabels[$row->module_key] ?? $row->module_key) : 'All modules' }}</td>
                                    <td class="p-3 text-xs text-[var(--secondary-text)]">
                                        {{ collect([
                                            $row->can_view ? 'view' : null,
                                            $row->can_create ? 'create' : null,
                                            $row->can_update ? 'update' : null,
                                            $row->can_delete ? 'delete' : null,
                                            $row->can_switch ? 'switch' : null,
                                            $row->can_manage ? 'manage' : null,
                                        ])->filter()->join(', ') ?: '-' }}
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="p-6 text-center text-[var(--secondary-text)]">No access rows yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
