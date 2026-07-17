@extends('layouts.admin')

@section('title', 'Edit Series')

@section('content')
    <h1 class="h3 mb-3">Edit Series</h1>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.series.update', $series) }}">
                @csrf
                @method('PUT')

                <div class="mb-3">
                    <label class="form-label">Name (Turkish)</label>
                    <input type="text" name="name[tr]" value="{{ old('name.tr', $series->name['tr'] ?? '') }}" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Name (English)</label>
                    <input type="text" name="name[en]" value="{{ old('name.en', $series->name['en'] ?? '') }}" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Description (Turkish)</label>
                    <textarea name="description[tr]" class="form-control" rows="3">{{ old('description.tr', $series->description['tr'] ?? '') }}</textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label">Description (English)</label>
                    <textarea name="description[en]" class="form-control" rows="3">{{ old('description.en', $series->description['en'] ?? '') }}</textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label">Slug</label>
                    <input type="text" name="slug" value="{{ old('slug', $series->slug) }}" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="active" @selected(old('status', $series->status) === 'active')>Active</option>
                        <option value="inactive" @selected(old('status', $series->status) === 'inactive')>Inactive</option>
                    </select>
                </div>

                <button type="submit" class="btn btn-primary">Save Changes</button>
                <a href="{{ route('admin.series.index') }}" class="btn btn-link">Cancel</a>
            </form>
        </div>
    </div>
@endsection
