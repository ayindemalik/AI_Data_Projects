@extends('layouts.admin')

@section('title', 'Colors')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">Colors</h1>
        <a href="{{ route('admin.colors.create') }}" class="btn btn-primary">+ Add Color</a>
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
                        <a href="{{ route('admin.colors.index', ['q' => request('q')]) }}" class="btn btn-link">Back to active</a>
                    @else
                        <a href="{{ route('admin.colors.index', ['q' => request('q'), 'trashed' => 1]) }}" class="btn btn-link text-danger">View trashed</a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th></th>
                    <th>Name (TR)</th>
                    <th>Name (EN)</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($colors as $color)
                    <tr>
                        <td>
                            @if ($color->hex_value)
                                <span style="display:inline-block;width:20px;height:20px;border-radius:4px;border:1px solid #ddd;background:{{ $color->hex_value }};"></span>
                            @endif
                        </td>
                        <td>{{ $color->name['tr'] ?? '-' }}</td>
                        <td>{{ $color->name['en'] ?? '-' }}</td>
                        <td>
                            <span class="badge {{ $color->status === 'active' ? 'bg-success' : 'bg-secondary' }}">
                                {{ ucfirst($color->status) }}
                            </span>
                        </td>
                        <td class="text-end">
                            @if ($showTrashed)
                                <form action="{{ route('admin.colors.restore', $color->id) }}" method="POST" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-success">Restore</button>
                                </form>
                                <form action="{{ route('admin.colors.force-delete', $color->id) }}" method="POST" class="d-inline"
                                      onsubmit="return confirm('Permanently delete this color? This cannot be undone.');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Delete Permanently</button>
                                </form>
                            @else
                                <a href="{{ route('admin.colors.edit', $color) }}" class="btn btn-sm btn-outline-secondary">Edit</a>
                                <form action="{{ route('admin.colors.destroy', $color) }}" method="POST" class="d-inline"
                                      onsubmit="return confirm('Move this color to trash?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-4">No colors found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">{{ $colors->links() }}</div>
@endsection
