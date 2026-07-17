@extends('layouts.admin')

@section('title', 'Edit Measure')

@section('content')
    <h1 class="h3 mb-3">Edit Measure</h1>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.measures.update', $measure) }}">
                @csrf
                @method('PUT')

                <div class="mb-3">
                    <label class="form-label">Name (Turkish)</label>
                    <input type="text" name="name[tr]" value="{{ old('name.tr', $measure->name['tr'] ?? '') }}" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Name (English)</label>
                    <input type="text" name="name[en]" value="{{ old('name.en', $measure->name['en'] ?? '') }}" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Unit</label>
                    <input type="text" name="unit" value="{{ old('unit', $measure->unit) }}" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Slug</label>
                    <input type="text" name="slug" value="{{ old('slug', $measure->slug) }}" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="active" @selected(old('status', $measure->status) === 'active')>Active</option>
                        <option value="inactive" @selected(old('status', $measure->status) === 'inactive')>Inactive</option>
                    </select>
                </div>

                <button type="submit" class="btn btn-primary">Save Changes</button>
                <a href="{{ route('admin.measures.index') }}" class="btn btn-link">Cancel</a>
            </form>
        </div>
    </div>
@endsection
