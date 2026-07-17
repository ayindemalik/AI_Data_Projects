@extends('layouts.admin')

@section('title', 'Edit Subcategory')

@section('content')
    <h1 class="h3 mb-3">Edit Subcategory</h1>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.subcategories.update', $subcategory) }}">
                @csrf
                @method('PUT')

                <div class="mb-3">
                    <label class="form-label">Category</label>
                    <select name="category_id" class="form-select" required>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected(old('category_id', $subcategory->category_id) == $category->id)>{{ $category->name['tr'] ?? '' }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">Name (Turkish)</label>
                    <input type="text" name="name[tr]" value="{{ old('name.tr', $subcategory->name['tr'] ?? '') }}" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Name (English)</label>
                    <input type="text" name="name[en]" value="{{ old('name.en', $subcategory->name['en'] ?? '') }}" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Slug</label>
                    <input type="text" name="slug" value="{{ old('slug', $subcategory->slug) }}" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="active" @selected(old('status', $subcategory->status) === 'active')>Active</option>
                        <option value="inactive" @selected(old('status', $subcategory->status) === 'inactive')>Inactive</option>
                    </select>
                </div>

                <button type="submit" class="btn btn-primary">Save Changes</button>
                <a href="{{ route('admin.subcategories.index') }}" class="btn btn-link">Cancel</a>
            </form>
        </div>
    </div>
@endsection
