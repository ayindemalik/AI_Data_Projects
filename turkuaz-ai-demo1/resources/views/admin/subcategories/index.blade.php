@extends('layouts.admin')

@section('title', 'Subcategories')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">Subcategories</h1>
        <a href="{{ route('admin.subcategories.create') }}" class="btn btn-primary">+ Add Subcategory</a>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-center">
                <div class="col-auto">
                    <input type="text" name="q" value="{{ request('q') }}" class="form-control" placeholder="Search by name...">
                </div>
                <div class="col-auto">
                    <select name="category_id" class="form-select">
                        <option value="">All Categories</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected(request('category_id') == $category->id)>{{ $category->name['tr'] ?? '' }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-outline-secondary">Filter</button>
                </div>
                <div class="col-auto">
                    @if (request('trashed'))
                        <a href="{{ route('admin.subcategories.index', request()->except('trashed')) }}" class="btn btn-link">Back to active</a>
                    @else
                        <a href="{{ route('admin.subcategories.index', array_merge(request()->all(), ['trashed' => 1])) }}" class="btn btn-link text-danger">View trashed</a>
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
                    <th>Category</th>
                    <th>Slug</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($subcategories as $item)
                    <tr>
                        <td>{{ $item->name['tr'] ?? '-' }}</td>
                        <td>{{ $item->category?->name['tr'] ?? '—' }}</td>
                        <td class="text-muted">{{ $item->slug }}</td>
                        <td>
                            <span class="badge {{ $item->status === 'active' ? 'bg-success' : 'bg-secondary' }}">
                                {{ ucfirst($item->status) }}
                            </span>
                        </td>
                        <td class="text-end">
                            @if ($showTrashed)
                                <form action="{{ route('admin.subcategories.restore', $item->id) }}" method="POST" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-success">Restore</button>
                                </form>
                                <form action="{{ route('admin.subcategories.force-delete', $item->id) }}" method="POST" class="d-inline"
                                      onsubmit="return confirm('Permanently delete this subcategory? This cannot be undone.');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Delete Permanently</button>
                                </form>
                            @else
                                <a href="{{ route('admin.subcategories.edit', $item) }}" class="btn btn-sm btn-outline-secondary">Edit</a>
                                <form action="{{ route('admin.subcategories.destroy', $item) }}" method="POST" class="d-inline"
                                      onsubmit="return confirm('Move this subcategory to trash?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-4">No subcategories found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">{{ $subcategories->links() }}</div>
@endsection
