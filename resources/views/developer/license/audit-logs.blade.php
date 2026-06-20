@extends('app')

@section('title', 'Audit Logs | ' . $client_company->name)

@section('content')
    <div class="w-full max-w-5xl mx-auto space-y-6">
        <div>
            <h1 class="text-2xl font-semibold">Audit Logs</h1>
            <p class="text-[var(--secondary-text)] mt-1">Foundation view for sanitized developer/admin audit events.</p>
        </div>

        <div class="bg-[var(--secondary-bg-color)] border border-gray-700 rounded-lg overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-[var(--h-bg-color)]">
                    <tr>
                        <th class="text-left p-3">Time</th>
                        <th class="text-left p-3">Event</th>
                        <th class="text-left p-3">Module</th>
                        <th class="text-left p-3">User</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr class="border-t border-gray-700">
                            <td class="p-3">{{ $log->occurred_at?->format('Y-m-d H:i') }}</td>
                            <td class="p-3">{{ $log->event_type }}</td>
                            <td class="p-3">{{ $log->module ?? '-' }}</td>
                            <td class="p-3">{{ $log->user_name_snapshot ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="p-3 text-[var(--secondary-text)]">No audit logs yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
