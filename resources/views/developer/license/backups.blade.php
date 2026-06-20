@extends('app')

@section('title', 'Backup & Restore | ' . $client_company->name)

@section('content')
    <div class="w-full max-w-5xl mx-auto space-y-6">
        <div>
            <h1 class="text-2xl font-semibold">Backup & Restore</h1>
            <p class="text-[var(--secondary-text)] mt-1">Foundation view only. Existing backup behavior is unchanged.</p>
        </div>

        <div class="bg-[var(--secondary-bg-color)] border border-gray-700 rounded-lg p-5">
            <h2 class="text-lg font-semibold mb-3">Restore Safety Requirements</h2>
            <ul class="space-y-2 text-[var(--secondary-text)]">
                <li>Confirmation required: {{ $restoreRequirements['confirmation_required'] ? 'Yes' : 'No' }}</li>
                <li>Emergency backup before restore: {{ $restoreRequirements['emergency_backup_before_restore'] ? 'Yes' : 'No' }}</li>
                <li>Public backups allowed: {{ $restoreRequirements['public_backups_allowed'] ? 'Yes' : 'No' }}</li>
            </ul>
        </div>

        <div class="bg-[var(--secondary-bg-color)] border border-gray-700 rounded-lg overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-[var(--h-bg-color)]">
                    <tr>
                        <th class="text-left p-3">Started</th>
                        <th class="text-left p-3">Action</th>
                        <th class="text-left p-3">Status</th>
                        <th class="text-left p-3">File</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr class="border-t border-gray-700">
                            <td class="p-3">{{ $log->started_at?->format('Y-m-d H:i') }}</td>
                            <td class="p-3">{{ $log->action }}</td>
                            <td class="p-3">{{ $log->status }}</td>
                            <td class="p-3">{{ $log->filename ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="p-3 text-[var(--secondary-text)]">No backup logs yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
