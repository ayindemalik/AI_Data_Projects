@extends('layouts.admin')

@section('title', 'Chat History')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">Chat History</h1>

        <div class="btn-group btn-group-sm">
            <a href="{{ route('admin.chat-history.index') }}"
               class="btn {{ $onlyNegative ? 'btn-outline-secondary' : 'btn-secondary' }}">All sessions</a>
            <a href="{{ route('admin.chat-history.index', ['negative' => 1]) }}"
               class="btn {{ $onlyNegative ? 'btn-danger' : 'btn-outline-danger' }}">👎 With negative feedback</a>
        </div>
    </div>

    <div class="card">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>#</th>
                    <th>User</th>
                    <th>Locale</th>
                    <th>Messages</th>
                    <th>Feedback</th>
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
                        <td>
                            @if ($session->negative_feedback_count)
                                <span class="badge bg-danger">👎 {{ $session->negative_feedback_count }}</span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td class="text-muted">{{ $session->created_at->format('d.m.Y H:i') }}</td>
                        <td class="text-muted">{{ $session->source }}</td>
                        <td class="text-end">
                            <a href="{{ route('admin.chat-history.show', $session) }}" class="btn btn-sm btn-outline-secondary">View</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-muted py-4">
                        {{ $onlyNegative ? 'No sessions with negative feedback.' : 'No chat sessions yet.' }}
                    </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">{{ $sessions->links() }}</div>
@endsection
