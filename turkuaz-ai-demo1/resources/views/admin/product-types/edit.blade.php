@extends('layouts.admin')

@section('title', 'Edit Product Type')

@section('content')
    <h1 class="h3 mb-3">Edit Product Type</h1>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.product-types.update', $productType) }}">
                @csrf
                @method('PUT')

                <div class="mb-3">
                    <label class="form-label">Name (Turkish)</label>
                    <input type="text" name="name[tr]" value="{{ old('name.tr', $productType->name['tr'] ?? '') }}" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Name (English)</label>
                    <input type="text" name="name[en]" value="{{ old('name.en', $productType->name['en'] ?? '') }}" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Subcategory</label>
                    <select name="subcategory_id" class="form-select">
                        <option value="">-- None --</option>
                        @foreach ($subcategories as $s)
                            <option value="{{ $s->id }}" @selected(old('subcategory_id', $productType->subcategory_id) == $s->id)>{{ $s->name['tr'] ?? '' }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="active" @selected(old('status', $productType->status) === 'active')>Active</option>
                        <option value="inactive" @selected(old('status', $productType->status) === 'inactive')>Inactive</option>
                    </select>
                </div>

                <button type="submit" class="btn btn-primary">Save Changes</button>
                <a href="{{ route('admin.product-types.index') }}" class="btn btn-link">Cancel</a>
            </form>
        </div>
    </div>
@endsection
