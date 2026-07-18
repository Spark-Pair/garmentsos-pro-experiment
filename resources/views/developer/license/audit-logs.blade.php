@extends('app')

@section('title', 'Audit Logs | ' . $client_company->name)

@section('content')
    @php
        $panel = 'rounded-2xl border border-[var(--h-bg-color)] bg-[var(--secondary-bg-color)] shadow-sm';
        $badge = 'inline-flex items-center rounded-xl border border-[var(--h-bg-color)] bg-[var(--h-bg-color)]/55 px-3 py-1.5 text-xs font-semibold text-[var(--secondary-text)]';
        $foundationReady = $foundationReady ?? true;
    @endphp

    <div class="w-full max-w-6xl mx-auto p-4 md:p-6 space-y-6 text-[var(--text-color)]">
        <header class="{{ $panel }} p-5">
            <x-developer-panel-title title="Audit Logs" description="Sanitized developer/admin audit events. Sensitive payloads, secrets, and raw license keys are not displayed here." class="mb-0 border-b-0 pb-0">
                <span class="{{ $badge }}">{{ $logs->count() }} events</span>
            </x-developer-panel-title>
        </header>

        @if (!$foundationReady)
            <section class="rounded-xl border border-[var(--border-warning)] bg-[var(--bg-warning)] p-4 text-sm text-[var(--text-warning)]">
                Audit log tables are not available in this local database yet. Run migrations on a verified staging/client-copy database to enable audit history.
            </section>
        @endif

        <section class="{{ $panel }} overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[44rem] text-sm">
                    <thead class="bg-[var(--h-bg-color)]">
                        <tr>
                            <th class="text-left p-3">Time</th>
                            <th class="text-left p-3">Event</th>
                            <th class="text-left p-3">Module</th>
                            <th class="text-left p-3">User</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[var(--glass-border-color)]/10">
                        @forelse ($logs as $log)
                            <tr>
                                <td class="p-3">{{ $log->occurred_at?->format('Y-m-d H:i') }}</td>
                                <td class="p-3 font-mono text-xs">{{ $log->event_type }}</td>
                                <td class="p-3">{{ $log->module ?? '-' }}</td>
                                <td class="p-3">{{ $log->user_name_snapshot ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="p-8 text-center text-[var(--secondary-text)]">
                                    No audit logs yet.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
@endsection
