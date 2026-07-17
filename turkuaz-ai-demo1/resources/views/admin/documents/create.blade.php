@extends('layouts.admin')

@section('title', 'Add Document')

@section('content')
    <h1 class="h3 mb-3">Add Document</h1>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.documents.store') }}" enctype="multipart/form-data">
                @csrf

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Title (Turkish)</label>
                        <input type="text" name="title[tr]" value="{{ old('title.tr') }}" class="form-control" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Title (English)</label>
                        <input type="text" name="title[en]" value="{{ old('title.en') }}" class="form-control">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Type</label>
                        <select name="type" class="form-select" required>
                            @foreach ($types as $type)
                                <option value="{{ $type }}" @selected(old('type', 'general') === $type)>{{ ucfirst(str_replace('_', ' ', $type)) }}</option>
                            @endforeach
                        </select>
                        <div class="form-text">"General" for corporate/knowledge documents; the rest attach to a product.</div>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label">Document Category (optional)</label>
                        <select name="document_category_id" class="form-select">
                            <option value="">-- None --</option>
                            @foreach ($documentCategories as $cat)
                                <option value="{{ $cat->id }}" @selected(old('document_category_id') == $cat->id)>{{ $cat->name['tr'] ?? '' }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label">Product (optional)</label>
                        <select name="product_id" class="form-select">
                            <option value="">-- None --</option>
                            @foreach ($products as $product)
                                <option value="{{ $product->id }}" @selected(old('product_id') == $product->id)>
                                    {{ $product->name['tr'] ?? '' }} {{ $product->sku ? "({$product->sku})" : '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <hr class="my-4">
                <h5 class="mb-3">Files</h5>
                <p class="text-muted small">Per language: paste an external URL <em>or</em> upload a file. If both are provided, the upload wins.</p>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Turkish File — URL</label>
                        <input type="url" name="file_url_tr" value="{{ old('file_url_tr') }}" class="form-control" placeholder="https://...">
                        <label class="form-label mt-2">or Upload</label>
                        <input type="file" name="file_upload_tr" class="form-control" accept=".pdf,.png,.jpg,.jpeg">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">English File — URL (optional)</label>
                        <input type="url" name="file_url_en" value="{{ old('file_url_en') }}" class="form-control" placeholder="https://...">
                        <label class="form-label mt-2">or Upload</label>
                        <input type="file" name="file_upload_en" class="form-control" accept=".pdf,.png,.jpg,.jpeg">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select" style="max-width:200px;">
                        <option value="active" @selected(old('status', 'active') === 'active')>Active</option>
                        <option value="inactive" @selected(old('status') === 'inactive')>Inactive</option>
                    </select>
                </div>

                <button type="submit" class="btn btn-primary">Create Document</button>
                <a href="{{ route('admin.documents.index') }}" class="btn btn-link">Cancel</a>
            </form>
        </div>
    </div>
@endsection
