@props(['moduleKey', 'mode' => 'single'])

@php
    $branchService = app(\App\Services\Branches\ModuleBranchService::class);
    $isMulti = $mode === 'multiple' && $branchService->supportsMultiBranchSelector($moduleKey);
    $canRender = $branchService->canShowSelector($moduleKey);
    $branches = $canRender ? $branchService->availableBranchesForModule($moduleKey) : collect();
    $selected = $canRender ? $branchService->selectedBranchForModule($moduleKey) : null;
    $selectedIds = $isMulti ? $branchService->selectedBranchIdsForModule($moduleKey) : collect([$selected?->id])->filter()->all();
    $summary = $isMulti ? $branchService->selectedBranchSummaryForModule($moduleKey) : ($selected?->name ?? 'Select Branch');
@endphp

@if ($canRender)
    @once
        <style>
            .branch-switcher > summary::-webkit-details-marker {
                display: none;
            }

            .branch-switcher > summary::marker {
                content: "";
            }
        </style>
    @endonce

    <details
        class="branch-switcher relative h-full"
        title="Current branch: {{ $selected?->name ?? 'Select branch' }}"
    >
        <summary
            aria-label="Switch branch"
            class="border border-gray-600 bg-[var(--bg-color)] h-full rounded-xl cursor-pointer flex items-center justify-end p-1 pr-3 overflow-hidden transition-all duration-300 ease-in-out list-none"
            style="list-style: none;"
        >
            <div class="flex items-center justify-center bg-[var(--h-bg-color)] rounded-lg p-2">
                <i class="fas fa-code-branch text-xs text-[var(--secondary-text)]"></i>
            </div>
            <span class="ml-2 max-w-[190px] overflow-hidden text-ellipsis whitespace-nowrap text-xs font-semibold text-[var(--secondary-text)]">
                {{ $summary }}
            </span>
            <i class="fas fa-chevron-down ml-2 text-[10px] text-[var(--secondary-text)]"></i>
        </summary>

        <div class="absolute left-0 top-[calc(100%+0.5rem)] z-50 min-w-[220px] max-w-[280px] rounded-xl border border-gray-600 bg-[var(--bg-color)] p-2 shadow-xl">
            <div class="px-2 pb-2 text-[11px] font-semibold uppercase tracking-wide text-[var(--secondary-text)]">
                {{ $isMulti ? 'Select branches' : 'Switch branch' }}
            </div>
            <div class="space-y-1">
                @if ($isMulti)
                    <label class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-left text-xs text-[var(--secondary-text)] hover:bg-[var(--h-bg-color)]">
                        <input type="checkbox" data-module-branch-all class="h-4 w-4" @checked(count($selectedIds) === $branches->count())>
                        <span>All Branches</span>
                    </label>
                    @foreach ($branches as $branch)
                        <label class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-left text-xs text-[var(--secondary-text)] hover:bg-[var(--h-bg-color)]">
                            <input
                                type="checkbox"
                                data-module-branch-checkbox
                                data-branch-id="{{ $branch->id }}"
                                class="h-4 w-4"
                                @checked(in_array((int) $branch->id, $selectedIds, true))
                            >
                            <span class="overflow-hidden text-ellipsis whitespace-nowrap">{{ $branch->name }}</span>
                        </label>
                    @endforeach
                    <div class="mt-2 flex gap-2 border-t border-gray-600 pt-2">
                        <button
                            type="button"
                            data-module-branch-apply
                            data-module-key="{{ $moduleKey }}"
                            data-selection-mode="multiple"
                            class="flex-1 rounded-lg bg-[var(--primary-color)] px-3 py-2 text-xs font-semibold text-[var(--text-color)] transition-colors duration-200 hover:bg-[var(--h-primary-color)]"
                        >
                            Apply
                        </button>
                        <button type="button" onclick="this.closest('details').removeAttribute('open')" class="rounded-lg border border-gray-600 px-3 py-2 text-xs font-semibold text-[var(--secondary-text)]">
                            Cancel
                        </button>
                    </div>
                @else
                    @foreach ($branches as $branch)
                        <button
                            type="button"
                            data-module-branch-option
                            data-module-key="{{ $moduleKey }}"
                            data-branch-id="{{ $branch->id }}"
                            class="flex w-full items-center justify-between gap-3 rounded-lg px-3 py-2 text-left text-xs transition-colors duration-200 {{ $selected?->id === $branch->id ? 'bg-[var(--h-bg-color)] font-semibold text-[var(--text-color)]' : 'text-[var(--secondary-text)] hover:bg-[var(--h-bg-color)]' }}"
                            @disabled($selected?->id === $branch->id)
                        >
                            <span class="overflow-hidden text-ellipsis whitespace-nowrap">{{ $branch->name }}</span>
                            @if ($selected?->id === $branch->id)
                                <i class="fas fa-check text-[10px] text-[var(--primary-color)]"></i>
                            @endif
                        </button>
                    @endforeach
                @endif
            </div>
        </div>
    </details>
@endif
