@extends('layouts.admin')

@section('title', 'Documents')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">Documents</h1>
        <a href="{{ route('admin.documents.create') }}" class="btn btn-primary">+ Add Document</a>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-center">
                <div class="col-auto">
                    <input type="text" name="q" value="{{ request('q') }}" class="form-control" placeholder="Search by title...">
                </div>
                <div class="col-auto">
                    <select name="document_category_id" class="form-select">
                        <option value="">All Categories</option>
                        @foreach ($documentCategories as $cat)
                            <option value="{{ $cat->id }}" @selected(request('document_category_id') == $cat->id)>{{ $cat->name['tr'] ?? '' }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <select name="type" class="form-select">
                        <option value="">All Types</option>
                        @foreach (\App\Models\Document::TYPES as $type)
                            <option value="{{ $type }}" @selected(request('type') === $type)>{{ ucfirst(str_replace('_', ' ', $type)) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-outline-secondary">Filter</button>
                </div>
                <div class="col-auto">
                    @if (request('trashed'))
                        <a href="{{ route('admin.documents.index', request()->except('trashed')) }}" class="btn btn-link">Back to active</a>
                    @else
                        <a href="{{ route('admin.documents.index', array_merge(request()->all(), ['trashed' => 1])) }}" class="btn btn-link text-danger">View trashed</a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <table class="table table-hover mb-0 align-middle">
            <thead>
                <tr>
                    <th>Title (TR)</th>
                    <th>Type</th>
                    <th>Category</th>
                    <th>Product</th>
                    <th>Files</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($documents as $document)
                    <tr>
                        <td>{{ $document->title['tr'] ?? '-' }}</td>
                        <td><span class="badge bg-light text-dark border">{{ str_replace('_', ' ', $document->type) }}</span></td>
                        <td>{{ $document->category?->name['tr'] ?? '—' }}</td>
                        <td>{{ $document->product?->name['tr'] ?? '—' }}</td>
                        <td>
                            @if (!empty($document->file['tr']))
                                <a href="{{ $document->fileUrl('tr') }}" target="_blank" class="badge bg-secondary text-decoration-none">TR</a>
                            @endif
                            @if (!empty($document->file['en']))
                                <a href="{{ $document->fileUrl('en') }}" target="_blank" class="badge bg-secondary text-decoration-none">EN</a>
                            @endif
                        </td>
                        <td>
                            <span class="badge {{ $document->status === 'active' ? 'bg-success' : 'bg-secondary' }}">
                                {{ ucfirst($document->status) }}
                            </span>
                        </td>
                        <td class="text-end">
                            @if ($showTrashed)
                                <form action="{{ route('admin.documents.restore', $document->id) }}" method="POST" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-success">Restore</button>
                                </form>
                                <form action="{{ route('admin.documents.force-delete', $document->id) }}" method="POST" class="d-inline"
                                      onsubmit="return confirm('Permanently delete this document? Locally uploaded files are also removed. This cannot be undone.');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Delete Permanently</button>
                                </form>
                            @else
                                <a href="{{ route('admin.documents.edit', $document) }}" class="btn btn-sm btn-outline-secondary">Edit</a>
                                <form action="{{ route('admin.documents.destroy', $document) }}" method="POST" class="d-inline"
                                      onsubmit="return confirm('Move this document to trash?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">No documents found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">{{ $documents->links() }}</div>
@endsection
