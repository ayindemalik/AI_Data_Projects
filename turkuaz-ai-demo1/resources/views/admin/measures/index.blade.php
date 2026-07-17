@extends('layouts.admin')

@section('title', 'Measures')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">Measures</h1>
        <a href="{{ route('admin.measures.create') }}" class="btn btn-primary">+ Add Measure</a>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-center">
                <div class="col-auto">
                    <input type="text" name="q" value="{{ request('q') }}" class="form-control" placeholder="Search by name...">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-outline-secondary">Search</button>
                </div>
                <div class="col-auto">
                    @if (request('trashed'))
                        <a href="{{ route('admin.measures.index', ['q' => request('q')]) }}" class="btn btn-link">Back to active</a>
                    @else
                        <a href="{{ route('admin.measures.index', ['q' => request('q'), 'trashed' => 1]) }}" class="btn btn-link text-danger">View trashed</a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Name (TR)</th>
                    <th>Name (EN)</th>
                    <th>Unit</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($measures as $measure)
                    <tr>
                        <td>{{ $measure->name['tr'] ?? '-' }}</td>
                        <td>{{ $measure->name['en'] ?? '-' }}</td>
                        <td class="text-muted">{{ $measure->unit }}</td>
                        <td>
                            <span class="badge {{ $measure->status === 'active' ? 'bg-success' : 'bg-secondary' }}">
                                {{ ucfirst($measure->status) }}
                            </span>
                        </td>
                        <td class="text-end">
                            @if ($showTrashed)
                                <form action="{{ route('admin.measures.restore', $measure->id) }}" method="POST" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-success">Restore</button>
                                </form>
                                <form action="{{ route('admin.measures.force-delete', $measure->id) }}" method="POST" class="d-inline"
                                      onsubmit="return confirm('Permanently delete this measure? This cannot be undone.');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Delete Permanently</button>
                                </form>
                            @else
                                <a href="{{ route('admin.measures.edit', $measure) }}" class="btn btn-sm btn-outline-secondary">Edit</a>
                                <form action="{{ route('admin.measures.destroy', $measure) }}" method="POST" class="d-inline"
                                      onsubmit="return confirm('Move this measure to trash?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-4">No measures found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">{{ $measures->links() }}</div>
@endsection
