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
                    <table class="w-full min-w-[78rem] text-sm">
                        <thead class="bg-[var(--h-bg-color)]">
                            <tr>
                                <th class="p-3 text-left">Module / Page</th>
                                <th class="p-3 text-left">Group</th>
                                <th class="p-3 text-left">Enabled for this branch</th>
                                <th class="p-3 text-left">Allow user switching</th>
                                <th class="p-3 text-left">Use branch branding</th>
                                <th class="p-3 text-left">Filter records by branch</th>
                                <th class="p-3 text-left">Status</th>
                                <th class="p-3 text-left">Warning / dependency notes</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-600/40">
                            @foreach ($moduleRegistry as $moduleKey => $module)
                                @php
                                    $setting = $moduleSettings->get($moduleKey);
                                    $canEnable = (bool) ($module['branchable'] ?? false);
                                    $canFilter = (bool) ($module['can_filter_records'] ?? false);
                                    $canBrand = (bool) ($module['can_use_branch_branding'] ?? false);
                                    $requiresConfirmation = $canEnable && ! (bool) ($module['safe_default_enabled'] ?? false);
                                    $confirmText = "Enable branch support for {$module['label']}? Related transaction/report modules should be enabled consistently.";
                                @endphp
                                <tr>
                                    <td class="p-3">
                                        <div class="font-semibold">{{ $module['label'] ?? $moduleKey }}</div>
                                        <div class="text-xs text-[var(--secondary-text)]">{{ $moduleKey }}</div>
                                    </td>
                                    <td class="p-3 text-[var(--secondary-text)]">{{ $module['group'] ?? '-' }}</td>
                                    <td class="p-3">
                                        <input type="hidden" name="modules[{{ $moduleKey }}][branch_enabled]" value="{{ $canEnable ? '0' : (int) (bool) $setting?->branch_enabled }}">
                                        <label class="inline-flex items-center gap-2">
                                            <input
                                                type="checkbox"
                                                name="modules[{{ $moduleKey }}][branch_enabled]"
                                                value="1"
                                                @checked($setting?->branch_enabled)
                                                @disabled(!$canEnable)
                                            >
                                            <span class="text-xs {{ $canEnable ? 'text-[var(--secondary-text)]' : 'text-[var(--border-warning)]' }}">{{ $canEnable ? 'Developer controlled' : 'Global only' }}</span>
                                        </label>
                                    </td>
                                    <td class="p-3">
                                        <input type="hidden" name="modules[{{ $moduleKey }}][allow_user_switching]" value="{{ $canEnable ? '0' : (int) (bool) $setting?->allow_user_switching }}">
                                        <input type="checkbox" name="modules[{{ $moduleKey }}][allow_user_switching]" value="1" @checked($setting?->allow_user_switching) @disabled(!$canEnable)>
                                    </td>
                                    <td class="p-3">
                                        <span class="{{ $badge }} {{ $canBrand ? 'border-[var(--border-success)] bg-[var(--bg-success)] text-[var(--text-success)]' : 'border-gray-600 bg-[var(--h-bg-color)] text-[var(--secondary-text)]' }}">
                                            {{ $canBrand ? 'Available' : 'Main Branch' }}
                                        </span>
                                    </td>
                                    <td class="p-3">
                                        <span class="{{ $badge }} {{ $canFilter ? 'border-[var(--border-success)] bg-[var(--bg-success)] text-[var(--text-success)]' : 'border-gray-600 bg-[var(--h-bg-color)] text-[var(--secondary-text)]' }}">
                                            {{ $canFilter ? 'Available' : 'Not applicable' }}
                                        </span>
                                    </td>
                                    <td class="p-3">
                                        <select name="modules[{{ $moduleKey }}][status]" class="{{ $input }}">
                                            <option value="active" @selected(($setting?->status ?? 'active') === 'active')>Active</option>
                                            <option value="inactive" @selected($setting?->status === 'inactive')>Inactive</option>
                                        </select>
                                    </td>
                                    <td class="p-3 text-xs text-[var(--secondary-text)]">
                                        <div>{{ $module['notes'] ?? '-' }}</div>
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
