@extends('layouts.admin')

@section('title', 'Product Types')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">Product Types</h1>
        <a href="{{ route('admin.product-types.create') }}" class="btn btn-primary">+ Add Product Type</a>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-center">
                <div class="col-auto">
                    <input type="text" name="q" value="{{ request('q') }}" class="form-control" placeholder="Search by name...">
                </div>
                <div class="col-auto">
                    <select name="subcategory_id" class="form-select">
                        <option value="">All Subcategories</option>
                        @foreach ($subcategories as $s)
                            <option value="{{ $s->id }}" @selected(request('subcategory_id') == $s->id)>{{ $s->name['tr'] ?? '' }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-outline-secondary">Filter</button>
                </div>
                <div class="col-auto">
                    @if (request('trashed'))
                        <a href="{{ route('admin.product-types.index', ['q' => request('q')]) }}" class="btn btn-link">Back to active</a>
                    @else
                        <a href="{{ route('admin.product-types.index', ['q' => request('q'), 'trashed' => 1]) }}" class="btn btn-link text-danger">View trashed</a>
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
                    <th>Subcategory</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($productTypes as $type)
                    <tr>
                        <td>{{ $type->name['tr'] ?? '-' }}</td>
                        <td>{{ $type->name['en'] ?? '-' }}</td>
                        <td>{{ $type->subcategory?->name['tr'] ?? '—' }}</td>
                        <td>
                            <span class="badge {{ $type->status === 'active' ? 'bg-success' : 'bg-secondary' }}">
                                {{ ucfirst($type->status) }}
                            </span>
                        </td>
                        <td class="text-end">
                            @if ($showTrashed)
                                <form action="{{ route('admin.product-types.restore', $type->id) }}" method="POST" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-success">Restore</button>
                                </form>
                                <form action="{{ route('admin.product-types.force-delete', $type->id) }}" method="POST" class="d-inline"
                                      onsubmit="return confirm('Permanently delete this product type? This cannot be undone.');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Delete Permanently</button>
                                </form>
                            @else
                                <a href="{{ route('admin.product-types.edit', $type) }}" class="btn btn-sm btn-outline-secondary">Edit</a>
                                <form action="{{ route('admin.product-types.destroy', $type) }}" method="POST" class="d-inline"
                                      onsubmit="return confirm('Move this product type to trash?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-4">No product types found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">{{ $productTypes->links() }}</div>
@endsection
