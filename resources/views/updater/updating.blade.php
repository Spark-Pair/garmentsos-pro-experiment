@extends('app')

@section('title', 'Updating | ' . $client_company->name)

@section('content')
    <div class="mx-auto max-w-xl rounded-xl border border-[var(--border-warning)] bg-[var(--bg-warning)] p-6 text-center text-[var(--text-warning)] shadow-xl">
        <h1 class="text-xl font-semibold">GarmentsOS PRO is updating</h1>
        <p class="mt-3 text-sm">
            {{ $updateLock['message'] ?? 'Please wait until the update is complete.' }}
        </p>
        @if (!empty($updateLock['expires_at']))
            <p class="mt-3 text-xs opacity-80">This update lock expires at {{ $updateLock['expires_at'] }}.</p>
        @endif
    </div>
@endsection
