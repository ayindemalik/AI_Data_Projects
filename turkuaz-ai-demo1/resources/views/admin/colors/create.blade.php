@extends('layouts.admin')

@section('title', 'Add Color')

@section('content')
    <h1 class="h3 mb-3">Add Color</h1>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.colors.store') }}">
                @csrf

                <div class="mb-3">
                    <label class="form-label">Name (Turkish)</label>
                    <input type="text" name="name[tr]" value="{{ old('name.tr') }}" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Name (English)</label>
                    <input type="text" name="name[en]" value="{{ old('name.en') }}" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Hex Value (optional)</label>
                    <input type="color" name="hex_value" value="{{ old('hex_value', '#000000') }}" class="form-control form-control-color">
                </div>

                <div class="mb-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="active" @selected(old('status', 'active') === 'active')>Active</option>
                        <option value="inactive" @selected(old('status') === 'inactive')>Inactive</option>
                    </select>
                </div>

                <button type="submit" class="btn btn-primary">Create Color</button>
                <a href="{{ route('admin.colors.index') }}" class="btn btn-link">Cancel</a>
            </form>
        </div>
    </div>
@endsection
