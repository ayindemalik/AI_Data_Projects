@extends('layouts.admin')

@section('title', 'Chat Session #' . $chatSession->id)

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">Chat Session #{{ $chatSession->id }}</h1>
        <a href="{{ route('admin.chat-history.index') }}" class="btn btn-link">← Back to sessions</a>
    </div>

    <div class="card mb-3">
        <div class="card-body d-flex gap-4">
            <div><span class="text-muted small">User:</span> {{ $chatSession->user?->name ?? 'Guest' }}</div>
            <div><span class="text-muted small">Locale:</span> {{ strtoupper($chatSession->locale) }}</div>
            <div><span class="text-muted small">Started:</span> {{ $chatSession->created_at->format('d.m.Y H:i') }}</div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            @foreach ($chatSession->messages as $message)
                <div class="mb-3 {{ $message->role === 'user' ? 'text-end' : '' }}">
                    <div class="d-inline-block text-start p-2 px-3 rounded {{ $message->role === 'user' ? 'bg-primary text-white' : 'bg-light border' }}"
                         style="max-width: 75%; white-space: pre-wrap;">{{ $message->content }}</div>
                    <div class="small text-muted mt-1">
                        {{ $message->created_at->format('H:i:s') }}
                        @if ($message->source)
                            · source: {{ $message->source }}
                        @endif
                        @if ($message->matched_product_ids)
                            · products: {{ implode(', ', $message->matched_product_ids) }}
                        @endif
                        @if ($message->rating === 1)
                            · <span class="badge bg-success">👍 helpful</span>
                        @elseif ($message->rating === -1)
                            · <span class="badge bg-danger">👎 not helpful</span>
                            {{-- An answer the model produced from real product data is a prompt
                                 problem worth acting on; a fallback or price-rule reply is not. --}}
                            @if ($message->isGeneratedAnswer())
                                <span class="badge bg-secondary">prompt issue</span>
                            @elseif ($message->source === 'openai')
                                <span class="badge bg-warning text-dark">no products matched — retrieval issue</span>
                            @else
                                <span class="badge bg-light text-dark">{{ $message->source }} reply — not a prompt issue</span>
                            @endif
                        @endif
                    </div>

                    @if ($message->feedback_note)
                        <div class="alert alert-warning py-2 px-3 mt-1 mb-0 small d-inline-block text-start" style="max-width: 75%;">
                            <strong>User note:</strong> {{ $message->feedback_note }}
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
@endsection
