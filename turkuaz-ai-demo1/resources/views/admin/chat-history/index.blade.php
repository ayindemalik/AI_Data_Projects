@extends('layouts.admin')

@section('title', 'Chat History')

@section('content')
    <h1 class="h3 mb-3">Chat History</h1>

    <div class="card">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>#</th>
                    <th>User</th>
                    <th>Locale</th>
                    <th>Messages</th>
                    <th>Started</th>
                    <th>Source</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($sessions as $session)
                
                    <tr>
                        <td>{{ $session->id }}</td>
                        <td>{{ $session->user?->name ?? 'Guest' }}</td>
                        <td class="text-muted">{{ strtoupper($session->locale) }}</td>
                        <td>{{ $session->messages_count }}</td>
                        <td class="text-muted">{{ $session->created_at->format('d.m.Y H:i') }}</td>
                        <td class="text-muted">{{ $session->source }}</td>
                        <td class="text-end">
                            <a href="{{ route('admin.chat-history.show', $session) }}" class="btn btn-sm btn-outline-secondary">View</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">No chat sessions yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">{{ $sessions->links() }}</div>
@endsection
