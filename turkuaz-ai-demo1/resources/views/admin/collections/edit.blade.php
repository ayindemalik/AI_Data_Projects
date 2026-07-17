@extends('layouts.admin')

@section('title', 'Edit Collection')

@section('content')
    <h1 class="h3 mb-3">Edit Collection</h1>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.collections.update', $collection) }}">
                @csrf
                @method('PUT')

                <div class="mb-3">
                    <label class="form-label">Name (Turkish)</label>
                    <input type="text" name="name[tr]" value="{{ old('name.tr', $collection->name['tr'] ?? '') }}" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Name (English)</label>
                    <input type="text" name="name[en]" value="{{ old('name.en', $collection->name['en'] ?? '') }}" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Description (Turkish)</label>
                    <textarea name="description[tr]" class="form-control" rows="3">{{ old('description.tr', $collection->description['tr'] ?? '') }}</textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label">Description (English)</label>
                    <textarea name="description[en]" class="form-control" rows="3">{{ old('description.en', $collection->description['en'] ?? '') }}</textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label">Slug</label>
                    <input type="text" name="slug" value="{{ old('slug', $collection->slug) }}" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="active" @selected(old('status', $collection->status) === 'active')>Active</option>
                        <option value="inactive" @selected(old('status', $collection->status) === 'inactive')>Inactive</option>
                    </select>
                </div>

                <button type="submit" class="btn btn-primary">Save Changes</button>
                <a href="{{ route('admin.collections.index') }}" class="btn btn-link">Cancel</a>
            </form>
        </div>
    </div>
@endsection
