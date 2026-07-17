@extends('layouts.admin')

@section('title', 'Collections')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">Collections</h1>
        <a href="{{ route('admin.collections.create') }}" class="btn btn-primary">+ Add Collection</a>
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
                        <a href="{{ route('admin.collections.index', ['q' => request('q')]) }}" class="btn btn-link">Back to active</a>
                    @else
                        <a href="{{ route('admin.collections.index', ['q' => request('q'), 'trashed' => 1]) }}" class="btn btn-link text-danger">View trashed</a>
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
                    <th>Slug</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($collections as $collection)
                    <tr>
                        <td>{{ $collection->name['tr'] ?? '-' }}</td>
                        <td>{{ $collection->name['en'] ?? '-' }}</td>
                        <td class="text-muted">{{ $collection->slug }}</td>
                        <td>
                            <span class="badge {{ $collection->status === 'active' ? 'bg-success' : 'bg-secondary' }}">
                                {{ ucfirst($collection->status) }}
                            </span>
                        </td>
                        <td class="text-end">
                            @if ($showTrashed)
                                <form action="{{ route('admin.collections.restore', $collection->id) }}" method="POST" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-success">Restore</button>
                                </form>
                                <form action="{{ route('admin.collections.force-delete', $collection->id) }}" method="POST" class="d-inline"
                                      onsubmit="return confirm('Permanently delete this collection? This cannot be undone.');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Delete Permanently</button>
                                </form>
                            @else
                                <a href="{{ route('admin.collections.edit', $collection) }}" class="btn btn-sm btn-outline-secondary">Edit</a>
                                <form action="{{ route('admin.collections.destroy', $collection) }}" method="POST" class="d-inline"
                                      onsubmit="return confirm('Move this collection to trash?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-4">No collections found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">{{ $collections->links() }}</div>
@endsection
